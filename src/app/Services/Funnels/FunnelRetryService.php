<?php

namespace App\Services\Funnels;

use App\Models\FunnelSubscriber;
use App\Models\FunnelStep;
use App\Models\FunnelStepRetry;
use App\Models\FunnelTask;
use App\Jobs\SendEmailJob;
use Illuminate\Support\Facades\Log;

class FunnelRetryService
{
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
        $message = $step->retryMessage ?? $step->message;

        if (!$message) {
            Log::warning("Funnel step {$step->id} has no retry message configured");
            return false;
        }

        $subscriber = $enrollment->subscriber;

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

        $subscriber = $enrollment->subscriber;
        $config = $step->condition_config ?? [];

        return match ($step->condition_type) {
            FunnelStep::CONDITION_EMAIL_OPENED => $this->checkEmailOpened($subscriber, $config),
            FunnelStep::CONDITION_EMAIL_CLICKED => $this->checkEmailClicked($subscriber, $config),
            FunnelStep::CONDITION_TASK_COMPLETED => $this->checkTaskCompleted($enrollment, $config),
            FunnelStep::CONDITION_TAG_EXISTS => $subscriber->hasTag($config['tag'] ?? ''),
            default => false,
        };
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
        return $retryCount >= $step->retry_max_attempts;
    }

    /**
     * Process all enrollments waiting for conditions.
     */
    public function processWaitingEnrollments(): int
    {
        $processed = 0;

        $enrollments = FunnelSubscriber::where('status', FunnelSubscriber::STATUS_WAITING_CONDITION)
            ->with(['funnel', 'currentStep', 'subscriber'])
            ->limit(100)
            ->get();

        foreach ($enrollments as $enrollment) {
            $step = $enrollment->currentStep;

            if (!$step || !$enrollment->funnel->isActive()) {
                continue;
            }

            // Check if condition is now met
            if ($this->isConditionMet($enrollment, $step)) {
                $this->markConditionMet($enrollment, $step);
                $this->proceedFromConditionStep($enrollment, $step, true);
                $processed++;
                continue;
            }

            // Check if we should send a retry
            if ($this->shouldSendRetry($enrollment, $step)) {
                $this->sendRetry($enrollment, $step);
                $processed++;
                continue;
            }

            // Check if retries are exhausted
            if ($this->isRetryExhausted($enrollment, $step)) {
                $this->handleRetryExhausted($enrollment, $step);
                $processed++;
            }
        }

        return $processed;
    }

    // =====================================
    // Private helpers
    // =====================================

    protected function checkEmailOpened($subscriber, array $config): bool
    {
        $messageId = $config['message_id'] ?? null;
        if (!$messageId) {
            return false;
        }

        return $subscriber->trackingEvents()
            ->where('message_id', $messageId)
            ->where('event_type', 'open')
            ->exists();
    }

    protected function checkEmailClicked($subscriber, array $config): bool
    {
        $messageId = $config['message_id'] ?? null;
        if (!$messageId) {
            return false;
        }

        return $subscriber->trackingEvents()
            ->where('message_id', $messageId)
            ->where('event_type', 'click')
            ->exists();
    }

    protected function checkTaskCompleted(FunnelSubscriber $enrollment, array $config): bool
    {
        $taskId = $config['task_id'] ?? null;
        if (!$taskId) {
            return false;
        }

        return FunnelTask::hasCompleted(
            $enrollment->funnel_id,
            $enrollment->subscriber_id,
            $taskId
        );
    }

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
        $subscriber = $enrollment->subscriber;

        // Unsubscribe from the funnel's trigger list if applicable
        if ($enrollment->funnel->trigger_list_id) {
            $subscriber->unsubscribeFromList($enrollment->funnel->trigger_list_id);
        }

        $enrollment->markExited('unsubscribed_after_retry_exhausted');
    }

    protected function continueToNextStep(FunnelSubscriber $enrollment, FunnelStep $step): void
    {
        // Move to the "NO" branch if this is a condition step
        $nextStep = $step->nextStepNo ?? $step->nextStep;

        if ($nextStep) {
            $enrollment->moveToStep($nextStep);
            $enrollment->status = FunnelSubscriber::STATUS_ACTIVE;
            $enrollment->save();
        } else {
            $enrollment->markCompleted();
        }
    }

    protected function proceedFromConditionStep(FunnelSubscriber $enrollment, FunnelStep $step, bool $conditionMet): void
    {
        $nextStep = $conditionMet ? $step->nextStepYes : $step->nextStepNo;

        if ($nextStep) {
            $enrollment->moveToStep($nextStep);
            $enrollment->status = FunnelSubscriber::STATUS_ACTIVE;
            $enrollment->save();
        } else {
            $enrollment->markCompleted();
        }
    }
}
