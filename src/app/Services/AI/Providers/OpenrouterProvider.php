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
            'moonshotai/kimi-k2.5',
            // Free Models
            'google/gemini-2.0-flash-exp:free',
            'meta-llama/llama-4-8b-instruct:free',
        ];

        foreach ($data as $model) {
            $id = $model['id'] ?? '';
            if (in_array($id, $popularModels)) {
                // Add popular models first with their proper names
                $models[$id] = [
                    'model_id' => $id,
                    'display_name' => $model['name'] ?? $id,
                ];
            }
        }

        // Add any popular models that weren't in API response (new models)
        foreach ($popularModels as $modelId) {
            if (!isset($models[$modelId])) {
                $models[$modelId] = [
                    'model_id' => $modelId,
                    'display_name' => $this->formatModelName($modelId),
                ];
            }
        }

        // Add remaining models from API up to 50 total
        foreach ($data as $model) {
            $id = $model['id'] ?? '';
            if (!isset($models[$id]) && count($models) < 50) {
                $models[$id] = [
                    'model_id' => $id,
                    'display_name' => $model['name'] ?? $id,
                ];
            }
        }

        return array_values($models);
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
            throw new \Exception('OpenRouter Error: ' . ($response['error'] ?? 'Unknown error'));
        }

        $usage = $response['data']['usage'] ?? [];

        return [
            'text' => $response['data']['choices'][0]['message']['content'] ?? '',
            'tokens_input' => (int) ($usage['prompt_tokens'] ?? 0),
            'tokens_output' => (int) ($usage['completion_tokens'] ?? 0),
        ];
    }

    /**
     * Format model ID to human-readable name
     */
    protected function formatModelName(string $modelId): string
    {
        // Remove :free suffix if present
        $cleanId = preg_replace('/:free$/', ' (Free)', $modelId);

        // Split by /
        $parts = explode('/', $cleanId);

        if (count($parts) === 2) {
            $provider = ucwords(str_replace(['-', 'ai'], [' ', 'AI'], $parts[0]));
            $model = ucwords(str_replace('-', ' ', $parts[1]));
            return trim($provider) . ': ' . trim($model);
        }

        return $modelId;
    }

    /**
     * Stream text generation via OpenRouter (OpenAI-compatible SSE).
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
