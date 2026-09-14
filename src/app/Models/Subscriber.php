<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use App\Events\TagAdded;
use App\Events\TagRemoved;
use App\Traits\LogsActivity;

class Subscriber extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    /**
     * Attributes to include in activity log
     */
    protected $activityLogAttributes = ['email', 'first_name', 'last_name', 'status'];


    protected $fillable = [
        'user_id',
        'email',
        'phone',
        'first_name',
        'last_name',
        'gender',
        'language',
        'status', // Global status or Keep for backward compatibility/global override
        'is_active_global',
        // 'contact_list_id', // Removing this
        // New standard fields
        'device',
        'ip_address',
        'user_agent',
        'subscribed_at',
        'confirmed_at',
        'last_opened_at',
        'last_clicked_at',
        'opens_count',
        'clicks_count',
        'source',
        'timezone',
        // 'tags',
    ];

    protected $casts = [
        'subscribed_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'last_opened_at' => 'datetime',
        'last_clicked_at' => 'datetime',
        'opens_count' => 'integer',
        'clicks_count' => 'integer',
        'is_active_global' => 'boolean',
        // 'tags' => 'array',
    ];

    /**
     * Global statuses. `inactive` is not stored in `status` — it is an `active`
     * row whose `is_active_global` flag is off. CronScheduleService skips a
     * queue entry unless the subscriber is both `active` and flagged active,
     * and an inactive subscriber is left out of every list and CRM audience
     * (SubscriberFieldFilterService::audienceQuery).
     */
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_UNSUBSCRIBED = 'unsubscribed';
    public const STATUS_BOUNCED = 'bounced';

    /**
     * Statuses the admin interface shows and filters by.
     */
    public const DISPLAY_STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
        self::STATUS_UNSUBSCRIBED,
        self::STATUS_BOUNCED,
    ];

    /**
     * Scope a query to only include active subscribers.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active_global', true);
    }

    /**
     * The one status to show for this subscriber.
     *
     * A bounced or unsubscribed `status` wins over the boolean flag: that
     * column is what stops delivery, so reporting such an address as "active"
     * (issue #31) hid exactly why it received nothing.
     */
    public function getDisplayStatusAttribute(): string
    {
        return self::displayStatusFor($this->status, (bool) $this->is_active_global);
    }

    /**
     * The same rule for a raw `subscribers` row that never became a model.
     */
    public static function displayStatusFor(?string $status, bool $isActiveGlobal): string
    {
        $status = $status ?: self::STATUS_ACTIVE;

        if ($status !== self::STATUS_ACTIVE) {
            return $status;
        }

        return $isActiveGlobal ? self::STATUS_ACTIVE : self::STATUS_INACTIVE;
    }

    /**
     * Whether a send may reach this subscriber: bounced, unsubscribed and
     * inactive addresses get nothing. For paths that dispatch a send directly
     * (automations, funnels) instead of through the CRON queue gate.
     */
    public function isDeliverable(): bool
    {
        return $this->display_status === self::STATUS_ACTIVE;
    }

    /**
     * Narrow a subscriber query to one display status. Works on an Eloquent
     * builder and on a plain query builder over `subscribers`.
     */
    public static function applyStatusFilter($query, string $status)
    {
        return match ($status) {
            self::STATUS_ACTIVE => $query->where('subscribers.status', self::STATUS_ACTIVE)
                ->where('subscribers.is_active_global', true),
            self::STATUS_INACTIVE => $query->where('subscribers.status', self::STATUS_ACTIVE)
                ->where('subscribers.is_active_global', false),
            default => $query->where('subscribers.status', $status),
        };
    }

    /**
     * Attributes that apply a status an admin picked in the interface.
     *
     * `active` is an explicit reactivation and clears a bounced or unsubscribed
     * marker too — updating the flag alone left such an address skipped by
     * every send, with no way back. `inactive` only lowers the flag and never
     * clears the marker, so it cannot quietly resume delivery to an address
     * that bounced. Anything else keeps what is stored.
     */
    public static function adminStatusAttributes(string $status): array
    {
        return match ($status) {
            self::STATUS_ACTIVE => ['status' => self::STATUS_ACTIVE, 'is_active_global' => true],
            self::STATUS_INACTIVE => ['is_active_global' => false],
            default => [],
        };
    }

    /**
     * Zero the soft-bounce counters of reactivated subscribers. A counter left
     * at the list's threshold would mark the address bounced again on the very
     * next temporary failure.
     */
    public static function resetSoftBounceCounts(array $subscriberIds): void
    {
        if (empty($subscriberIds)) {
            return;
        }

        DB::table('contact_list_subscriber')
            ->whereIn('subscriber_id', $subscriberIds)
            ->where('soft_bounce_count', '>', 0)
            ->update(['soft_bounce_count' => 0]);
    }

    /**
     * Get the user that owns the subscriber.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the contact lists this subscriber belongs to
     */
    public function contactLists(): BelongsToMany
    {
        return $this->belongsToMany(ContactList::class, 'contact_list_subscriber')
            ->withPivot('status', 'source', 'subscribed_at', 'unsubscribed_at', 'confirmed_at', 'resubscribed_at', 'soft_bounce_count')
            ->withTimestamps();
    }
    // Removed old contactList relationship to avoid confusion

    /**
     * Make this subscriber an active member of a list — attach a new
     * membership or reactivate an existing one, honoring the list's
     * resubscription_behavior. Fires no event.
     *
     * Returns true when the membership is new or was reactivated, the cases
     * that count as a signup and should start the list's sequences; false
     * when it was already active.
     */
    public function activateListMembership(ContactList $list, ?string $source = null): bool
    {
        $existingPivot = $this->contactLists()->where('contact_list_id', $list->id)->first();

        if (!$existingPivot) {
            $this->contactLists()->attach($list->id, [
                'status' => 'active',
                'subscribed_at' => now(),
                'source' => $source,
            ]);

            return true;
        }

        $wasActive = $existingPivot->pivot->status === self::STATUS_ACTIVE;
        $shouldResetDate = !$wasActive || ($list->resubscription_behavior ?? 'reset_date') === 'reset_date';

        $pivotData = [
            'status' => 'active',
            'unsubscribed_at' => null,
        ];

        if ($shouldResetDate) {
            $pivotData['subscribed_at'] = now();
        }

        // A membership bounced on this list starts counting afresh — a counter
        // left at the threshold would mark it bounced again on the next
        // temporary failure
        if ($existingPivot->pivot->status === self::STATUS_BOUNCED) {
            $pivotData['soft_bounce_count'] = 0;
        }

        $this->contactLists()->updateExistingPivot($list->id, $pivotData);

        return !$wasActive;
    }

    /**
     * Add (or reactivate) this subscriber on a list, honoring the list's
     * resubscription_behavior, and fire SubscriberSignedUp so autoresponder
     * sequences and automations start. Used by funnel/system actions.
     *
     * The event is fired only for a new attach or a reactivation — an
     * already-active membership is left untouched so automated actions
     * cannot restart sequences on every run.
     */
    public function addToList(int $listId, string $source = 'system'): bool
    {
        $list = ContactList::find($listId);
        if (!$list) {
            return false;
        }

        if ($this->activateListMembership($list, $source)) {
            event(new \App\Events\SubscriberSignedUp($this, $list, null, $source));
        }

        return true;
    }

    /**
     * Move this subscriber from one list to another (detach + addToList).
     *
     * Only an active membership of the source list is moved. Unsubscribed,
     * bounced and unconfirmed ones keep their row — the opt-out history, the
     * per-list bounce record, the pending double opt-in — and the subscriber
     * is not signed up to the target list in their place. Returns false when
     * nothing was moved.
     */
    public function moveToList(int $fromListId, int $toListId, string $source = 'system'): bool
    {
        if (!ContactList::whereKey($toListId)->exists()) {
            return false;
        }

        $detached = $this->contactLists()
            ->wherePivot('status', self::STATUS_ACTIVE)
            ->detach($fromListId);

        if (!$detached) {
            return false;
        }

        return $this->addToList($toListId, $source);
    }

    /**
     * Unsubscribe this subscriber from a list, as the unsubscribe link does:
     * the membership keeps its row with status `unsubscribed`, what is still
     * planned for the list's messages is dropped, and SubscriberUnsubscribed
     * fires. Only an active membership is changed — a bounced or unconfirmed
     * one keeps its status. Returns false when nothing was changed.
     */
    public function unsubscribeFromList(int $listId, string $source = 'system'): bool
    {
        $list = ContactList::find($listId);

        if (!$list) {
            return false;
        }

        $updated = $this->contactLists()
            ->wherePivot('status', self::STATUS_ACTIVE)
            ->updateExistingPivot($list->id, [
                'status' => self::STATUS_UNSUBSCRIBED,
                'unsubscribed_at' => now(),
            ]);

        if (!$updated) {
            return false;
        }

        MessageQueueEntry::where('subscriber_id', $this->id)
            ->whereIn('status', [MessageQueueEntry::STATUS_PLANNED, MessageQueueEntry::STATUS_QUEUED])
            ->whereIn('message_id', function ($query) use ($list) {
                $query->select('message_id')
                    ->from('contact_list_message')
                    ->where('contact_list_id', $list->id);
            })
            ->delete();

        event(new \App\Events\SubscriberUnsubscribed($this, $list, $source));

        return true;
    }


    /**
     * Get all custom field values for this subscriber
     */
    public function fieldValues(): HasMany
    {
        return $this->hasMany(SubscriberFieldValue::class);
    }

    /**
     * Get value of a specific custom field by name
     */
    public function getCustomFieldValue(string $fieldName): ?string
    {
        $value = $this->fieldValues()
            ->whereHas('customField', function ($query) use ($fieldName) {
                $query->where('name', $fieldName);
            })
            ->first();

        if ($value) {
            return $value->value;
        }

        // Return default value from field definition if no value set
        $field = CustomField::where('name', $fieldName)->first();
        return $field?->default_value;
    }

    /**
     * Set value for a custom field
     */
    public function setCustomFieldValue(string $fieldName, ?string $value): void
    {
        $field = CustomField::where('name', $fieldName)->first();

        if (!$field) {
            return;
        }

        $this->fieldValues()->updateOrCreate(
            ['custom_field_id' => $field->id],
            ['value' => $value]
        );
    }

    /**
     * Get all placeholder values (standard + custom fields)
     * Returns array like ['email' => 'test@example.com', 'first_name' => 'John', ...]
     */
    public function getAllPlaceholderValues(): array
    {
        $values = [
            // Standard subscriber fields
            'email' => $this->email ?? '',
            'first_name' => $this->first_name ?? '',
            'last_name' => $this->last_name ?? '',
            'fname' => $this->first_name ?? '', // Alias for first_name
            'lname' => $this->last_name ?? '',  // Alias for last_name
            'phone' => $this->phone ?? '',
            'device' => $this->device ?? '',
            'ip_address' => $this->ip_address ?? '',
            'subscribed_at' => $this->subscribed_at?->format('Y-m-d H:i:s') ?? '',
            'confirmed_at' => $this->confirmed_at?->format('Y-m-d H:i:s') ?? '',
            'source' => $this->source ?? '',
            'language' => $this->language ?? '',
        ];

        // Add custom field values
        $customFields = $this->fieldValues()->with('customField')->get();
        foreach ($customFields as $fieldValue) {
            if ($fieldValue->customField) {
                $values[$fieldValue->customField->name] = $fieldValue->value ?? $fieldValue->customField->default_value ?? '';
            }
        }

        // Also include global custom fields that have default values but no subscriber value yet
        $globalFields = CustomField::global()
            ->where('user_id', $this->user_id)
            ->get();

        foreach ($globalFields as $field) {
            if (!isset($values[$field->name])) {
                $values[$field->name] = $field->default_value ?? '';
            }
        }

        return $values;
    }

    /**
     * Record an email open event
     */
    public function recordOpen(): void
    {
        $this->increment('opens_count');
        $this->update(['last_opened_at' => now()]);
    }

    /**
     * Record a link click event
     */
    public function recordClick(): void
    {
        $this->increment('clicks_count');
        $this->update(['last_clicked_at' => now()]);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'subscriber_tag')
            ->withTimestamps();
    }

    /**
     * Get all devices linked to this subscriber
     */
    public function devices(): HasMany
    {
        return $this->hasMany(SubscriberDevice::class);
    }

    /**
     * Get all pixel events for this subscriber
     */
    public function pixelEvents(): HasMany
    {
        return $this->hasMany(PixelEvent::class);
    }

    /**
     * Add a tag to subscriber and dispatch event.
     *
     * Idempotent (fixes #24): the previous implementation guarded the insert
     * with an in-memory `$this->tags->contains(...)` check, which is unreliable
     * when the relationship is stale or was loaded before another code path
     * attached the tag — the raw INSERT then hit the
     * `subscriber_tag_subscriber_id_tag_id_unique` constraint (SQLSTATE 23000 /
     * 1062) and threw, aborting the surrounding automation/event chain.
     *
     * `syncWithoutDetaching()` checks the live pivot table before inserting, so
     * re-adding a tag the subscriber already has is a no-op. The rare
     * concurrent-insert race (two requests attaching the same tag at once) is
     * caught so a unique-constraint violation can never bubble up. The
     * `TagAdded` event is dispatched only when the tag was genuinely attached.
     */
    public function addTag(Tag $tag): void
    {
        try {
            $changes = $this->tags()->syncWithoutDetaching([$tag->id]);
            $attached = !empty($changes['attached']);
        } catch (UniqueConstraintViolationException $e) {
            // A concurrent request attached the same tag between the existence
            // check and the insert — the tag is present, so treat it as a no-op.
            $attached = false;
        }

        $this->load('tags'); // Refresh relationship

        if ($attached) {
            event(new TagAdded($this, $tag));
        }
    }

    /**
     * Remove a tag from subscriber and dispatch event.
     *
     * The pivot is queried directly rather than read through `$this->tags`:
     * `subscribers.tags` is a legacy JSON column, and an attribute always wins
     * over a relation of the same name, so `$this->tags` yields the (null)
     * column instead of the loaded tags. Reading it made the membership check
     * always fail, so detaching silently did nothing. `detach()` reports how
     * many rows it removed, which is both accurate and idempotent.
     */
    public function removeTag(Tag $tag): void
    {
        $detached = $this->tags()->detach($tag->id);

        if ($detached > 0) {
            $this->load('tags'); // Refresh relationship

            event(new TagRemoved($this, $tag));
        }
    }

    /**
     * Sync tags with event dispatching.
     *
     * Current tags come from the relation query for the same reason as in
     * removeTag() — reading `$this->tags` returned the legacy column, so the
     * "tags to remove" set was always empty and sync only ever added.
     */
    public function syncTagsWithEvents(array $tagIds): void
    {
        $currentTagIds = $this->tags()->pluck('tags.id')->toArray();

        // Find tags to add
        $toAdd = array_diff($tagIds, $currentTagIds);
        foreach ($toAdd as $tagId) {
            $tag = Tag::find($tagId);
            if ($tag) {
                $this->addTag($tag);
            }
        }

        // Find tags to remove
        $toRemove = array_diff($currentTagIds, $tagIds);
        foreach ($toRemove as $tagId) {
            $tag = Tag::find($tagId);
            if ($tag) {
                $this->removeTag($tag);
            }
        }
    }

    /**
     * Get the effective timezone for this subscriber.
     * Returns the subscriber's timezone if set, otherwise the provided fallback.
     */
    public function getEffectiveTimezone(string $fallback = 'UTC'): string
    {
        return $this->timezone ?? $fallback;
    }
}
