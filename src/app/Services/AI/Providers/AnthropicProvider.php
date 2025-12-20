<?php

namespace App\Services\AI\Providers;

use App\Services\AI\BaseProvider;

class AnthropicProvider extends BaseProvider
{
    protected function getDefaultModel(): string
    {
        return 'claude-3-5-sonnet-20241022';
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
        // Anthropic doesn't have a simple test endpoint, so we send a minimal request
        $response = $this->makeRequest('post', 'v1/messages', [
            'model' => $this->getDefaultModel(),
            'max_tokens' => 10,
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

        // Check for specific error types
        $error = $response['error'] ?? 'Nieznany błąd';
        if (str_contains($error, 'invalid_api_key') || str_contains($error, 'authentication')) {
            return [
                'success' => false,
                'message' => 'Nieprawidłowy klucz API.',
            ];
        }

        return [
            'success' => false,
            'message' => 'Błąd połączenia: ' . $error,
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
            'max_tokens' => $options['max_tokens'] ?? 1024,
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
