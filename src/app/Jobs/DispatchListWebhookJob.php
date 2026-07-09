<?php

namespace App\Jobs;

use App\Models\ContactList;
use App\Services\WebhookDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Delivers a per-list webhook (list settings → Integration) asynchronously.
 *
 * Mirrors DispatchWebhookJob but targets a ContactList's webhook_url instead
 * of a registered Webhook row. Introduced for GitHub #20 so list webhooks fire
 * on every real subscriber event via DispatchWebhooksListener.
 */
class DispatchListWebhookJob implements ShouldQueue
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

    public function __construct(
        public int $listId,
        public string $event,
        public array $data
    ) {}

    public function handle(WebhookDispatcher $dispatcher): void
    {
        $list = ContactList::find($this->listId);

        if (!$list || empty($list->webhook_url)) {
            Log::info('List webhook job skipped - list missing or no webhook_url', [
                'list_id' => $this->listId,
                'event' => $this->event,
            ]);
            return;
        }

        $dispatcher->sendToList($list, $this->event, $this->data);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('List webhook job failed', [
            'list_id' => $this->listId,
            'event' => $this->event,
            'error' => $exception->getMessage(),
        ]);
    }
}
