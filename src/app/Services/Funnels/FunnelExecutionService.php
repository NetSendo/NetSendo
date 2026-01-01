<?php

namespace App\Services\Funnels;

use App\Models\Funnel;
use App\Models\FunnelStep;
use App\Models\FunnelSubscriber;
use App\Models\FunnelTask;
use App\Models\Subscriber;
use App\Jobs\SendEmailJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class FunnelExecutionService
{
    /**
     * Enroll a subscriber in a funnel.
     */
    public function enrollSubscriber(Funnel $funnel, Subscriber $subscriber): ?FunnelSubscriber
    {
        if (!$funnel->isActive()) {
            return null;
        }

        $enrollment = FunnelSubscriber::enroll($funnel, $subscriber);

        if ($enrollment) {
            Log::info("Subscriber {$subscriber->id} enrolled in funnel {$funnel->id}");

            // Process the first step immediately
            $this->processNextStep($enrollment);
        }

        return $enrollment;
    }

    /**
     * Process the next step for an enrollment.
     */
    public function processNextStep(FunnelSubscriber $enrollment): void
    {
        $step = $enrollment->currentStep;

        if (!$step) {
            $enrollment->markCompleted();
            return;
        }

        // Execute based on step type
        match ($step->type) {
            FunnelStep::TYPE_START => $this->executeStartStep($enrollment, $step),
            FunnelStep::TYPE_EMAIL => $this->executeEmailStep($enrollment, $step),
            FunnelStep::TYPE_DELAY => $this->executeDelayStep($enrollment, $step),
            FunnelStep::TYPE_CONDITION => $this->executeConditionStep($enrollment, $step),
            FunnelStep::TYPE_ACTION => $this->executeActionStep($enrollment, $step),
            FunnelStep::TYPE_END => $this->executeEndStep($enrollment, $step),
            default => $this->moveToNextStep($enrollment, $step->nextStep),
        };
    }

    /**
     * Execute start step - just move to next.
     */
    protected function executeStartStep(FunnelSubscriber $enrollment, FunnelStep $step): void
    {
        $enrollment->addToHistory('started', ['step_id' => $step->id]);
        $this->moveToNextStep($enrollment, $step->nextStep);
    }

    /**
     * Execute email step - queue email and move to next.
     */
    protected function executeEmailStep(FunnelSubscriber $enrollment, FunnelStep $step): void
    {
        $message = $step->message;
        $subscriber = $enrollment->subscriber;

        if (!$message) {
            Log::warning("Funnel step {$step->id} has no message assigned");
            $this->moveToNextStep($enrollment, $step->nextStep);
            return;
        }

        // Queue the email
        SendEmailJob::dispatch($message, $subscriber, [
            'funnel_id' => $enrollment->funnel_id,
            'funnel_step_id' => $step->id,
        ]);

        $enrollment->addToHistory('email_queued', [
            'message_id' => $message->id,
            'subject' => $message->subject,
        ]);

        $enrollment->incrementStepsCompleted();
        $this->moveToNextStep($enrollment, $step->nextStep);
    }

    /**
     * Execute delay step - schedule next action.
     */
    protected function executeDelayStep(FunnelSubscriber $enrollment, FunnelStep $step): void
    {
        $delaySeconds = $step->delay_in_seconds;

        if (!$delaySeconds || $delaySeconds <= 0) {
            // No delay, move immediately
            $this->moveToNextStep($enrollment, $step->nextStep);
            return;
        }

        $enrollment->addToHistory('delay_started', [
            'duration' => $step->delay_display,
            'seconds' => $delaySeconds,
        ]);

        $enrollment->incrementStepsCompleted();

        // Schedule next action
        $enrollment->scheduleNextAction($delaySeconds);

        // Update current step to next
        if ($step->nextStep) {
            $enrollment->current_step_id = $step->next_step_id;
            $enrollment->save();
        }
    }

    /**
     * Execute condition step - evaluate and branch.
     */
    protected function executeConditionStep(FunnelSubscriber $enrollment, FunnelStep $step): void
    {
        // If wait_for_condition is enabled, check if condition is met first
        if ($step->wait_for_condition) {
            $conditionMet = $this->evaluateCondition($enrollment, $step);

            if (!$conditionMet) {
                // Put enrollment in waiting state for retry processing
                $enrollment->addToHistory('condition_started', [
                    'condition_type' => $step->condition_type,
                    'wait_for_condition' => true,
                ]);

                $enrollment->status = FunnelSubscriber::STATUS_WAITING_CONDITION;
                $enrollment->save();
                return; // Will be processed by FunnelRetryService
            }
        }

        $conditionMet = $this->evaluateCondition($enrollment, $step);

        $enrollment->addToHistory('condition_evaluated', [
            'condition_type' => $step->condition_type,
            'result' => $conditionMet,
        ]);

        $enrollment->incrementStepsCompleted();

        $nextStep = $step->getNextStepForCondition($conditionMet);
        $this->moveToNextStep($enrollment, $nextStep);
    }

    /**
     * Evaluate a condition.
     */
    protected function evaluateCondition(FunnelSubscriber $enrollment, FunnelStep $step): bool
    {
        $subscriber = $enrollment->subscriber;
        $config = $step->condition_config ?? [];

        return match ($step->condition_type) {
            FunnelStep::CONDITION_EMAIL_OPENED => $this->checkEmailOpened($subscriber, $config),
            FunnelStep::CONDITION_EMAIL_CLICKED => $this->checkEmailClicked($subscriber, $config),
            FunnelStep::CONDITION_TAG_EXISTS => $this->checkTagExists($subscriber, $config),
            FunnelStep::CONDITION_FIELD_VALUE => $this->checkFieldValue($subscriber, $config),
            FunnelStep::CONDITION_TASK_COMPLETED => $this->checkTaskCompleted($enrollment, $config),
            default => false,
        };
    }

    protected function checkEmailOpened(Subscriber $subscriber, array $config): bool
    {
        $messageId = $config['message_id'] ?? null;
        if (!$messageId) {
            return false;
        }

        // Check if subscriber has opened this message
        // This would need integration with tracking system
        return $subscriber->trackingEvents()
            ->where('message_id', $messageId)
            ->where('event_type', 'open')
            ->exists();
    }

    protected function checkEmailClicked(Subscriber $subscriber, array $config): bool
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

    protected function checkTagExists(Subscriber $subscriber, array $config): bool
    {
        $tag = $config['tag'] ?? null;
        if (!$tag) {
            return false;
        }

        return $subscriber->hasTag($tag);
    }

    protected function checkFieldValue(Subscriber $subscriber, array $config): bool
    {
        $field = $config['field'] ?? null;
        $operator = $config['operator'] ?? 'equals';
        $value = $config['value'] ?? null;

        if (!$field) {
            return false;
        }

        $subscriberValue = $subscriber->getCustomFieldValue($field);

        return match ($operator) {
            'equals' => $subscriberValue == $value,
            'not_equals' => $subscriberValue != $value,
            'contains' => str_contains($subscriberValue ?? '', $value ?? ''),
            'not_empty' => !empty($subscriberValue),
            'empty' => empty($subscriberValue),
            default => false,
        };
    }

    /**
     * Execute action step.
     */
    protected function executeActionStep(FunnelSubscriber $enrollment, FunnelStep $step): void
    {
        $subscriber = $enrollment->subscriber;
        $config = $step->action_config ?? [];

        match ($step->action_type) {
            FunnelStep::ACTION_ADD_TAG => $this->actionAddTag($subscriber, $config),
            FunnelStep::ACTION_REMOVE_TAG => $this->actionRemoveTag($subscriber, $config),
            FunnelStep::ACTION_MOVE_TO_LIST => $this->actionMoveToList($subscriber, $config),
            FunnelStep::ACTION_COPY_TO_LIST => $this->actionCopyToList($subscriber, $config),
            FunnelStep::ACTION_WEBHOOK => $this->actionWebhook($enrollment, $config),
            FunnelStep::ACTION_UNSUBSCRIBE => $this->actionUnsubscribe($subscriber, $config),
            FunnelStep::ACTION_NOTIFY => $this->actionNotify($enrollment, $config),
            default => null,
        };

        $enrollment->addToHistory('action_executed', [
            'action_type' => $step->action_type,
            'config' => $config,
        ]);

        $enrollment->incrementStepsCompleted();
        $this->moveToNextStep($enrollment, $step->nextStep);
    }

    protected function actionAddTag(Subscriber $subscriber, array $config): void
    {
        $tag = $config['tag'] ?? null;
        if ($tag) {
            $subscriber->addTag($tag);
        }
    }

    protected function actionRemoveTag(Subscriber $subscriber, array $config): void
    {
        $tag = $config['tag'] ?? null;
        if ($tag) {
            $subscriber->removeTag($tag);
        }
    }

    protected function actionMoveToList(Subscriber $subscriber, array $config): void
    {
        $fromListId = $config['from_list_id'] ?? null;
        $toListId = $config['to_list_id'] ?? null;

        if ($fromListId && $toListId) {
            $subscriber->moveToList($fromListId, $toListId);
        }
    }

    protected function actionCopyToList(Subscriber $subscriber, array $config): void
    {
        $listId = $config['list_id'] ?? null;
        if ($listId) {
            $subscriber->addToList($listId);
        }
    }

    protected function actionWebhook(FunnelSubscriber $enrollment, array $config): void
    {
        $url = $config['url'] ?? null;
        if (!$url) {
            return;
        }

        $subscriber = $enrollment->subscriber;

        try {
            Http::post($url, [
                'event' => 'funnel_webhook',
                'funnel_id' => $enrollment->funnel_id,
                'subscriber' => [
                    'id' => $subscriber->id,
                    'email' => $subscriber->email,
                    'first_name' => $subscriber->first_name,
                    'last_name' => $subscriber->last_name,
                ],
                'data' => $config['data'] ?? null,
            ]);
        } catch (\Exception $e) {
            Log::error("Funnel webhook failed: " . $e->getMessage());
        }
    }

    protected function actionUnsubscribe(Subscriber $subscriber, array $config): void
    {
        $listId = $config['list_id'] ?? null;
        if ($listId) {
            $subscriber->unsubscribeFromList($listId);
        }
    }

    protected function actionNotify(FunnelSubscriber $enrollment, array $config): void
    {
        // Send notification to funnel owner
        $email = $config['email'] ?? $enrollment->funnel->user->email;
        $message = $config['message'] ?? 'Subscriber completed funnel action';

        // Could use notification system here
        Log::info("Funnel notification: {$message} for {$enrollment->subscriber->email}");
    }

    /**
     * Execute end step - mark as completed.
     */
    protected function executeEndStep(FunnelSubscriber $enrollment, FunnelStep $step): void
    {
        $enrollment->addToHistory('completed', ['step_id' => $step->id]);
        $enrollment->markCompleted();
    }

    /**
     * Move enrollment to next step.
     */
    protected function moveToNextStep(FunnelSubscriber $enrollment, ?FunnelStep $nextStep): void
    {
        if (!$nextStep) {
            $enrollment->markCompleted();
            return;
        }

        $enrollment->moveToStep($nextStep);

        // If not waiting (delay), process immediately
        if (!$enrollment->isWaiting()) {
            $this->processNextStep($enrollment);
        }
    }

    /**
     * Process all ready-to-execute enrollments.
     */
    public function processReadyEnrollments(): int
    {
        $processed = 0;

        $enrollments = FunnelSubscriber::readyToProcess()
            ->with(['funnel', 'currentStep', 'subscriber'])
            ->limit(100)
            ->get();

        foreach ($enrollments as $enrollment) {
            if (!$enrollment->funnel->isActive()) {
                continue;
            }

            $enrollment->status = FunnelSubscriber::STATUS_ACTIVE;
            $enrollment->save();

            $this->processNextStep($enrollment);
            $processed++;
        }

        return $processed;
    }
}
