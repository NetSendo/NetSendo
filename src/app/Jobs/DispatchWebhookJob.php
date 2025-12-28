<?php

namespace App\Jobs;

use App\Models\Webhook;
use App\Services\WebhookDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class DispatchWebhookJob implements ShouldQueue
{
    use Queueable;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Seconds to wait before retrying the job.
     */
    public int $backoff = 10;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $webhookId,
        public string $event,
        public array $data
    ) {}

    /**
     * Execute the job.
     */
    public function handle(WebhookDispatcher $dispatcher): void
    {
        $webhook = Webhook::find($this->webhookId);

        if (!$webhook || !$webhook->is_active) {
            Log::info('Webhook job skipped - webhook inactive or not found', [
                'webhook_id' => $this->webhookId,
                'event' => $this->event,
            ]);
            return;
        }

        $dispatcher->send($webhook, $this->event, $this->data);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Webhook job failed', [
            'webhook_id' => $this->webhookId,
            'event' => $this->event,
            'error' => $exception->getMessage(),
        ]);
    }
}
