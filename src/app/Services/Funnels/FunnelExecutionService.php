<?php

namespace App\Services\Funnels;

use App\Models\ContactList;
use App\Models\EmailClick;
use App\Models\EmailOpen;
use App\Models\Funnel;
use App\Models\FunnelStep;
use App\Models\FunnelSubscriber;
use App\Models\FunnelTask;
use App\Models\Message;
use App\Models\Subscriber;
use App\Models\Tag;
use App\Jobs\SendEmailJob;
use App\Jobs\SendFunnelSmsJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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
            FunnelStep::TYPE_SMS => $this->executeSmsStep($enrollment, $step),
            FunnelStep::TYPE_DELAY => $this->executeDelayStep($enrollment, $step),
            FunnelStep::TYPE_WAIT_UNTIL => $this->executeWaitUntilStep($enrollment, $step),
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
     * Execute SMS step - queue the text and move to next.
     *
     * The step carries its own text (`sms_content`), so it is sent without a
     * message record; like the email step, it is skipped for a subscriber who
     * cannot receive it and the funnel moves on.
     */
    protected function executeSmsStep(FunnelSubscriber $enrollment, FunnelStep $step): void
    {
        $subscriber = $enrollment->subscriber;
        $content = trim((string) $step->sms_content);

        $reason = match (true) {
            $content === '' => 'The step has no text',
            !$subscriber->isDeliverable() => "Subscriber is {$subscriber->display_status}",
            blank($subscriber->phone) => 'Subscriber has no phone number',
            default => null,
        };

        if ($reason !== null) {
            $enrollment->addToHistory('sms_skipped', ['reason' => $reason]);

            Log::info("Funnel SMS step {$step->id} skipped for subscriber {$subscriber->id}: {$reason}");

            $this->moveToNextStep($enrollment, $step->nextStep);
            return;
        }

        SendFunnelSmsJob::dispatch($subscriber, $content, $enrollment->funnel->user_id, $step->id);

        $enrollment->addToHistory('sms_queued', ['length' => mb_strlen($content)]);

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

        $this->resumeAfter($enrollment, $step, now()->addSeconds($delaySeconds));
    }

    /**
     * Execute "wait until" step - hold the enrollment until a date, a weekday
     * or business hours (see FunnelStep::getWaitUntilMoment()).
     */
    protected function executeWaitUntilStep(FunnelSubscriber $enrollment, FunnelStep $step): void
    {
        $until = $step->getWaitUntilMoment(now(), $enrollment->funnel->user?->timezone);

        if (!$until) {
            Log::warning("Funnel wait-until step {$step->id} is not configured, moving on");
            $this->moveToNextStep($enrollment, $step->nextStep);
            return;
        }

        // Already there (a past date, or within business hours): no wait
        if ($until->lte(now())) {
            $this->moveToNextStep($enrollment, $step->nextStep);
            return;
        }

        $enrollment->addToHistory('wait_until_started', [
            'type' => $step->wait_until_type,
            'until' => $until->toIso8601String(),
        ]);

        $enrollment->incrementStepsCompleted();

        $this->resumeAfter($enrollment, $step, $until);
    }

    /**
     * Park the enrollment until `$at`; the scheduled processor then resumes it.
     */
    protected function resumeAfter(FunnelSubscriber $enrollment, FunnelStep $step, Carbon $at): void
    {
        $enrollment->scheduleNextActionAt($at);

        // Resuming runs the current step, so it already points past the wait.
        // A wait that ends the funnel leaves none: kept on the wait itself,
        // every resume started the same wait again and it never completed
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
     * Evaluate a condition step for an enrollment. Shared with FunnelRetryService,
     * which re-checks waiting conditions.
     *
     * Config keys: `message_id` (opened/clicked; without one, the last email this
     * funnel queued for the subscriber), `url` (link clicked, optionally within
     * `message_id`), `tag` or `tag_id`, `field`/`operator`/`value`, `task_id`.
     */
    public function evaluateCondition(FunnelSubscriber $enrollment, FunnelStep $step): bool
    {
        $subscriber = $enrollment->subscriber;
        $config = $step->condition_config ?? [];

        return match ($step->condition_type) {
            FunnelStep::CONDITION_EMAIL_OPENED => $this->checkEmailOpened($enrollment, $config),
            FunnelStep::CONDITION_EMAIL_CLICKED => $this->checkEmailClicked($enrollment, $config),
            FunnelStep::CONDITION_LINK_CLICKED => $this->checkLinkClicked($subscriber, $config),
            FunnelStep::CONDITION_TAG_EXISTS => $this->checkTagExists($subscriber, $config),
            FunnelStep::CONDITION_FIELD_VALUE => $this->checkFieldValue($subscriber, $config),
            FunnelStep::CONDITION_TASK_COMPLETED => $this->checkTaskCompleted($enrollment, $config),
            default => false,
        };
    }

    protected function checkEmailOpened(FunnelSubscriber $enrollment, array $config): bool
    {
        $messageId = $this->conditionMessageId($enrollment, $config);

        return $messageId && EmailOpen::where('subscriber_id', $enrollment->subscriber_id)
            ->where('message_id', $messageId)
            ->exists();
    }

    protected function checkEmailClicked(FunnelSubscriber $enrollment, array $config): bool
    {
        $messageId = $this->conditionMessageId($enrollment, $config);

        return $messageId && EmailClick::where('subscriber_id', $enrollment->subscriber_id)
            ->where('message_id', $messageId)
            ->exists();
    }

    protected function checkLinkClicked(Subscriber $subscriber, array $config): bool
    {
        $url = trim((string) ($config['url'] ?? ''));

        if ($url === '') {
            return false;
        }

        return EmailClick::where('subscriber_id', $subscriber->id)
            ->when($config['message_id'] ?? null, fn ($query, $messageId) => $query->where('message_id', $messageId))
            ->where('url', $url)
            ->exists();
    }

    /**
     * The email a condition step is about (see conditionMessageId()), for a
     * reminder that has none of its own.
     */
    public function conditionMessage(FunnelSubscriber $enrollment, FunnelStep $step): ?Message
    {
        $messageId = $this->conditionMessageId($enrollment, $step->condition_config ?? []);

        return $messageId ? Message::find($messageId) : null;
    }

    /**
     * The email an opened/clicked condition is about: the chosen one, or else
     * the last one this funnel queued for the subscriber.
     */
    protected function conditionMessageId(FunnelSubscriber $enrollment, array $config): ?int
    {
        if (!empty($config['message_id'])) {
            return (int) $config['message_id'];
        }

        $queued = collect($enrollment->getHistory())
            ->where('action', 'email_queued')
            ->last();

        return isset($queued['details']['message_id']) ? (int) $queued['details']['message_id'] : null;
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
        $tag = $this->findTag($subscriber, $config);

        return $tag && $subscriber->tags()->whereKey($tag->id)->exists();
    }

    /**
     * The subscriber owner's tag named in a step config, by `tag_id` or by name
     * (`tag`, as the builder stores it).
     */
    protected function findTag(Subscriber $subscriber, array $config): ?Tag
    {
        $query = Tag::where('user_id', $subscriber->user_id);

        if (!empty($config['tag_id'])) {
            return $query->whereKey($config['tag_id'])->first();
        }

        $name = trim((string) ($config['tag'] ?? ''));

        return $name === '' ? null : $query->where('name', $name)->first();
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
            FunnelStep::ACTION_ADD_TAG => $this->actionAddTag($enrollment, $config),
            FunnelStep::ACTION_REMOVE_TAG => $this->actionRemoveTag($enrollment, $config),
            FunnelStep::ACTION_MOVE_TO_LIST => $this->actionMoveToList($enrollment, $config),
            FunnelStep::ACTION_COPY_TO_LIST => $this->actionCopyToList($subscriber, $config),
            FunnelStep::ACTION_WEBHOOK => $this->actionWebhook($enrollment, $config),
            FunnelStep::ACTION_UNSUBSCRIBE => $this->actionUnsubscribe($enrollment, $config),
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

    /**
     * Add a tag, by `tag_id` or by name; a name the account has no tag for yet
     * creates it, as the automation action does.
     */
    protected function actionAddTag(FunnelSubscriber $enrollment, array $config): void
    {
        $subscriber = $enrollment->subscriber;
        $tag = $this->findTag($subscriber, $config);
        $name = trim((string) ($config['tag'] ?? ''));

        if (!$tag && empty($config['tag_id']) && $name !== '') {
            $tag = Tag::firstOrCreate(['user_id' => $subscriber->user_id, 'name' => $name]);
        }

        if (!$tag) {
            $enrollment->addToHistory('tag_skipped', ['reason' => 'Tag not found']);
            return;
        }

        $subscriber->addTag($tag);
    }

    protected function actionRemoveTag(FunnelSubscriber $enrollment, array $config): void
    {
        $tag = $this->findTag($enrollment->subscriber, $config);

        if (!$tag) {
            $enrollment->addToHistory('tag_skipped', ['reason' => 'Tag not found']);
            return;
        }

        $enrollment->subscriber->removeTag($tag);
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
    public function signupListId(Funnel $funnel): ?int
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

    /**
     * Unsubscribe from `list_id`, or without one from the list a "list signup"
     * funnel is triggered by. Only an active membership is changed.
     */
    protected function actionUnsubscribe(FunnelSubscriber $enrollment, array $config): void
    {
        $listId = (int) (($config['list_id'] ?? null) ?: $this->signupListId($enrollment->funnel));

        $reason = match (true) {
            !$listId => 'No list: the step names none and the funnel is not triggered by a list signup',
            !$enrollment->subscriber->unsubscribeFromList($listId, 'funnel') => 'Subscriber is not active on the list',
            default => null,
        };

        $enrollment->addToHistory($reason === null ? 'unsubscribed' : 'unsubscribe_skipped', array_filter([
            'list_id' => $listId ?: null,
            'reason' => $reason,
        ]));
    }

    /**
     * Email the funnel owner (or `email`) that a subscriber reached this step.
     * `subject` and `message` accept {{subscriber_email}}, {{subscriber_name}}
     * and {{funnel_name}}. A mail failure is logged, the funnel carries on.
     */
    protected function actionNotify(FunnelSubscriber $enrollment, array $config): void
    {
        $subscriber = $enrollment->subscriber;
        $funnel = $enrollment->funnel;
        $email = trim((string) ($config['email'] ?? '')) ?: $funnel->user?->email;

        if (!$email) {
            $enrollment->addToHistory('notification_skipped', ['reason' => 'No recipient']);
            return;
        }

        $replacements = [
            '{{subscriber_email}}' => $subscriber->email,
            '{{subscriber_name}}' => trim("{$subscriber->first_name} {$subscriber->last_name}") ?: $subscriber->email,
            '{{funnel_name}}' => $funnel->name,
        ];

        $subject = strtr(trim((string) ($config['subject'] ?? '')) ?: 'Lejek „{{funnel_name}}”: {{subscriber_email}}', $replacements);
        $body = strtr(trim((string) ($config['message'] ?? '')) ?: 'Subskrybent {{subscriber_name}} ({{subscriber_email}}) jest na kroku powiadomienia w lejku „{{funnel_name}}”.', $replacements);

        try {
            Mail::raw($body, fn ($mail) => $mail->to($email)->subject($subject));
            $enrollment->addToHistory('notification_sent', ['email' => $email]);
        } catch (\Throwable $e) {
            $enrollment->addToHistory('notification_failed', ['email' => $email, 'error' => $e->getMessage()]);
            Log::warning("Funnel notification to {$email} failed: {$e->getMessage()}");
        }
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
