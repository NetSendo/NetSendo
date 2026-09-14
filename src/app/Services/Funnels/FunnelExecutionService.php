<?php

namespace App\Services\Funnels;

use App\Models\ContactList;
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
    protected ABTestService $abTestService;
    protected WebhookService $webhookService;

    public function __construct(ABTestService $abTestService, WebhookService $webhookService)
    {
        $this->abTestService = $abTestService;
        $this->webhookService = $webhookService;
    }

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
            FunnelStep::TYPE_SPLIT => $this->executeSplitStep($enrollment, $step),
            FunnelStep::TYPE_GOAL => $this->executeGoalStep($enrollment, $step),
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

        // Skipped once due and the funnel moves on, as the CRON queue does for
        // autoresponders: a subscriber reactivated before a later email step
        // still gets that one
        if (!$subscriber->isDeliverable()) {
            $reason = "Subscriber is {$subscriber->display_status}";

            $enrollment->addToHistory('email_skipped', [
                'message_id' => $message->id,
                'reason' => $reason,
            ]);

            Log::info("Funnel email step {$step->id} skipped for subscriber {$subscriber->id}: {$reason}");

            $this->moveToNextStep($enrollment, $step->nextStep);
            return;
        }

        // Queue the email
        SendEmailJob::dispatch($message, $subscriber);

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

        // Resuming runs the current step, so it already points past the delay.
        // A delay that ends the funnel leaves none: kept on the delay itself,
        // every resume started the same delay again and it never completed
        $enrollment->current_step_id = $step->next_step_id;
        $enrollment->save();
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
            FunnelStep::ACTION_MOVE_TO_LIST => $this->actionMoveToList($enrollment, $config),
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

    /**
     * Move the subscriber from one list to another.
     *
     * The builder stores the target as `list_id`, the key "copy to list" uses
     * too, and the source as `from_list_id`; without one the source is the
     * list a "list signup" funnel is triggered by. `to_list_id` is still read
     * for steps saved with the older from/to keys. Only an active membership
     * of the source list is moved (see Subscriber::moveToList()); a step that
     * moves nobody records why in the enrollment history.
     */
    protected function actionMoveToList(FunnelSubscriber $enrollment, array $config): void
    {
        $subscriber = $enrollment->subscriber;
        $toListId = (int) (($config['list_id'] ?? null) ?: ($config['to_list_id'] ?? null));
        $fromListId = (int) (($config['from_list_id'] ?? null) ?: $this->signupListId($enrollment->funnel));

        $reason = null;

        if (!$toListId || !ContactList::whereKey($toListId)->exists()) {
            $reason = 'Target list not found';
        } elseif (!$fromListId) {
            $reason = 'No source list: the step names none and the funnel is not triggered by a list signup';
        } elseif ($fromListId === $toListId) {
            // Re-adding would restart the list's sequences for someone already on it
            $reason = 'Source and target list are the same';
        } elseif (!$subscriber->moveToList($fromListId, $toListId, 'funnel_move')) {
            $reason = 'Subscriber is not active on the source list';
        }

        $details = [
            'from_list_id' => $fromListId ?: null,
            'to_list_id' => $toListId ?: null,
        ];

        if ($reason === null) {
            $enrollment->addToHistory('list_moved', $details);
            return;
        }

        $enrollment->addToHistory('list_move_skipped', $details + ['reason' => $reason]);

        Log::info("Funnel move to list skipped for subscriber {$subscriber->id} in funnel {$enrollment->funnel_id}: {$reason}");
    }

    /**
     * The list a "list signup" funnel is triggered by. Other trigger types have
     * none: switching the trigger type keeps the old trigger_list_id, which the
     * builder no longer shows, so it must not decide where anyone is moved from.
     */
    protected function signupListId(Funnel $funnel): ?int
    {
        return $funnel->trigger_type === Funnel::TRIGGER_LIST_SIGNUP ? $funnel->trigger_list_id : null;
    }

    protected function actionCopyToList(Subscriber $subscriber, array $config): void
    {
        $listId = $config['list_id'] ?? null;
        if ($listId) {
            $subscriber->addToList($listId, 'funnel_copy');
        }
    }

    protected function actionWebhook(FunnelSubscriber $enrollment, array $config): void
    {
        $url = $config['url'] ?? null;
        if (!$url) {
            return;
        }

        // Build payload with variable substitution
        $templateData = $config['data'] ?? [];
        $eventName = $config['event_name'] ?? 'funnel_webhook';
        $payload = $this->webhookService->buildPayload($enrollment, $templateData, $eventName);

        // Parse custom headers
        $headers = $this->webhookService->parseHeaders($config);

        // Get HTTP method (default POST)
        $method = strtoupper($config['method'] ?? 'POST');

        // Send with retry logic
        $result = $this->webhookService->send($url, $payload, $headers, $method);

        // Log the result
        if ($result['success']) {
            $enrollment->addToHistory('webhook_sent', [
                'url' => $url,
                'method' => $method,
                'status_code' => $result['status_code'],
                'attempts' => $result['attempts'],
            ]);

            // Store response for condition checking if configured
            if (!empty($config['store_response'])) {
                $enrollment->setData('last_webhook_response', $result['body']);
            }
        } else {
            $enrollment->addToHistory('webhook_failed', [
                'url' => $url,
                'method' => $method,
                'error' => $result['error'] ?? 'Unknown error',
                'attempts' => $result['attempts'],
            ]);

            Log::error("Funnel webhook failed after {$result['attempts']} attempts", [
                'funnel_id' => $enrollment->funnel_id,
                'url' => $url,
                'error' => $result['error'] ?? 'Unknown error',
            ]);
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
     * Execute split step - enroll in A/B test and route to selected variant.
     */
    protected function executeSplitStep(FunnelSubscriber $enrollment, FunnelStep $step): void
    {
        // Get or create the A/B test for this split step
        $abTest = $this->abTestService->getOrCreateTest($step);

        // If test isn't running yet, start it automatically when first subscriber arrives
        if ($abTest->status === \App\Models\FunnelAbTest::STATUS_DRAFT) {
            $abTest->start();
        }

        // Enroll subscriber and get the selected variant
        $variant = $this->abTestService->enrollSubscriber($abTest, $enrollment);

        if (!$variant) {
            Log::warning("Failed to enroll subscriber {$enrollment->id} in A/B test {$abTest->id}");
            // Fallback to default next step
            $this->moveToNextStep($enrollment, $step->nextStep);
            return;
        }

        $enrollment->addToHistory('ab_test_enrolled', [
            'test_id' => $abTest->id,
            'test_name' => $abTest->name,
            'variant_id' => $variant->id,
            'variant_name' => $variant->name,
        ]);

        $enrollment->incrementStepsCompleted();

        // Route to the variant's next step
        $nextStep = $variant->nextStep ?? $step->nextStep;
        $this->moveToNextStep($enrollment, $nextStep);
    }

    /**
     * Execute goal step - record conversion tracking.
     */
    protected function executeGoalStep(FunnelSubscriber $enrollment, FunnelStep $step): void
    {
        $goalType = $step->goal_type;
        $goalValue = $step->goal_value ?? 0;
        $goalName = $step->goal_name ?? 'Goal';

        $enrollment->addToHistory('goal_reached', [
            'step_id' => $step->id,
            'goal_name' => $goalName,
            'goal_type' => $goalType,
            'goal_value' => $goalValue,
        ]);

        // Record conversion to dedicated table
        try {
            \App\Models\FunnelGoalConversion::recordConversion(
                $enrollment,
                $step,
                $goalValue,
                ['goal_config' => $step->goal_config ?? []],
                \App\Models\FunnelGoalConversion::SOURCE_FUNNEL
            );
        } catch (\Exception $e) {
            Log::warning("Failed to record goal conversion: " . $e->getMessage());
        }

        // Record conversion for any active A/B tests this subscriber is enrolled in
        $abEnrollments = \App\Models\FunnelAbEnrollment::where('funnel_subscriber_id', $enrollment->id)
            ->whereHas('abTest', fn($q) => $q->where('status', \App\Models\FunnelAbTest::STATUS_RUNNING))
            ->get();

        foreach ($abEnrollments as $abEnrollment) {
            $this->abTestService->recordConversion(
                $abEnrollment->abTest,
                $enrollment,
                $goalValue
            );
        }

        $enrollment->incrementStepsCompleted();
        $this->moveToNextStep($enrollment, $step->nextStep);
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

        // Enrollments of a paused funnel are left out in the query, not skipped
        // in the loop: skipped ones stayed ready and filled every batch
        FunnelSubscriber::readyToProcess()
            ->whereHas('funnel', fn ($query) => $query->where('status', Funnel::STATUS_ACTIVE))
            ->with(['funnel', 'currentStep', 'subscriber'])
            ->chunkById(100, function ($enrollments) use (&$processed) {
                foreach ($enrollments as $enrollment) {
                    try {
                        $enrollment->status = FunnelSubscriber::STATUS_ACTIVE;
                        $enrollment->save();

                        $this->processNextStep($enrollment);
                        $processed++;
                    } catch (\Throwable $e) {
                        Log::error("Funnel enrollment {$enrollment->id} failed to resume: {$e->getMessage()}", [
                            'exception' => $e,
                        ]);
                    }
                }
            });

        return $processed;
    }
}
