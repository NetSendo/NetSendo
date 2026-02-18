<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiBrainActivityLog extends Model
{
    protected $fillable = [
        'user_id',
        'event_type',
        'agent_name',
        'status',
        'metadata',
        'duration_ms',
    ];

    protected $casts = [
        'metadata' => 'array',
        'duration_ms' => 'integer',
    ];

    // --- Relationships ---

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // --- Static Helpers ---

    /**
     * Log a Brain activity event.
     */
    public static function logEvent(
        int $userId,
        string $eventType,
        string $status,
        ?string $agentName = null,
        array $metadata = [],
        int $durationMs = 0,
    ): self {
        return static::create([
            'user_id' => $userId,
            'event_type' => $eventType,
            'agent_name' => $agentName,
            'status' => $status,
            'metadata' => $metadata ?: null,
            'duration_ms' => $durationMs,
        ]);
    }

    // --- Scopes ---

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeOfType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }
}
