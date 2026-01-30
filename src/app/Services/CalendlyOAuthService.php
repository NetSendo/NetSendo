<?php

namespace App\Services;

use App\Models\CalendlyIntegration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CalendlyOAuthService
{
    private string $authorizationUrl = 'https://auth.calendly.com/oauth/authorize';
    private string $tokenUrl = 'https://auth.calendly.com/oauth/token';

    /**
     * Generate the OAuth authorization URL with user-provided credentials.
     */
    public function getAuthorizationUrl(string $clientId, string $redirectUri, string $state): string
    {
        $params = http_build_query([
            'client_id' => $clientId,
            'response_type' => 'code',
            'redirect_uri' => $redirectUri,
            'state' => $state,
        ]);

        return "{$this->authorizationUrl}?{$params}";
    }

    /**
     * Exchange authorization code for access and refresh tokens.
     */
    public function exchangeCodeForTokens(string $code, string $clientId, string $clientSecret, string $redirectUri): array
    {
        $response = Http::asForm()->post($this->tokenUrl, [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ]);

        if (!$response->successful()) {
            Log::error('Calendly OAuth token exchange failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Failed to exchange authorization code for tokens');
        }

        $data = $response->json();

        return [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'],
            'token_type' => $data['token_type'] ?? 'Bearer',
            'expires_in' => $data['expires_in'] ?? 7200,
            'created_at' => $data['created_at'] ?? time(),
        ];
    }

    /**
     * Refresh an expired access token using integration credentials.
     */
    public function refreshAccessToken(CalendlyIntegration $integration): array
    {
        $response = Http::asForm()->post($this->tokenUrl, [
            'grant_type' => 'refresh_token',
            'refresh_token' => $integration->refresh_token,
            'client_id' => $integration->client_id,
            'client_secret' => $integration->client_secret,
        ]);

        if (!$response->successful()) {
            Log::error('Calendly OAuth token refresh failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'integration_id' => $integration->id,
            ]);
            throw new \Exception('Failed to refresh access token');
        }

        $data = $response->json();

        return [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'],
            'token_type' => $data['token_type'] ?? 'Bearer',
            'expires_in' => $data['expires_in'] ?? 7200,
            'created_at' => $data['created_at'] ?? time(),
        ];
    }

    /**
     * Revoke access token (on disconnect).
     */
    public function revokeToken(CalendlyIntegration $integration): bool
    {
        $response = Http::withToken($integration->access_token)
            ->post('https://auth.calendly.com/oauth/revoke', [
                'client_id' => $integration->client_id,
                'client_secret' => $integration->client_secret,
                'token' => $integration->access_token,
            ]);

        return $response->successful();
    }

    /**
     * Get the redirect URI for the application.
     */
    public function getRedirectUri(): string
    {
        return url('/settings/calendly/callback');
    }
}
