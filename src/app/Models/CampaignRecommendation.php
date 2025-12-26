<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignRecommendation extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_audit_id',
        'type',
        'priority',
        'title',
        'description',
        'expected_impact',
        'effort_level',
        'category',
        'action_steps',
        'context',
        'is_applied',
        'applied_at',
        'result_impact',
    ];

    protected $casts = [
        'action_steps' => 'array',
        'context' => 'array',
        'expected_impact' => 'decimal:2',
        'result_impact' => 'decimal:2',
        'priority' => 'integer',
        'is_applied' => 'boolean',
        'applied_at' => 'datetime',
    ];

    // Type constants
    const TYPE_QUICK_WIN = 'quick_win';
    const TYPE_STRATEGIC = 'strategic';
    const TYPE_GROWTH = 'growth';

    // Effort level constants
    const EFFORT_LOW = 'low';
    const EFFORT_MEDIUM = 'medium';
    const EFFORT_HIGH = 'high';

    // Type labels for frontend
    const TYPE_LABELS = [
        self::TYPE_QUICK_WIN => 'Quick Win',
        self::TYPE_STRATEGIC => 'Strategic',
        self::TYPE_GROWTH => 'Growth',
    ];

    // Effort labels for frontend
    const EFFORT_LABELS = [
        self::EFFORT_LOW => 'Low',
        self::EFFORT_MEDIUM => 'Medium',
        self::EFFORT_HIGH => 'High',
    ];

    // Effort colors for frontend
    const EFFORT_COLORS = [
        self::EFFORT_LOW => 'green',
        self::EFFORT_MEDIUM => 'amber',
        self::EFFORT_HIGH => 'red',
    ];

    // Type colors for frontend
    const TYPE_COLORS = [
        self::TYPE_QUICK_WIN => 'emerald',
        self::TYPE_STRATEGIC => 'blue',
        self::TYPE_GROWTH => 'purple',
    ];

    /**
     * Relationships
     */
    public function audit(): BelongsTo
    {
        return $this->belongsTo(CampaignAudit::class, 'campaign_audit_id');
    }

    /**
     * Scopes
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeQuickWins($query)
    {
        return $query->where('type', self::TYPE_QUICK_WIN);
    }

    public function scopeStrategic($query)
    {
        return $query->where('type', self::TYPE_STRATEGIC);
    }

    public function scopeGrowth($query)
    {
        return $query->where('type', self::TYPE_GROWTH);
    }

    public function scopeApplied($query)
    {
        return $query->where('is_applied', true);
    }

    public function scopeNotApplied($query)
    {
        return $query->where('is_applied', false);
    }

    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'desc');
    }

    /**
     * Accessors
     */
    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    public function getEffortLabelAttribute(): string
    {
        return self::EFFORT_LABELS[$this->effort_level] ?? $this->effort_level;
    }

    public function getTypeColorAttribute(): string
    {
        return self::TYPE_COLORS[$this->type] ?? 'gray';
    }

    public function getEffortColorAttribute(): string
    {
        return self::EFFORT_COLORS[$this->effort_level] ?? 'gray';
    }

    /**
     * Mark recommendation as applied
     */
    public function markAsApplied(): void
    {
        $this->update([
            'is_applied' => true,
            'applied_at' => now(),
        ]);
    }

    /**
     * Record the measured impact after applying
     */
    public function recordImpact(float $impact): void
    {
        $this->update([
            'result_impact' => $impact,
        ]);
    }

    /**
     * Get all available types
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_QUICK_WIN,
            self::TYPE_STRATEGIC,
            self::TYPE_GROWTH,
        ];
    }

    /**
     * Get all available effort levels
     */
    public static function getEffortLevels(): array
    {
        return [
            self::EFFORT_LOW,
            self::EFFORT_MEDIUM,
            self::EFFORT_HIGH,
        ];
    }
}
