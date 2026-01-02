<?php

namespace App\Services\AI;

use App\Models\AiIntegration;
use App\Services\AI\Providers\AnthropicProvider;
use App\Services\AI\Providers\GeminiProvider;
use App\Services\AI\Providers\GrokProvider;
use App\Services\AI\Providers\OllamaProvider;
use App\Services\AI\Providers\OpenAiProvider;
use App\Services\AI\Providers\OpenrouterProvider;

class AiService
{
    /**
     * Get a provider instance for the given integration.
     */
    public function getProvider(AiIntegration $integration): AiProviderInterface
    {
        $provider = match ($integration->provider) {
            'openai' => new OpenAiProvider(),
            'anthropic' => new AnthropicProvider(),
            'grok' => new GrokProvider(),
            'openrouter' => new OpenrouterProvider(),
            'ollama' => new OllamaProvider(),
            'gemini' => new GeminiProvider(),
            default => throw new \InvalidArgumentException("Unknown provider: {$integration->provider}"),
        };

        return $provider->setIntegration($integration);
    }

    /**
     * Test the connection for an integration.
     *
     * @return array{success: bool, message: string}
     */
    public function testConnection(AiIntegration $integration): array
    {
        try {
            $provider = $this->getProvider($integration);
            $result = $provider->testConnection();

            // Update integration with test result
            $integration->update([
                'last_tested_at' => now(),
                'last_test_status' => $result['success'] ? 'success' : 'error',
                'last_test_message' => $result['message'],
            ]);

            return $result;
        } catch (\Exception $e) {
            $integration->update([
                'last_tested_at' => now(),
                'last_test_status' => 'error',
                'last_test_message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Błąd: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Fetch available models for an integration.
     */
    public function fetchModels(AiIntegration $integration): array
    {
        try {
            $provider = $this->getProvider($integration);

            if (!$provider->supportsFetchModels()) {
                return AiIntegration::getDefaultModels($integration->provider);
            }

            $models = $provider->fetchAvailableModels();

            if (empty($models)) {
                return AiIntegration::getDefaultModels($integration->provider);
            }

            return $models;
        } catch (\Exception $e) {
            return AiIntegration::getDefaultModels($integration->provider);
        }
    }

    /**
     * Generate a title for the given content.
     */
    public function generateTitle(string $content, AiIntegration $integration): string
    {
        $provider = $this->getProvider($integration);

        $prompt = "Wygeneruj krótki, chwytliwy tytuł dla poniższej treści. Odpowiedz TYLKO tytułem, bez żadnych dodatkowych wyjaśnień:\n\n{$content}";

        return trim($provider->generateText($prompt, null, [
            'max_tokens' => 100,
            'temperature' => 0.7,
        ]));
    }

    /**
     * Generate content based on a prompt.
     */
    public function generateContent(string $prompt, AiIntegration $integration, array $options = []): string
    {
        $provider = $this->getProvider($integration);

        return $provider->generateText($prompt, $options['model'] ?? null, $options);
    }

    /**
     * Get current date context for AI prompts.
     * This ensures AI models know the current date when generating content.
     */
    public static function getDateContext(): string
    {
        $now = now();
        return sprintf(
            "CURRENT DATE: %s (%s, %s) | AKTUALNA DATA: %s. Use this date when creating time-sensitive content.",
            $now->format('Y-m-d'),
            $now->format('l'),
            $now->format('F j, Y'),
            $now->translatedFormat('l, j F Y')
        );
    }

    /**
     * Prepend date context to a prompt.
     */
    public static function prependDateContext(string $prompt): string
    {
        return self::getDateContext() . "\n\n" . $prompt;
    }

    /**
     * Get the first active integration (for quick access).
     */
    public function getDefaultIntegration(): ?AiIntegration
    {
        return AiIntegration::active()
            ->whereNotNull('api_key')
            ->orWhere('provider', 'ollama')
            ->first();
    }
}
