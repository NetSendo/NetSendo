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
        'channel', // email, sms
        'mailbox_id',
        'template_id',
        'type', // broadcast, autoresponder
        'day', // day offset
        'subject',
        'preheader',
        'content',
        'status',
        'timezone',
        'send_at',
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
        'ab_enabled' => 'boolean',
        'ab_split_percentage' => 'integer',
        'trigger_config' => 'array',
        'is_active' => 'boolean',
        'sent_count' => 'integer',
        'planned_recipients_count' => 'integer',
        'recipients_calculated_at' => 'datetime',
    ];

    public function scopeEmail($query)
    {
        return $query->where('channel', 'email');
    }

    public function scopeSms($query)
    {
        return $query->where('channel', 'sms');
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
     * Synchronize planned recipients with current active subscribers.
     * This adds new subscribers and marks unsubscribed ones as skipped.
     *
     * @return array{added: int, skipped: int}
     */
    public function syncPlannedRecipients(): array
    {
        $result = ['added' => 0, 'skipped' => 0];
        
        // Get current active subscribers
        $currentRecipients = $this->getUniqueRecipients();
        $currentSubscriberIds = $currentRecipients->pluck('id')->toArray();
        
        // Get existing queue entries
        $existingEntryIds = $this->queueEntries()
            ->pluck('subscriber_id')
            ->toArray();
        
        // Add new subscribers as planned
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

        return Subscriber::whereIn('contact_list_id', $includedListIds)
            ->where('status', 'active')
            ->when(!empty($excludedListIds), function ($query) use ($excludedListIds) {
                // Exclude subscribers that are on any of the excluded lists
                $excludedEmails = Subscriber::whereIn('contact_list_id', $excludedListIds)
                    ->pluck('email')
                    ->toArray();
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

