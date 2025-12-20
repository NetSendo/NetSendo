<?php

namespace App\Services\AI\Providers;

use App\Services\AI\BaseProvider;

class OpenrouterProvider extends BaseProvider
{
    protected function getDefaultModel(): string
    {
        return 'openai/gpt-4o';
    }

    public function supportsFetchModels(): bool
    {
        return true;
    }

    protected function getDefaultHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $this->getApiKey(),
            'HTTP-Referer' => config('app.url', 'http://localhost'),
            'X-Title' => config('app.name', 'NetSendo'),
        ];
    }

    public function testConnection(): array
    {
        $response = $this->makeRequest('get', 'models');

        if ($response['success']) {
            return [
                'success' => true,
                'message' => 'Połączenie z OpenRouter działa poprawnie.',
            ];
        }

        return [
            'success' => false,
            'message' => 'Błąd połączenia: ' . ($response['error'] ?? 'Nieznany błąd'),
        ];
    }

    public function fetchAvailableModels(): array
    {
        $response = $this->makeRequest('get', 'models');

        if (!$response['success']) {
            return [];
        }

        $models = [];
        $data = $response['data']['data'] ?? [];

        // Get top popular models
        $popularModels = [
            'openai/gpt-4o',
            'openai/gpt-4o-mini',
            'anthropic/claude-3.5-sonnet',
            'anthropic/claude-3-opus',
            'google/gemini-pro-1.5',
            'meta-llama/llama-3.1-405b-instruct',
            'meta-llama/llama-3.1-70b-instruct',
            'mistralai/mistral-large',
        ];

        foreach ($data as $model) {
            $id = $model['id'] ?? '';
            if (in_array($id, $popularModels) || count($models) < 50) {
                $models[] = [
                    'model_id' => $id,
                    'display_name' => $model['name'] ?? $id,
                ];
            }
        }

        return $models;
    }

    public function generateText(string $prompt, ?string $model = null, array $options = []): string
    {
        $response = $this->makeRequest('post', 'chat/completions', [
            'model' => $this->getModel($model),
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'max_tokens' => $options['max_tokens'] ?? 1024,
            'temperature' => $options['temperature'] ?? 0.7,
        ]);

        if (!$response['success']) {
            throw new \Exception('OpenRouter Error: ' . ($response['error'] ?? 'Unknown error'));
        }

        return $response['data']['choices'][0]['message']['content'] ?? '';
    }
}
