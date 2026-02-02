<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CrmActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'created_by_id',
        'subject_type',
        'subject_id',
        'type',
        'content',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Get the user (account owner) for this activity.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user who created this activity.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * Get the subject of this activity (contact, deal, or company).
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    // ==================== SCOPES ====================

    /**
     * Scope a query to only include activities for a specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include recent activities.
     */
    public function scopeRecent($query, $limit = 20)
    {
        return $query->orderByDesc('created_at')->limit($limit);
    }

    /**
     * Scope a query to filter by type.
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    // ==================== HELPERS ====================

    /**
     * Get the activity type label.
     */
    public function getTypeLabelAttribute(): string
    {
        return match($this->type) {
            'note' => 'Notatka',
            'call' => 'Rozmowa',
            'email' => 'Email',
            'meeting' => 'Spotkanie',
            'task_completed' => 'Zadanie ukończone',
            'stage_changed' => 'Zmiana etapu',
            'deal_created' => 'Nowy deal',
            'deal_won' => 'Deal wygrany',
            'deal_lost' => 'Deal przegrany',
            'contact_created' => 'Kontakt utworzony',
            'status_changed' => 'Zmiana statusu',
            'system' => 'System',
            default => $this->type,
        };
    }

    /**
     * Get the activity type icon.
     */
    public function getTypeIconAttribute(): string
    {
        return match($this->type) {
            'note' => 'file-text',
            'call' => 'phone',
            'email' => 'mail',
            'meeting' => 'users',
            'task_completed' => 'check-circle',
            'stage_changed' => 'arrow-right',
            'deal_created' => 'plus-circle',
            'deal_won' => 'trophy',
            'deal_lost' => 'x-circle',
            'contact_created' => 'user-plus',
            'status_changed' => 'refresh-cw',
            'system' => 'settings',
            default => 'activity',
        };
    }

    /**
     * Get the activity type color.
     */
    public function getTypeColorAttribute(): string
    {
        return match($this->type) {
            'note' => '#6366f1',
            'call' => '#10b981',
            'email' => '#3b82f6',
            'meeting' => '#8b5cf6',
            'task_completed' => '#10b981',
            'stage_changed' => '#f59e0b',
            'deal_created' => '#6366f1',
            'deal_won' => '#10b981',
            'deal_lost' => '#ef4444',
            'contact_created' => '#3b82f6',
            'status_changed' => '#f59e0b',
            'system' => '#6b7280',
            default => '#6b7280',
        };
    }

    /**
     * Get the creator's name.
     */
    public function getCreatorNameAttribute(): string
    {
        return $this->createdBy?->name ?? 'System';
    }
}
