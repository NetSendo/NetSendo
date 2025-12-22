<?php

namespace App\Services\Automation;

use App\Models\AutomationRule;
use App\Models\Subscriber;
use App\Events\SubscriberBirthday;
use App\Events\SubscriptionAnniversary;
use Illuminate\Support\Facades\Log;

class DateTriggerService
{
    /**
     * Process all date-based triggers.
     * Should be called daily via cron.
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
                $age = $this->calculateAge($subscriber);

                event(new SubscriberBirthday(
                    $subscriber->id,
                    $userId,
                    $age
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
                $yearsSubscribed = $subscriber->created_at->diffInYears(now());

                // Only trigger for actual anniversaries (1+ years)
                if ($yearsSubscribed > 0) {
                    event(new SubscriptionAnniversary(
                        $subscriber->id,
                        $userId,
                        $yearsSubscribed
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
            $targetCarbon = \Carbon\Carbon::parse($targetDate);
            
            if (!$targetCarbon->isToday()) {
                continue;
            }

            // Get all subscribers for this user's lists
            $subscribers = $this->getSubscribersForUser($rule->user_id);

            foreach ($subscribers as $subscriber) {
                // Process automation for this subscriber
                app(AutomationService::class)->processEvent(
                    'date_reached',
                    [
                        'subscriber_id' => $subscriber->id,
                        'user_id' => $rule->user_id,
                        'date' => $targetDate,
                    ]
                );

                $count++;
            }
        }

        return $count;
    }

    /**
     * Get subscribers with birthdays today for a specific user.
     */
    protected function getSubscribersWithBirthdayToday(int $userId): \Illuminate\Database\Eloquent\Collection
    {
        $today = now();

        return Subscriber::whereHas('contactList', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->where('status', 'active')
            ->where(function ($query) use ($today) {
                // Check standard birth_date field
                $query->whereMonth('birth_date', $today->month)
                      ->whereDay('birth_date', $today->day);
            })
            ->orWhereHas('fieldValues', function ($query) use ($today) {
                // Check custom birthday fields
                $query->whereHas('customField', function ($q) {
                    $q->where('type', 'date')
                      ->where(function ($q2) {
                          $q2->where('name', 'like', '%urodzin%')
                             ->orWhere('name', 'like', '%birthday%')
                             ->orWhere('slug', 'like', '%birthday%');
                      });
                })
                ->whereMonth('value', $today->month)
                ->whereDay('value', $today->day);
            })
            ->get();
    }

    /**
     * Get subscribers with subscription anniversaries today.
     */
    protected function getSubscribersWithAnniversaryToday(int $userId): \Illuminate\Database\Eloquent\Collection
    {
        $today = now();

        return Subscriber::whereHas('contactList', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->where('status', 'active')
            ->whereMonth('created_at', $today->month)
            ->whereDay('created_at', $today->day)
            ->whereYear('created_at', '<', $today->year) // Exclude signups from today
            ->get();
    }

    /**
     * Get all active subscribers for a user.
     */
    protected function getSubscribersForUser(int $userId): \Illuminate\Database\Eloquent\Collection
    {
        return Subscriber::whereHas('contactList', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->where('status', 'active')
            ->get();
    }

    /**
     * Calculate subscriber's age from birth_date.
     */
    protected function calculateAge(Subscriber $subscriber): ?int
    {
        if (!$subscriber->birth_date) {
            return null;
        }

        return \Carbon\Carbon::parse($subscriber->birth_date)->age;
    }
}
