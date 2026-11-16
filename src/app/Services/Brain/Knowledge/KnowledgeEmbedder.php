<?php

namespace App\Services\Brain\Knowledge;

use App\Models\AiBrainSettings;
use App\Models\AiIntegration;
use App\Services\AI\AiService;
use App\Services\AI\EmbeddingProviderInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Picks the integration that embeds a user's knowledge base and turns text into vectors.
 *
 * Vectors are asked for in DIMENSIONS where the model can shorten them, normalised to unit
 * length (so a dot product is the cosine similarity) and stored as packed float32.
 */
class KnowledgeEmbedder
{
    /** Shorter vectors halve memory and search time with almost the same quality. */
    public const DIMENSIONS = 768;

    /** Providers with an embedding endpoint, in the order one is picked automatically. */
    public const PROVIDERS = ['openai', 'gemini', 'openrouter', 'ollama'];

    private const BATCH = 32;

    /** A search question is embedded once and reused for a while (same model, same text). */
    private const QUERY_CACHE_SECONDS = 600;

    public function __construct(
        protected AiService $aiService,
    ) {}

    /**
     * The integration and model chosen in the Brain settings, or the first usable integration
     * that can embed with its default embedding model; null when there is none.
     */
    public function resolve(int $userId): ?EmbeddingTarget
    {
        $settings = AiBrainSettings::where('user_id', $userId)->first();

        $integration = null;
        if ($settings?->embedding_integration_id) {
            $integration = AiIntegration::usable()
                ->whereIn('provider', self::PROVIDERS)
                ->find($settings->embedding_integration_id);
        }
        $integration ??= $this->candidates()->first();

        if (!$integration) {
            return null;
        }

        $provider = $this->provider($integration);
        if (!$provider) {
            return null;
        }

        // The stored model applies only to the integration it was chosen for
        $model = $settings?->embedding_integration_id === $integration->id && filled($settings?->embedding_model)
            ? $settings->embedding_model
            : $provider->embeddingModels()[0];

        return new EmbeddingTarget($integration, $provider, $model);
    }

    /**
     * Usable integrations that can embed, in the order one is picked automatically.
     *
     * @return \Illuminate\Support\Collection<int, AiIntegration>
     */
    public function candidates()
    {
        return AiIntegration::usable()
            ->whereIn('provider', self::PROVIDERS)
            ->where(fn ($query) => $query->whereNotNull('api_key')->orWhere('provider', 'ollama'))
            ->get()
            ->sortBy(fn (AiIntegration $integration) => array_search($integration->provider, self::PROVIDERS, true))
            ->values();
    }

    public function provider(AiIntegration $integration): ?EmbeddingProviderInterface
    {
        $provider = $this->aiService->getProvider($integration);

        return $provider instanceof EmbeddingProviderInterface ? $provider : null;
    }

    /**
     * Unit-length vectors for texts to store, in batches.
     *
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    public function embedDocuments(EmbeddingTarget $target, array $texts): array
    {
        $vectors = [];

        foreach (array_chunk(array_values($texts), self::BATCH) as $batch) {
            $embedded = $target->provider->embed($batch, $target->model, [
                'purpose' => 'document',
                'dimensions' => self::DIMENSIONS,
            ]);

            foreach ($embedded as $vector) {
                $vectors[] = self::normalize($vector);
            }
        }

        return $vectors;
    }

    /**
     * Unit-length vector of a search question.
     *
     * @return list<float>
     *
     * @throws \RuntimeException when the provider fails
     */
    public function embedQuery(EmbeddingTarget $target, string $query): array
    {
        $cacheKey = 'brain-kb-query:' . sha1($target->integration->id . '|' . $target->key() . '|' . $query);

        return Cache::remember($cacheKey, self::QUERY_CACHE_SECONDS, function () use ($target, $query) {
            // Brain waits for this answer, so the request may not take long
            $vector = $target->provider->embed([$query], $target->model, [
                'purpose' => 'query',
                'dimensions' => self::DIMENSIONS,
                'timeout' => 10,
            ])[0] ?? [];

            if ($vector === []) {
                throw new \RuntimeException('The provider returned an empty vector.');
            }

            return self::normalize($vector);
        });
    }

    /**
     * @param  list<float>  $vector
     * @return list<float>
     */
    public static function normalize(array $vector): array
    {
        $norm = sqrt(array_sum(array_map(fn ($value) => $value * $value, $vector)));
        if ($norm <= 0.0) {
            return $vector;
        }

        return array_map(fn ($value) => $value / $norm, $vector);
    }

    /** @param list<float> $vector */
    public static function pack(array $vector): string
    {
        return pack('g*', ...$vector);
    }

    /** @return list<float> */
    public static function unpack(string $binary): array
    {
        return array_values(unpack('g*', $binary));
    }
}
