<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\UniqueConstraintViolationException;
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
     * Scope a query to only include active subscribers.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active_global', true);
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

        $existingPivot = $this->contactLists()->where('contact_list_id', $listId)->first();

        if ($existingPivot) {
            $wasActive = $existingPivot->pivot->status === 'active';
            $shouldResetDate = !$wasActive || ($list->resubscription_behavior ?? 'reset_date') === 'reset_date';

            $pivotData = [
                'status' => 'active',
                'unsubscribed_at' => null,
            ];

            if ($shouldResetDate) {
                $pivotData['subscribed_at'] = now();
            }

            $this->contactLists()->updateExistingPivot($listId, $pivotData);

            if ($wasActive) {
                return true;
            }
        } else {
            $this->contactLists()->attach($listId, [
                'status' => 'active',
                'subscribed_at' => now(),
                'source' => $source,
            ]);
        }

        event(new \App\Events\SubscriberSignedUp($this, $list, null, $source));

        return true;
    }

    /**
     * Move this subscriber from one list to another (detach + addToList).
     */
    public function moveToList(int $fromListId, int $toListId, string $source = 'system'): bool
    {
        $this->contactLists()->detach($fromListId);

        return $this->addToList($toListId, $source);
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
