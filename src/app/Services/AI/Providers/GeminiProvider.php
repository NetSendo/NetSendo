<?php

namespace App\Services\AI\Providers;

use App\Services\AI\BaseProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiProvider extends BaseProvider
{
    protected function getDefaultModel(): string
    {
        return 'gemini-2.5-pro';
    }

    public function supportsFetchModels(): bool
    {
        return true;
    }

    protected function getBaseUrl(): string
    {
        return 'https://generativelanguage.googleapis.com/v1beta';
    }

    public function testConnection(): array
    {
        $url = $this->getBaseUrl() . '/models?key=' . $this->getApiKey();

        try {
            $response = Http::timeout(30)->get($url);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Połączenie z Google Gemini działa poprawnie.',
                ];
            }

            $error = $response->json('error.message') ?? $response->body();
            return [
                'success' => false,
                'message' => 'Błąd połączenia: ' . $error,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Błąd połączenia: ' . $e->getMessage(),
            ];
        }
    }

    public function fetchAvailableModels(): array
    {
        $url = $this->getBaseUrl() . '/models?key=' . $this->getApiKey();

        try {
            $response = Http::timeout(30)->get($url);

            if (!$response->successful()) {
                return [];
            }

            $models = [];
            $data = $response->json('models') ?? [];

            foreach ($data as $model) {
                $name = $model['name'] ?? '';
                // Extract model ID from "models/gemini-1.5-flash" format
                $modelId = str_replace('models/', '', $name);

                // Only include generative models
                if (str_starts_with($modelId, 'gemini')) {
                    $models[] = [
                        'model_id' => $modelId,
                        'display_name' => $model['displayName'] ?? $this->formatModelName($modelId),
                    ];
                }
            }

            return $models;
        } catch (\Exception $e) {
            Log::error('Gemini fetchModels error: ' . $e->getMessage());
            return [];
        }
    }

    public function generateText(string $prompt, ?string $model = null, array $options = []): string
    {
        $modelId = $this->getModel($model);
        $url = $this->getBaseUrl() . "/models/{$modelId}:generateContent?key=" . $this->getApiKey();

        try {
            $response = Http::timeout(60)
                ->post($url, [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'maxOutputTokens' => $options['max_tokens'] ?? 65536,
                        'temperature' => $options['temperature'] ?? 0.7,
                    ],
                ]);

            if (!$response->successful()) {
                $error = $response->json('error.message') ?? $response->body();
                throw new \Exception('Gemini Error: ' . $error);
            }

            $candidates = $response->json('candidates') ?? [];
            if (empty($candidates)) {
                return '';
            }

            $content = $candidates[0]['content']['parts'] ?? [];
            $text = '';
            foreach ($content as $part) {
                $text .= $part['text'] ?? '';
            }

            return $text;
        } catch (\Exception $e) {
            throw new \Exception('Gemini Error: ' . $e->getMessage());
        }
    }

    public function generateTextWithUsage(string $prompt, ?string $model = null, array $options = []): array
    {
        $modelId = $this->getModel($model);
        $url = $this->getBaseUrl() . "/models/{$modelId}:generateContent?key=" . $this->getApiKey();

        try {
            $response = Http::timeout(60)
                ->post($url, [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'maxOutputTokens' => $options['max_tokens'] ?? 65536,
                        'temperature' => $options['temperature'] ?? 0.7,
                    ],
                ]);

            if (!$response->successful()) {
                $error = $response->json('error.message') ?? $response->body();
                throw new \Exception('Gemini Error: ' . $error);
            }

            $data = $response->json();
            $candidates = $data['candidates'] ?? [];
            $text = '';
            if (!empty($candidates)) {
                $content = $candidates[0]['content']['parts'] ?? [];
                foreach ($content as $part) {
                    $text .= $part['text'] ?? '';
                }
            }

            $usage = $data['usageMetadata'] ?? [];

            return [
                'text' => $text,
                'tokens_input' => (int) ($usage['promptTokenCount'] ?? 0),
                'tokens_output' => (int) ($usage['candidatesTokenCount'] ?? 0),
            ];
        } catch (\Exception $e) {
            throw new \Exception('Gemini Error: ' . $e->getMessage());
        }
    }

    private function formatModelName(string $modelId): string
    {
        $names = [
            'gemini-2.5-pro' => 'Gemini 2.5 Pro (Styczeń 2026)',
            'gemini-2.5-flash' => 'Gemini 2.5 Flash',
            'gemini-2.0-pro' => 'Gemini 2.0 Pro',
            'gemini-2.0-flash' => 'Gemini 2.0 Flash',
            'gemini-1.5-pro' => 'Gemini 1.5 Pro',
            'gemini-1.5-flash' => 'Gemini 1.5 Flash',
        ];

        return $names[$modelId] ?? ucfirst(str_replace('-', ' ', $modelId));
    }

    /**
     * Check if this provider supports vision/image analysis.
     */
    public function supportsVision(): bool
    {
        return true; // All Gemini models support vision
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
        $modelId = $this->getModel($options['model'] ?? null);
        $url = $this->getBaseUrl() . "/models/{$modelId}:generateContent?key=" . $this->getApiKey();

        try {
            $response = Http::timeout(60)
                ->post($url, [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt],
                                [
                                    'inline_data' => [
                                        'mime_type' => $mimeType,
                                        'data' => $base64Image,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'maxOutputTokens' => $options['max_tokens'] ?? 2000,
                        'temperature' => $options['temperature'] ?? 0.3,
                    ],
                ]);

            if (!$response->successful()) {
                $error = $response->json('error.message') ?? $response->body();
                throw new \Exception('Gemini Vision Error: ' . $error);
            }

            $candidates = $response->json('candidates') ?? [];
            if (empty($candidates)) {
                return '';
            }

            $content = $candidates[0]['content']['parts'] ?? [];
            $text = '';
            foreach ($content as $part) {
                $text .= $part['text'] ?? '';
            }

            return $text;
        } catch (\Exception $e) {
            throw new \Exception('Gemini Vision Error: ' . $e->getMessage());
        }
    }

    /**
     * Stream text generation via Gemini SSE.
     * Uses streamGenerateContent endpoint with alt=sse.
     *
     * @return \Generator<string>
     */
    public function generateTextStream(string $prompt, ?string $model = null, array $options = []): \Generator
    {
        $modelId = $this->getModel($model);
        $url = $this->getBaseUrl() . "/models/{$modelId}:streamGenerateContent?alt=sse&key=" . $this->getApiKey();

        $data = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'maxOutputTokens' => $options['max_tokens'] ?? 65536,
                'temperature' => $options['temperature'] ?? 0.7,
            ],
        ];

        // Gemini uses API key in URL, no Bearer auth needed
        $headers = [
            'Content-Type' => 'application/json',
        ];

        foreach ($this->makeStreamingRequest($url, $data, $headers) as $line) {
            if (!str_starts_with($line, 'data: ')) {
                continue;
            }

            $payload = substr($line, 6);
            $json = json_decode($payload, true);

            if (!$json) {
                continue;
            }

            $parts = $json['candidates'][0]['content']['parts'] ?? [];
            foreach ($parts as $part) {
                $text = $part['text'] ?? '';
                if ($text !== '') {
                    yield $text;
                }
            }
        }
    }
}
