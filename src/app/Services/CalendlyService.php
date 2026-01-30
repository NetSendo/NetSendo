<?php

namespace App\Services;

use App\Models\CalendlyIntegration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CalendlyService
{
    private string $baseUrl = 'https://api.calendly.com';
    private CalendlyOAuthService $oauthService;

    public function __construct(CalendlyOAuthService $oauthService)
    {
        $this->oauthService = $oauthService;
    }

    /**
     * Get HTTP client with authorization.
     */
    private function client(CalendlyIntegration $integration)
    {
        // Check if token needs refresh
        if ($integration->isTokenExpired()) {
            $this->refreshToken($integration);
        }

        return Http::withToken($integration->access_token)
            ->baseUrl($this->baseUrl)
            ->acceptJson();
    }

    /**
     * Refresh the access token.
     */
    private function refreshToken(CalendlyIntegration $integration): void
    {
        try {
            $tokens = $this->oauthService->refreshAccessToken($integration);

            $integration->update([
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'],
                'token_expires_at' => now()->addSeconds($tokens['expires_in']),
            ]);

            // Reload the model to get decrypted tokens
            $integration->refresh();
        } catch (\Exception $e) {
            Log::error('Failed to refresh Calendly token', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);

            // Mark integration as inactive if refresh fails
            $integration->update(['is_active' => false]);

            throw $e;
        }
    }

    /**
     * Get current user information.
     */
    public function getCurrentUser(CalendlyIntegration $integration): array
    {
        $response = $this->client($integration)->get('/users/me');

        if (!$response->successful()) {
            throw new \Exception('Failed to get Calendly user: ' . $response->body());
        }

        return $response->json('resource');
    }

    /**
     * Get user's event types.
     */
    public function getEventTypes(CalendlyIntegration $integration): array
    {
        $userUri = $integration->calendly_user_uri;

        if (!$userUri) {
            $user = $this->getCurrentUser($integration);
            $userUri = $user['uri'];
        }

        $response = $this->client($integration)->get('/event_types', [
            'user' => $userUri,
            'active' => 'true',
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to get event types: ' . $response->body());
        }

        return $response->json('collection') ?? [];
    }

    /**
     * Get a specific event by URI.
     */
    public function getEvent(CalendlyIntegration $integration, string $eventUri): array
    {
        // Extract event UUID from URI if full URI is provided
        $eventUuid = $this->extractUuidFromUri($eventUri);

        $response = $this->client($integration)->get("/scheduled_events/{$eventUuid}");

        if (!$response->successful()) {
            throw new \Exception('Failed to get event: ' . $response->body());
        }

        return $response->json('resource');
    }

    /**
     * Get invitee details.
     */
    public function getInvitee(CalendlyIntegration $integration, string $inviteeUri): array
    {
        // Parse the invitee URI to get event UUID and invitee UUID
        // Format: https://api.calendly.com/scheduled_events/{event_uuid}/invitees/{invitee_uuid}
        preg_match('/scheduled_events\/([^\/]+)\/invitees\/([^\/]+)/', $inviteeUri, $matches);

        if (count($matches) !== 3) {
            throw new \Exception('Invalid invitee URI format');
        }

        $eventUuid = $matches[1];
        $inviteeUuid = $matches[2];

        $response = $this->client($integration)
            ->get("/scheduled_events/{$eventUuid}/invitees/{$inviteeUuid}");

        if (!$response->successful()) {
            throw new \Exception('Failed to get invitee: ' . $response->body());
        }

        return $response->json('resource');
    }

    /**
     * Create a webhook subscription.
     */
    public function createWebhookSubscription(
        CalendlyIntegration $integration,
        string $callbackUrl,
        array $events = ['invitee.created', 'invitee.canceled', 'invitee.no_show']
    ): array {
        $organizationUri = $integration->calendly_organization_uri;

        if (!$organizationUri) {
            $user = $this->getCurrentUser($integration);
            $organizationUri = $user['current_organization'];
        }

        $response = $this->client($integration)->post('/webhook_subscriptions', [
            'url' => $callbackUrl,
            'events' => $events,
            'organization' => $organizationUri,
            'scope' => 'organization',
            'signing_key' => $this->generateSigningKey(),
        ]);

        if (!$response->successful()) {
            Log::error('Failed to create Calendly webhook', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Failed to create webhook subscription: ' . $response->body());
        }

        return $response->json('resource');
    }

    /**
     * Delete a webhook subscription.
     */
    public function deleteWebhookSubscription(CalendlyIntegration $integration): bool
    {
        if (!$integration->webhook_id) {
            return true;
        }

        $webhookUuid = $this->extractUuidFromUri($integration->webhook_id);

        $response = $this->client($integration)
            ->delete("/webhook_subscriptions/{$webhookUuid}");

        if (!$response->successful() && $response->status() !== 404) {
            Log::warning('Failed to delete Calendly webhook', [
                'webhook_id' => $integration->webhook_id,
                'status' => $response->status(),
            ]);
            return false;
        }

        return true;
    }

    /**
     * Get webhook subscription details.
     */
    public function getWebhookSubscription(CalendlyIntegration $integration): ?array
    {
        if (!$integration->webhook_id) {
            return null;
        }

        $webhookUuid = $this->extractUuidFromUri($integration->webhook_id);

        $response = $this->client($integration)
            ->get("/webhook_subscriptions/{$webhookUuid}");

        if (!$response->successful()) {
            return null;
        }

        return $response->json('resource');
    }

    /**
     * List all webhook subscriptions for the organization.
     */
    public function listWebhookSubscriptions(CalendlyIntegration $integration): array
    {
        $organizationUri = $integration->calendly_organization_uri;

        $response = $this->client($integration)->get('/webhook_subscriptions', [
            'organization' => $organizationUri,
            'scope' => 'organization',
        ]);

        if (!$response->successful()) {
            return [];
        }

        return $response->json('collection') ?? [];
    }

    /**
     * Extract UUID from Calendly URI.
     */
    private function extractUuidFromUri(string $uri): string
    {
        // If it's already just a UUID, return it
        if (!str_contains($uri, '/')) {
            return $uri;
        }

        // Extract the last segment of the URI path
        $parts = explode('/', rtrim($uri, '/'));
        return end($parts);
    }

    /**
     * Generate a random signing key for webhooks.
     */
    private function generateSigningKey(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Verify webhook signature.
     */
    public function verifyWebhookSignature(string $payload, string $signature, string $signingKey): bool
    {
        // Calendly uses HMAC-SHA256 for webhook signatures
        // The signature header format is: t=timestamp,v1=signature

        if (!preg_match('/t=(\d+),v1=([a-f0-9]+)/', $signature, $matches)) {
            return false;
        }

        $timestamp = $matches[1];
        $providedSignature = $matches[2];

        // Check if timestamp is within 5 minutes
        if (abs(time() - (int)$timestamp) > 300) {
            Log::warning('Calendly webhook signature timestamp too old');
            return false;
        }

        // Calculate expected signature
        $signedPayload = "{$timestamp}.{$payload}";
        $expectedSignature = hash_hmac('sha256', $signedPayload, $signingKey);

        return hash_equals($expectedSignature, $providedSignature);
    }
}
