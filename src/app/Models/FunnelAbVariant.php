<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FunnelAbVariant extends Model
{
    use HasFactory;

    protected $table = 'funnel_ab_variants';

    protected $fillable = [
        'ab_test_id',
        'name',
        'weight',
        'next_step_id',
        'enrollments',
        'conversions',
        'opens',
        'clicks',
        'revenue',
        'metadata',
    ];

    protected $casts = [
        'weight' => 'integer',
        'enrollments' => 'integer',
        'conversions' => 'integer',
        'opens' => 'integer',
        'clicks' => 'integer',
        'revenue' => 'decimal:2',
        'metadata' => 'array',
    ];

    // =====================================
    // Relationships
    // =====================================

    public function abTest(): BelongsTo
    {
        return $this->belongsTo(FunnelAbTest::class, 'ab_test_id');
    }

    public function nextStep(): BelongsTo
    {
        return $this->belongsTo(FunnelStep::class, 'next_step_id');
    }

    public function variantEnrollments(): HasMany
    {
        return $this->hasMany(FunnelAbEnrollment::class, 'variant_id');
    }

    // =====================================
    // Rate calculations
    // =====================================

    public function getConversionRate(): float
    {
        if ($this->enrollments === 0) return 0;
        return round(($this->conversions / $this->enrollments) * 100, 2);
    }

    public function getOpenRate(): float
    {
        if ($this->enrollments === 0) return 0;
        return round(($this->opens / $this->enrollments) * 100, 2);
    }

    public function getClickRate(): float
    {
        if ($this->enrollments === 0) return 0;
        return round(($this->clicks / $this->enrollments) * 100, 2);
    }

    public function getRevenuePerEnrollment(): float
    {
        if ($this->enrollments === 0) return 0;
        return round($this->revenue / $this->enrollments, 2);
    }

    // =====================================
    // Counter methods
    // =====================================

    public function recordEnrollment(): self
    {
        $this->increment('enrollments');
        return $this;
    }

    public function recordConversion(float $value = 0): self
    {
        $this->increment('conversions');
        if ($value > 0) {
            $this->increment('revenue', $value);
        }
        return $this;
    }

    public function recordOpen(): self
    {
        $this->increment('opens');
        return $this;
    }

    public function recordClick(): self
    {
        $this->increment('clicks');
        return $this;
    }

    // =====================================
    // Statistics
    // =====================================

    /**
     * Get percentage of total enrollments this variant has.
     */
    public function getEnrollmentShare(): float
    {
        $totalEnrollments = $this->abTest->getTotalEnrollments();
        if ($totalEnrollments === 0) return 0;
        return round(($this->enrollments / $totalEnrollments) * 100, 2);
    }

    /**
     * Check if this variant is the winner.
     */
    public function isWinner(): bool
    {
        return $this->abTest->winner_variant_id === $this->id;
    }

    /**
     * Get formatted stats array.
     */
    public function getStatsArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'weight' => $this->weight,
            'enrollments' => $this->enrollments,
            'conversions' => $this->conversions,
            'opens' => $this->opens,
            'clicks' => $this->clicks,
            'revenue' => $this->revenue,
            'conversion_rate' => $this->getConversionRate(),
            'open_rate' => $this->getOpenRate(),
            'click_rate' => $this->getClickRate(),
            'revenue_per_enrollment' => $this->getRevenuePerEnrollment(),
            'is_winner' => $this->isWinner(),
        ];
    }
}
