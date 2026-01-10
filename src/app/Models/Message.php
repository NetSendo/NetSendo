<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\LogsActivity;

class Message extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    /**
     * Attributes to include in activity log
     */
    protected $activityLogAttributes = ['subject', 'status', 'channel'];

    protected $fillable = [
        'user_id',
        'campaign_plan_id',
        'channel', // email, sms
        'mailbox_id',
        'template_id',
        'webinar_id',
        'webinar_auto_register',
        'type', // broadcast, autoresponder
        'day', // day offset
        'subject',
        'preheader',
        'content',
        'status',
        'timezone',
        'send_at',
        'scheduled_at', // For CRON processing
        'time_of_day',
        // A/B Testing
        'ab_enabled',
        'ab_variant_subject',
        'ab_variant_content',
        'ab_split_percentage',
        // Triggers
        'trigger_type',
        'trigger_config',
        // Queue status
        'is_active',
        'sent_count',
        'planned_recipients_count',
        'recipients_calculated_at',
    ];

    protected $casts = [
        'send_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'ab_enabled' => 'boolean',
        'ab_split_percentage' => 'integer',
        'trigger_config' => 'array',
        'is_active' => 'boolean',
        'sent_count' => 'integer',
        'planned_recipients_count' => 'integer',
        'recipients_calculated_at' => 'datetime',
        'webinar_auto_register' => 'boolean',
    ];

    public function scopeEmail($query)
    {
        return $query->where('channel', 'email');
    }

    public function scopeSms($query)
    {
        return $query->where('channel', 'sms');
    }

    public function scopeForCampaignPlan($query, $planId)
    {
        return $query->where('campaign_plan_id', $planId);
    }

    public function campaignPlan()
    {
        return $this->belongsTo(CampaignPlan::class);
    }

    /**
     * Check if this message is a queue/autoresponder type.
     */
    public function isQueueType(): bool
    {
        return $this->type === 'autoresponder';
    }

    /**
     * Scope for active queue messages.
     */
    public function scopeActiveQueue($query)
    {
        return $query->where('type', 'autoresponder')
            ->where('is_active', true);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function mailbox()
    {
        return $this->belongsTo(Mailbox::class);
    }

    public function template()
    {
        return $this->belongsTo(Template::class);
    }

    public function webinar()
    {
        return $this->belongsTo(Webinar::class);
    }

    public function contactLists()
    {
        return $this->belongsToMany(ContactList::class, 'contact_list_message');
    }

    /**
     * Lists excluded from receiving this message.
     */
    public function excludedLists()
    {
        return $this->belongsToMany(ContactList::class, 'excluded_contact_list_message');
    }

    /**
     * Get all queue entries for tracking per-subscriber sending status.
     */
    public function queueEntries()
    {
        return $this->hasMany(MessageQueueEntry::class);
    }

    /**
     * Get all attachments for this message.
     */
    public function attachments()
    {
        return $this->hasMany(MessageAttachment::class);
    }

    /**
     * Get aggregated queue statistics.
     *
     * @return array{planned: int, queued: int, sent: int, failed: int, skipped: int, total: int}
     */
    public function getQueueStats(): array
    {
        $stats = $this->queueEntries()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'planned' => $stats[MessageQueueEntry::STATUS_PLANNED] ?? 0,
            'queued' => $stats[MessageQueueEntry::STATUS_QUEUED] ?? 0,
            'sent' => $stats[MessageQueueEntry::STATUS_SENT] ?? 0,
            'failed' => $stats[MessageQueueEntry::STATUS_FAILED] ?? 0,
            'skipped' => $stats[MessageQueueEntry::STATUS_SKIPPED] ?? 0,
            'total' => array_sum($stats),
        ];
    }

    /**
     * Get detailed queue schedule statistics for autoresponder messages.
     * Shows breakdown by scheduled send date and missed recipients.
     *
     * Logic:
     * - expectedSendDateTime is calculated as: subscribed_at + day offset
     * - If time_of_day is set, the time portion is set to that hour
     * - If time_of_day is not set, the time from subscribed_at is used
     * - A subscriber is "missed" if their expectedSendDateTime < NOW
     * - Schedule categories (today, tomorrow, etc.) show future sends only
     *
     * @return array
     */
    public function getQueueScheduleStats(): array
    {
        if (!$this->isQueueType()) {
            return [];
        }

        $dayOffset = $this->day ?? 0;
        $timeOfDay = $this->time_of_day; // e.g., "15:00" or null
        $now = now();
        $today = $now->copy()->startOfDay();
        $tomorrow = $today->copy()->addDay();
        $dayAfterTomorrow = $today->copy()->addDays(2);
        $weekFromNow = $today->copy()->addDays(7);

        // Get all active subscribers from assigned lists with their subscribed_at dates
        $includedListIds = $this->contactLists->pluck('id')->toArray();
        $excludedListIds = $this->excludedLists->pluck('id')->toArray();

        if (empty($includedListIds)) {
            return [
                'sent' => 0,
                'today' => 0,
                'tomorrow' => 0,
                'day_after_tomorrow' => 0,
                'days_3_7' => 0,
                'over_7_days' => 0,
                'missed' => 0,
                'missed_subscribers' => [],
                'total_scheduled' => 0,
            ];
        }

        // Get subscribers with their subscribed_at from pivot
        $subscribers = Subscriber::whereHas('contactLists', function ($query) use ($includedListIds) {
                $query->whereIn('contact_lists.id', $includedListIds)
                    ->where('contact_list_subscriber.status', 'active');
            })
            ->when(!empty($excludedListIds), function ($query) use ($excludedListIds) {
                $excludedEmails = Subscriber::whereHas('contactLists', function ($q) use ($excludedListIds) {
                    $q->whereIn('contact_lists.id', $excludedListIds);
                })->pluck('email')->toArray();
                $query->whereNotIn('email', $excludedEmails);
            })
            ->with(['contactLists' => function ($query) use ($includedListIds) {
                $query->whereIn('contact_lists.id', $includedListIds);
            }])
            ->get();

        // Get existing queue entries to check sent status
        $sentSubscriberIds = $this->queueEntries()
            ->where('status', MessageQueueEntry::STATUS_SENT)
            ->pluck('subscriber_id')
            ->toArray();

        $stats = [
            'sent' => count($sentSubscriberIds),
            'today' => 0,
            'tomorrow' => 0,
            'day_after_tomorrow' => 0,
            'days_3_7' => 0,
            'over_7_days' => 0,
            'missed' => 0,
            'missed_subscribers' => [],
        ];

        foreach ($subscribers as $subscriber) {
            // Skip if already sent
            if (in_array($subscriber->id, $sentSubscriberIds)) {
                continue;
            }

            // Get subscribed_at from the first matching list's pivot
            $pivot = $subscriber->contactLists->first()?->pivot;
            $subscribedAt = $pivot?->subscribed_at ? \Carbon\Carbon::parse($pivot->subscribed_at) : null;

            if (!$subscribedAt) {
                continue;
            }

            // Calculate expected send datetime
            // Base: subscribed_at + day offset
            $expectedSendDateTime = $subscribedAt->copy()->addDays($dayOffset);

            // If time_of_day is set, use that specific hour on the expected day
            if ($timeOfDay) {
                // Parse time_of_day (format: "HH:MM" or "H:i")
                $timeParts = explode(':', $timeOfDay);
                $hour = (int) ($timeParts[0] ?? 0);
                $minute = (int) ($timeParts[1] ?? 0);
                $expectedSendDateTime = $expectedSendDateTime->copy()->startOfDay()->setTime($hour, $minute, 0);
            }
            // If no time_of_day, keep the original subscribed_at time
            // This means for day=0: expectedSendDateTime = subscribedAt (immediate)

            // Get just the date portion for category comparison
            $expectedSendDate = $expectedSendDateTime->copy()->startOfDay();

            // Check if this subscriber was "missed" (expected send datetime is in the past)
            if ($expectedSendDateTime->lt($now)) {
                $stats['missed']++;
                // Store first 100 missed subscribers for display
                if (count($stats['missed_subscribers']) < 100) {
                    $stats['missed_subscribers'][] = [
                        'id' => $subscriber->id,
                        'email' => $subscriber->email,
                        'name' => trim(($subscriber->first_name ?? '') . ' ' . ($subscriber->last_name ?? '')),
                        'subscribed_at' => $subscribedAt->format('Y-m-d H:i'),
                        'would_send_at' => $expectedSendDateTime->format('Y-m-d H:i'),
                    ];
                }
            } elseif ($expectedSendDate->isSameDay($today)) {
                // Will be sent later today
                $stats['today']++;
            } elseif ($expectedSendDate->isSameDay($tomorrow)) {
                $stats['tomorrow']++;
            } elseif ($expectedSendDate->isSameDay($dayAfterTomorrow)) {
                $stats['day_after_tomorrow']++;
            } elseif ($expectedSendDate->lte($weekFromNow)) {
                $stats['days_3_7']++;
            } else {
                $stats['over_7_days']++;
            }
        }

        $stats['total_scheduled'] = $stats['today'] + $stats['tomorrow'] + $stats['day_after_tomorrow'] + $stats['days_3_7'] + $stats['over_7_days'];

        return $stats;
    }

    /**
     * Synchronize planned recipients with current active subscribers.
     * This adds new subscribers and marks unsubscribed ones as skipped.
     *
     * For autoresponders: Does NOT add all subscribers automatically.
     * Queue entries are created when subscribers join (SubscriberController)
     * or manually via "Send to missed" button. This prevents adding
     * subscribers whose send time has already passed.
     *
     * @return array{added: int, skipped: int}
     */
    public function syncPlannedRecipients(): array
    {
        // For broadcasts, if sending has already started (sent_count > 0),
        // we lock the recipient list (Snapshot behavior).
        // New subscribers joining after this point should NOT receive this message.
        if ($this->type === 'broadcast' && $this->sent_count > 0) {
            return ['added' => 0, 'skipped' => 0];
        }

        $result = ['added' => 0, 'skipped' => 0];

        // Get current active subscribers
        $currentRecipients = $this->getUniqueRecipients();
        $currentSubscriberIds = $currentRecipients->pluck('id')->toArray();

        // Get existing queue entries
        $existingEntryIds = $this->queueEntries()
            ->pluck('subscriber_id')
            ->toArray();

        // For autoresponders: DON'T add new subscribers automatically
        // Queue entries are created:
        // 1. When subscriber joins the list (in SubscriberController)
        // 2. Manually via "Send to missed" button
        // This ensures we don't add subscribers whose send time has already passed
        if ($this->isQueueType()) {
            // Only mark removed/unsubscribed subscribers as skipped
            $removedSubscriberIds = array_diff($existingEntryIds, $currentSubscriberIds);
            if (!empty($removedSubscriberIds)) {
                $skipped = $this->queueEntries()
                    ->whereIn('subscriber_id', $removedSubscriberIds)
                    ->whereIn('status', [MessageQueueEntry::STATUS_PLANNED, MessageQueueEntry::STATUS_QUEUED])
                    ->update([
                        'status' => MessageQueueEntry::STATUS_SKIPPED,
                        'error_message' => 'Subscriber removed from list or unsubscribed',
                    ]);
                $result['skipped'] = $skipped;
            }

            return $result;
        }

        // For broadcasts: add all new subscribers as planned
        $newSubscriberIds = array_diff($currentSubscriberIds, $existingEntryIds);
        foreach ($newSubscriberIds as $subscriberId) {
            $this->queueEntries()->create([
                'subscriber_id' => $subscriberId,
                'status' => MessageQueueEntry::STATUS_PLANNED,
                'planned_at' => now(),
            ]);
            $result['added']++;
        }

        // Mark removed/unsubscribed subscribers as skipped (only if still pending)
        $removedSubscriberIds = array_diff($existingEntryIds, $currentSubscriberIds);
        if (!empty($removedSubscriberIds)) {
            $skipped = $this->queueEntries()
                ->whereIn('subscriber_id', $removedSubscriberIds)
                ->whereIn('status', [MessageQueueEntry::STATUS_PLANNED, MessageQueueEntry::STATUS_QUEUED])
                ->update([
                    'status' => MessageQueueEntry::STATUS_SKIPPED,
                    'error_message' => 'Subscriber removed from list or unsubscribed',
                ]);
            $result['skipped'] = $skipped;
        }

        // Update message stats
        $this->update([
            'planned_recipients_count' => count($currentSubscriberIds),
            'recipients_calculated_at' => now(),
        ]);

        return $result;
    }

    /**
     * Get unique active subscribers for this message with exclusions applied.
     * Ensures each email is only included once (deduplication).
     *
     * @return \Illuminate\Support\Collection
     */
    public function getUniqueRecipients()
    {
        $includedListIds = $this->contactLists->pluck('id')->toArray();
        $excludedListIds = $this->excludedLists->pluck('id')->toArray();

        if (empty($includedListIds)) {
            return collect();
        }

        return Subscriber::whereHas('contactLists', function ($query) use ($includedListIds) {
                $query->whereIn('contact_lists.id', $includedListIds)
                    ->where('contact_list_subscriber.status', 'active');
            })
            ->when(!empty($excludedListIds), function ($query) use ($excludedListIds) {
                // Exclude subscribers that are on any of the excluded lists
                $excludedEmails = Subscriber::whereHas('contactLists', function ($q) use ($excludedListIds) {
                    $q->whereIn('contact_lists.id', $excludedListIds);
                })->pluck('email')->toArray();
                $query->whereNotIn('email', $excludedEmails);
            })
            ->get()
            ->unique('email'); // Final deduplication by email
    }

    // TODO: Implement tracking models when stats feature is ready
    // public function opens()
    // {
    //     return $this->hasMany(MessageOpen::class);
    // }

    // public function clicks()
    // {
    //     return $this->hasMany(MessageClick::class);
    // }

    /**
     * Get the effective mailbox for the message using hierarchical resolution.
     * Priority: Message -> List -> User Default
     */
    public function getEffectiveMailbox(): ?Mailbox
    {
        // 1. Explicit mailbox for this message
        if ($this->mailbox_id) {
            return $this->mailbox;
        }

        // 2. Default mailbox from the first contact list
        $list = $this->contactLists->first();
        if ($list && $list->default_mailbox_id) {
            return Mailbox::find($list->default_mailbox_id);
        }

        // 3. User's global default mailbox
        return Mailbox::getDefaultFor($this->user_id);
    }

    /**
     * Get the effective timezone for the message.
     * Hierarchy: Message -> List (first) -> Group -> User -> Account Default (UTC)
     */
    public function getEffectiveTimezoneAttribute()
    {
        if ($this->timezone) {
            return $this->timezone;
        }

        // Check primary contact list (if any)
        $list = $this->contactLists->first();
        if ($list) {
            if ($list->timezone) {
                return $list->timezone;
            }
            if ($list->group && $list->group->timezone) {
                return $list->group->timezone;
            }
        }

        if ($this->user && $this->user->timezone) {
            return $this->user->timezone;
        }

        return 'UTC';
    }
}

