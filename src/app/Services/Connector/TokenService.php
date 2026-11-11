<?php

namespace App\Services\Connector;

use App\Models\ConnectorAuthCode;
use App\Models\ConnectorClient;
use App\Models\ConnectorConnection;
use App\Models\ConnectorToken;
use App\Models\User;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Issues and checks the connector's OAuth tokens.
 *
 * Tokens are random strings; only their SHA-256 hashes are stored, as with API
 * keys. Access tokens are short-lived; refresh tokens are single-use and
 * rotated on every refresh (OAuth 2.1 for public clients).
 */
class TokenService
{
    public const ACCESS_PREFIX = 'ns_oat_';

    public const REFRESH_PREFIX = 'ns_ort_';

    public const CODE_PREFIX = 'ns_oac_';

    /**
     * Create the one-time code the consent screen sends back to the host.
     *
     * @param  list<string>  $scopes
     */
    public function createAuthorizationCode(
        ConnectorClient $client,
        User $user,
        string $redirectUri,
        array $scopes,
        string $codeChallenge,
        ?string $resource,
    ): string {
        $code = self::CODE_PREFIX.Str::random(48);

        ConnectorAuthCode::create([
            'code_hash' => hash('sha256', $code),
            'user_id' => $user->id,
            'connector_client_id' => $client->id,
            'redirect_uri' => $redirectUri,
            'scopes' => $scopes,
            'code_challenge' => $codeChallenge,
            'resource' => $resource,
            'expires_at' => now()->addSeconds((int) config('connector.auth_code_ttl', 120)),
        ]);

        return $code;
    }

    /**
     * authorization_code grant: turn a code into a connection with its first token pair.
     *
     * @return array<string, mixed> the token response
     *
     * @throws OAuthException
     */
    public function exchangeAuthorizationCode(
        ConnectorClient $client,
        string $code,
        string $redirectUri,
        string $codeVerifier,
        ?string $resource,
    ): array {
        $authCode = ConnectorAuthCode::where('code_hash', hash('sha256', $code))->first();

        if (! $authCode || $authCode->connector_client_id !== $client->id) {
            throw new OAuthException('invalid_grant', 'The authorization code is not valid.');
        }

        // A code presented twice was intercepted or replayed: what it produced
        // the first time is withdrawn (RFC 6749 §4.1.2).
        if ($authCode->consumed_at !== null) {
            $authCode->connection?->revoke();

            throw new OAuthException('invalid_grant', 'The authorization code was already used.');
        }

        if ($authCode->expires_at->isPast()) {
            throw new OAuthException('invalid_grant', 'The authorization code has expired.');
        }

        if (! hash_equals($authCode->redirect_uri, $redirectUri)) {
            throw new OAuthException('invalid_grant', 'redirect_uri does not match the authorization request.');
        }

        if (! $this->verifierMatches($codeVerifier, $authCode->code_challenge)) {
            throw new OAuthException('invalid_grant', 'The PKCE code_verifier does not match.');
        }

        if ($resource !== null && $authCode->resource !== null && ! hash_equals($authCode->resource, $resource)) {
            throw new OAuthException('invalid_target', 'resource does not match the authorization request.');
        }

        return DB::transaction(function () use ($authCode, $client) {
            // Two requests racing with the same code: only one may win.
            $claimed = ConnectorAuthCode::whereKey($authCode->id)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);

            if ($claimed !== 1) {
                throw new OAuthException('invalid_grant', 'The authorization code was already used.');
            }

            $connection = ConnectorConnection::create([
                'user_id' => $authCode->user_id,
                'connector_client_id' => $client->id,
                'client_name' => $client->name,
                'scopes' => $authCode->scopes,
                'resource' => $authCode->resource,
            ]);

            $authCode->forceFill(['connector_connection_id' => $connection->id])->save();
            $client->forceFill(['last_used_at' => now()])->save();

            return $this->issue($connection);
        });
    }

    /**
     * refresh_token grant: exchange a refresh token for a new pair.
     *
     * @return array<string, mixed> the token response
     *
     * @throws OAuthException
     */
    public function refresh(ConnectorClient $client, string $refreshToken): array
    {
        // Two requests with the same refresh token at the same moment (two
        // processes of one host) are handled one after the other, so that the
        // second one finds what the first one issued.
        $lock = null;

        try {
            $lock = Cache::lock('connector.refresh.'.hash('sha256', $refreshToken), 20);
            $lock->block(8);
        } catch (LockTimeoutException) {
            $lock = null;
        } catch (Throwable $e) {
            // A cache store without locks: go on unserialised.
            report($e);
            $lock = null;
        }

        try {
            return $this->exchangeRefreshToken($client, $refreshToken);
        } finally {
            $lock?->release();
        }
    }

    /** @return array<string, mixed> */
    private function exchangeRefreshToken(ConnectorClient $client, string $refreshToken): array
    {
        $token = ConnectorToken::with('connection')
            ->where('refresh_token_hash', hash('sha256', $refreshToken))
            ->first();

        $connection = $token?->connection;

        if (! $token || ! $connection || $connection->connector_client_id !== $client->id) {
            throw new OAuthException('invalid_grant', 'The refresh token is not valid.');
        }

        if ($connection->isRevoked() || $token->refresh_expires_at->isPast()) {
            throw new OAuthException('invalid_grant', 'The refresh token has expired or was revoked. Sign in again.');
        }

        // A token that was withdrawn while its connection lives on lost a race
        // for the same refresh token (see below). Two parties hold tokens of
        // this connection, so it ends for both.
        if ($token->revoked_at !== null) {
            $connection->revoke();

            throw new OAuthException('invalid_grant', 'The refresh token was replaced. Sign in again.');
        }

        if ($token->rotated_at !== null) {
            $grace = (int) config('connector.refresh_reuse_grace', 60);

            // An old refresh token long after it was exchanged: someone holds a
            // copy. End the connection so that neither party can go on.
            if ($token->rotated_at->lt(now()->subSeconds($grace))) {
                $connection->revoke();

                throw new OAuthException('invalid_grant', 'The refresh token was already used. Sign in again.');
            }

            // The same refresh token again, moments after it was exchanged: a host
            // that never received the answer, or two processes of one host that
            // refreshed at the same moment. They get the pair the first exchange
            // produced, so that one token chain exists and not two.
            $first = $this->recallRefreshResponse($refreshToken);

            if ($first !== null) {
                return $first;
            }
        }

        return DB::transaction(function () use ($token, $connection, $client, $refreshToken) {
            if ($token->rotated_at === null) {
                $token->forceFill(['rotated_at' => now()])->save();
            } else {
                // The first answer can no longer be repeated (the cache lost it):
                // what it produced is withdrawn before a new pair is issued.
                $connection->tokens()
                    ->where('id', '>', $token->id)
                    ->whereNull('revoked_at')
                    ->update(['revoked_at' => now()]);
            }

            // Kept current so that an unused client can be told from one in use (connector:prune).
            $client->forceFill(['last_used_at' => now()])->save();

            $response = $this->issue($connection);
            $this->rememberRefreshResponse($refreshToken, $response);

            return $response;
        });
    }

    /**
     * Keep the answer to a refresh for the grace period, so that a repeated
     * request can be given the same pair. It is encrypted with a key made from
     * the refresh token that was exchanged, which the server does not store:
     * only a request that presents that token again can read it.
     *
     * @param  array<string, mixed>  $response
     */
    private function rememberRefreshResponse(string $refreshToken, array $response): void
    {
        $grace = (int) config('connector.refresh_reuse_grace', 60);

        if ($grace <= 0) {
            return;
        }

        try {
            Cache::put(
                'connector.refresh_response.'.hash('sha256', $refreshToken),
                $this->refreshResponseCipher($refreshToken)->encryptString(json_encode($response)),
                $grace,
            );
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** @return array<string, mixed>|null */
    private function recallRefreshResponse(string $refreshToken): ?array
    {
        try {
            $stored = Cache::get('connector.refresh_response.'.hash('sha256', $refreshToken));
            $response = is_string($stored)
                ? json_decode($this->refreshResponseCipher($refreshToken)->decryptString($stored), true)
                : null;
        } catch (Throwable) {
            return null;
        }

        return is_array($response) && isset($response['access_token'], $response['refresh_token']) ? $response : null;
    }

    private function refreshResponseCipher(string $refreshToken): Encrypter
    {
        return new Encrypter(hash('sha256', 'connector-refresh-response|'.$refreshToken, true), 'aes-256-gcm');
    }

    /**
     * RFC 7009 revocation. Whichever token of a connection is presented, the
     * whole connection ends: a host revokes when the user disconnects it.
     */
    public function revokeByToken(string $token): void
    {
        $hash = hash('sha256', $token);

        ConnectorToken::with('connection')
            ->where('access_token_hash', $hash)
            ->orWhere('refresh_token_hash', $hash)
            ->first()
            ?->connection
            ?->revoke();
    }

    /** The connection an access token belongs to, or null when the token does not work (any more). */
    public function connectionForAccessToken(string $accessToken): ?ConnectorConnection
    {
        if (! str_starts_with($accessToken, self::ACCESS_PREFIX)) {
            return null;
        }

        $token = ConnectorToken::with('connection.user')
            ->where('access_token_hash', hash('sha256', $accessToken))
            ->first();

        $connection = $token?->connection;

        if (! $token || ! $connection || ! $connection->user
            || $token->revoked_at !== null
            || $connection->isRevoked()
            || $token->access_expires_at->isPast()) {
            return null;
        }

        return $connection;
    }

    /** @return array<string, mixed> */
    private function issue(ConnectorConnection $connection): array
    {
        $accessToken = self::ACCESS_PREFIX.Str::random(48);
        $refreshToken = self::REFRESH_PREFIX.Str::random(64);
        $ttl = (int) config('connector.access_token_ttl', 3600);

        ConnectorToken::create([
            'connector_connection_id' => $connection->id,
            'access_token_hash' => hash('sha256', $accessToken),
            'access_expires_at' => now()->addSeconds($ttl),
            'refresh_token_hash' => hash('sha256', $refreshToken),
            'refresh_expires_at' => now()->addSeconds((int) config('connector.refresh_token_ttl')),
        ]);

        return [
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => $ttl,
            'refresh_token' => $refreshToken,
            'scope' => implode(' ', $connection->scopes ?? []),
        ];
    }

    /** PKCE S256: BASE64URL(SHA256(verifier)) must equal the challenge of the authorization request. */
    private function verifierMatches(string $verifier, string $challenge): bool
    {
        if (! preg_match('/^[A-Za-z0-9\-._~]{43,128}$/', $verifier)) {
            return false;
        }

        $computed = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        return hash_equals($challenge, $computed);
    }
}
