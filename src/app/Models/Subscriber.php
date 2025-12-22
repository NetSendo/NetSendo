<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
            ->withPivot('status', 'subscribed_at', 'unsubscribed_at')
            ->withTimestamps();
    } 
    // Removed old contactList relationship to avoid confusion


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
            'phone' => $this->phone ?? '',
            'device' => $this->device ?? '',
            'ip_address' => $this->ip_address ?? '',
            'subscribed_at' => $this->subscribed_at?->format('Y-m-d H:i:s') ?? '',
            'confirmed_at' => $this->confirmed_at?->format('Y-m-d H:i:s') ?? '',
            'source' => $this->source ?? '',
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

    /**
     * Get all tags assigned to this subscriber
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'subscriber_tag')
            ->withTimestamps();
    }

    /**
     * Add a tag to subscriber and dispatch event
     */
    public function addTag(Tag $tag): void
    {
        if (!$this->tags->contains($tag->id)) {
            $this->tags()->attach($tag->id);
            $this->load('tags'); // Refresh relationship
            
            event(new TagAdded($this, $tag));
        }
    }

    /**
     * Remove a tag from subscriber and dispatch event
     */
    public function removeTag(Tag $tag): void
    {
        if ($this->tags->contains($tag->id)) {
            $this->tags()->detach($tag->id);
            $this->load('tags'); // Refresh relationship
            
            event(new TagRemoved($this, $tag));
        }
    }

    /**
     * Sync tags with event dispatching
     */
    public function syncTagsWithEvents(array $tagIds): void
    {
        $currentTagIds = $this->tags->pluck('id')->toArray();
        
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
}
