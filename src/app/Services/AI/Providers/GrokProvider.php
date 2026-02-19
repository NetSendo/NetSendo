<?php

namespace App\Services\AI\Providers;

use App\Services\AI\BaseProvider;

class GrokProvider extends BaseProvider
{
    protected function getDefaultModel(): string
    {
        return 'grok-3-ultra';
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
            'max_tokens' => $options['max_tokens'] ?? 65536,
            'temperature' => $options['temperature'] ?? 0.7,
        ]);

        if (!$response['success']) {
            throw new \Exception('Grok Error: ' . ($response['error'] ?? 'Unknown error'));
        }

        return $response['data']['choices'][0]['message']['content'] ?? '';
    }

    public function generateTextWithUsage(string $prompt, ?string $model = null, array $options = []): array
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
            throw new \Exception('Grok Error: ' . ($response['error'] ?? 'Unknown error'));
        }

        $usage = $response['data']['usage'] ?? [];

        return [
            'text' => $response['data']['choices'][0]['message']['content'] ?? '',
            'tokens_input' => (int) ($usage['prompt_tokens'] ?? 0),
            'tokens_output' => (int) ($usage['completion_tokens'] ?? 0),
        ];
    }

    private function formatModelName(string $modelId): string
    {
        $names = [
            'grok-3-ultra' => 'Grok 3 Ultra (Styczeń 2026)',
            'grok-3' => 'Grok 3',
            'grok-2' => 'Grok 2',
        ];

        return $names[$modelId] ?? ucfirst(str_replace('-', ' ', $modelId));
    }

    /**
     * Stream text generation via Grok (OpenAI-compatible SSE).
     *
     * @return \Generator<string>
     */
    public function generateTextStream(string $prompt, ?string $model = null, array $options = []): \Generator
    {
        $url = rtrim($this->getBaseUrl(), '/') . '/chat/completions';

        $data = [
            'model' => $this->getModel($model),
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'max_tokens' => $options['max_tokens'] ?? 65536,
            'temperature' => $options['temperature'] ?? 0.7,
            'stream' => true,
        ];

        foreach ($this->makeStreamingRequest($url, $data) as $line) {
            if (!str_starts_with($line, 'data: ')) {
                continue;
            }

            $payload = substr($line, 6);
            if ($payload === '[DONE]') {
                break;
            }

            $json = json_decode($payload, true);
            $delta = $json['choices'][0]['delta']['content'] ?? '';

            if ($delta !== '') {
                yield $delta;
            }
        }
    }
}
