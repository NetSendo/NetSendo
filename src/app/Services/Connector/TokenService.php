<?php

namespace App\Services\Connector;

use App\Models\ConnectorAuthCode;
use App\Models\ConnectorClient;
use App\Models\ConnectorConnection;
use App\Models\ConnectorToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
        $token = ConnectorToken::with('connection')
            ->where('refresh_token_hash', hash('sha256', $refreshToken))
            ->first();

        $connection = $token?->connection;

        if (! $token || ! $connection || $connection->connector_client_id !== $client->id) {
            throw new OAuthException('invalid_grant', 'The refresh token is not valid.');
        }

        if ($token->revoked_at !== null || $connection->isRevoked() || $token->refresh_expires_at->isPast()) {
            throw new OAuthException('invalid_grant', 'The refresh token has expired or was revoked. Sign in again.');
        }

        if ($token->rotated_at !== null) {
            $grace = (int) config('connector.refresh_reuse_grace', 60);

            // An old refresh token long after it was exchanged: someone holds a
            // copy. End the connection so that neither party can go on.
            if ($token->rotated_at->lt(now()->subSeconds($grace))) {
                $connection->revoke();

                throw new OAuthException('invalid_grant', 'The refresh token was already used. Sign in again.');
            }
        }

        return DB::transaction(function () use ($token, $connection) {
            if ($token->rotated_at === null) {
                $token->forceFill(['rotated_at' => now()])->save();
            }

            return $this->issue($connection);
        });
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
