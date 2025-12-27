<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'webhook_id',
        'event',
        'url',
        'status',
        'response_code',
        'response_body',
        'error_message',
        'duration_ms',
        'payload',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Get the user that owns this log
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the webhook this log belongs to
     */
    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }

    /**
     * Scope to get successful webhook calls
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    /**
     * Scope to get failed webhook calls
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope to filter by event type
     */
    public function scopeForEvent($query, string $event)
    {
        return $query->where('event', $event);
    }

    /**
     * Get recent logs
     */
    public static function getRecent(int $limit = 100, ?int $userId = null)
    {
        $query = static::orderBy('created_at', 'desc');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->limit($limit)->get();
    }

    /**
     * Clean up old logs
     */
    public static function cleanupOld(int $days = 30): int
    {
        return static::where('created_at', '<', now()->subDays($days))->delete();
    }

    /**
     * Get stats for last 24 hours
     */
    public static function getLast24HoursStats(?int $userId = null): array
    {
        $query = static::where('created_at', '>=', now()->subDay());

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $logs = $query->get();

        return [
            'total' => $logs->count(),
            'successful' => $logs->where('status', 'success')->count(),
            'failed' => $logs->where('status', 'failed')->count(),
            'avg_duration_ms' => round($logs->avg('duration_ms') ?? 0),
        ];
    }
}
