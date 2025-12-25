<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'icon',
        'title',
        'message',
        'data',
        'action_url',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    /**
     * Available notification types
     */
    public const TYPE_INFO = 'info';
    public const TYPE_SUCCESS = 'success';
    public const TYPE_WARNING = 'warning';
    public const TYPE_ERROR = 'error';

    /**
     * Get the user that owns the notification.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to get unread notifications.
     */
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    /**
     * Scope to get read notifications.
     */
    public function scopeRead($query)
    {
        return $query->whereNotNull('read_at');
    }

    /**
     * Scope to get recent notifications (last 30 days).
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Scope to filter by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Mark the notification as read.
     */
    public function markAsRead(): bool
    {
        if ($this->read_at) {
            return true;
        }

        return $this->update(['read_at' => now()]);
    }

    /**
     * Mark the notification as unread.
     */
    public function markAsUnread(): bool
    {
        return $this->update(['read_at' => null]);
    }

    /**
     * Check if notification is read.
     */
    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    /**
     * Check if notification is unread.
     */
    public function isUnread(): bool
    {
        return $this->read_at === null;
    }

    /**
     * Get icon based on notification type.
     */
    public function getIconAttribute($value): string
    {
        if ($value) {
            return $value;
        }

        return match ($this->type) {
            self::TYPE_SUCCESS => 'check-circle',
            self::TYPE_WARNING => 'exclamation-triangle',
            self::TYPE_ERROR => 'x-circle',
            default => 'information-circle',
        };
    }

    /**
     * Get color class based on notification type.
     */
    public function getColorClassAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_SUCCESS => 'text-emerald-500',
            self::TYPE_WARNING => 'text-amber-500',
            self::TYPE_ERROR => 'text-rose-500',
            default => 'text-blue-500',
        };
    }

    /**
     * Get background color class based on notification type.
     */
    public function getBgColorClassAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_SUCCESS => 'bg-emerald-50 dark:bg-emerald-900/20',
            self::TYPE_WARNING => 'bg-amber-50 dark:bg-amber-900/20',
            self::TYPE_ERROR => 'bg-rose-50 dark:bg-rose-900/20',
            default => 'bg-blue-50 dark:bg-blue-900/20',
        };
    }
}
