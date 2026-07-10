<?php

namespace App\Services\AI;

/**
 * Estimates USD cost of AI usage from the config/ai_pricing.php table.
 * Single source of truth for model pricing (Brain 2.0, D5).
 */
class AiCostEstimator
{
    /**
     * Estimate cost in USD for the given model and token counts.
     */
    public static function estimate(?string $model, int $inputTokens, int $outputTokens): float
    {
        $rates = self::ratesFor($model);

        return round(
            ($inputTokens / 1_000_000) * $rates[0] + ($outputTokens / 1_000_000) * $rates[1],
            6
        );
    }

    /**
     * Resolve [input_price, output_price] per 1M tokens for a model name.
     * Substring matching, first match wins (config order matters).
     *
     * @return array{0: float, 1: float}
     */
    public static function ratesFor(?string $model): array
    {
        $pricing = config('ai_pricing.models', []);
        $fallback = config('ai_pricing.fallback', [1.00, 4.00]);

        if (!$model) {
            return $fallback;
        }

        $modelLower = strtolower($model);

        foreach ($pricing as $key => $rates) {
            if (str_contains($modelLower, (string) $key)) {
                return $rates;
            }
        }

        return $fallback;
    }
}
