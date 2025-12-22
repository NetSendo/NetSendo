<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailReadSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'message_id',
        'subscriber_id',
        'session_id',
        'started_at',
        'ended_at',
        'read_time_seconds',
        'is_active',
        'ip_address',
        'user_agent',
        'visibility_events',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'is_active' => 'boolean',
        'visibility_events' => 'array',
    ];

    // Relationships

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class);
    }

    // Accessors

    /**
     * Get the estimated read time in seconds.
     * Uses recorded read_time_seconds if available, otherwise calculates from timestamps.
     */
    public function getEstimatedReadTimeAttribute(): int
    {
        if ($this->read_time_seconds) {
            return $this->read_time_seconds;
        }

        if ($this->ended_at) {
            return $this->started_at->diffInSeconds($this->ended_at);
        }

        // If session is still active, calculate from start to now
        return $this->started_at->diffInSeconds(now());
    }

    /**
     * Get the read time formatted as human-readable string.
     */
    public function getReadTimeFormattedAttribute(): string
    {
        $seconds = $this->estimated_read_time;

        if ($seconds < 60) {
            return $seconds . ' sek.';
        }

        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;

        if ($minutes < 60) {
            return $minutes . ' min ' . $remainingSeconds . ' sek.';
        }

        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        return $hours . ' godz. ' . $remainingMinutes . ' min';
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeCompleted($query)
    {
        return $query->where('is_active', false)->whereNotNull('ended_at');
    }

    public function scopeForMessage($query, int $messageId)
    {
        return $query->where('message_id', $messageId);
    }

    public function scopeForSubscriber($query, int $subscriberId)
    {
        return $query->where('subscriber_id', $subscriberId);
    }

    // Helper methods

    /**
     * End the reading session and calculate total read time.
     */
    public function endSession(?int $frontendReadTime = null): void
    {
        $this->ended_at = now();
        $this->is_active = false;

        // Use frontend-reported time if available, otherwise calculate
        if ($frontendReadTime !== null) {
            $this->read_time_seconds = $frontendReadTime;
        } else {
            $this->read_time_seconds = $this->started_at->diffInSeconds($this->ended_at);
        }

        $this->save();
    }

    /**
     * Add a visibility event (tab focus/blur).
     */
    public function addVisibilityEvent(string $type, int $timestamp): void
    {
        $events = $this->visibility_events ?? [];
        $events[] = [
            'type' => $type, // 'visible' or 'hidden'
            'timestamp' => $timestamp,
            'recorded_at' => now()->toIso8601String(),
        ];
        $this->visibility_events = $events;
        $this->save();
    }

    /**
     * Calculate actual reading time from visibility events.
     * Only counts time when the email was visible.
     */
    public function calculateActiveReadTime(): int
    {
        $events = $this->visibility_events ?? [];

        if (empty($events)) {
            return $this->estimated_read_time;
        }

        $totalVisibleTime = 0;
        $lastVisibleTime = null;

        foreach ($events as $event) {
            if ($event['type'] === 'visible') {
                $lastVisibleTime = $event['timestamp'];
            } elseif ($event['type'] === 'hidden' && $lastVisibleTime !== null) {
                $totalVisibleTime += ($event['timestamp'] - $lastVisibleTime);
                $lastVisibleTime = null;
            }
        }

        // If still visible at the end, add remaining time
        if ($lastVisibleTime !== null && $this->ended_at) {
            $endTimestamp = $this->ended_at->timestamp * 1000; // Convert to ms if events are in ms
            $totalVisibleTime += ($endTimestamp - $lastVisibleTime);
        }

        // Convert from milliseconds to seconds
        return (int) ($totalVisibleTime / 1000);
    }

    // Static helpers

    /**
     * Get average read time for a message in seconds.
     */
    public static function averageReadTimeForMessage(int $messageId): ?float
    {
        return static::completed()
            ->forMessage($messageId)
            ->avg('read_time_seconds');
    }

    /**
     * Get read time statistics for a message.
     */
    public static function getReadTimeStats(int $messageId): array
    {
        $query = static::completed()->forMessage($messageId);

        return [
            'total_sessions' => $query->count(),
            'average_seconds' => round($query->avg('read_time_seconds') ?? 0),
            'median_seconds' => static::calculateMedian($messageId),
            'max_seconds' => $query->max('read_time_seconds') ?? 0,
            'min_seconds' => $query->min('read_time_seconds') ?? 0,
        ];
    }

    /**
     * Calculate median read time for a message.
     */
    protected static function calculateMedian(int $messageId): int
    {
        $times = static::completed()
            ->forMessage($messageId)
            ->pluck('read_time_seconds')
            ->sort()
            ->values();

        $count = $times->count();

        if ($count === 0) {
            return 0;
        }

        $middle = floor($count / 2);

        if ($count % 2 === 0) {
            return (int) (($times[$middle - 1] + $times[$middle]) / 2);
        }

        return (int) $times[$middle];
    }
}
