<?php

namespace App\Services\Brain\Knowledge;

use App\Models\AiIntegration;
use App\Services\AI\EmbeddingProviderInterface;

/**
 * The integration and model that embed one user's knowledge base.
 */
final class EmbeddingTarget
{
    public function __construct(
        public readonly AiIntegration $integration,
        public readonly EmbeddingProviderInterface $provider,
        public readonly string $model,
    ) {}

    /**
     * Stored with every chunk: vectors of different models are never compared, and a new
     * model means the knowledge base is embedded again.
     */
    public function key(): string
    {
        return $this->integration->provider . ':' . $this->model;
    }
}
