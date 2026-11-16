<?php

namespace App\Jobs;

use App\Models\AiActionPlan;
use App\Models\AiBrainActivityLog;
use App\Models\AiBrainSettings;
use App\Models\User;
use App\Services\Brain\AgentOrchestrator;
use App\Services\Brain\Telegram\TelegramBotService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Brain 2.0 (Phase 1) — asynchronous plan execution.
 *
 * Approved plans run on the queue instead of inside the HTTP request or the
 * Telegram webhook. Plan steps carry their own retry logic (BaseAgent), so
 * the job itself does not retry — a re-run would re-execute completed steps.
 */
class ExecuteBrainPlanJob implements ShouldQueue
{
    use Queueable;

    public $tries = 1;

    /** LLM step chains can be slow — allow up to 10 minutes. */
    public $timeout = 600;

    public function __construct(
        public int $planId,
        public int $userId,
        public ?string $notifyChannel = null, // 'telegram' => send result via bot
    ) {}

    public function handle(AgentOrchestrator $orchestrator): void
    {
        $plan = AiActionPlan::find($this->planId);
        $user = User::find($this->userId);

        if (!$plan || !$user || $plan->user_id !== $user->id) {
            Log::warning('ExecuteBrainPlanJob: plan or user not found', [
                'plan_id' => $this->planId,
                'user_id' => $this->userId,
            ]);
            return;
        }

        // Only execute plans that are actually cleared to run
        if (!in_array($plan->status, ['approved', 'draft'], true)) {
            Log::info('ExecuteBrainPlanJob: plan not in executable state, skipping', [
                'plan_id' => $plan->id,
                'status' => $plan->status,
            ]);
            return;
        }

        AiBrainActivityLog::logEvent($user->id, 'plan_execution_started', 'started', $plan->agent_type, [
            'plan_id' => $plan->id,
            'plan_title' => $plan->title,
            'source' => 'queue',
        ]);

        $startTime = microtime(true);

        try {
            $result = $orchestrator->executePlan($plan, $user);
        } catch (\Exception $e) {
            Log::error('ExecuteBrainPlanJob: execution failed', [
                'plan_id' => $plan->id,
                'error' => $e->getMessage(),
            ]);

            $result = [
                'type' => 'error',
                'message' => __('brain.plan_execution_error', ['error' => $e->getMessage()]),
            ];
        }

        $durationMs = (int) ((microtime(true) - $startTime) * 1000);

        $failed = ($result['type'] ?? '') === 'error' || !empty($result['failed']);

        AiBrainActivityLog::logEvent(
            $user->id,
            'plan_execution_finished',
            $failed ? 'error' : 'completed',
            $plan->agent_type,
            [
                'plan_id' => $plan->id,
                'plan_title' => $plan->title,
                'result_type' => $result['type'] ?? null,
                'duration_ms' => $durationMs,
            ],
            $durationMs
        );

        // The chat that asked for the plan gets its result: approving in the Monitor or on
        // Telegram used to leave the conversation without an answer
        $this->postToConversation($plan, $result);

        if ($this->notifyChannel === 'telegram') {
            $this->notifyTelegram($user, $result);
        }
    }

    protected function postToConversation(AiActionPlan $plan, array $result): void
    {
        try {
            $conversation = $plan->ai_conversation_id
                ? \App\Models\AiConversation::where('user_id', $plan->user_id)->find($plan->ai_conversation_id)
                : null;

            if (!$conversation) {
                return;
            }

            app(\App\Services\Brain\ConversationManager::class)->addAssistantMessage(
                $conversation,
                $result['message'] ?? __('brain.plan_executed'),
                ['plan_id' => $plan->id, 'agent' => $plan->agent_type, 'type' => 'plan_result'],
                $result['tokens_input'] ?? 0,
                $result['tokens_output'] ?? 0,
            );
        } catch (\Exception $e) {
            Log::warning('ExecuteBrainPlanJob: posting the result to the conversation failed', [
                'plan_id' => $plan->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function notifyTelegram(User $user, array $result): void
    {
        try {
            $settings = AiBrainSettings::getForUser($user->id);

            if (!$settings->isTelegramConnected()) {
                return;
            }

            /** @var TelegramBotService $bot */
            $bot = app(TelegramBotService::class);
            $bot->sendMessage($settings->telegram_chat_id, $result['message'] ?? __('brain.plan_executed'));
        } catch (\Exception $e) {
            Log::warning('ExecuteBrainPlanJob: Telegram notification failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
