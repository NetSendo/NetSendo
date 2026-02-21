<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiPerformanceSnapshot extends Model
{
    protected $fillable = [
        'user_id',
        'ai_action_plan_id',
        'message_id',
        'campaign_title',
        'agent_type',
        'sent_count',
        'open_rate',
        'click_rate',
        'unsubscribe_rate',
        'bounce_rate',
        'benchmark_comparison',
        'lessons_learned',
        'what_worked',
        'what_to_improve',
        'review_status',
        'campaign_sent_at',
        'captured_at',
    ];

    protected $casts = [
        'benchmark_comparison' => 'array',
        'what_worked' => 'array',
        'what_to_improve' => 'array',
        'sent_count' => 'integer',
        'open_rate' => 'decimal:2',
        'click_rate' => 'decimal:2',
        'unsubscribe_rate' => 'decimal:2',
        'bounce_rate' => 'decimal:2',
        'campaign_sent_at' => 'datetime',
        'captured_at' => 'datetime',
    ];

    // ── Relations ──

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(AiActionPlan::class, 'ai_action_plan_id');
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    // ── Scopes ──

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopePending($query)
    {
        return $query->where('review_status', 'pending');
    }

    public function scopeReviewed($query)
    {
        return $query->where('review_status', 'reviewed');
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('captured_at', '>=', now()->subDays($days));
    }

    // ── Helpers ──

    /**
     * Get the user's average benchmarks from past snapshots.
     */
    public static function getUserBenchmarks(int $userId, int $minSnapshots = 3): ?array
    {
        $snapshots = static::forUser($userId)
            ->where('sent_count', '>', 0)
            ->latest('captured_at')
            ->limit(20)
            ->get();

        if ($snapshots->count() < $minSnapshots) {
            return null;
        }

        return [
            'avg_open_rate' => round($snapshots->avg('open_rate'), 2),
            'avg_click_rate' => round($snapshots->avg('click_rate'), 2),
            'avg_unsubscribe_rate' => round($snapshots->avg('unsubscribe_rate'), 2),
            'avg_bounce_rate' => round($snapshots->avg('bounce_rate'), 2),
            'total_campaigns' => $snapshots->count(),
        ];
    }

    /**
     * Check if this campaign performed above average.
     */
    public function isAboveAverage(): bool
    {
        if (!$this->benchmark_comparison) {
            return false;
        }

        $aboveCount = collect($this->benchmark_comparison)
            ->filter(fn($v) => $v === 'above')
            ->count();

        return $aboveCount >= 2;
    }

    /**
     * Format a compact summary for display.
     */
    public function getCompactSummary(): string
    {
        $icon = $this->isAboveAverage() ? '🟢' : '🟡';
        return "{$icon} {$this->campaign_title}: OR {$this->open_rate}%, CTR {$this->click_rate}%, sent: {$this->sent_count}";
    }
}
