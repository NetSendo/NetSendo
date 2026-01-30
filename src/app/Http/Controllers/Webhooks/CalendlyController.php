<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessCalendlyWebhook;
use App\Models\CalendlyIntegration;
use App\Services\CalendlyService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class CalendlyController extends Controller
{
    public function __construct(
        private CalendlyService $calendlyService
    ) {}

    /**
     * Handle incoming Calendly webhook.
     */
    public function handle(Request $request): Response
    {
        $payload = $request->all();
        $signature = $request->header('Calendly-Webhook-Signature');

        Log::info('Received Calendly webhook', [
            'event' => $payload['event'] ?? 'unknown',
            'has_signature' => !empty($signature),
        ]);

        // Validate required fields
        if (empty($payload['event'])) {
            Log::warning('Calendly webhook: missing event type');
            return response('Missing event type', 400);
        }

        // Find the integration by organization URI from the payload
        $createdBy = $payload['created_by'] ?? null;
        $organizationUri = $this->extractOrganizationUri($payload);

        if (!$organizationUri && !$createdBy) {
            Log::warning('Calendly webhook: cannot identify organization');
            return response('Cannot identify organization', 400);
        }

        // Try to find integration by organization URI or user URI
        $integration = $this->findIntegration($organizationUri, $createdBy);

        if (!$integration) {
            Log::warning('Calendly webhook: integration not found', [
                'organization_uri' => $organizationUri,
                'created_by' => $createdBy,
            ]);
            return response('Integration not found', 404);
        }

        // Verify webhook signature if we have a signing key
        if ($integration->webhook_signing_key && $signature) {
            $rawPayload = $request->getContent();

            if (!$this->calendlyService->verifyWebhookSignature($rawPayload, $signature, $integration->webhook_signing_key)) {
                Log::warning('Calendly webhook: invalid signature', [
                    'integration_id' => $integration->id,
                ]);
                return response('Invalid signature', 401);
            }
        }

        // Dispatch job for async processing
        ProcessCalendlyWebhook::dispatch($payload, $integration->id);

        Log::info('Calendly webhook queued for processing', [
            'integration_id' => $integration->id,
            'event' => $payload['event'],
        ]);

        // Return 200 OK immediately to acknowledge receipt
        return response('OK', 200);
    }

    /**
     * Extract organization URI from payload.
     */
    private function extractOrganizationUri(array $payload): ?string
    {
        // Try to get from scheduled_event
        $scheduledEvent = $payload['payload']['scheduled_event'] ?? [];
        if (!empty($scheduledEvent['event_memberships'])) {
            foreach ($scheduledEvent['event_memberships'] as $membership) {
                if (!empty($membership['user'])) {
                    // We can't get org directly from user, but we can use user URI
                    return null;
                }
            }
        }

        return null;
    }

    /**
     * Find integration by organization or user URI.
     */
    private function findIntegration(?string $organizationUri, ?string $createdBy): ?CalendlyIntegration
    {
        // First try by organization
        if ($organizationUri) {
            $integration = CalendlyIntegration::where('calendly_organization_uri', $organizationUri)
                ->where('is_active', true)
                ->first();

            if ($integration) {
                return $integration;
            }
        }

        // Try by user URI (created_by)
        if ($createdBy) {
            $integration = CalendlyIntegration::where('calendly_user_uri', $createdBy)
                ->where('is_active', true)
                ->first();

            if ($integration) {
                return $integration;
            }
        }

        // Fallback: try to find any active integration
        // This is useful for single-user setups
        return CalendlyIntegration::where('is_active', true)->first();
    }
}
