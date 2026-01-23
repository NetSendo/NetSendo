<?php

namespace App\Services\AI\Providers;

use App\Services\AI\BaseProvider;

class AnthropicProvider extends BaseProvider
{
    protected function getDefaultModel(): string
    {
        return 'claude-sonnet-4-5-20250929';
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

    public function testConnection(): array
    {
        // Try the configured model first, then fall back to default
        $model = $this->getModel(null);
        $apiKey = $this->getApiKey();

        // Log debug info (only first 10 chars of API key for security)
        $keyPreview = $apiKey ? substr($apiKey, 0, 10) . '...' : 'NULL';
        \Illuminate\Support\Facades\Log::info("Testing Anthropic: model={$model}, api_key_prefix={$keyPreview}");

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

        \Illuminate\Support\Facades\Log::error("Anthropic testConnection failed for model [{$model}]: " . json_encode($response));

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
            'max_tokens' => $options['max_tokens'] ?? 65536,
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
