<?php

namespace App\Services\Funnels;

use App\Models\FunnelSubscriber;
use App\Models\Subscriber;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\Response;

class WebhookService
{
    // Retry configuration
    public const MAX_RETRIES = 3;
    public const INITIAL_DELAY_MS = 1000; // 1 second
    public const BACKOFF_MULTIPLIER = 2;

    /**
     * Send webhook with retry logic.
     */
    public function send(
        string $url,
        array $payload,
        array $headers = [],
        string $method = 'POST',
        int $timeoutSeconds = 30
    ): array {
        $lastError = null;
        $lastResponse = null;
        $attempts = 0;

        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            $attempts = $attempt;

            try {
                $request = Http::timeout($timeoutSeconds)
                    ->withHeaders(array_merge([
                        'Content-Type' => 'application/json',
                        'X-Webhook-Source' => 'NetSendo',
                        'X-Webhook-Attempt' => (string) $attempt,
                    ], $headers));

                $response = match (strtoupper($method)) {
                    'GET' => $request->get($url, $payload),
                    'PUT' => $request->put($url, $payload),
                    'PATCH' => $request->patch($url, $payload),
                    'DELETE' => $request->delete($url, $payload),
                    default => $request->post($url, $payload),
                };

                $lastResponse = $response;

                // Success (2xx status codes)
                if ($response->successful()) {
                    return [
                        'success' => true,
                        'status_code' => $response->status(),
                        'body' => $response->json() ?? $response->body(),
                        'attempts' => $attempts,
                    ];
                }

                // Client error (4xx) - don't retry
                if ($response->clientError()) {
                    return [
                        'success' => false,
                        'status_code' => $response->status(),
                        'error' => 'Client error: ' . $response->status(),
                        'body' => $response->body(),
                        'attempts' => $attempts,
                    ];
                }

                // Server error (5xx) - retry
                $lastError = 'Server error: ' . $response->status();

            } catch (\Exception $e) {
                $lastError = $e->getMessage();
                Log::warning("Webhook attempt {$attempt} failed: {$lastError}", [
                    'url' => $url,
                ]);
            }

            // Wait before retry with exponential backoff
            if ($attempt < self::MAX_RETRIES) {
                $delayMs = self::INITIAL_DELAY_MS * pow(self::BACKOFF_MULTIPLIER, $attempt - 1);
                usleep($delayMs * 1000);
            }
        }

        return [
            'success' => false,
            'error' => $lastError ?? 'Unknown error',
            'status_code' => $lastResponse?->status(),
            'attempts' => $attempts,
        ];
    }

    /**
     * Build webhook payload with variable substitution.
     */
    public function buildPayload(
        FunnelSubscriber $enrollment,
        array $templateData = [],
        ?string $eventName = null
    ): array {
        $subscriber = $enrollment->subscriber;
        $funnel = $enrollment->funnel;

        $basePayload = [
            'event' => $eventName ?? 'funnel_webhook',
            'timestamp' => now()->toISOString(),
            'funnel' => [
                'id' => $funnel->id,
                'name' => $funnel->name,
            ],
            'subscriber' => $this->formatSubscriberData($subscriber),
            'enrollment' => [
                'id' => $enrollment->id,
                'status' => $enrollment->status,
                'steps_completed' => $enrollment->steps_completed,
                'entered_at' => $enrollment->entered_at?->toISOString(),
                'current_step' => $enrollment->currentStep?->name,
            ],
        ];

        // Merge with template data and substitute variables
        $mergedData = array_merge($basePayload, $templateData);

        return $this->substituteVariables($mergedData, $subscriber, $enrollment);
    }

    /**
     * Format subscriber data for webhook payload.
     */
    protected function formatSubscriberData(Subscriber $subscriber): array
    {
        $data = [
            'id' => $subscriber->id,
            'email' => $subscriber->email,
            'first_name' => $subscriber->first_name,
            'last_name' => $subscriber->last_name,
            'full_name' => $subscriber->full_name,
            'status' => $subscriber->status,
            'created_at' => $subscriber->created_at?->toISOString(),
        ];

        // Add custom fields
        if (method_exists($subscriber, 'getCustomFields')) {
            $data['custom_fields'] = $subscriber->getCustomFields();
        }

        // Add tags
        if (method_exists($subscriber, 'getAllTags')) {
            $data['tags'] = $subscriber->getAllTags();
        }

        return $data;
    }

    /**
     * Substitute variables in payload.
     * Supports {{subscriber.email}}, {{funnel.name}}, etc.
     */
    protected function substituteVariables(array $data, Subscriber $subscriber, FunnelSubscriber $enrollment): array
    {
        $variables = [
            '{{subscriber.email}}' => $subscriber->email,
            '{{subscriber.first_name}}' => $subscriber->first_name ?? '',
            '{{subscriber.last_name}}' => $subscriber->last_name ?? '',
            '{{subscriber.full_name}}' => $subscriber->full_name ?? '',
            '{{subscriber.id}}' => (string) $subscriber->id,
            '{{funnel.id}}' => (string) $enrollment->funnel_id,
            '{{funnel.name}}' => $enrollment->funnel?->name ?? '',
            '{{enrollment.id}}' => (string) $enrollment->id,
            '{{enrollment.status}}' => $enrollment->status,
            '{{enrollment.steps_completed}}' => (string) $enrollment->steps_completed,
            '{{timestamp}}' => now()->toISOString(),
            '{{date}}' => now()->toDateString(),
        ];

        return $this->replaceRecursive($data, $variables);
    }

    /**
     * Recursively replace variables in array.
     */
    protected function replaceRecursive(array $data, array $variables): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = strtr($value, $variables);
            } elseif (is_array($value)) {
                $data[$key] = $this->replaceRecursive($value, $variables);
            }
        }

        return $data;
    }

    /**
     * Parse custom headers from config.
     */
    public function parseHeaders(array $config): array
    {
        $headers = [];

        // Headers from config array
        if (isset($config['headers']) && is_array($config['headers'])) {
            $headers = $config['headers'];
        }

        // Authorization header shortcuts
        if (!empty($config['api_key'])) {
            $headers['Authorization'] = 'Bearer ' . $config['api_key'];
        }

        if (!empty($config['basic_auth'])) {
            $headers['Authorization'] = 'Basic ' . base64_encode($config['basic_auth']);
        }

        return $headers;
    }
}
