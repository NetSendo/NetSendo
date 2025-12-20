<?php

namespace App\Services\AI;

use App\Models\AiIntegration;

interface AiProviderInterface
{
    /**
     * Set the integration configuration.
     */
    public function setIntegration(AiIntegration $integration): self;

    /**
     * Test the connection to the AI provider.
     *
     * @return array{success: bool, message: string}
     */
    public function testConnection(): array;

    /**
     * Fetch available models from the provider API (if supported).
     *
     * @return array<array{model_id: string, display_name: string}>
     */
    public function fetchAvailableModels(): array;

    /**
     * Generate text based on a prompt.
     *
     * @param string $prompt The input prompt
     * @param string|null $model The model to use (or default)
     * @param array $options Additional options
     * @return string The generated text
     */
    public function generateText(string $prompt, ?string $model = null, array $options = []): string;

    /**
     * Check if the provider supports fetching models from API.
     */
    public function supportsFetchModels(): bool;
}
