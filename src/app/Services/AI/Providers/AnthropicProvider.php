<?php

namespace App\Services\AI\Providers;

use App\Services\AI\BaseProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnthropicProvider extends BaseProvider
{
    protected function getDefaultModel(): string
    {
        return 'claude-opus-4-6';
    }

    public function supportsFetchModels(): bool
    {
        return false; // Anthropic doesn't have a models list endpoint
    }

    protected function getDefaultHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'x-api-key' => $this->getApiKey(),
            'anthropic-version' => '2023-06-01',
        ];
    }

    /**
     * Override makeRequest for Anthropic-specific handling.
     * Uses longer timeout (120s) as Claude can take longer for complex/large requests.
     */
    protected function makeRequest(string $method, string $endpoint, array $data = [], array $headers = []): array
    {
        $url = rtrim($this->getBaseUrl(), '/') . '/' . ltrim($endpoint, '/');

        $defaultHeaders = $this->getDefaultHeaders();
        $allHeaders = array_merge($defaultHeaders, $headers);

        try {
            // Use 120 second timeout for Anthropic (Claude can be slower for large requests)
            $response = Http::withHeaders($allHeaders)
                ->timeout(120)
                ->$method($url, $data);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            // Anthropic uses error.message format
            $errorBody = $response->json();
            $errorMessage = $errorBody['error']['message'] ?? $response->body();
            $errorType = $errorBody['error']['type'] ?? 'unknown_error';

            Log::warning("Anthropic API error", [
                'status' => $response->status(),
                'type' => $errorType,
                'message' => $errorMessage,
                'endpoint' => $endpoint,
            ]);

            return [
                'success' => false,
                'error' => $errorMessage,
                'error_type' => $errorType,
                'status' => $response->status(),
            ];
        } catch (\Exception $e) {
            Log::error("Anthropic API Exception: " . $e->getMessage(), [
                'endpoint' => $endpoint,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function testConnection(): array
    {
        // Try the configured model first, then fall back to default
        $model = $this->getModel(null);
        $apiKey = $this->getApiKey();

        // Log debug info (only first 10 chars of API key for security)
        $keyPreview = $apiKey ? substr($apiKey, 0, 10) . '...' : 'NULL';
        Log::info("Testing Anthropic: model={$model}, api_key_prefix={$keyPreview}");

        // Anthropic doesn't have a simple test endpoint, so we send a minimal request
        $response = $this->makeRequest('post', 'v1/messages', [
            'model' => $model,
            'max_tokens' => 1,
            'messages' => [
                ['role' => 'user', 'content' => 'Hi'],
            ],
        ]);

        if ($response['success']) {
            return [
                'success' => true,
                'message' => 'Połączenie z Anthropic działa poprawnie.',
            ];
        }

        Log::error("Anthropic testConnection failed for model [{$model}]: " . json_encode($response));

        // Check for specific error types
        $error = $response['error'] ?? 'Nieznany błąd';

        // Handle 401 - Invalid API key
        if (str_contains($error, 'invalid') && str_contains($error, 'api-key')) {
            return [
                'success' => false,
                'message' => 'Nieprawidłowy klucz API. Sprawdź czy klucz jest poprawny.',
            ];
        }

        // Handle 404 - Model not found
        if (isset($response['status']) && $response['status'] === 404) {
            return [
                'success' => false,
                'message' => 'Model "' . $model . '" nie istnieje. Wybierz inny model.',
            ];
        }

        return [
            'success' => false,
            'message' => 'Błąd połączenia (model ' . $model . '): ' . $error,
        ];
    }

    public function fetchAvailableModels(): array
    {
        // Return default models since Anthropic doesn't have a models endpoint
        return [];
    }

    public function generateText(string $prompt, ?string $model = null, array $options = []): string
    {
        $response = $this->makeRequest('post', 'v1/messages', [
            'model' => $this->getModel($model),
            'max_tokens' => $options['max_tokens'] ?? 128000,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        if (!$response['success']) {
            throw new \Exception('Anthropic Error: ' . ($response['error'] ?? 'Unknown error'));
        }

        $content = $response['data']['content'] ?? [];
        $text = '';
        foreach ($content as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'];
            }
        }

        return $text;
    }
}
