<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use App\Traits\LogsActivity;
use App\Models\CrmContact;

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
        'sms_provider_id',
        'template_id',
        'webinar_id',
        'webinar_auto_register',
        'type', // broadcast, autoresponder
        'day', // day offset
        'subject',
        'preheader',
        'custom_headers',
        'content',
        'status',
        'timezone',
        'send_at',
        'scheduled_at', // For CRON processing
        'time_of_day',
        'send_in_subscriber_timezone',
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
        'custom_headers' => 'array',
        'is_active' => 'boolean',
        'send_in_subscriber_timezone' => 'boolean',
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

    public function smsProvider()
    {
        return $this->belongsTo(SmsProvider::class);
    }

    public function template()
    {
        return $this->belongsTo(Template::class);
    }

    public function webinar()
    {
        return $this->belongsTo(Webinar::class);
    }

    /**
     * Get the tags associated with this message (for campaign tracking).
     */
    public function tags()
    {
        return $this->morphToMany(Tag::class, 'taggable');
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
     * CRM contacts selected to receive this message.
     */
    public function crmContacts()
    {
        return $this->belongsToMany(CrmContact::class, 'message_crm_contact');
    }

    /**
     * CRM contacts excluded from receiving this message.
     */
    public function excludedCrmContacts()
    {
        return $this->belongsToMany(CrmContact::class, 'excluded_crm_contact_message');
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
     * Get all tracked links configuration for this message.
     */
    public function trackedLinks()
    {
        return $this->hasMany(MessageTrackedLink::class);
    }

    /**
     * Get the A/B test for this message.
     */
    public function abTest()
    {
        return $this->hasOne(AbTest::class);
    }

    /**
     * Get all translations for this message.
     */
    public function translations()
    {
        return $this->hasMany(MessageTranslation::class);
    }

    /**
     * Get translation for a specific language.
     * Returns null if no translation exists for the given language.
     */
    public function getTranslationForLanguage(?string $language): ?MessageTranslation
    {
        if (!$language) {
            return null;
        }

        return $this->translations()->where('language', $language)->first();
    }

    /**
     * Calculate the expected send datetime (UTC) for this autoresponder message,
     * anchored at the given base time (usually the subscription/re-signup moment).
     *
     * Applies the day offset and, when time_of_day is set, the configured hour —
     * interpreted in the subscriber's timezone when send_in_subscriber_timezone
     * is enabled. Single source of truth shared by the signup listener and CRON.
     */
    public function calculateExpectedSendAt(\Carbon\Carbon $base, ?Subscriber $subscriber = null): \Carbon\Carbon
    {
        $expected = $base->copy()->addDays($this->day ?? 0);

        if ($this->time_of_day) {
            $timeParts = explode(':', $this->time_of_day);
            $hour = (int) ($timeParts[0] ?? 0);
            $minute = (int) ($timeParts[1] ?? 0);

            $targetTimezone = 'UTC';
            if ($this->send_in_subscriber_timezone && $subscriber) {
                $targetTimezone = $subscriber->getEffectiveTimezone($this->effective_timezone);
            }

            $expected = $expected->copy()
                ->startOfDay()
                ->shiftTimezone($targetTimezone)
                ->setTime($hour, $minute, 0)
                ->setTimezone('UTC');
        }

        return $expected;
    }

    /**
     * Get aggregated queue statistics.
     *
     * @return array{planned: int, queued: int, sent: int, failed: int, skipped: int, total: int}
     */
    public function getQueueStats(): array
    {
        $messageId = $this->id;
        $stats = $this->queueEntries()
            ->join('subscribers', 'message_queue_entries.subscriber_id', '=', 'subscribers.id')
            ->whereIn('message_queue_entries.id', function ($query) use ($messageId) {
                $query->select('id')
                    ->fromRaw("(
                        SELECT mq.id, s.email,
                               ROW_NUMBER() OVER (
                                   PARTITION BY s.email
                                   ORDER BY
                                       CASE mq.status
                                           WHEN 'sent' THEN 1
                                           WHEN 'failed' THEN 2
                                           WHEN 'queued' THEN 3
                                           WHEN 'planned' THEN 4
                                           WHEN 'skipped' THEN 5
                                           ELSE 6
                                       END,
                                       mq.id DESC
                               ) as rn
                        FROM message_queue_entries mq
                        JOIN subscribers s ON mq.subscriber_id = s.id
                        WHERE mq.message_id = {$messageId}
                          AND NOT (mq.status = 'skipped' AND mq.error_message LIKE '%removed from list%')
                    ) as ranked")
                    ->where('rn', 1);
            })
            // Exclude skipped entries for removed subscribers from stats
            ->where(function ($q) {
                $q->where('message_queue_entries.status', '!=', 'skipped')
                  ->orWhereNull('message_queue_entries.error_message')
                  ->orWhere('message_queue_entries.error_message', 'NOT LIKE', '%removed from list%');
            })
            ->selectRaw('message_queue_entries.status, COUNT(*) as count')
            ->groupBy('message_queue_entries.status')
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
    public function getQueueScheduleStats(?int $missedLimit = 100): array
    {
        if (!$this->isQueueType()) {
            return [];
        }

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
                'pending' => 0,
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
            ->get()
            ->unique('email'); // Deduplicate by email before counting

        // Get emails that have already been sent (using email for deduplication)
        $sentEmails = $this->queueEntries()
            ->join('subscribers', 'message_queue_entries.subscriber_id', '=', 'subscribers.id')
            ->where('message_queue_entries.status', MessageQueueEntry::STATUS_SENT)
            ->pluck('subscribers.email')
            ->unique()
            ->toArray();

        // Emails that have queue entries waiting to be processed (PLANNED or QUEUED)
        // These are NOT missed - they are waiting for CRON to process them.
        // Keep their stored scheduled_for so they can be bucketed by send date.
        $pendingByEmail = $this->queueEntries()
            ->join('subscribers', 'message_queue_entries.subscriber_id', '=', 'subscribers.id')
            ->whereIn('message_queue_entries.status', [
                MessageQueueEntry::STATUS_PLANNED,
                MessageQueueEntry::STATUS_QUEUED
            ])
            ->pluck('message_queue_entries.scheduled_for', 'subscribers.email')
            ->toArray();

        $stats = [
            'sent' => count($sentEmails),
            'pending' => count($pendingByEmail), // Entries already queued, waiting for CRON
            'today' => 0,
            'tomorrow' => 0,
            'day_after_tomorrow' => 0,
            'days_3_7' => 0,
            'over_7_days' => 0,
            'missed' => 0,
            'missed_subscribers' => [],
        ];

        foreach ($subscribers as $subscriber) {
            // Skip if already sent (check by email for deduplication)
            if (in_array($subscriber->email, $sentEmails)) {
                continue;
            }

            $isPending = array_key_exists($subscriber->email, $pendingByEmail);

            // Get subscribed_at from the first matching list's pivot
            $pivot = $subscriber->contactLists->first()?->pivot;
            $subscribedAt = $pivot?->subscribed_at ? \Carbon\Carbon::parse($pivot->subscribed_at) : null;

            if (!$subscribedAt && !$isPending) {
                continue;
            }

            // Expected send datetime: for pending entries prefer the schedule
            // stored on the queue entry; otherwise derive from subscribed_at
            $expectedSendDateTime = null;
            if ($isPending && $pendingByEmail[$subscriber->email]) {
                $expectedSendDateTime = \Carbon\Carbon::parse($pendingByEmail[$subscriber->email]);
            } elseif ($subscribedAt) {
                $expectedSendDateTime = $this->calculateExpectedSendAt($subscribedAt, $subscriber);
            }

            if (!$expectedSendDateTime) {
                // Pending entry without a stored schedule and no pivot date —
                // it will be sent on the next CRON run
                $stats['today']++;
                continue;
            }

            // Get just the date portion for category comparison
            $expectedSendDate = $expectedSendDateTime->copy()->startOfDay();

            // Check if this subscriber was "missed" (expected send datetime is in the past).
            // Pending entries are never "missed" — CRON will pick them up; a past
            // schedule simply means they are due now, so count them as "today".
            if ($expectedSendDateTime->lt($now)) {
                if ($isPending) {
                    $stats['today']++;
                    continue;
                }
                $stats['missed']++;
                // Store missed subscribers for display / send-to-missed
                if ($missedLimit === null || count($stats['missed_subscribers']) < $missedLimit) {
                    $stats['missed_subscribers'][] = [
                        'id' => $subscriber->id,
                        'email' => $subscriber->email,
                        'name' => trim(($subscriber->first_name ?? '') . ' ' . ($subscriber->last_name ?? '')),
                        'subscribed_at' => $subscribedAt?->format('Y-m-d H:i'),
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

        // For autoresponders: entries are created when a subscriber joins the
        // list (SubscriberSignedUp listener). The CRON backfill below is the
        // safety net for everyone the listener could not cover — subscribers
        // who were on the list before the message existed / was activated, or
        // whose signup path failed to fire the event. Only recipients whose
        // expected send time is still in the FUTURE are backfilled; past ones
        // stay "missed" and require the explicit "Send to missed" action.
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

            // Backfill: schedule recipients that have NO queue entry yet and
            // whose send time has not passed. Without this, a subscriber only
            // "projected" for tomorrow would silently become "missed" when the
            // time arrived, because nothing ever created their entry.
            $missingSubscriberIds = array_diff($currentSubscriberIds, $existingEntryIds);
            if (!empty($missingSubscriberIds)) {
                $listIds = $this->contactLists->pluck('id')->toArray();

                // Bulk-load active pivot rows for the missing subscribers
                $pivotRows = DB::table('contact_list_subscriber')
                    ->whereIn('subscriber_id', $missingSubscriberIds)
                    ->whereIn('contact_list_id', $listIds)
                    ->where('status', 'active')
                    ->whereNotNull('subscribed_at')
                    ->get()
                    ->groupBy('subscriber_id');

                $recipientsById = $currentRecipients->keyBy('id');
                $now = now('UTC');

                foreach ($missingSubscriberIds as $subscriberId) {
                    $rows = $pivotRows->get($subscriberId);
                    if (!$rows || $rows->isEmpty()) {
                        continue;
                    }

                    // Most recent signup among the message's lists is the anchor
                    $subscribedAt = \Carbon\Carbon::parse($rows->max('subscribed_at'));
                    $expectedSendAt = $this->calculateExpectedSendAt(
                        $subscribedAt,
                        $recipientsById->get($subscriberId)
                    );

                    if ($expectedSendAt->lt($now)) {
                        continue; // Time already passed — stays "missed" (manual action)
                    }

                    try {
                        $this->queueEntries()->create([
                            'subscriber_id' => $subscriberId,
                            'status' => MessageQueueEntry::STATUS_PLANNED,
                            'planned_at' => now(),
                            'scheduled_for' => $expectedSendAt,
                        ]);
                        $result['added']++;
                    } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                        // Entry created concurrently (signup listener) — fine
                        continue;
                    }
                }
            }

            // Refresh the stored recipients count for autoresponders too, so list
            // views show a stable number instead of null. Throttled to avoid a DB
            // write on every CRON run ($currentRecipients is already computed).
            if (!$this->recipients_calculated_at || $this->recipients_calculated_at->lt(now()->subMinutes(10))) {
                $this->update([
                    'planned_recipients_count' => count($currentSubscriberIds),
                    'recipients_calculated_at' => now(),
                ]);
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
     * Includes both list-based subscribers and individually selected CRM contacts.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getUniqueRecipients()
    {
        $includedListIds = $this->contactLists->pluck('id')->toArray();
        $excludedListIds = $this->excludedLists->pluck('id')->toArray();

        // Get subscriber IDs from selected CRM contacts
        $crmContactSubscriberIds = $this->crmContacts
            ->pluck('subscriber_id')
            ->filter()
            ->toArray();

        // Get subscriber IDs to exclude from CRM contact exclusions
        $excludedCrmSubscriberIds = $this->excludedCrmContacts
            ->pluck('subscriber_id')
            ->filter()
            ->toArray();

        // If no lists selected and no CRM contacts, return empty
        if (empty($includedListIds) && empty($crmContactSubscriberIds)) {
            return collect();
        }

        // Base query for list-based subscribers
        $listSubscribers = collect();
        if (!empty($includedListIds)) {
            $listSubscribers = Subscriber::whereHas('contactLists', function ($query) use ($includedListIds) {
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
                ->when(!empty($excludedCrmSubscriberIds), function ($query) use ($excludedCrmSubscriberIds) {
                    // Exclude CRM contacts from list recipients
                    $query->whereNotIn('id', $excludedCrmSubscriberIds);
                })
                ->when($this->trigger_type === 'recent_subscribers' && !empty($this->trigger_config['recent_days']), function ($query) {
                    // Filter to only include subscribers who joined within the recent X days
                    $days = (int) $this->trigger_config['recent_days'];
                    $query->whereHas('contactLists', function ($q) use ($days) {
                        $q->where('contact_list_subscriber.subscribed_at', '>=', now()->subDays($days));
                    });
                })
                ->when($this->trigger_type === 'opened_message' && !empty($this->trigger_config['message_id']), function ($query) {
                    // Include only subscribers who opened the specified message
                    $messageId = (int) $this->trigger_config['message_id'];
                    $openedSubscriberIds = EmailOpen::where('message_id', $messageId)
                        ->pluck('subscriber_id')
                        ->unique()
                        ->toArray();
                    $query->whereIn('id', $openedSubscriberIds);
                })
                ->when($this->trigger_type === 'not_opened_message' && !empty($this->trigger_config['message_id']), function ($query) {
                    // Exclude subscribers who opened the specified message
                    $messageId = (int) $this->trigger_config['message_id'];
                    $openedSubscriberIds = EmailOpen::where('message_id', $messageId)
                        ->pluck('subscriber_id')
                        ->unique()
                        ->toArray();
                    $query->whereNotIn('id', $openedSubscriberIds);
                })
                ->get();
        }

        // Get individually selected CRM contact subscribers (not excluded)
        $crmSubscribers = collect();
        if (!empty($crmContactSubscriberIds)) {
            $includableCrmIds = array_diff($crmContactSubscriberIds, $excludedCrmSubscriberIds);
            if (!empty($includableCrmIds)) {
                $crmSubscribers = Subscriber::whereIn('id', $includableCrmIds)->get();
            }
        }

        // Merge both collections and deduplicate by email
        return $listSubscribers->merge($crmSubscribers)->unique('email');
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
     * Get the effective SMS provider for the message using hierarchical resolution.
     * Priority: Message -> List -> User Default
     */
    public function getEffectiveSmsProvider(): ?SmsProvider
    {
        // 1. Explicit SMS provider for this message
        if ($this->sms_provider_id) {
            return $this->smsProvider;
        }

        // 2. Default SMS provider from the first contact list
        $list = $this->contactLists->first();
        if ($list && $list->default_sms_provider_id) {
            return SmsProvider::find($list->default_sms_provider_id);
        }

        // 3. User's global default SMS provider
        return SmsProvider::getDefaultFor($this->user_id);
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

