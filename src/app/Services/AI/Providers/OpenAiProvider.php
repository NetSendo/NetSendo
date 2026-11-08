<?php

namespace App\Services\AI\Providers;

use App\Services\AI\BaseProvider;
use App\Services\AI\Concerns\OpenAiCompatibleEmbeddings;
use App\Services\AI\EmbeddingProviderInterface;

class OpenAiProvider extends BaseProvider implements EmbeddingProviderInterface
{
    use OpenAiCompatibleEmbeddings;

    public function embeddingModels(): array
    {
        return ['text-embedding-3-small', 'text-embedding-3-large'];
    }

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
        $modelId = $this->getModel($model);
        $response = $this->makeRequest('post', 'chat/completions', [
            'model' => $modelId,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ] + $this->generationParams($modelId, $options));

        if (!$response['success']) {
            throw new \Exception('OpenAI Error: ' . ($response['error'] ?? 'Unknown error'));
        }

        return $response['data']['choices'][0]['message']['content'] ?? '';
    }

    public function generateTextWithUsage(string $prompt, ?string $model = null, array $options = []): array
    {
        $modelId = $this->getModel($model);
        $response = $this->makeRequest('post', 'chat/completions', [
            'model' => $modelId,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ] + $this->generationParams($modelId, $options));

        if (!$response['success']) {
            throw new \Exception('OpenAI Error: ' . ($response['error'] ?? 'Unknown error'));
        }

        $usage = $response['data']['usage'] ?? [];

        return [
            'text' => $response['data']['choices'][0]['message']['content'] ?? '',
            'tokens_input' => (int) ($usage['prompt_tokens'] ?? 0),
            'tokens_output' => (int) ($usage['completion_tokens'] ?? 0),
        ];
    }

    /**
     * Below this a reasoning model's limit leaves no room for the answer after its hidden
     * reasoning, which counts against the same limit.
     */
    private const REASONING_MIN_TOKENS = 4096;

    /**
     * The token limit and sampling parameters the model accepts. Reasoning models (gpt-5, o1, o3,
     * o4…) reject `max_tokens` and any temperature but the default, so they get
     * `max_completion_tokens` with room for the reasoning; a limit is sent only when the caller
     * (or the method) sets one, because a fixed 65,536 is more than most models allow.
     */
    protected function generationParams(string $modelId, array $options, ?int $defaultMaxTokens = null, float $defaultTemperature = 0.7): array
    {
        $maxTokens = $options['max_tokens'] ?? $defaultMaxTokens;

        if ($this->isReasoningModel($modelId)) {
            return $maxTokens === null
                ? []
                : ['max_completion_tokens' => max((int) $maxTokens, self::REASONING_MIN_TOKENS)];
        }

        $params = ['temperature' => $options['temperature'] ?? $defaultTemperature];
        if ($maxTokens !== null) {
            $params['max_tokens'] = (int) $maxTokens;
        }

        return $params;
    }

    /**
     * Whether the model is an OpenAI reasoning model (gpt-5 family and the o-series), apart from
     * the chat variants such as gpt-5-chat-latest, which take the usual parameters.
     */
    public function isReasoningModel(string $modelId): bool
    {
        $id = strtolower($modelId);

        return !str_contains($id, '-chat') && (bool) preg_match('/^(gpt-5|o\d)/', $id);
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
     * Stream text generation via OpenAI-compatible SSE.
     *
     * @return \Generator<string>
     */
    public function generateTextStream(string $prompt, ?string $model = null, array $options = []): \Generator
    {
        $url = rtrim($this->getBaseUrl(), '/') . '/chat/completions';

        $modelId = $this->getModel($model);
        $data = [
            'model' => $modelId,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'stream' => true,
        ] + $this->generationParams($modelId, $options);

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
        ] + $this->generationParams($model, $options, 2000, 0.3));

        if (!$response['success']) {
            throw new \Exception('OpenAI Vision Error: ' . ($response['error'] ?? 'Unknown error'));
        }

        return $response['data']['choices'][0]['message']['content'] ?? '';
    }
}
