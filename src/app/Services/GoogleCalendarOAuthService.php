<?php

namespace App\Services;

use App\Models\GoogleIntegration;
use App\Models\UserCalendarConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class GoogleCalendarOAuthService
{
    private const GOOGLE_AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const GOOGLE_TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const GOOGLE_USERINFO_URL = 'https://www.googleapis.com/oauth2/v2/userinfo';
    private const GOOGLE_REVOKE_URL = 'https://oauth2.googleapis.com/revoke';

    // Calendar-specific scopes
    private const SCOPES = [
        'https://www.googleapis.com/auth/calendar',
        'https://www.googleapis.com/auth/calendar.events',
        'https://www.googleapis.com/auth/userinfo.email',
        'openid',
    ];

    /**
     * Get authorization URL for Calendar OAuth.
     */
    public function getAuthorizationUrl(GoogleIntegration $integration, string $state): string
    {
        $params = [
            'client_id' => $integration->client_id,
            'redirect_uri' => route('settings.calendar.callback'),
            'response_type' => 'code',
            'scope' => implode(' ', self::SCOPES),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ];

        return self::GOOGLE_AUTH_URL . '?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for tokens.
     */
    public function exchangeCodeForTokens(GoogleIntegration $integration, string $code): array
    {
        $response = Http::asForm()->post(self::GOOGLE_TOKEN_URL, [
            'client_id' => $integration->client_id,
            'client_secret' => $integration->client_secret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => route('settings.calendar.callback'),
        ]);

        if (!$response->successful()) {
            Log::error('Google Calendar OAuth token exchange failed', [
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
    public function refreshAccessToken(UserCalendarConnection $connection): array
    {
        $integration = $connection->googleIntegration;
        $refreshToken = $connection->getDecryptedRefreshToken();

        if (!$refreshToken) {
            throw new Exception('No refresh token available');
        }

        $response = Http::asForm()->post(self::GOOGLE_TOKEN_URL, [
            'client_id' => $integration->client_id,
            'client_secret' => $integration->client_secret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);

        if (!$response->successful()) {
            Log::error('Google Calendar OAuth token refresh failed', [
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
    public function getValidAccessToken(UserCalendarConnection $connection): string
    {
        if ($connection->isTokenExpired()) {
            $tokens = $this->refreshAccessToken($connection);
            return $tokens['access_token'];
        }

        return $connection->getDecryptedAccessToken();
    }

    /**
     * Get user info (email) from Google.
     */
    public function getUserInfo(string $accessToken): array
    {
        $response = Http::withToken($accessToken)->get(self::GOOGLE_USERINFO_URL);

        if (!$response->successful()) {
            throw new Exception('Failed to get user info from Google');
        }

        return $response->json();
    }

    /**
     * Revoke OAuth tokens.
     */
    public function revokeTokens(UserCalendarConnection $connection): bool
    {
        $accessToken = $connection->getDecryptedAccessToken();

        if (!$accessToken) {
            return true; // Nothing to revoke
        }

        try {
            $response = Http::asForm()->post(self::GOOGLE_REVOKE_URL, [
                'token' => $accessToken,
            ]);

            return $response->successful();
        } catch (Exception $e) {
            Log::warning('Failed to revoke Google Calendar tokens', [
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
}
