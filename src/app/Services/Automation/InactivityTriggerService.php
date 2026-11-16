<?php

namespace App\Services\Automation;

use App\Events\SubscriberInactive;
use App\Models\AutomationRule;
use App\Models\Subscriber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Raises "subscriber_inactive" for every active rule of that trigger.
 *
 * A subscriber is inactive for a rule when, for at least the rule's days
 * (trigger_config.days, or inactive_days as the message editor saves it;
 * default 30), they have neither opened nor clicked, and they joined before
 * that. A rule limited to a list counts the time since joining that list.
 *
 * Each rule runs once per subscriber per inactive spell: the run is marked in
 * automation_inactivity_marks, and an open or a click clears the marks
 * (Subscriber::recordEngagement), so a subscriber who comes back and goes
 * quiet again is caught again.
 */
class InactivityTriggerService
{
    public const DEFAULT_DAYS = 30;

    /** Subscribers raised per rule per run; the rest follow on the next run. */
    public const BATCH_PER_RULE = 1000;

    public static function daysFor(AutomationRule $rule): int
    {
        $config = $rule->trigger_config ?? [];
        $days = (int) ($config['days'] ?? $config['inactive_days'] ?? self::DEFAULT_DAYS);

        return max(1, $days);
    }

    /**
     * @return int subscribers raised
     */
    public function processAll(?int $limitPerRule = null): int
    {
        $count = 0;

        $rules = AutomationRule::active()
            ->where('trigger_event', 'subscriber_inactive')
            ->get();

        foreach ($rules as $rule) {
            try {
                $count += $this->processRule($rule, $limitPerRule ?? self::BATCH_PER_RULE);
            } catch (\Throwable $e) {
                Log::error('InactivityTriggerService error: ' . $e->getMessage(), ['rule_id' => $rule->id]);
            }
        }

        return $count;
    }

    public function processRule(AutomationRule $rule, int $limit = self::BATCH_PER_RULE): int
    {
        $days = self::daysFor($rule);
        $threshold = now()->subDays($days);
        $listId = (int) (($rule->trigger_config ?? [])['list_id'] ?? 0) ?: null;

        $subscribers = $this->inactiveSubscribers($rule, $threshold, $listId)
            ->orderBy('subscribers.id')
            ->limit($limit)
            ->get();

        foreach ($subscribers as $subscriber) {
            $lastActivity = collect([$subscriber->last_opened_at, $subscriber->last_clicked_at])
                ->filter()
                ->max();

            // Mark first: a rule that fails half-way must not run again every hour.
            DB::table('automation_inactivity_marks')->insertOrIgnore([
                'automation_rule_id' => $rule->id,
                'subscriber_id' => $subscriber->id,
                'fired_at' => now(),
            ]);

            event(new SubscriberInactive(
                $subscriber->id,
                (int) $rule->user_id,
                (int) $rule->id,
                $days,
                $lastActivity?->toIso8601String(),
                $subscriber->contactLists->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            ));
        }

        return $subscribers->count();
    }

    /**
     * Deliverable subscribers of the rule's account, active on one of its lists
     * (on the rule's list, when it has one), quiet since the threshold and not
     * yet raised for this rule.
     */
    protected function inactiveSubscribers(AutomationRule $rule, \DateTimeInterface $threshold, ?int $listId): Builder
    {
        $userId = (int) $rule->user_id;

        $membership = function ($lists) use ($userId, $listId, $threshold) {
            $lists->where('contact_lists.user_id', $userId)
                ->where('contact_list_subscriber.status', 'active');

            if ($listId) {
                $lists->where('contact_lists.id', $listId)
                    ->where(fn ($joined) => $joined
                        ->where('contact_list_subscriber.subscribed_at', '<', $threshold)
                        ->orWhere(fn ($legacy) => $legacy
                            ->whereNull('contact_list_subscriber.subscribed_at')
                            ->where('contact_list_subscriber.created_at', '<', $threshold)));
            }
        };

        $query = Subscriber::query()
            ->select('subscribers.*')
            ->where('subscribers.user_id', $userId)
            ->where('subscribers.created_at', '<', $threshold)
            ->where(fn ($q) => $q->whereNull('subscribers.last_opened_at')->orWhere('subscribers.last_opened_at', '<', $threshold))
            ->where(fn ($q) => $q->whereNull('subscribers.last_clicked_at')->orWhere('subscribers.last_clicked_at', '<', $threshold))
            ->whereHas('contactLists', $membership)
            ->whereNotExists(fn ($marks) => $marks
                ->select(DB::raw(1))
                ->from('automation_inactivity_marks')
                ->whereColumn('automation_inactivity_marks.subscriber_id', 'subscribers.id')
                ->where('automation_inactivity_marks.automation_rule_id', $rule->id))
            ->with(['contactLists' => fn ($lists) => $lists
                ->where('contact_lists.user_id', $userId)
                ->where('contact_list_subscriber.status', 'active')]);

        return Subscriber::applyStatusFilter($query, Subscriber::STATUS_ACTIVE);
    }
}
