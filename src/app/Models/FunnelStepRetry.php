<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FunnelStepRetry extends Model
{
    use HasFactory;

    protected $fillable = [
        'funnel_subscriber_id',
        'funnel_step_id',
        'attempt_number',
        'sent_at',
        'condition_met_at',
    ];

    protected $casts = [
        'attempt_number' => 'integer',
        'sent_at' => 'datetime',
        'condition_met_at' => 'datetime',
    ];

    // =====================================
    // Relationships
    // =====================================

    public function funnelSubscriber(): BelongsTo
    {
        return $this->belongsTo(FunnelSubscriber::class);
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(FunnelStep::class, 'funnel_step_id');
    }

    // =====================================
    // Scopes
    // =====================================

    public function scopeForSubscriber($query, int $subscriberId)
    {
        return $query->where('funnel_subscriber_id', $subscriberId);
    }

    public function scopeForStep($query, int $stepId)
    {
        return $query->where('funnel_step_id', $stepId);
    }

    public function scopePending($query)
    {
        return $query->whereNull('condition_met_at');
    }

    public function scopeCompleted($query)
    {
        return $query->whereNotNull('condition_met_at');
    }

    // =====================================
    // Methods
    // =====================================

    public function isPending(): bool
    {
        return is_null($this->condition_met_at);
    }

    public function isCompleted(): bool
    {
        return !is_null($this->condition_met_at);
    }

    public function markConditionMet(): self
    {
        $this->condition_met_at = now();
        $this->save();

        return $this;
    }

    /**
     * Get the latest retry attempt for a subscriber/step combination.
     */
    public static function getLatestAttempt(int $funnelSubscriberId, int $stepId): ?self
    {
        return static::forSubscriber($funnelSubscriberId)
            ->forStep($stepId)
            ->orderBy('attempt_number', 'desc')
            ->first();
    }

    /**
     * Get the retry count for a subscriber/step combination.
     */
    public static function getAttemptCount(int $funnelSubscriberId, int $stepId): int
    {
        return static::forSubscriber($funnelSubscriberId)
            ->forStep($stepId)
            ->count();
    }

    /**
     * Create a new retry attempt.
     */
    public static function createAttempt(int $funnelSubscriberId, int $stepId): self
    {
        $currentCount = static::getAttemptCount($funnelSubscriberId, $stepId);

        return static::create([
            'funnel_subscriber_id' => $funnelSubscriberId,
            'funnel_step_id' => $stepId,
            'attempt_number' => $currentCount + 1,
            'sent_at' => now(),
        ]);
    }
}
