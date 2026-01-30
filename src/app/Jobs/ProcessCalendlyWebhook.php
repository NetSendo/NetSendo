<?php

namespace App\Jobs;

use App\Models\CalendlyIntegration;
use App\Services\CalendlyWebhookHandler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessCalendlyWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public array $payload,
        public int $integrationId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(CalendlyWebhookHandler $handler): void
    {
        $integration = CalendlyIntegration::find($this->integrationId);

        if (!$integration) {
            Log::warning('Calendly integration not found for webhook processing', [
                'integration_id' => $this->integrationId,
            ]);
            return;
        }

        if (!$integration->is_active) {
            Log::info('Calendly integration is inactive, skipping webhook', [
                'integration_id' => $this->integrationId,
            ]);
            return;
        }

        $event = $this->payload['event'] ?? null;

        if (!$event) {
            Log::warning('No event type in Calendly webhook payload', [
                'integration_id' => $this->integrationId,
            ]);
            return;
        }

        Log::info('Processing Calendly webhook', [
            'integration_id' => $this->integrationId,
            'event' => $event,
        ]);

        try {
            match ($event) {
                'invitee.created' => $handler->handleInviteeCreated($this->payload, $integration),
                'invitee.canceled' => $handler->handleInviteeCanceled($this->payload, $integration),
                'invitee.no_show' => $handler->handleInviteeNoShow($this->payload, $integration),
                default => Log::info('Unhandled Calendly webhook event', ['event' => $event]),
            };
        } catch (\Exception $e) {
            Log::error('Error processing Calendly webhook', [
                'integration_id' => $this->integrationId,
                'event' => $event,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e; // Re-throw to trigger retry
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Calendly webhook job failed after all retries', [
            'integration_id' => $this->integrationId,
            'event' => $this->payload['event'] ?? 'unknown',
            'error' => $exception->getMessage(),
        ]);
    }
}
