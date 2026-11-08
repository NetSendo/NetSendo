<?php

namespace App\Services\AI;

/**
 * A provider that turns text into embedding vectors (for the semantic knowledge base).
 *
 * OpenAI, Gemini, OpenRouter and Ollama implement it; Anthropic and Grok have no embedding
 * endpoint, so an installation that only uses them cannot index its knowledge base.
 */
interface EmbeddingProviderInterface
{
    /**
     * Embedding models suggested in the settings; the first one is the default.
     *
     * @return list<string>
     */
    public function embeddingModels(): array;

    /**
     * Embed texts in one request, in the order given.
     *
     * Options: `purpose` ('document' for stored text, 'query' for a search question; some
     * providers embed them differently), `dimensions` (asked of models that can shorten their
     * vectors) and `timeout` in seconds.
     *
     * @param  list<string>  $texts
     * @return list<list<float>>
     *
     * @throws \RuntimeException when the provider refuses or returns no vectors
     */
    public function embed(array $texts, ?string $model = null, array $options = []): array;
}
