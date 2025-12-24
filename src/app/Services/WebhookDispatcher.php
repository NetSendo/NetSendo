<?php

namespace App\Services;

use App\Models\Webhook;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookDispatcher
{
    /**
     * Dispatch an event to all registered webhooks
     */
    public function dispatch(int $userId, string $event, array $data): void
    {
        $webhooks = Webhook::where('user_id', $userId)
            ->active()
            ->forEvent($event)
            ->get();

        foreach ($webhooks as $webhook) {
            $this->send($webhook, $event, $data);
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

            if ($response->successful()) {
                $webhook->markTriggered();

                Log::info('Webhook dispatched successfully', [
                    'webhook_id' => $webhook->id,
                    'event' => $event,
                    'url' => $webhook->url,
                    'status' => $response->status(),
                ]);

                return true;
            }

            $webhook->incrementFailure();

            Log::warning('Webhook failed with non-success status', [
                'webhook_id' => $webhook->id,
                'event' => $event,
                'url' => $webhook->url,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;

        } catch (\Exception $e) {
            $webhook->incrementFailure();

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
