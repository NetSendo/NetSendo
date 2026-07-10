<?php

namespace App\Services\Brain;

use App\Models\AiBrainActivityLog;
use App\Models\AiGoal;
use App\Models\EmailClick;
use App\Models\EmailOpen;
use App\Models\MessageQueueEntry;
use App\Models\RevenueEvent;
use App\Models\Subscriber;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Brain 2.0 Phase 4 — outcome-driven goal evaluation.
 *
 * Goals with a target_metric complete when the METRIC reaches the target —
 * not when their sub-plans finish running. Evaluated every Brain cron
 * cycle: the first evaluation freezes the baseline; progress is the
 * metric's movement from baseline toward target (both directions
 * supported, e.g. "reduce unsubscribe rate").
 */
class GoalOutcomeService
{
    public const METRICS = ['open_rate', 'click_rate', 'subscribers', 'revenue_30d'];

    /**
     * Evaluate all active metric-driven goals for a user.
     *
     * @return array{evaluated: int, completed: int}
     */
    public function evaluateForUser(User $user): array
    {
        $goals = AiGoal::forUser($user->id)
            ->active()
            ->whereNotNull('target_metric')
            ->whereNotNull('target_value')
            ->get();

        $evaluated = 0;
        $completed = 0;

        foreach ($goals as $goal) {
            try {
                $current = $this->computeMetric($user, $goal->target_metric);
                if ($current === null) {
                    continue;
                }

                // Freeze the baseline on first evaluation
                if ($goal->baseline_value === null) {
                    $goal->baseline_value = $current;
                }

                $progress = $this->progressPercent(
                    (float) $goal->baseline_value,
                    (float) $goal->target_value,
                    $current
                );

                $reached = $progress >= 100;

                $goal->fill([
                    'progress_percent' => $progress,
                    'context' => array_merge($goal->context ?? [], [
                        'metric_current' => $current,
                        'metric_evaluated_at' => now()->toIso8601String(),
                    ]),
                ]);

                if ($reached) {
                    $goal->status = 'completed';
                    $goal->completed_at = now();
                    $completed++;
                }

                $goal->save();
                $evaluated++;

                AiBrainActivityLog::logEvent($user->id, 'goal_metric_evaluated', $reached ? 'completed' : 'progress', null, [
                    'goal_id' => $goal->id,
                    'metric' => $goal->target_metric,
                    'baseline' => (float) $goal->baseline_value,
                    'current' => $current,
                    'target' => (float) $goal->target_value,
                    'progress' => $progress,
                ]);
            } catch (\Exception $e) {
                Log::warning('GoalOutcomeService: evaluation failed', [
                    'goal_id' => $goal->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return ['evaluated' => $evaluated, 'completed' => $completed];
    }

    /**
     * Current value of a supported metric (30-day windows).
     */
    public function computeMetric(User $user, string $metric): ?float
    {
        $since = now()->subDays(30);

        switch ($metric) {
            case 'subscribers':
                return (float) Subscriber::where('user_id', $user->id)->active()->count();

            case 'revenue_30d':
                return round(((int) RevenueEvent::forUser($user->id)->recent(30)->sum('amount')) / 100, 2);

            case 'open_rate':
            case 'click_rate':
                $delivered = MessageQueueEntry::whereHas('message', fn ($q) => $q->where('user_id', $user->id))
                    ->where('status', MessageQueueEntry::STATUS_SENT)
                    ->where('sent_at', '>=', $since)
                    ->count();

                if ($delivered < 1) {
                    return null; // no sends — metric undefined, keep waiting
                }

                if ($metric === 'open_rate') {
                    $events = EmailOpen::whereHas('message', fn ($q) => $q->where('user_id', $user->id))
                        ->where('created_at', '>=', $since)
                        ->distinct('subscriber_id')->count('subscriber_id');
                } else {
                    $events = EmailClick::whereHas('message', fn ($q) => $q->where('user_id', $user->id))
                        ->where('created_at', '>=', $since)
                        ->distinct('subscriber_id')->count('subscriber_id');
                }

                return round(($events / $delivered) * 100, 2);
        }

        return null;
    }

    /**
     * Progress from baseline toward target, clamped 0-100.
     * Supports both increase goals (target > baseline) and decrease goals.
     */
    public function progressPercent(float $baseline, float $target, float $current): int
    {
        if (abs($target - $baseline) < 0.0001) {
            // Degenerate target == baseline: reached if we're at/past it
            return $current >= $target ? 100 : 0;
        }

        $progress = ($current - $baseline) / ($target - $baseline) * 100;

        return (int) max(0, min(100, round($progress)));
    }
}
