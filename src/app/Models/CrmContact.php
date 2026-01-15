<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class CrmContact extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'subscriber_id',
        'user_id',
        'owner_id',
        'crm_company_id',
        'status',
        'source',
        'score',
        'position',
    ];

    protected $casts = [
        'score' => 'integer',
    ];

    /**
     * Get the subscriber (marketing data) for this CRM contact.
     * This is the 1:1 relationship with the existing Subscriber model.
     */
    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class);
    }

    /**
     * Get the user (account owner) that owns this contact.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the salesperson assigned to this contact.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get the company this contact belongs to.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(CrmCompany::class, 'crm_company_id');
    }

    /**
     * Get all deals for this contact.
     */
    public function deals(): HasMany
    {
        return $this->hasMany(CrmDeal::class);
    }

    /**
     * Get all tasks for this contact.
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(CrmTask::class);
    }

    /**
     * Get all activities for this contact.
     */
    public function activities(): MorphMany
    {
        return $this->morphMany(CrmActivity::class, 'subject');
    }

    // ==================== ACCESSOR PROXIES FROM SUBSCRIBER ====================

    /**
     * Get the email from the linked subscriber.
     */
    public function getEmailAttribute(): ?string
    {
        return $this->subscriber?->email;
    }

    /**
     * Get the first name from the linked subscriber.
     */
    public function getFirstNameAttribute(): ?string
    {
        return $this->subscriber?->first_name;
    }

    /**
     * Get the last name from the linked subscriber.
     */
    public function getLastNameAttribute(): ?string
    {
        return $this->subscriber?->last_name;
    }

    /**
     * Get the phone from the linked subscriber.
     */
    public function getPhoneAttribute(): ?string
    {
        return $this->subscriber?->phone;
    }

    /**
     * Get the full name.
     */
    public function getFullNameAttribute(): string
    {
        $name = trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
        return $name ?: ($this->email ?? 'Brak nazwy');
    }

    /**
     * Get the tags from the linked subscriber.
     */
    public function getTagsAttribute()
    {
        return $this->subscriber?->tags ?? collect();
    }

    /**
     * Get the contact lists from the linked subscriber.
     */
    public function getContactListsAttribute()
    {
        return $this->subscriber?->contactLists ?? collect();
    }

    // ==================== SCOPES ====================

    /**
     * Scope a query to only include contacts for a specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include contacts with a specific status.
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include contacts owned by a specific user.
     */
    public function scopeOwnedBy($query, $ownerId)
    {
        return $query->where('owner_id', $ownerId);
    }

    /**
     * Scope a query to include "hot" leads (high score + recent activity).
     */
    public function scopeHotLeads($query, $minScore = 50)
    {
        return $query->where('score', '>=', $minScore)
            ->whereIn('status', ['lead', 'prospect'])
            ->orderByDesc('score');
    }

    // ==================== HELPERS ====================

    /**
     * Create a CRM contact from an existing subscriber.
     */
    public static function createFromSubscriber(Subscriber $subscriber, array $attributes = []): self
    {
        return self::create(array_merge([
            'subscriber_id' => $subscriber->id,
            'user_id' => $subscriber->user_id,
            'status' => 'lead',
            'source' => $subscriber->source ?? 'import',
        ], $attributes));
    }

    /**
     * Log an activity for this contact.
     */
    public function logActivity(string $type, ?string $content = null, array $metadata = [], ?int $createdById = null): CrmActivity
    {
        return $this->activities()->create([
            'user_id' => $this->user_id,
            'created_by_id' => $createdById ?? auth()->id(),
            'type' => $type,
            'content' => $content,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Update the lead score.
     */
    public function updateScore(int $delta): void
    {
        $this->increment('score', $delta);
    }
}
