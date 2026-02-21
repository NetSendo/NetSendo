<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiCampaignCalendar extends Model
{
    protected $table = 'ai_campaign_calendar';

    protected $fillable = [
        'user_id',
        'week_start',
        'planned_date',
        'campaign_type',
        'target_audience',
        'topic',
        'description',
        'status',
        'ai_goal_id',
        'metadata',
    ];

    protected $casts = [
        'week_start' => 'date',
        'planned_date' => 'date',
        'metadata' => 'array',
    ];

    // --- Scopes ---

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('planned_date', '>=', now()->toDateString())
            ->whereIn('status', ['draft', 'approved']);
    }

    public function scopeForWeek($query, $weekStart)
    {
        return $query->where('week_start', $weekStart);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    // --- Relations ---

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function goal(): BelongsTo
    {
        return $this->belongsTo(AiGoal::class, 'ai_goal_id');
    }

    // --- Helpers ---

    public function markExecuted(): void
    {
        $this->update(['status' => 'executed']);
    }

    public function markSkipped(): void
    {
        $this->update(['status' => 'skipped']);
    }

    public function markApproved(): void
    {
        $this->update(['status' => 'approved']);
    }
}
