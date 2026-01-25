<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Events\CrmScoreThresholdReached;
use App\Events\CrmContactStatusChanged;

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
        'last_activity_at',
        'score_updated_at',
        'last_decay_at',
    ];

    protected $casts = [
        'score' => 'integer',
        'last_activity_at' => 'datetime',
        'score_updated_at' => 'datetime',
        'last_decay_at' => 'datetime',
    ];

    /**
     * Score thresholds for notifications and promotions.
     */
    public const SCORE_THRESHOLDS = [50, 75, 100];

    /**
     * Status promotion thresholds.
     */
    public const STATUS_THRESHOLDS = [
        'prospect' => 30,  // Lead -> Prospect at 30 points
        'client' => 70,    // Prospect -> Client at 70 points
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::created(function (CrmContact $contact) {
            event(new \App\Events\CrmContactCreated($contact, auth()->id()));
        });
    }

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

    /**
     * Get score history for this contact.
     */
    public function scoreHistory(): HasMany
    {
        return $this->hasMany(LeadScoreHistory::class)->orderByDesc('created_at');
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

    /**
     * Scope for contacts needing score decay.
     */
    public function scopeNeedsDecay($query, int $inactiveDays = 7)
    {
        return $query->where('score', '>', 0)
            ->where(function ($q) use ($inactiveDays) {
                $q->whereNull('last_activity_at')
                    ->orWhere('last_activity_at', '<', now()->subDays($inactiveDays));
            })
            ->where(function ($q) use ($inactiveDays) {
                $q->whereNull('last_decay_at')
                    ->orWhere('last_decay_at', '<', now()->subDays($inactiveDays));
            });
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
     * Update the lead score with optional history logging.
     */
    public function updateScore(int $delta, string $eventType = 'manual', ?LeadScoringRule $rule = null, array $metadata = []): int
    {
        $oldScore = $this->score;
        $newScore = max(0, $oldScore + $delta); // Score cannot go below 0

        $this->update([
            'score' => $newScore,
            'score_updated_at' => now(),
            'last_activity_at' => $delta > 0 ? now() : $this->last_activity_at,
        ]);

        // Log to history
        LeadScoreHistory::log($this, $eventType, $delta, $oldScore, $newScore, $rule, $metadata);

        // Check for threshold crossings
        $this->checkThresholds($oldScore, $newScore);

        // Check for status promotion
        $this->checkStatusPromotion($newScore);

        return $newScore;
    }

    /**
     * Check if score crossed a notification threshold.
     */
    protected function checkThresholds(int $oldScore, int $newScore): void
    {
        foreach (self::SCORE_THRESHOLDS as $threshold) {
            // Crossed above threshold
            if ($oldScore < $threshold && $newScore >= $threshold) {
                event(new CrmScoreThresholdReached($this, $oldScore, $newScore, $threshold, 'above'));
            }
            // Crossed below threshold
            if ($oldScore >= $threshold && $newScore < $threshold) {
                event(new CrmScoreThresholdReached($this, $oldScore, $newScore, $threshold, 'below'));
            }
        }
    }

    /**
     * Automatically promote status based on score.
     */
    protected function checkStatusPromotion(int $newScore): void
    {
        $oldStatus = $this->status;
        $newStatus = $oldStatus;

        // Only auto-promote leads and prospects
        if ($oldStatus === 'lead' && $newScore >= self::STATUS_THRESHOLDS['prospect']) {
            $newStatus = 'prospect';
        } elseif ($oldStatus === 'prospect' && $newScore >= self::STATUS_THRESHOLDS['client']) {
            $newStatus = 'client';
        }

        if ($newStatus !== $oldStatus) {
            $this->update(['status' => $newStatus]);
            event(new CrmContactStatusChanged($this, $oldStatus, $newStatus));

            // Log the status change as activity
            $this->logActivity('status_changed', "Status zmieniony z {$oldStatus} na {$newStatus} (automatycznie przez scoring)");
        }
    }

    /**
     * Record last activity timestamp.
     */
    public function recordActivity(): void
    {
        $this->update(['last_activity_at' => now()]);
    }

    /**
     * Get recent score changes for display.
     */
    public function getRecentScoreChanges(int $limit = 10): \Illuminate\Support\Collection
    {
        return $this->scoreHistory()->limit($limit)->get();
    }

    /**
     * Get score trend (positive, negative, stable) over the last N days.
     */
    public function getScoreTrend(int $days = 7): string
    {
        $changes = $this->scoreHistory()
            ->where('created_at', '>=', now()->subDays($days))
            ->sum('points_change');

        if ($changes > 0) return 'positive';
        if ($changes < 0) return 'negative';
        return 'stable';
    }
}
