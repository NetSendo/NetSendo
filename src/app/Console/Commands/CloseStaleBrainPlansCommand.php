<?php

namespace App\Console\Commands;

use App\Models\AiActionPlan;
use Illuminate\Console\Command;

/**
 * Closes Brain plans that will never move again, so the Monitor and the "Brain is working…" bar
 * show real work only. Nothing is deleted: the plans keep their steps and get a status and a
 * reason.
 *
 * - "executing" with no progress for AiActionPlan::STALLED_AFTER_HOURS → failed (interrupted run)
 * - "approved" for over a day without a run → failed (never queued)
 * - "draft", or "pending_approval" with no open approval, for over 7 days → cancelled
 */
class CloseStaleBrainPlansCommand extends Command
{
    protected $signature = 'brain:close-stale-plans';

    protected $description = 'Close Brain plans that stalled or were left waiting';

    public function handle(): int
    {
        $stalled = 0;
        AiActionPlan::stalled()->each(function (AiActionPlan $plan) use (&$stalled) {
            $plan->markFailed(array_merge($plan->execution_summary ?? [], [
                'error' => 'stalled',
                'message' => 'No progress for ' . AiActionPlan::STALLED_AFTER_HOURS . ' hours: the run was interrupted.',
            ]));
            $stalled++;
        });

        $neverRun = 0;
        AiActionPlan::withStatus('approved')
            ->where('updated_at', '<', now()->subDay())
            ->each(function (AiActionPlan $plan) use (&$neverRun) {
                $plan->markFailed(array_merge($plan->execution_summary ?? [], [
                    'error' => 'not_run',
                    'message' => 'Approved but never run.',
                ]));
                $neverRun++;
            });

        $cancelled = AiActionPlan::whereIn('status', ['draft', 'pending_approval'])
            ->where('updated_at', '<', now()->subDays(7))
            ->whereDoesntHave('pendingApproval', fn ($q) => $q->where('expires_at', '>', now()))
            ->update(['status' => 'cancelled', 'completed_at' => now()]);

        $this->info("Stalled runs failed: {$stalled}, approved but never run: {$neverRun}, left waiting and cancelled: {$cancelled}.");

        return self::SUCCESS;
    }
}
