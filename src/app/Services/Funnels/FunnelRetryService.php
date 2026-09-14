<?php

namespace App\Services\Funnels;

use App\Models\Funnel;
use App\Models\FunnelSubscriber;
use App\Models\FunnelStep;
use App\Models\FunnelStepRetry;
use App\Jobs\SendEmailJob;
use Illuminate\Support\Facades\Log;

class FunnelRetryService
{
    public function __construct(protected FunnelExecutionService $executionService)
    {
    }

    /**
     * Check if a retry should be sent for an enrollment waiting on a condition.
     */
    public function shouldSendRetry(FunnelSubscriber $enrollment, FunnelStep $step): bool
    {
        // Step must have retry enabled
        if (!$step->hasRetryEnabled()) {
            return false;
        }

        // Check if condition is already met
        if ($this->isConditionMet($enrollment, $step)) {
            return false;
        }

        // Get retry count
        $retryCount = FunnelStepRetry::getAttemptCount($enrollment->id, $step->id);

        // Check if max attempts reached
        if ($retryCount >= $step->retry_max_attempts) {
            return false;
        }

        // Check if enough time has passed since last retry
        $lastRetry = FunnelStepRetry::getLatestAttempt($enrollment->id, $step->id);

        if ($lastRetry) {
            $intervalSeconds = $step->retry_interval_in_seconds;
            $nextRetryAt = $lastRetry->sent_at->addSeconds($intervalSeconds);

            if (now()->lt($nextRetryAt)) {
                return false;
            }
        } else {
            // First retry - check if enough time passed since entering this step
            $stepEntryTime = $this->getStepEntryTime($enrollment, $step);
            if ($stepEntryTime) {
                $intervalSeconds = $step->retry_interval_in_seconds;
                $nextRetryAt = $stepEntryTime->addSeconds($intervalSeconds);

                if (now()->lt($nextRetryAt)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Send a retry reminder for an enrollment.
     */
    public function sendRetry(FunnelSubscriber $enrollment, FunnelStep $step): bool
    {
        // Without a reminder of its own, the email the condition is about. A
        // condition step has no email itself, so the reminder used to be missing:
        // nothing was counted and the exhausted action never applied
        $message = $step->retryMessage
            ?? $step->message
            ?? $this->executionService->conditionMessage($enrollment, $step);

        $subscriber = $enrollment->subscriber;

        if (!$message) {
            $retry = FunnelStepRetry::createAttempt($enrollment->id, $step->id);

            $enrollment->addToHistory('retry_skipped', [
                'attempt_number' => $retry->attempt_number,
                'reason' => 'No reminder email: none is chosen and the condition is about no email',
            ]);

            Log::warning("Funnel step {$step->id} has no reminder email, retry #{$retry->attempt_number} counted as skipped");

            return false;
        }

        // Not sent, but counted: otherwise the reminder would be retried on
        // every run, and the step's exhausted action would never apply
        if (!$subscriber->isDeliverable()) {
            $reason = "Subscriber is {$subscriber->display_status}";
            $retry = FunnelStepRetry::createAttempt($enrollment->id, $step->id);

            $enrollment->addToHistory('retry_skipped', [
                'attempt_number' => $retry->attempt_number,
                'message_id' => $message->id,
                'reason' => $reason,
            ]);

            Log::info("Skipped retry #{$retry->attempt_number} for subscriber {$subscriber->id} on step {$step->id}: {$reason}");

            return false;
        }

        // Queue the reminder email
        SendEmailJob::dispatch($message, $subscriber);

        // Record the retry attempt
        $retry = FunnelStepRetry::createAttempt($enrollment->id, $step->id);

        $enrollment->addToHistory('retry_sent', [
            'attempt_number' => $retry->attempt_number,
            'message_id' => $message->id,
        ]);

        Log::info("Sent retry #{$retry->attempt_number} for subscriber {$subscriber->id} on step {$step->id}");

        return true;
    }

    /**
     * Handle when retry attempts are exhausted.
     */
    public function handleRetryExhausted(FunnelSubscriber $enrollment, FunnelStep $step): void
    {
        $action = $step->retry_exhausted_action ?? FunnelStep::RETRY_ACTION_CONTINUE;

        $enrollment->addToHistory('retry_exhausted', [
            'action' => $action,
            'max_attempts' => $step->retry_max_attempts,
        ]);

        match ($action) {
            FunnelStep::RETRY_ACTION_EXIT => $this->exitEnrollment($enrollment),
            FunnelStep::RETRY_ACTION_UNSUBSCRIBE => $this->unsubscribeAndExit($enrollment),
            default => $this->continueToNextStep($enrollment, $step),
        };
    }

    /**
     * Check if the condition for a step is met.
     */
    public function isConditionMet(FunnelSubscriber $enrollment, FunnelStep $step): bool
    {
        if (!$step->isCondition()) {
            return true;
        }

        // The same evaluation as when the step was reached: a separate copy here
        // knew fewer condition types, so a waiting `field_value` was never met
        return $this->executionService->evaluateCondition($enrollment, $step);
    }

    /**
     * Mark condition as met for an enrollment/step.
     */
    public function markConditionMet(FunnelSubscriber $enrollment, FunnelStep $step): void
    {
        // Update pending retries
        FunnelStepRetry::forSubscriber($enrollment->id)
            ->forStep($step->id)
            ->pending()
            ->each(function ($retry) {
                $retry->markConditionMet();
            });

        $enrollment->addToHistory('condition_met', [
            'step_id' => $step->id,
            'condition_type' => $step->condition_type,
        ]);
    }

    /**
     * Check if max retries have been exhausted.
     */
    public function isRetryExhausted(FunnelSubscriber $enrollment, FunnelStep $step): bool
    {
        if (!$step->hasRetryEnabled()) {
            return false;
        }

        $retryCount = FunnelStepRetry::getAttemptCount($enrollment->id, $step->id);

        if ($retryCount < $step->retry_max_attempts) {
            return false;
        }

        // The last reminder gets its interval too: without this the exhausted
        // action (exit, unsubscribe) followed it on the very next run
        $lastRetry = FunnelStepRetry::getLatestAttempt($enrollment->id, $step->id);

        return !$lastRetry
            || now()->gte($lastRetry->sent_at->addSeconds($step->retry_interval_in_seconds));
    }

    /**
     * Process all enrollments waiting for conditions.
     */
    public function processWaitingEnrollments(): int
    {
        $processed = 0;

        // Walked by id rather than taking the first 100: an enrollment still
        // waiting stays in the set, so the same 100 were checked on every run
        // and the rest never were
        FunnelSubscriber::where('status', FunnelSubscriber::STATUS_WAITING_CONDITION)
            ->whereHas('funnel', fn ($query) => $query->where('status', Funnel::STATUS_ACTIVE))
            ->with(['funnel', 'currentStep', 'subscriber'])
            ->chunkById(100, function ($enrollments) use (&$processed) {
                foreach ($enrollments as $enrollment) {
                    try {
                        if ($this->processWaitingEnrollment($enrollment)) {
                            $processed++;
                        }
                    } catch (\Throwable $e) {
                        Log::error("Funnel enrollment {$enrollment->id} failed while waiting for a condition: {$e->getMessage()}", [
                            'exception' => $e,
                        ]);
                    }
                }
            });

        return $processed;
    }

    protected function processWaitingEnrollment(FunnelSubscriber $enrollment): bool
    {
        $step = $enrollment->currentStep;

        if (!$step) {
            return false;
        }

        // Check if condition is now met
        if ($this->isConditionMet($enrollment, $step)) {
            $this->markConditionMet($enrollment, $step);
            $this->proceedFromConditionStep($enrollment, $step, true);
            return true;
        }

        // Check if we should send a retry
        if ($this->shouldSendRetry($enrollment, $step)) {
            $this->sendRetry($enrollment, $step);
            return true;
        }

        // Check if retries are exhausted
        if ($this->isRetryExhausted($enrollment, $step)) {
            $this->handleRetryExhausted($enrollment, $step);
            return true;
        }

        return false;
    }

    // =====================================
    // Private helpers
    // =====================================

    protected function getStepEntryTime(FunnelSubscriber $enrollment, FunnelStep $step): ?\Carbon\Carbon
    {
        $history = $enrollment->getHistory();

        // Find when we moved to this step
        foreach (array_reverse($history) as $entry) {
            if (($entry['step_id'] ?? null) === $step->id &&
                in_array($entry['action'] ?? '', ['moved_to_step', 'email_queued', 'condition_started'])) {
                return \Carbon\Carbon::parse($entry['at']);
            }
        }

        return null;
    }

    protected function exitEnrollment(FunnelSubscriber $enrollment): void
    {
        $enrollment->markExited('retry_exhausted');
    }

    protected function unsubscribeAndExit(FunnelSubscriber $enrollment): void
    {
        // From the list a "list signup" funnel is triggered by; a trigger list
        // kept after switching the trigger type is not one
        $listId = $this->executionService->signupListId($enrollment->funnel);

        if ($listId) {
            $enrollment->subscriber->unsubscribeFromList($listId, 'funnel_retry_exhausted');
        }

        $enrollment->markExited('unsubscribed_after_retry_exhausted');
    }

    protected function continueToNextStep(FunnelSubscriber $enrollment, FunnelStep $step): void
    {
        // Move to the "NO" branch if this is a condition step
        $nextStep = $step->nextStepNo ?? $step->nextStep;

        $this->runStep($enrollment, $nextStep);
    }

    protected function proceedFromConditionStep(FunnelSubscriber $enrollment, FunnelStep $step, bool $conditionMet): void
    {
        $nextStep = $conditionMet ? $step->nextStepYes : $step->nextStepNo;

        $this->runStep($enrollment, $nextStep);
    }

    protected function runStep(FunnelSubscriber $enrollment, ?FunnelStep $nextStep): void
    {
        if (!$nextStep) {
            $enrollment->markCompleted();
            return;
        }

        $enrollment->moveToStep($nextStep);

        // Run it now, like a step reached during enrollment: nothing picks up
        // an active enrollment later, so it stayed on this step for good
        $this->executionService->processNextStep($enrollment);
    }
}
