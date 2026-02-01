<?php

namespace App\Services\AI\Providers;

use App\Services\AI\BaseProvider;

class OpenAiProvider extends BaseProvider
{
    protected function getDefaultModel(): string
    {
        return 'gpt-4o-mini';
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
                'message' => 'Połączenie z OpenAI działa poprawnie.',
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

        // Filter to only chat models
        $chatModels = array_filter($data, function ($model) {
            $id = $model['id'] ?? '';
            return str_starts_with($id, 'gpt-') || str_starts_with($id, 'o1');
        });

        foreach ($chatModels as $model) {
            $id = $model['id'];
            $models[] = [
                'model_id' => $id,
                'display_name' => $this->formatModelName($id),
            ];
        }

        // Sort by model name
        usort($models, fn($a, $b) => strcmp($a['model_id'], $b['model_id']));

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
            throw new \Exception('OpenAI Error: ' . ($response['error'] ?? 'Unknown error'));
        }

        return $response['data']['choices'][0]['message']['content'] ?? '';
    }

    private function formatModelName(string $modelId): string
    {
        $names = [
            'gpt-4o' => 'GPT-4o',
            'gpt-4o-mini' => 'GPT-4o Mini',
            'gpt-4-turbo' => 'GPT-4 Turbo',
            'gpt-4' => 'GPT-4',
            'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
            'o1-preview' => 'o1 Preview',
            'o1-mini' => 'o1 Mini',
        ];

        return $names[$modelId] ?? strtoupper(str_replace('-', ' ', $modelId));
    }

    /**
     * Check if this provider supports vision/image analysis.
     */
    public function supportsVision(): bool
    {
        return true; // GPT-4o and GPT-4o-mini support vision
    }

    /**
     * Generate text from a prompt with an image.
     */
    public function generateWithImage(
        string $prompt,
        string $base64Image,
        string $mimeType,
        array $options = []
    ): string {
        $model = $options['model'] ?? 'gpt-4o'; // GPT-4o supports vision

        $response = $this->makeRequest('post', 'chat/completions', [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => $prompt,
                        ],
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => "data:{$mimeType};base64,{$base64Image}",
                                'detail' => $options['detail'] ?? 'high',
                            ],
                        ],
                    ],
                ],
            ],
            'max_tokens' => $options['max_tokens'] ?? 2000,
            'temperature' => $options['temperature'] ?? 0.3,
        ]);

        if (!$response['success']) {
            throw new \Exception('OpenAI Vision Error: ' . ($response['error'] ?? 'Unknown error'));
        }

        return $response['data']['choices'][0]['message']['content'] ?? '';
    }
}
