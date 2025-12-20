<?php

namespace App\Services\Mail;

use App\Models\Mailbox;
use App\Models\GoogleIntegration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Exception;

class GmailOAuthService
{
    private const GOOGLE_AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const GOOGLE_TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const GOOGLE_USERINFO_URL = 'https://www.googleapis.com/oauth2/v2/userinfo';
    
    private const SCOPES = [
        'https://www.googleapis.com/auth/gmail.send',
        'https://www.googleapis.com/auth/userinfo.email',
        'https://www.googleapis.com/auth/userinfo.profile',
        'openid',
    ];

    /**
     * Get authorization URL for authorization
     */
    public function getAuthorizationUrl(GoogleIntegration $integration, string $state): string
    {
        $params = [
            'client_id' => $integration->client_id,
            'redirect_uri' => route('settings.mailboxes.gmail.callback'), // Always use the same callback
            'response_type' => 'code',
            'scope' => implode(' ', self::SCOPES),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ];

        return self::GOOGLE_AUTH_URL . '?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for tokens
     */
    public function exchangeCodeForTokens(GoogleIntegration $integration, string $code): array
    {
        $response = Http::asForm()->post(self::GOOGLE_TOKEN_URL, [
            'client_id' => $integration->client_id,
            'client_secret' => $integration->client_secret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => route('settings.mailboxes.gmail.callback'),
        ]);

        if (!$response->successful()) {
            Log::error('Gmail OAuth token exchange failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new Exception('Failed to exchange code for tokens: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Refresh an expired access token
     */
    public function refreshAccessToken(GoogleIntegration $integration, string $refreshToken): array
    {
        $response = Http::asForm()->post(self::GOOGLE_TOKEN_URL, [
            'client_id' => $integration->client_id,
            'client_secret' => $integration->client_secret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);

        if (!$response->successful()) {
            Log::error('Gmail OAuth token refresh failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new Exception('Failed to refresh access token');
        }

        return $response->json();
    }

    /**
     * Get user info (email) from Google
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
     * Store OAuth tokens in mailbox
     */
    public function storeTokens(Mailbox $mailbox, array $tokenData): void
    {
        $credentials = $mailbox->getDecryptedCredentials();
        
        $credentials['access_token'] = $tokenData['access_token'];
        $credentials['token_expires_at'] = now()->addSeconds($tokenData['expires_in'] ?? 3600)->toIso8601String();
        
        // Only update refresh_token if provided (not always returned on refresh)
        if (isset($tokenData['refresh_token'])) {
            $credentials['refresh_token'] = $tokenData['refresh_token'];
        }

        $mailbox->update(['credentials' => $credentials]);
    }

    /**
     * Store connected Gmail email in mailbox
     */
    public function storeGmailEmail(Mailbox $mailbox, string $email): void
    {
        $credentials = $mailbox->getDecryptedCredentials();
        $credentials['gmail_email'] = $email;
        $mailbox->update(['credentials' => $credentials]);
    }

    /**
     * Get a valid access token (refresh if expired)
     */
    public function getValidAccessToken(Mailbox $mailbox): string
    {
        if (!$mailbox->googleIntegration) {
             throw new Exception('Mailbox does not have a Google Integration assigned.');
        }

        $credentials = $mailbox->getDecryptedCredentials();

        if (empty($credentials['access_token'])) {
            throw new Exception('Gmail not connected - no access token');
        }

        // Check if token is expired
        $expiresAt = isset($credentials['token_expires_at']) 
            ? now()->parse($credentials['token_expires_at']) 
            : now()->subMinute();

        if ($expiresAt->isPast() || $expiresAt->diffInMinutes(now()) < 5) {
            // Token expired or expiring soon, refresh it
            if (empty($credentials['refresh_token'])) {
                throw new Exception('Gmail token expired and no refresh token available');
            }

            $newTokens = $this->refreshAccessToken($mailbox->googleIntegration, $credentials['refresh_token']);
            $this->storeTokens($mailbox, $newTokens);
            
            return $newTokens['access_token'];
        }

        return $credentials['access_token'];
    }

    /**
     * Check if mailbox is connected to Gmail
     */
    public function isConnected(Mailbox $mailbox): bool
    {
        $credentials = $mailbox->getDecryptedCredentials();
        return !empty($credentials['access_token']) && !empty($credentials['refresh_token']);
    }

    /**
     * Get connected Gmail email
     */
    public function getConnectedEmail(Mailbox $mailbox): ?string
    {
        $credentials = $mailbox->getDecryptedCredentials();
        return $credentials['gmail_email'] ?? null;
    }

    /**
     * Disconnect Gmail (remove tokens)
     */
    public function disconnect(Mailbox $mailbox): void
    {
        $mailbox->update([
            'credentials' => [],
            'last_test_success' => null,
            'last_test_message' => null,
            'last_tested_at' => null,
        ]);
    }

    /**
     * Encode state parameter for OAuth
     */
    public function encodeState(array $data): string
    {
        $data['csrf'] = csrf_token();
        return base64_encode(json_encode($data));
    }

    /**
     * Decode OAuth state parameter
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
