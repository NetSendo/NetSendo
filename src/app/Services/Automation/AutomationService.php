<?php

namespace App\Services\Automation;

use App\Models\AutomationRule;
use App\Models\AutomationRuleLog;
use App\Models\Subscriber;
use Illuminate\Support\Facades\Log;

class AutomationService
{
    protected AutomationActionExecutor $actionExecutor;

    public function __construct(AutomationActionExecutor $actionExecutor)
    {
        $this->actionExecutor = $actionExecutor;
    }

    /**
     * Process an event and trigger matching automations.
     */
    public function processEvent(string $triggerEvent, array $context): void
    {
        $subscriber = $this->getSubscriberFromContext($context);
        $rules = $this->findMatchingRules($triggerEvent, $context);

        foreach ($rules as $rule) {
            try {
                $this->executeRule($rule, $subscriber, $context, $triggerEvent);
            } catch (\Exception $e) {
                Log::error('Automation rule execution failed', [
                    'rule_id' => $rule->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Find all active rules matching the trigger event and conditions.
     */
    public function findMatchingRules(string $triggerEvent, array $context): \Illuminate\Support\Collection
    {
        // Get rules for this trigger event owned by users who own the relevant resources
        $rules = AutomationRule::active()
            ->forTrigger($triggerEvent)
            ->get();

        // Filter by trigger config and conditions
        return $rules->filter(function ($rule) use ($context) {
            return $this->matchesTriggerConfig($rule, $context)
                && $this->evaluateConditions($rule, $context);
        });
    }

    /**
     * Check if the rule's trigger config matches the event context.
     */
    protected function matchesTriggerConfig(AutomationRule $rule, array $context): bool
    {
        $config = $rule->trigger_config ?? [];

        // No config means match all
        if (empty($config)) {
            return true;
        }

        // Check list_id filter
        if (!empty($config['list_id'])) {
            $listId = $context['list_id'] ?? null;
            if ($listId !== (int) $config['list_id']) {
                return false;
            }
        }

        // Check form_id filter
        if (!empty($config['form_id'])) {
            $formId = $context['form_id'] ?? null;
            if ($formId !== (int) $config['form_id']) {
                return false;
            }
        }

        // Check message_id filter
        if (!empty($config['message_id'])) {
            $messageId = $context['message_id'] ?? null;
            if ($messageId !== (int) $config['message_id']) {
                return false;
            }
        }

        // Check tag_id filter
        if (!empty($config['tag_id'])) {
            $tagId = $context['tag_id'] ?? null;
            if ($tagId !== (int) $config['tag_id']) {
                return false;
            }
        }

        // Check page URL pattern for page_visited trigger
        if (!empty($config['url_pattern'])) {
            $pageUrl = $context['page_url'] ?? '';
            if (!$this->matchesUrlPattern($pageUrl, $config['url_pattern'])) {
                return false;
            }
        }

        // Check specific link URL for specific_link_clicked trigger
        if (!empty($config['link_url'])) {
            $clickedUrl = $context['url'] ?? '';
            if (!$this->matchesUrlPattern($clickedUrl, $config['link_url'])) {
                return false;
            }
        }

        // Check read time threshold
        if (!empty($config['read_time_threshold'])) {
            $readTimeSeconds = $context['read_time_seconds'] ?? 0;
            $threshold = (int) $config['read_time_threshold'];
            if ($readTimeSeconds < $threshold) {
                return false;
            }
        }

        // Check user_id ownership (security)
        if (!empty($context['user_id']) && $rule->user_id !== (int) $context['user_id']) {
            return false;
        }

        return true;
    }

    /**
     * Match URL against a pattern (supports wildcards).
     */
    protected function matchesUrlPattern(string $url, string $pattern): bool
    {
        // Exact match
        if ($url === $pattern) {
            return true;
        }

        // Wildcard pattern matching
        if (str_contains($pattern, '*')) {
            $regex = '/^' . str_replace(
                ['*', '/'],
                ['.*', '\/'],
                preg_quote($pattern, '/')
            ) . '$/i';
            return (bool) preg_match($regex, $url);
        }

        // Contains match (if pattern doesn't have protocol, check if URL contains it)
        if (!str_starts_with($pattern, 'http')) {
            return str_contains($url, $pattern);
        }

        return false;
    }

    /**
     * Evaluate conditions for a rule.
     */
    public function evaluateConditions(AutomationRule $rule, array $context): bool
    {
        $conditions = $rule->conditions ?? [];

        if (empty($conditions)) {
            return true;
        }

        $subscriber = $this->getSubscriberFromContext($context);
        if (!$subscriber) {
            return false;
        }

        $results = [];
        foreach ($conditions as $condition) {
            $results[] = $this->evaluateSingleCondition($condition, $subscriber, $context);
        }

        // Apply condition logic (all = AND, any = OR)
        if ($rule->condition_logic === 'any') {
            return in_array(true, $results, true);
        }

        return !in_array(false, $results, true);
    }

    /**
     * Evaluate a single condition.
     */
    protected function evaluateSingleCondition(array $condition, Subscriber $subscriber, array $context): bool
    {
        $type = $condition['type'] ?? '';
        $value = $condition['value'] ?? null;

        return match ($type) {
            'list_is' => ($context['list_id'] ?? null) == $value,
            'list_is_not' => ($context['list_id'] ?? null) != $value,
            'tag_exists' => $subscriber->tags()->where('tags.id', $value)->exists(),
            'tag_not_exists' => !$subscriber->tags()->where('tags.id', $value)->exists(),
            'field_equals' => $this->checkFieldEquals($subscriber, $condition),
            'field_not_equals' => !$this->checkFieldEquals($subscriber, $condition),
            'field_contains' => $this->checkFieldContains($subscriber, $condition),
            'field_is_empty' => $this->checkFieldEmpty($subscriber, $condition),
            'field_is_not_empty' => !$this->checkFieldEmpty($subscriber, $condition),
            'email_opened_message' => $this->checkEmailOpened($subscriber, $value),
            'email_clicked_message' => $this->checkEmailClicked($subscriber, $value),
            'subscribed_days_ago' => $this->checkSubscribedDaysAgo($subscriber, $value, $context),
            'source_is' => ($context['source'] ?? null) === $value,
            default => true,
        };
    }

    protected function checkFieldEquals(Subscriber $subscriber, array $condition): bool
    {
        $field = $condition['field'] ?? '';
        $value = $condition['value'] ?? '';

        if (in_array($field, ['email', 'first_name', 'last_name', 'phone'])) {
            return ($subscriber->{$field} ?? '') === $value;
        }

        // Custom field
        $fieldValue = $subscriber->fieldValues()
            ->whereHas('customField', fn($q) => $q->where('slug', $field))
            ->first();

        return ($fieldValue?->value ?? '') === $value;
    }

    protected function checkFieldContains(Subscriber $subscriber, array $condition): bool
    {
        $field = $condition['field'] ?? '';
        $value = $condition['value'] ?? '';

        if (in_array($field, ['email', 'first_name', 'last_name', 'phone'])) {
            return str_contains($subscriber->{$field} ?? '', $value);
        }

        $fieldValue = $subscriber->fieldValues()
            ->whereHas('customField', fn($q) => $q->where('slug', $field))
            ->first();

        return str_contains($fieldValue?->value ?? '', $value);
    }

    protected function checkFieldEmpty(Subscriber $subscriber, array $condition): bool
    {
        $field = $condition['field'] ?? '';

        if (in_array($field, ['email', 'first_name', 'last_name', 'phone'])) {
            return empty($subscriber->{$field});
        }

        $fieldValue = $subscriber->fieldValues()
            ->whereHas('customField', fn($q) => $q->where('slug', $field))
            ->first();

        return empty($fieldValue?->value);
    }

    protected function checkEmailOpened(Subscriber $subscriber, $messageId): bool
    {
        return $subscriber->emailOpens()->where('message_id', $messageId)->exists();
    }

    protected function checkEmailClicked(Subscriber $subscriber, $messageId): bool
    {
        return $subscriber->emailClicks()->where('message_id', $messageId)->exists();
    }

    protected function checkSubscribedDaysAgo(Subscriber $subscriber, $days, array $context): bool
    {
        $listId = $context['list_id'] ?? null;
        if (!$listId) {
            return false;
        }

        $pivot = $subscriber->lists()->where('contact_lists.id', $listId)->first()?->pivot;
        if (!$pivot || !$pivot->subscribed_at) {
            return false;
        }

        $subscribedDaysAgo = now()->diffInDays($pivot->subscribed_at);
        return $subscribedDaysAgo >= (int) $days;
    }

    /**
     * Execute a single rule.
     */
    public function executeRule(AutomationRule $rule, ?Subscriber $subscriber, array $context, string $triggerEvent): void
    {
        // Check rate limiting
        if ($subscriber && !$rule->canExecuteForSubscriber($subscriber->id)) {
            AutomationRuleLog::logSkipped($rule, $subscriber, $triggerEvent, $context, 'Rate limit exceeded');
            return;
        }

        $startTime = microtime(true);
        $actionsExecuted = [];
        $hasErrors = false;

        foreach ($rule->actions as $actionConfig) {
            try {
                $result = $this->actionExecutor->execute($actionConfig, $subscriber, $context);
                $actionsExecuted[] = [
                    'type' => $actionConfig['type'] ?? 'unknown',
                    'status' => 'success',
                    'result' => $result,
                ];
            } catch (\Exception $e) {
                $hasErrors = true;
                $actionsExecuted[] = [
                    'type' => $actionConfig['type'] ?? 'unknown',
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
                Log::error('Automation action failed', [
                    'rule_id' => $rule->id,
                    'action' => $actionConfig,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $executionTime = (int) ((microtime(true) - $startTime) * 1000);

        // Log execution
        if ($hasErrors) {
            $successCount = collect($actionsExecuted)->where('status', 'success')->count();
            $status = $successCount > 0 ? AutomationRuleLog::STATUS_PARTIAL : AutomationRuleLog::STATUS_FAILED;
            
            AutomationRuleLog::create([
                'automation_rule_id' => $rule->id,
                'subscriber_id' => $subscriber?->id,
                'trigger_event' => $triggerEvent,
                'trigger_data' => $context,
                'actions_executed' => $actionsExecuted,
                'status' => $status,
                'error_message' => 'Some actions failed',
                'execution_time_ms' => $executionTime,
                'executed_at' => now(),
            ]);
        } else {
            AutomationRuleLog::logSuccess($rule, $subscriber, $triggerEvent, $context, $actionsExecuted, $executionTime);
        }

        // Update rule stats
        $rule->incrementExecutionCount();
    }

    /**
     * Get subscriber from context.
     */
    protected function getSubscriberFromContext(array $context): ?Subscriber
    {
        if (!empty($context['subscriber_id'])) {
            return Subscriber::find($context['subscriber_id']);
        }
        return null;
    }
}
