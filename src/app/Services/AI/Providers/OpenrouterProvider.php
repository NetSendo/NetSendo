<?php

namespace App\Services\AI\Providers;

use App\Services\AI\BaseProvider;

class OpenrouterProvider extends BaseProvider
{
    protected function getDefaultModel(): string
    {
        return 'openai/gpt-5';
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

        // Get top popular models for Jan 2026
        $popularModels = [
            'openai/gpt-5',
            'openai/gpt-5-mini',
            'openai/o3',
            'anthropic/claude-4-opus',
            'anthropic/claude-4-sonnet',
            'google/gemini-2.0-pro',
            'meta-llama/llama-4-405b-instruct',
            'meta-llama/llama-4-70b-instruct',
            'mistralai/mistral-large-3',
            'x-ai/grok-3',
            'deepseek/deepseek-v3',
            // Free Models
            'google/gemini-2.0-flash-exp:free',
            'meta-llama/llama-4-8b-instruct:free',
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
            'max_tokens' => $options['max_tokens'] ?? 65536,
            'temperature' => $options['temperature'] ?? 0.7,
        ]);

        if (!$response['success']) {
            throw new \Exception('OpenRouter Error: ' . ($response['error'] ?? 'Unknown error'));
        }

        return $response['data']['choices'][0]['message']['content'] ?? '';
    }
}
