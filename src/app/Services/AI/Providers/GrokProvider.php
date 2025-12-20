<?php

namespace App\Services\AI\Providers;

use App\Services\AI\BaseProvider;

class GrokProvider extends BaseProvider
{
    protected function getDefaultModel(): string
    {
        return 'grok-2-1212';
    }

    public function supportsFetchModels(): bool
    {
        return true;
    }

    public function testConnection(): array
    {
        $response = $this->makeRequest('get', 'models');

        if ($response['success']) {
            return [
                'success' => true,
                'message' => 'Połączenie z Grok (xAI) działa poprawnie.',
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

        foreach ($data as $model) {
            $id = $model['id'] ?? '';
            if (str_starts_with($id, 'grok')) {
                $models[] = [
                    'model_id' => $id,
                    'display_name' => $this->formatModelName($id),
                ];
            }
        }

        return $models;
    }

    public function generateText(string $prompt, ?string $model = null, array $options = []): string
    {
        // Grok uses OpenAI-compatible API
        $response = $this->makeRequest('post', 'chat/completions', [
            'model' => $this->getModel($model),
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'max_tokens' => $options['max_tokens'] ?? 1024,
            'temperature' => $options['temperature'] ?? 0.7,
        ]);

        if (!$response['success']) {
            throw new \Exception('Grok Error: ' . ($response['error'] ?? 'Unknown error'));
        }

        return $response['data']['choices'][0]['message']['content'] ?? '';
    }

    private function formatModelName(string $modelId): string
    {
        $names = [
            'grok-2-1212' => 'Grok 2 (Grudzień 2024)',
            'grok-2-vision-1212' => 'Grok 2 Vision',
            'grok-beta' => 'Grok Beta',
        ];

        return $names[$modelId] ?? ucfirst(str_replace('-', ' ', $modelId));
    }
}
