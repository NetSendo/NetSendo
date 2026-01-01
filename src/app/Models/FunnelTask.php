<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FunnelTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'funnel_id',
        'subscriber_id',
        'task_id',
        'metadata',
        'completed_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'completed_at' => 'datetime',
    ];

    // =====================================
    // Relationships
    // =====================================

    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class);
    }

    // =====================================
    // Scopes
    // =====================================

    public function scopeForFunnel($query, int $funnelId)
    {
        return $query->where('funnel_id', $funnelId);
    }

    public function scopeForSubscriber($query, int $subscriberId)
    {
        return $query->where('subscriber_id', $subscriberId);
    }

    public function scopeForTask($query, string $taskId)
    {
        return $query->where('task_id', $taskId);
    }

    // =====================================
    // Static Methods
    // =====================================

    /**
     * Check if a subscriber has completed a specific task.
     */
    public static function hasCompleted(int $funnelId, int $subscriberId, string $taskId): bool
    {
        return static::forFunnel($funnelId)
            ->forSubscriber($subscriberId)
            ->forTask($taskId)
            ->exists();
    }

    /**
     * Mark a task as completed for a subscriber.
     */
    public static function markCompleted(
        int $funnelId,
        int $subscriberId,
        string $taskId,
        array $metadata = []
    ): self {
        return static::updateOrCreate(
            [
                'funnel_id' => $funnelId,
                'subscriber_id' => $subscriberId,
                'task_id' => $taskId,
            ],
            [
                'metadata' => $metadata,
                'completed_at' => now(),
            ]
        );
    }

    /**
     * Get all completed tasks for a subscriber in a funnel.
     */
    public static function getCompletedTasks(int $funnelId, int $subscriberId): array
    {
        return static::forFunnel($funnelId)
            ->forSubscriber($subscriberId)
            ->pluck('task_id')
            ->toArray();
    }
}
