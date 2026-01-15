<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class FunnelGoalConversion extends Model
{
    use HasFactory;

    // Source constants
    public const SOURCE_FUNNEL = 'funnel';
    public const SOURCE_WEBHOOK = 'webhook';
    public const SOURCE_MANUAL = 'manual';

    protected $fillable = [
        'funnel_id',
        'funnel_step_id',
        'funnel_subscriber_id',
        'subscriber_id',
        'goal_name',
        'goal_type',
        'value',
        'metadata',
        'source',
        'converted_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'value' => 'decimal:2',
        'converted_at' => 'datetime',
    ];

    // =====================================
    // Relationships
    // =====================================

    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(FunnelStep::class, 'funnel_step_id');
    }

    public function funnelSubscriber(): BelongsTo
    {
        return $this->belongsTo(FunnelSubscriber::class);
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

    public function scopeByGoalType($query, string $goalType)
    {
        return $query->where('goal_type', $goalType);
    }

    public function scopeFromSource($query, string $source)
    {
        return $query->where('source', $source);
    }

    public function scopeConvertedBetween($query, $start, $end)
    {
        return $query->whereBetween('converted_at', [$start, $end]);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('converted_at', '>=', now()->subDays($days));
    }

    // =====================================
    // Static methods
    // =====================================

    /**
     * Record a goal conversion.
     */
    public static function recordConversion(
        FunnelSubscriber $enrollment,
        FunnelStep $step,
        float $value = 0,
        array $metadata = [],
        string $source = self::SOURCE_FUNNEL
    ): self {
        return static::create([
            'funnel_id' => $enrollment->funnel_id,
            'funnel_step_id' => $step->id,
            'funnel_subscriber_id' => $enrollment->id,
            'subscriber_id' => $enrollment->subscriber_id,
            'goal_name' => $step->goal_name ?? 'Goal',
            'goal_type' => $step->goal_type ?? 'custom',
            'value' => $value,
            'metadata' => $metadata,
            'source' => $source,
            'converted_at' => now(),
        ]);
    }

    /**
     * Record conversion from webhook/external source.
     */
    public static function recordExternalConversion(
        Funnel $funnel,
        Subscriber $subscriber,
        string $goalType,
        string $goalName,
        float $value = 0,
        array $metadata = []
    ): ?self {
        // Find the subscriber's enrollment
        $enrollment = FunnelSubscriber::where('funnel_id', $funnel->id)
            ->where('subscriber_id', $subscriber->id)
            ->first();

        if (!$enrollment) {
            return null;
        }

        // Find a goal step matching the type (or use current step)
        $goalStep = FunnelStep::where('funnel_id', $funnel->id)
            ->where('type', FunnelStep::TYPE_GOAL)
            ->where('goal_type', $goalType)
            ->first();

        $stepId = $goalStep?->id ?? $enrollment->current_step_id;

        if (!$stepId) {
            return null;
        }

        // Check if already converted for this step
        $existing = static::where('funnel_step_id', $stepId)
            ->where('funnel_subscriber_id', $enrollment->id)
            ->exists();

        if ($existing) {
            return null;
        }

        return static::create([
            'funnel_id' => $funnel->id,
            'funnel_step_id' => $stepId,
            'funnel_subscriber_id' => $enrollment->id,
            'subscriber_id' => $subscriber->id,
            'goal_name' => $goalName,
            'goal_type' => $goalType,
            'value' => $value,
            'metadata' => $metadata,
            'source' => self::SOURCE_WEBHOOK,
            'converted_at' => now(),
        ]);
    }

    /**
     * Get total revenue for a funnel.
     */
    public static function getTotalRevenue(int $funnelId): float
    {
        return static::where('funnel_id', $funnelId)->sum('value');
    }

    /**
     * Get conversion stats for a funnel.
     */
    public static function getStats(int $funnelId): array
    {
        $conversions = static::where('funnel_id', $funnelId)->get();

        $byType = $conversions->groupBy('goal_type')->map(fn($group) => [
            'count' => $group->count(),
            'revenue' => $group->sum('value'),
        ]);

        return [
            'total_conversions' => $conversions->count(),
            'total_revenue' => $conversions->sum('value'),
            'by_type' => $byType,
            'recent' => $conversions->where('converted_at', '>=', now()->subDays(7))->count(),
        ];
    }

    /**
     * Get sources.
     */
    public static function getSources(): array
    {
        return [
            self::SOURCE_FUNNEL => 'Automatycznie (lejek)',
            self::SOURCE_WEBHOOK => 'Webhook zewnętrzny',
            self::SOURCE_MANUAL => 'Ręcznie',
        ];
    }
}
