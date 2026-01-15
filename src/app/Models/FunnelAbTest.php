<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FunnelAbTest extends Model
{
    use HasFactory;

    protected $table = 'funnel_ab_tests';

    // Status constants
    public const STATUS_DRAFT = 'draft';
    public const STATUS_RUNNING = 'running';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_COMPLETED = 'completed';

    // Winning metric constants
    public const METRIC_CONVERSION_RATE = 'conversion_rate';
    public const METRIC_CLICK_RATE = 'click_rate';
    public const METRIC_OPEN_RATE = 'open_rate';
    public const METRIC_GOAL_COMPLETION = 'goal_completion';

    protected $fillable = [
        'funnel_id',
        'split_step_id',
        'name',
        'description',
        'status',
        'sample_size',
        'confidence_level',
        'winning_metric',
        'winner_variant_id',
        'started_at',
        'winner_declared_at',
        'settings',
    ];

    protected $casts = [
        'sample_size' => 'integer',
        'confidence_level' => 'integer',
        'settings' => 'array',
        'started_at' => 'datetime',
        'winner_declared_at' => 'datetime',
    ];

    // =====================================
    // Relationships
    // =====================================

    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }

    public function splitStep(): BelongsTo
    {
        return $this->belongsTo(FunnelStep::class, 'split_step_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(FunnelAbVariant::class, 'ab_test_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(FunnelAbEnrollment::class, 'ab_test_id');
    }

    public function winnerVariant(): BelongsTo
    {
        return $this->belongsTo(FunnelAbVariant::class, 'winner_variant_id');
    }

    // =====================================
    // Scopes
    // =====================================

    public function scopeRunning($query)
    {
        return $query->where('status', self::STATUS_RUNNING);
    }

    public function scopeForFunnel($query, int $funnelId)
    {
        return $query->where('funnel_id', $funnelId);
    }

    // =====================================
    // Status helpers
    // =====================================

    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function start(): self
    {
        $this->status = self::STATUS_RUNNING;
        $this->started_at = now();
        $this->save();
        return $this;
    }

    public function pause(): self
    {
        $this->status = self::STATUS_PAUSED;
        $this->save();
        return $this;
    }

    public function complete(FunnelAbVariant $winner): self
    {
        $this->status = self::STATUS_COMPLETED;
        $this->winner_variant_id = $winner->id;
        $this->winner_declared_at = now();
        $this->save();
        return $this;
    }

    // =====================================
    // Statistics
    // =====================================

    public function getTotalEnrollments(): int
    {
        return $this->variants->sum('enrollments');
    }

    public function getTotalConversions(): int
    {
        return $this->variants->sum('conversions');
    }

    public function getOverallConversionRate(): float
    {
        $enrollments = $this->getTotalEnrollments();
        if ($enrollments === 0) return 0;
        return round(($this->getTotalConversions() / $enrollments) * 100, 2);
    }

    /**
     * Check if sample size has been reached.
     */
    public function hasSufficientSample(): bool
    {
        if (!$this->sample_size) return true;
        return $this->getTotalEnrollments() >= $this->sample_size;
    }

    /**
     * Get the leading variant based on the winning metric.
     */
    public function getLeadingVariant(): ?FunnelAbVariant
    {
        if ($this->variants->isEmpty()) return null;

        return $this->variants->sortByDesc(function ($variant) {
            return match ($this->winning_metric) {
                self::METRIC_CONVERSION_RATE => $variant->getConversionRate(),
                self::METRIC_CLICK_RATE => $variant->getClickRate(),
                self::METRIC_OPEN_RATE => $variant->getOpenRate(),
                default => $variant->getConversionRate(),
            };
        })->first();
    }

    // =====================================
    // Static helpers
    // =====================================

    public static function getStatuses(): array
    {
        return [
            self::STATUS_DRAFT => 'Wersja robocza',
            self::STATUS_RUNNING => 'Aktywny',
            self::STATUS_PAUSED => 'Wstrzymany',
            self::STATUS_COMPLETED => 'Zakończony',
        ];
    }

    public static function getWinningMetrics(): array
    {
        return [
            self::METRIC_CONVERSION_RATE => 'Współczynnik konwersji',
            self::METRIC_CLICK_RATE => 'Współczynnik klikalności',
            self::METRIC_OPEN_RATE => 'Współczynnik otwarć',
            self::METRIC_GOAL_COMPLETION => 'Realizacja celu',
        ];
    }
}
