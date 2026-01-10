<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CampaignAuditIssue extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_audit_id',
        'severity',
        'category',
        'issue_key',
        'message',
        'recommendation',
        'impact_score',
        'affected_type',
        'affected_id',
        'context',
        'is_fixable',
        'is_fixed',
        'fixed_at',
    ];

    protected $casts = [
        'context' => 'array',
        'impact_score' => 'decimal:2',
        'is_fixable' => 'boolean',
        'is_fixed' => 'boolean',
        'fixed_at' => 'datetime',
    ];

    // Severity constants
    const SEVERITY_CRITICAL = 'critical';
    const SEVERITY_WARNING = 'warning';
    const SEVERITY_INFO = 'info';

    // Category constants
    const CATEGORY_FREQUENCY = 'frequency';
    const CATEGORY_CONTENT = 'content';
    const CATEGORY_TIMING = 'timing';
    const CATEGORY_SEGMENTATION = 'segmentation';
    const CATEGORY_DELIVERABILITY = 'deliverability';
    const CATEGORY_REVENUE = 'revenue';
    const CATEGORY_AUTOMATION = 'automation';
    const CATEGORY_GROWTH = 'growth';

    // Issue key constants (for translations)
    const ISSUE_OVER_MAILING = 'over_mailing';
    const ISSUE_SPAM_CONTENT = 'spam_content';
    const ISSUE_NO_FOLLOW_UP = 'no_follow_up';
    const ISSUE_STALE_LIST = 'stale_list';
    const ISSUE_POOR_TIMING = 'poor_timing';
    const ISSUE_NO_SEGMENTATION = 'no_segmentation';
    const ISSUE_SMS_MISSING = 'sms_missing';
    const ISSUE_LOW_OPEN_RATE = 'low_open_rate';
    const ISSUE_HIGH_UNSUBSCRIBE = 'high_unsubscribe';
    const ISSUE_INACTIVE_AUTOMATION = 'inactive_automation';
    const ISSUE_NO_AUTOMATION = 'no_automation';
    const ISSUE_DUPLICATE_CONTENT = 'duplicate_content';
    const ISSUE_MISSING_PREHEADER = 'missing_preheader';
    const ISSUE_LONG_SUBJECT = 'long_subject';
    const ISSUE_NO_PERSONALIZATION = 'no_personalization';
    const ISSUE_LOW_SUBSCRIBER_COUNT = 'low_subscriber_count';

    // Severity weights for score calculation
    const SEVERITY_WEIGHTS = [
        self::SEVERITY_CRITICAL => 20,
        self::SEVERITY_WARNING => 8,
        self::SEVERITY_INFO => 2,
    ];

    // Category labels (for frontend)
    const CATEGORY_LABELS = [
        self::CATEGORY_FREQUENCY => 'Sending Frequency',
        self::CATEGORY_CONTENT => 'Content Quality',
        self::CATEGORY_TIMING => 'Send Timing',
        self::CATEGORY_SEGMENTATION => 'Audience Segmentation',
        self::CATEGORY_DELIVERABILITY => 'Deliverability',
        self::CATEGORY_REVENUE => 'Revenue Impact',
        self::CATEGORY_AUTOMATION => 'Automation',
        self::CATEGORY_GROWTH => 'List Growth',
    ];

    // Category icons (for frontend)
    const CATEGORY_ICONS = [
        self::CATEGORY_FREQUENCY => 'clock',
        self::CATEGORY_CONTENT => 'document-text',
        self::CATEGORY_TIMING => 'calendar',
        self::CATEGORY_SEGMENTATION => 'user-group',
        self::CATEGORY_DELIVERABILITY => 'shield-check',
        self::CATEGORY_REVENUE => 'currency-dollar',
        self::CATEGORY_AUTOMATION => 'cog',
        self::CATEGORY_GROWTH => 'chart-bar',
    ];

    /**
     * Relationships
     */
    public function audit(): BelongsTo
    {
        return $this->belongsTo(CampaignAudit::class, 'campaign_audit_id');
    }

    /**
     * Get the affected model (polymorphic-like behavior)
     */
    public function getAffectedModelAttribute()
    {
        if (!$this->affected_type || !$this->affected_id) {
            return null;
        }

        return match ($this->affected_type) {
            'message' => Message::find($this->affected_id),
            'contact_list' => ContactList::find($this->affected_id),
            'automation' => AutomationRule::find($this->affected_id),
            default => null,
        };
    }

    /**
     * Scopes
     */
    public function scopeBySeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeCritical($query)
    {
        return $query->where('severity', self::SEVERITY_CRITICAL);
    }

    public function scopeWarnings($query)
    {
        return $query->where('severity', self::SEVERITY_WARNING);
    }

    public function scopeFixable($query)
    {
        return $query->where('is_fixable', true)->where('is_fixed', false);
    }

    public function scopeUnfixed($query)
    {
        return $query->where('is_fixed', false);
    }

    /**
     * Get severity badge color
     */
    public function getSeverityColorAttribute(): string
    {
        return match ($this->severity) {
            self::SEVERITY_CRITICAL => 'red',
            self::SEVERITY_WARNING => 'amber',
            self::SEVERITY_INFO => 'blue',
            default => 'gray',
        };
    }

    /**
     * Get category label
     */
    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORY_LABELS[$this->category] ?? $this->category;
    }

    /**
     * Get category icon
     */
    public function getCategoryIconAttribute(): string
    {
        return self::CATEGORY_ICONS[$this->category] ?? 'exclamation-circle';
    }

    /**
     * Mark issue as fixed
     */
    public function markAsFixed(): void
    {
        $this->update([
            'is_fixed' => true,
            'fixed_at' => now(),
        ]);
    }

    /**
     * Get score impact of this issue
     */
    public function getScoreImpactAttribute(): int
    {
        return self::SEVERITY_WEIGHTS[$this->severity] ?? 0;
    }

    /**
     * Get all available categories
     */
    public static function getCategories(): array
    {
        return array_keys(self::CATEGORY_LABELS);
    }

    /**
     * Get all available severities
     */
    public static function getSeverities(): array
    {
        return [
            self::SEVERITY_CRITICAL,
            self::SEVERITY_WARNING,
            self::SEVERITY_INFO,
        ];
    }
}
