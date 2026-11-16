<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Models\ConnectorConnection;
use App\Services\Connector\ConnectorUrls;
use App\Services\Connector\TokenService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates a request to the MCP endpoint.
 *
 * Accepted in `Authorization: Bearer …`:
 *  - an OAuth access token of a connected app (the normal case), or
 *  - a NetSendo API key, for hosts that can only send a fixed header.
 *
 * Without a valid credential the answer is 401 with a pointer to the OAuth
 * metadata; that challenge is what makes an MCP host start the sign-in.
 */
class AuthenticateConnector
{
    /** ApiKey: who is calling and with which permissions (see ApiDispatcher). */
    public const IDENTITY_ATTRIBUTE = 'netsendo.connector_identity';

    /** ConnectorConnection, when the caller is a connected app. */
    public const CONNECTION_ATTRIBUTE = 'netsendo.connector_connection';

    public function __construct(private TokenService $tokens)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();

        if (! is_string($bearer) || $bearer === '') {
            return $this->challenge();
        }

        if (str_starts_with($bearer, 'ns_live_')) {
            $apiKey = ApiKey::findByKey($bearer);

            if (! $apiKey || $apiKey->isExpired() || ! $apiKey->user) {
                return $this->challenge('The API key is not valid.');
            }

            $identity = $apiKey;
        } else {
            $connection = $this->tokens->connectionForAccessToken($bearer);

            if (! $connection) {
                return $this->challenge('The access token is invalid, expired or revoked.');
            }

            $this->touch($connection, $request);
            $request->attributes->set(self::CONNECTION_ATTRIBUTE, $connection);
            $identity = $connection->asApiKey();
        }

        $request->attributes->set(self::IDENTITY_ATTRIBUTE, $identity);
        $request->setUserResolver(fn () => $identity->user);

        return $next($request);
    }

    /** "Last used" in the panel; written at most once a minute per connection. */
    private function touch(ConnectorConnection $connection, Request $request): void
    {
        if ($connection->last_used_at === null || $connection->last_used_at->lt(now()->subMinute())) {
            $connection->forceFill([
                'last_used_at' => now(),
                'last_used_ip' => $request->ip(),
            ])->saveQuietly();
        }
    }

    private function challenge(?string $invalidTokenReason = null): Response
    {
        $header = 'Bearer resource_metadata="'.ConnectorUrls::protectedResourceMetadata().'"';

        if ($invalidTokenReason !== null) {
            $header .= ', error="invalid_token", error_description="'.$invalidTokenReason.'"';
        }

        return response()->json([
            'error' => $invalidTokenReason === null ? 'unauthorized' : 'invalid_token',
            'message' => $invalidTokenReason ?? 'Sign in to NetSendo to use this connector.',
        ], 401)->header('WWW-Authenticate', $header);
    }
}
