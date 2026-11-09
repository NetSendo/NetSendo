<?php

namespace App\Jobs;

use App\Models\AiBrainActivityLog;
use App\Models\AiGoal;
use App\Models\User;
use App\Services\Brain\AgentOrchestrator;
use App\Services\Brain\ConversationManager;
use App\Services\Brain\GoalPlanner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Starts the next step of a goal after one of its plans finished.
 *
 * Goals from the chat used to stop after their first step unless the hourly Brain cron was
 * enabled. The step's agent mode decides what happens: a plan waiting for approval (shown in
 * the goal's conversation and the Monitor) or an immediate run, which queues the step after it.
 */
class ContinueBrainGoalJob implements ShouldQueue
{
    use Queueable;

    public $tries = 1;

    /** A step may run a whole plan (autonomous mode). */
    public $timeout = 600;

    public function __construct(
        public int $goalId,
        public int $userId,
    ) {}

    public function handle(AgentOrchestrator $orchestrator, GoalPlanner $goalPlanner): void
    {
        $user = User::find($this->userId);
        $goal = AiGoal::forUser($this->userId)->find($this->goalId);

        if (!$user || !$goal || $goal->status !== 'active') {
            return;
        }

        // Null while a plan of the goal is still open, when all steps are done, or when a step
        // failed twice — nothing to start then
        $step = $goalPlanner->getNextAction($goal);
        if (!$step) {
            return;
        }

        try {
            $result = $orchestrator->startGoalStep($goal, $step, $user);
        } catch (\Exception $e) {
            Log::warning('ContinueBrainGoalJob: goal step failed to start', [
                'goal_id' => $goal->id,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        AiBrainActivityLog::logEvent($user->id, 'goal_step_started', !empty($result['failed']) ? 'failed' : 'started', $step['agent'] ?? null, [
            'goal_id' => $goal->id,
            'task_title' => $step['title'] ?? null,
            'plan_id' => $result['plan_id'] ?? null,
        ]);

        // The goal's conversation shows what happened with the step
        $conversation = $goal->conversation;
        if ($conversation && !empty($result['message'])) {
            app(ConversationManager::class)->addAssistantMessage(
                $conversation,
                "🎯 **{$goal->title}** — " . ($step['title'] ?? '') . "\n\n" . $result['message'],
                array_filter([
                    'agent' => $step['agent'] ?? null,
                    'goal_id' => $goal->id,
                    'plan_id' => $result['plan_id'] ?? null,
                ]),
                $result['tokens_input'] ?? 0,
                $result['tokens_output'] ?? 0,
            );
        }
    }
}
