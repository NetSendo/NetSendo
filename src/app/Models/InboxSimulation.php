<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InboxSimulation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'domain_configuration_id',
        'message_id',
        'subject',
        'content_preview',
        'from_email',
        'inbox_score',
        'predicted_folder',
        'provider_predictions',
        'domain_analysis',
        'content_analysis',
        'issues',
        'recommendations',
        'score_breakdown',
        'is_test',
        'analyzed_at',
    ];

    protected $casts = [
        'inbox_score' => 'integer',
        'provider_predictions' => 'array',
        'domain_analysis' => 'array',
        'content_analysis' => 'array',
        'issues' => 'array',
        'recommendations' => 'array',
        'score_breakdown' => 'array',
        'is_test' => 'boolean',
        'analyzed_at' => 'datetime',
    ];

    /**
     * Folder constants
     */
    public const FOLDER_INBOX = 'inbox';
    public const FOLDER_PROMOTIONS = 'promotions';
    public const FOLDER_SPAM = 'spam';
    public const FOLDER_UNKNOWN = 'unknown';

    /**
     * Score thresholds
     */
    public const SCORE_EXCELLENT = 80;  // 80-100: High delivery rate
    public const SCORE_GOOD = 60;       // 60-79: Likely inbox
    public const SCORE_RISKY = 40;      // 40-59: May go to promotions/spam
    public const SCORE_POOR = 0;        // 0-39: Likely spam

    /**
     * Relationships
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function domainConfiguration()
    {
        return $this->belongsTo(DomainConfiguration::class);
    }

    public function message()
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * Get score category
     */
    public function getScoreCategory(): string
    {
        if ($this->inbox_score >= self::SCORE_EXCELLENT) {
            return 'excellent';
        } elseif ($this->inbox_score >= self::SCORE_GOOD) {
            return 'good';
        } elseif ($this->inbox_score >= self::SCORE_RISKY) {
            return 'risky';
        }
        return 'poor';
    }

    /**
     * Get user-friendly score info
     */
    public function getScoreInfo(): array
    {
        $categories = [
            'excellent' => [
                'color' => 'green',
                'icon' => 'check-circle',
                'label_key' => 'deliverability.score.excellent',
            ],
            'good' => [
                'color' => 'blue',
                'icon' => 'check',
                'label_key' => 'deliverability.score.good',
            ],
            'risky' => [
                'color' => 'yellow',
                'icon' => 'exclamation-triangle',
                'label_key' => 'deliverability.score.risky',
            ],
            'poor' => [
                'color' => 'red',
                'icon' => 'x-circle',
                'label_key' => 'deliverability.score.poor',
            ],
        ];

        return $categories[$this->getScoreCategory()];
    }

    /**
     * Get folder info with icon
     */
    public function getFolderInfo(): array
    {
        $folders = [
            self::FOLDER_INBOX => [
                'color' => 'green',
                'icon' => 'inbox',
                'label_key' => 'deliverability.folder.inbox',
            ],
            self::FOLDER_PROMOTIONS => [
                'color' => 'yellow',
                'icon' => 'tag',
                'label_key' => 'deliverability.folder.promotions',
            ],
            self::FOLDER_SPAM => [
                'color' => 'red',
                'icon' => 'x-circle',
                'label_key' => 'deliverability.folder.spam',
            ],
            self::FOLDER_UNKNOWN => [
                'color' => 'gray',
                'icon' => 'question-mark-circle',
                'label_key' => 'deliverability.folder.unknown',
            ],
        ];

        return $folders[$this->predicted_folder] ?? $folders[self::FOLDER_UNKNOWN];
    }

    /**
     * Get critical issues (high priority)
     */
    public function getCriticalIssues(): array
    {
        return array_filter($this->issues ?? [], function ($issue) {
            return ($issue['severity'] ?? 'low') === 'critical';
        });
    }

    /**
     * Get warning issues (medium priority)
     */
    public function getWarningIssues(): array
    {
        return array_filter($this->issues ?? [], function ($issue) {
            return ($issue['severity'] ?? 'low') === 'warning';
        });
    }

    /**
     * Get top recommendations (first 5)
     */
    public function getTopRecommendations(): array
    {
        return array_slice($this->recommendations ?? [], 0, 5);
    }

    /**
     * Scopes
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeLowScore($query, int $threshold = 60)
    {
        return $query->where('inbox_score', '<', $threshold);
    }

    public function scopeSpamPredicted($query)
    {
        return $query->where('predicted_folder', self::FOLDER_SPAM);
    }
}
