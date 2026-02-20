<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AiGoal extends Model
{
    protected $fillable = [
        'user_id',
        'ai_conversation_id',
        'title',
        'description',
        'status',
        'priority',
        'success_criteria',
        'context',
        'total_plans',
        'completed_plans',
        'failed_plans',
        'progress_percent',
        'target_date',
        'completed_at',
    ];

    protected $casts = [
        'success_criteria' => 'array',
        'context' => 'array',
        'total_plans' => 'integer',
        'completed_plans' => 'integer',
        'failed_plans' => 'integer',
        'progress_percent' => 'integer',
        'target_date' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // ── Relations ──

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }

    public function plans(): HasMany
    {
        return $this->hasMany(AiActionPlan::class, 'ai_goal_id')->orderBy('created_at');
    }

    public function activePlan(): HasOne
    {
        return $this->hasOne(AiActionPlan::class, 'ai_goal_id')
                    ->whereIn('status', ['draft', 'approved', 'executing', 'pending_approval'])
                    ->latest();
    }

    // ── Progress ──

    /**
     * Recalculate progress based on linked plans.
     */
    public function updateProgress(): void
    {
        $plans = $this->plans()->get();
        $total = $plans->count();
        $completed = $plans->where('status', 'completed')->count();
        $failed = $plans->where('status', 'failed')->count();

        $progress = $total > 0 ? (int) round(($completed / $total) * 100) : 0;

        $this->update([
            'total_plans' => $total,
            'completed_plans' => $completed,
            'failed_plans' => $failed,
            'progress_percent' => $progress,
        ]);

        // Auto-complete goal when all plans are done
        if ($total > 0 && $completed === $total) {
            $this->complete();
        }
    }

    // ── Status Management ──

    public function pause(): void
    {
        $this->update(['status' => 'paused']);
    }

    public function resume(): void
    {
        $this->update(['status' => 'active']);
    }

    public function complete(): void
    {
        $this->update([
            'status' => 'completed',
            'progress_percent' => 100,
            'completed_at' => now(),
        ]);
    }

    public function fail(): void
    {
        $this->update([
            'status' => 'failed',
            'completed_at' => now(),
        ]);
    }

    public function cancel(): void
    {
        $this->update([
            'status' => 'cancelled',
            'completed_at' => now(),
        ]);
    }

    /**
     * Add context data to the goal (accumulates over time).
     */
    public function addContext(string $key, mixed $value): void
    {
        $context = $this->context ?? [];
        $context[$key] = $value;
        $this->update(['context' => $context]);
    }

    // ── Scopes ──

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeWithPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeNotCompleted($query)
    {
        return $query->whereNotIn('status', ['completed', 'cancelled', 'failed']);
    }
}
