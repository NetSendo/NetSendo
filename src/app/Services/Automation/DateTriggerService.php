<?php

namespace App\Services\Automation;

use App\Models\AutomationRule;
use App\Models\CustomField;
use App\Models\Subscriber;
use App\Events\SubscriberBirthday;
use App\Events\SubscriptionAnniversary;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class DateTriggerService
{
    /**
     * Process all date-based triggers.
     * Should be called via cron (safe to call frequently due to internal caching).
     */
    public function processAll(): array
    {
        $stats = [
            'birthdays_processed' => 0,
            'anniversaries_processed' => 0,
            'date_triggers_processed' => 0,
        ];

        try {
            $stats['birthdays_processed'] = $this->processBirthdays();
            $stats['anniversaries_processed'] = $this->processAnniversaries();
            $stats['date_triggers_processed'] = $this->processDateTriggers();
        } catch (\Exception $e) {
            Log::error('DateTriggerService error: ' . $e->getMessage());
        }

        return $stats;
    }

    /**
     * Process birthday triggers for all subscribers with birthdays today.
     */
    public function processBirthdays(): int
    {
        $count = 0;

        // Find active birthday automation rules
        $rules = AutomationRule::active()
            ->where('trigger_event', 'subscriber_birthday')
            ->get();

        if ($rules->isEmpty()) {
            return 0;
        }

        // Get unique user IDs that have birthday automations
        $userIds = $rules->pluck('user_id')->unique();

        foreach ($userIds as $userId) {
            // Find subscribers with birthdays today for this user
            $subscribers = $this->getSubscribersWithBirthdayToday($userId);

            foreach ($subscribers as $subscriber) {
                // Deduplication: Ensure we process this subscriber only once per day
                if (!$this->shouldProcess('birthday', $subscriber->id)) {
                    continue;
                }

                event(new SubscriberBirthday(
                    $subscriber->id,
                    $userId,
                    $this->calculateAge($subscriber),
                    $this->activeListIds($subscriber)
                ));

                $count++;
            }
        }

        return $count;
    }

    /**
     * Process subscription anniversary triggers.
     */
    public function processAnniversaries(): int
    {
        $count = 0;

        // Find active anniversary automation rules
        $rules = AutomationRule::active()
            ->where('trigger_event', 'subscription_anniversary')
            ->get();

        if ($rules->isEmpty()) {
            return 0;
        }

        // Get unique user IDs
        $userIds = $rules->pluck('user_id')->unique();

        foreach ($userIds as $userId) {
            // Find subscribers with subscription anniversaries today
            $subscribers = $this->getSubscribersWithAnniversaryToday($userId);

            foreach ($subscribers as $subscriber) {
                // Deduplication: Ensure we process this subscriber only once per day
                if (!$this->shouldProcess('anniversary', $subscriber->id)) {
                    continue;
                }

                // Carbon 3 returns a float; whole years since the signup
                $yearsSubscribed = (int) floor($subscriber->created_at->diffInYears(now()));

                // Only trigger for actual anniversaries (1+ years)
                if ($yearsSubscribed > 0) {
                    event(new SubscriptionAnniversary(
                        $subscriber->id,
                        $userId,
                        $yearsSubscribed,
                        $this->activeListIds($subscriber)
                    ));

                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Process date_reached triggers (specific dates).
     */
    public function processDateTriggers(): int
    {
        $count = 0;

        // Find active date_reached automation rules
        $rules = AutomationRule::active()
            ->where('trigger_event', 'date_reached')
            ->get();

        foreach ($rules as $rule) {
            $triggerConfig = $rule->trigger_config ?? [];
            $targetDate = $triggerConfig['date'] ?? null;

            if (!$targetDate) {
                continue;
            }

            // Check if target date is today
            $targetCarbon = Carbon::parse($targetDate);

            if (!$targetCarbon->isToday()) {
                continue;
            }

            // Get all subscribers for this user's lists
            $subscribers = $this->getSubscribersForUser($rule->user_id);

            foreach ($subscribers as $subscriber) {
                // processEvent runs every date_reached rule of this account for
                // this date, so process each (account, date, subscriber) once —
                // a second rule with the same date must not run the first again.
                $key = 'date_' . $rule->user_id . '_' . $targetCarbon->toDateString();
                if (!$this->shouldProcess($key, $subscriber->id)) {
                    continue;
                }

                // Process automation for this subscriber
                app(AutomationService::class)->processEvent(
                    'date_reached',
                    [
                        'subscriber_id' => $subscriber->id,
                        'user_id' => $rule->user_id,
                        'date' => $targetCarbon->toDateString(),
                        'list_ids' => $this->activeListIds($subscriber),
                    ]
                );

                $count++;
            }
        }

        return $count;
    }

    /**
     * Check if a trigger should be processed for a subscriber today.
     * Uses Cache to prevent duplicate processing on the same day.
     */
    protected function shouldProcess(string $type, int $subscriberId): bool
    {
        $today = now()->format('Y-m-d');
        $key = "automation_processed:{$type}:{$subscriberId}:{$today}";

        // Try to add key to cache with 24h expiry
        // Returns true if added (didn't exist), false if already exists
        return \Illuminate\Support\Facades\Cache::add($key, true, now()->addHours(24));
    }

    /**
     * Deliverable subscribers of an account (not unsubscribed, bounced or
     * inactive) who are an active member of at least one of its lists, with
     * those memberships loaded.
     */
    protected function subscribersOf(int $userId): Builder
    {
        $query = Subscriber::query()
            ->where('subscribers.user_id', $userId)
            ->whereHas('contactLists', fn ($lists) => $lists
                ->where('contact_lists.user_id', $userId)
                ->where('contact_list_subscriber.status', 'active'))
            ->with(['contactLists' => fn ($lists) => $lists
                ->where('contact_lists.user_id', $userId)
                ->where('contact_list_subscriber.status', 'active')]);

        return Subscriber::applyStatusFilter($query, Subscriber::STATUS_ACTIVE);
    }

    /**
     * Lists the subscriber is an active member of, so rules limited to one
     * list (e.g. a message sent "on the subscriber's birthday") can match.
     */
    protected function activeListIds(Subscriber $subscriber): array
    {
        return $subscriber->contactLists->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
    }

    /**
     * The account's date custom fields that hold a birthday: subscribers have
     * no birth date column, so the birthday is a "date" field whose name or
     * label mentions it (urodziny, birthday, birth date…).
     */
    protected function birthdayFieldIds(int $userId): \Illuminate\Support\Collection
    {
        return CustomField::where('user_id', $userId)
            ->where('type', 'date')
            ->where(function ($query) {
                foreach (['name', 'label'] as $column) {
                    $query->orWhere($column, 'like', '%urodzin%')
                        ->orWhere($column, 'like', '%birth%');
                }
            })
            ->pluck('id');
    }

    /**
     * Get subscribers with birthdays today for a specific user.
     */
    protected function getSubscribersWithBirthdayToday(int $userId): Collection
    {
        $fieldIds = $this->birthdayFieldIds($userId);
        if ($fieldIds->isEmpty()) {
            return new Collection();
        }

        $today = now();

        return $this->subscribersOf($userId)
            ->whereHas('fieldValues', fn ($values) => $values
                ->whereIn('custom_field_id', $fieldIds)
                // a date field stores Y-m-d; imports may bring d.m.Y
                ->where(fn ($value) => $value
                    ->where('value', 'like', '%' . $today->format('-m-d'))
                    ->orWhere('value', 'like', $today->format('d.m.') . '%')))
            ->with(['fieldValues' => fn ($values) => $values->whereIn('custom_field_id', $fieldIds)])
            ->get();
    }

    /**
     * Get subscribers with subscription anniversaries today.
     */
    protected function getSubscribersWithAnniversaryToday(int $userId): Collection
    {
        $today = now();

        return $this->subscribersOf($userId)
            ->whereMonth('subscribers.created_at', $today->month)
            ->whereDay('subscribers.created_at', $today->day)
            ->whereYear('subscribers.created_at', '<', $today->year) // Exclude signups from today
            ->get();
    }

    /**
     * Get all active subscribers for a user.
     */
    protected function getSubscribersForUser(int $userId): Collection
    {
        return $this->subscribersOf($userId)->get();
    }

    /**
     * Age from the birthday field, when its value is a full date.
     */
    protected function calculateAge(Subscriber $subscriber): ?int
    {
        foreach ($subscriber->fieldValues as $fieldValue) {
            $value = trim((string) $fieldValue->value);

            foreach (['Y-m-d', 'd.m.Y'] as $format) {
                try {
                    $date = Carbon::createFromFormat('!' . $format, substr($value, 0, 10));
                } catch (\Throwable) {
                    continue;
                }
                if ($date->format($format) === substr($value, 0, 10) && $date->isPast()) {
                    return (int) floor($date->diffInYears(now()));
                }
            }
        }

        return null;
    }
}
