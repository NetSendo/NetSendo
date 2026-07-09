<?php

namespace App\Services;

use App\Jobs\DispatchListWebhookJob;
use App\Jobs\DispatchWebhookJob;
use App\Models\ContactList;
use App\Models\Webhook;
use App\Models\WebhookLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookDispatcher
{
    /**
     * Dispatch an event to all registered webhooks
     *
     * @param int $userId User ID
     * @param string $event Event name
     * @param array $data Event data
     * @param bool $async If true, dispatch via queue (default). If false, send synchronously.
     */
    public function dispatch(int $userId, string $event, array $data, bool $async = true): void
    {
        $webhooks = Webhook::where('user_id', $userId)
            ->active()
            ->forEvent($event)
            ->get();

        foreach ($webhooks as $webhook) {
            if ($async) {
                // Dispatch to queue for async processing
                DispatchWebhookJob::dispatch($webhook->id, $event, $data);
            } else {
                // Send synchronously (for testing or specific cases)
                $this->send($webhook, $event, $data);
            }
        }
    }


    /**
     * Send webhook payload
     */
    public function send(Webhook $webhook, string $event, array $data): bool
    {
        $payload = [
            'event' => $event,
            'timestamp' => now()->toISOString(),
            'data' => $data,
        ];

        $jsonPayload = json_encode($payload);
        $signature = $webhook->sign($jsonPayload);

        $startTime = microtime(true);
        $logEntry = [
            'user_id' => $webhook->user_id,
            'webhook_id' => $webhook->id,
            'event' => $event,
            'url' => $webhook->url,
            'payload' => $payload,
        ];

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-NetSendo-Signature' => $signature,
                    'X-NetSendo-Event' => $event,
                    'User-Agent' => 'NetSendo-Webhook/1.0',
                ])
                ->withBody($jsonPayload, 'application/json')
                ->post($webhook->url);

            $durationMs = (int)((microtime(true) - $startTime) * 1000);

            if ($response->successful()) {
                $webhook->markTriggered();

                // Log to database
                WebhookLog::create(array_merge($logEntry, [
                    'status' => 'success',
                    'response_code' => $response->status(),
                    'response_body' => substr($response->body(), 0, 1000),
                    'duration_ms' => $durationMs,
                    'created_at' => now(),
                ]));

                Log::info('Webhook dispatched successfully', [
                    'webhook_id' => $webhook->id,
                    'event' => $event,
                    'url' => $webhook->url,
                    'status' => $response->status(),
                ]);

                return true;
            }

            $webhook->incrementFailure();

            // Log failure to database
            WebhookLog::create(array_merge($logEntry, [
                'status' => 'failed',
                'response_code' => $response->status(),
                'response_body' => substr($response->body(), 0, 1000),
                'error_message' => 'Non-success HTTP status',
                'duration_ms' => $durationMs,
                'created_at' => now(),
            ]));

            Log::warning('Webhook failed with non-success status', [
                'webhook_id' => $webhook->id,
                'event' => $event,
                'url' => $webhook->url,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;

        } catch (\Exception $e) {
            $durationMs = (int)((microtime(true) - $startTime) * 1000);
            $webhook->incrementFailure();

            // Log exception to database
            WebhookLog::create(array_merge($logEntry, [
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'duration_ms' => $durationMs,
                'created_at' => now(),
            ]));

            Log::error('Webhook dispatch error', [
                'webhook_id' => $webhook->id,
                'event' => $event,
                'url' => $webhook->url,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Dispatch an event to a specific list's configured webhook_url.
     *
     * Bridges the per-list webhook (list settings → Integration) into the
     * event-driven pipeline so it fires on every real subscriber event and is
     * recorded in webhook_logs — not only on the handful of inline
     * 'subscribe'/'unsubscribe' entry points it used to cover (GitHub #20).
     */
    public function dispatchToList(ContactList $list, string $event, array $data, bool $async = true): void
    {
        if (empty($list->webhook_url) || !$list->subscribedToWebhookEvent($event)) {
            return;
        }

        if ($async) {
            DispatchListWebhookJob::dispatch($list->id, $event, $data);
        } else {
            $this->sendToList($list, $event, $data);
        }
    }

    /**
     * Send a per-list webhook payload and record the delivery in webhook_logs.
     *
     * Unlike registered webhooks, a list's webhook_url has no stored secret,
     * so the payload is delivered unsigned (matching the pre-existing per-list
     * behaviour) and logged with a null webhook_id.
     */
    public function sendToList(ContactList $list, string $event, array $data): bool
    {
        if (empty($list->webhook_url)) {
            return false;
        }

        $payload = [
            'event' => $event,
            'list_id' => $list->id,
            'list_name' => $list->name,
            'timestamp' => now()->toISOString(),
            'data' => $data,
        ];

        $jsonPayload = json_encode($payload);
        $startTime = microtime(true);

        $logEntry = [
            'user_id' => $list->user_id,
            'webhook_id' => null,
            'event' => $event,
            'url' => $list->webhook_url,
            'payload' => $payload,
        ];

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-NetSendo-Event' => $event,
                    'User-Agent' => 'NetSendo-Webhook/1.0',
                ])
                ->withBody($jsonPayload, 'application/json')
                ->post($list->webhook_url);

            $durationMs = (int)((microtime(true) - $startTime) * 1000);

            WebhookLog::create(array_merge($logEntry, [
                'status' => $response->successful() ? 'success' : 'failed',
                'response_code' => $response->status(),
                'response_body' => substr($response->body(), 0, 1000),
                'error_message' => $response->successful() ? null : 'Non-success HTTP status',
                'duration_ms' => $durationMs,
                'created_at' => now(),
            ]));

            if (!$response->successful()) {
                Log::warning('List webhook failed with non-success status', [
                    'list_id' => $list->id,
                    'event' => $event,
                    'url' => $list->webhook_url,
                    'status' => $response->status(),
                ]);
            }

            return $response->successful();
        } catch (\Exception $e) {
            $durationMs = (int)((microtime(true) - $startTime) * 1000);

            WebhookLog::create(array_merge($logEntry, [
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'duration_ms' => $durationMs,
                'created_at' => now(),
            ]));

            Log::error('List webhook dispatch error', [
                'list_id' => $list->id,
                'event' => $event,
                'url' => $list->webhook_url,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send a test webhook
     */
    public function sendTest(Webhook $webhook): array
    {
        $testData = [
            'test' => true,
            'message' => 'This is a test webhook from NetSendo',
            'subscriber' => [
                'id' => 0,
                'email' => 'test@example.com',
                'first_name' => 'Test',
                'last_name' => 'User',
            ],
        ];

        $success = $this->send($webhook, 'webhook.test', $testData);

        return [
            'success' => $success,
            'message' => $success
                ? 'Test webhook sent successfully'
                : 'Failed to send test webhook',
        ];
    }
}
