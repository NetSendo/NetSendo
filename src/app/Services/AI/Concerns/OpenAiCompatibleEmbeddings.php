<?php

namespace App\Services\AI\Concerns;

use Illuminate\Support\Facades\Http;

/**
 * POST /embeddings in the OpenAI format, which OpenRouter speaks as well.
 */
trait OpenAiCompatibleEmbeddings
{
    public function embed(array $texts, ?string $model = null, array $options = []): array
    {
        if ($texts === []) {
            return [];
        }

        $model = $model ?: $this->embeddingModels()[0];
        $payload = [
            'model' => $model,
            'input' => array_values($texts),
            'encoding_format' => 'float',
        ];

        // Only the text-embedding-3 family can shorten its vectors; other models reject the field
        if (!empty($options['dimensions']) && str_contains($model, 'text-embedding-3')) {
            $payload['dimensions'] = (int) $options['dimensions'];
        }

        $url = rtrim($this->getBaseUrl(), '/') . '/embeddings';
        $response = Http::withHeaders($this->getDefaultHeaders())
            ->timeout($options['timeout'] ?? 60)
            ->post($url, $payload);

        if (!$response->successful()) {
            $error = $response->json('error.message') ?? mb_substr($response->body(), 0, 300);
            throw new \RuntimeException("Embedding request failed ({$response->status()}): {$error}");
        }

        $data = $response->json('data');
        if (!is_array($data) || count($data) !== count($texts)) {
            throw new \RuntimeException('Embedding response has no vectors for every text.');
        }

        usort($data, fn ($a, $b) => ($a['index'] ?? 0) <=> ($b['index'] ?? 0));

        return array_map(fn ($item) => array_map('floatval', $item['embedding'] ?? []), $data);
    }
}
