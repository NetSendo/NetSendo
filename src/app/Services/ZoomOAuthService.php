<?php

namespace App\Services;

use App\Models\UserZoomConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class ZoomOAuthService
{
    private const ZOOM_AUTH_URL = 'https://zoom.us/oauth/authorize';
    private const ZOOM_TOKEN_URL = 'https://zoom.us/oauth/token';
    private const ZOOM_USERINFO_URL = 'https://api.zoom.us/v2/users/me';
    private const ZOOM_REVOKE_URL = 'https://zoom.us/oauth/revoke';

    /**
     * Get Zoom Client ID from settings.
     * Database settings take priority, config is fallback.
     */
    private function getClientId(): ?string
    {
        // Database first, then config as fallback
        return \App\Models\Setting::where('key', 'zoom_client_id')->value('value')
            ?: config('services.zoom.client_id');
    }

    /**
     * Get Zoom Client Secret from settings.
     * Database settings take priority, config is fallback.
     */
    private function getClientSecret(): ?string
    {
        // Database first, then config as fallback
        return \App\Models\Setting::where('key', 'zoom_client_secret')->value('value')
            ?: config('services.zoom.client_secret');
    }

    /**
     * Required OAuth scopes for Zoom integration.
     * These scopes use Zoom's granular scope format (introduced 2024).
     * See: https://developers.zoom.us/docs/integrations/oauth-scopes/
     */
    private const ZOOM_SCOPES = [
        'user:read:user:admin',            // View a user (required to get user info after auth)
        'meeting:write:meeting:admin',     // Create a meeting for a user
        'meeting:read:meeting:admin',      // View a meeting
        'meeting:update:meeting:admin',    // Update a meeting
        'meeting:delete:meeting:admin',    // Delete a meeting
    ];

    /**
     * Get authorization URL for Zoom OAuth.
     */
    public function getAuthorizationUrl(string $state): string
    {
        $params = [
            'client_id' => $this->getClientId(),
            'redirect_uri' => route('settings.zoom.callback'),
            'response_type' => 'code',
            'state' => $state,
            'scope' => implode(' ', self::ZOOM_SCOPES),
        ];

        return self::ZOOM_AUTH_URL . '?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for tokens.
     */
    public function exchangeCodeForTokens(string $code): array
    {
        $clientId = $this->getClientId();
        $clientSecret = $this->getClientSecret();

        $response = Http::withBasicAuth($clientId, $clientSecret)
            ->asForm()
            ->post(self::ZOOM_TOKEN_URL, [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => route('settings.zoom.callback'),
            ]);

        if (!$response->successful()) {
            Log::error('Zoom OAuth token exchange failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new Exception('Failed to exchange code for tokens: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Refresh an expired access token.
     */
    public function refreshAccessToken(UserZoomConnection $connection): array
    {
        $clientId = $this->getClientId();
        $clientSecret = $this->getClientSecret();
        $refreshToken = $connection->getDecryptedRefreshToken();

        if (!$refreshToken) {
            throw new Exception('No refresh token available');
        }

        $response = Http::withBasicAuth($clientId, $clientSecret)
            ->asForm()
            ->post(self::ZOOM_TOKEN_URL, [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
            ]);

        if (!$response->successful()) {
            Log::error('Zoom OAuth token refresh failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new Exception('Failed to refresh access token');
        }

        $tokens = $response->json();
        $connection->updateTokens($tokens);

        return $tokens;
    }

    /**
     * Get a valid access token (refresh if expired).
     */
    public function getValidAccessToken(UserZoomConnection $connection): string
    {
        if ($connection->isTokenExpired()) {
            $tokens = $this->refreshAccessToken($connection);
            return $tokens['access_token'];
        }

        return $connection->getDecryptedAccessToken();
    }

    /**
     * Get user info from Zoom.
     */
    public function getUserInfo(string $accessToken): array
    {
        $response = Http::withToken($accessToken)->get(self::ZOOM_USERINFO_URL);

        if (!$response->successful()) {
            throw new Exception('Failed to get user info from Zoom');
        }

        return $response->json();
    }

    /**
     * Revoke OAuth tokens.
     */
    public function revokeTokens(UserZoomConnection $connection): bool
    {
        $clientId = $this->getClientId();
        $clientSecret = $this->getClientSecret();
        $accessToken = $connection->getDecryptedAccessToken();

        if (!$accessToken) {
            return true;
        }

        try {
            $response = Http::withBasicAuth($clientId, $clientSecret)
                ->asForm()
                ->post(self::ZOOM_REVOKE_URL, [
                    'token' => $accessToken,
                ]);

            return $response->successful();
        } catch (Exception $e) {
            Log::warning('Failed to revoke Zoom tokens', [
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Encode state parameter for OAuth.
     */
    public function encodeState(array $data): string
    {
        $data['csrf'] = csrf_token();
        return base64_encode(json_encode($data));
    }

    /**
     * Decode OAuth state parameter.
     */
    public function decodeState(string $state): array
    {
        $decoded = json_decode(base64_decode($state), true);

        if (!$decoded) {
            throw new Exception('Invalid OAuth state parameter');
        }

        return $decoded;
    }

    /**
     * Check if Zoom integration is configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->getClientId()) && !empty($this->getClientSecret());
    }
}
