<?php

namespace App\Services\Brain;

use App\Models\AiBrainSettings;
use App\Models\AiExecutionLog;
use App\Models\AiIntegration;
use App\Models\User;
use App\Services\AI\AiService;
use Illuminate\Support\Facades\Log;

/**
 * Tracked AI call helper for Brain services (Brain 2.0, D5).
 *
 * Wraps AiService::generateContentWithUsage so that EVERY Brain LLM call:
 * - charges the user's daily token budget (AiBrainSettings::addTokensUsed)
 * - is logged to ai_execution_logs with real token counts and model,
 *   which feeds the Monitor's token/cost aggregation
 */
class BrainAi
{
    /**
     * Generate text with full usage tracking.
     *
     * @param string $action Log label, e.g. 'situation_analysis', 'goal_decomposition'
     */
    public static function generate(
        User $user,
        string $action,
        string $prompt,
        AiIntegration $integration,
        array $options = [],
        string $agentType = 'brain',
    ): string {
        $aiService = app(AiService::class);
        $startTime = microtime(true);

        $result = $aiService->generateContentWithUsage($prompt, $integration, $options);

        $tokensIn = $result['tokens_input'];
        $tokensOut = $result['tokens_output'];

        if (($tokensIn + $tokensOut) > 0) {
            try {
                AiBrainSettings::getForUser($user->id)->addTokensUsed($tokensIn + $tokensOut);
            } catch (\Exception $e) {
                Log::warning('BrainAi: token budget update failed', ['error' => $e->getMessage()]);
            }

            try {
                AiExecutionLog::logSuccess(
                    $user->id,
                    $agentType,
                    $action,
                    [],
                    [],
                    $tokensIn,
                    $tokensOut,
                    $result['model'] ?? null,
                    (int) ((microtime(true) - $startTime) * 1000)
                );
            } catch (\Exception $e) {
                // Logging must never break the caller
            }
        }

        return $result['text'];
    }
}
