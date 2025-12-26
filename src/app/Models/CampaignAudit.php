<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CampaignAudit extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'overall_score',
        'status',
        'audit_type',
        'summary',
        'ai_summary',
        'metrics',
        'critical_count',
        'warning_count',
        'info_count',
        'estimated_revenue_loss',
        'messages_analyzed',
        'lists_analyzed',
        'automations_analyzed',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'summary' => 'array',
        'metrics' => 'array',
        'overall_score' => 'integer',
        'critical_count' => 'integer',
        'warning_count' => 'integer',
        'info_count' => 'integer',
        'estimated_revenue_loss' => 'decimal:2',
        'messages_analyzed' => 'integer',
        'lists_analyzed' => 'integer',
        'automations_analyzed' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_RUNNING = 'running';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    // Audit type constants
    const TYPE_QUICK = 'quick';
    const TYPE_FULL = 'full';

    // Score thresholds
    const SCORE_EXCELLENT = 80;
    const SCORE_GOOD = 60;
    const SCORE_NEEDS_ATTENTION = 40;

    /**
     * Relationships
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(CampaignAuditIssue::class);
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(CampaignRecommendation::class);
    }

    /**
     * Scopes
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeRecent($query, $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    /**
     * Get the latest completed audit for a user
     */
    public static function latestFor(int $userId): ?self
    {
        return static::where('user_id', $userId)
            ->where('status', self::STATUS_COMPLETED)
            ->latest()
            ->first();
    }

    /**
     * Check if user has a recent audit (within 24 hours)
     */
    public static function hasRecentFor(int $userId, int $hours = 24): bool
    {
        return static::where('user_id', $userId)
            ->where('status', self::STATUS_COMPLETED)
            ->where('created_at', '>=', now()->subHours($hours))
            ->exists();
    }

    /**
     * Get score label for display
     */
    public function getScoreLabelAttribute(): string
    {
        if ($this->overall_score >= self::SCORE_EXCELLENT) {
            return 'excellent';
        }
        if ($this->overall_score >= self::SCORE_GOOD) {
            return 'good';
        }
        if ($this->overall_score >= self::SCORE_NEEDS_ATTENTION) {
            return 'needs_attention';
        }
        return 'critical';
    }

    /**
     * Get total issues count
     */
    public function getTotalIssuesAttribute(): int
    {
        return $this->critical_count + $this->warning_count + $this->info_count;
    }

    /**
     * Get fixable issues count
     */
    public function getFixableIssuesCountAttribute(): int
    {
        return $this->issues()->where('is_fixable', true)->where('is_fixed', false)->count();
    }

    /**
     * Check if audit is still valid (not expired)
     */
    public function isValid(int $hours = 24): bool
    {
        return $this->status === self::STATUS_COMPLETED
            && $this->created_at->gte(now()->subHours($hours));
    }

    /**
     * Get issues grouped by category
     */
    public function getIssuesByCategory(): array
    {
        return $this->issues()
            ->get()
            ->groupBy('category')
            ->toArray();
    }

    /**
     * Get issues grouped by severity
     */
    public function getIssuesBySeverity(): array
    {
        return $this->issues()
            ->get()
            ->groupBy('severity')
            ->toArray();
    }
}
