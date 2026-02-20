<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AiActionPlan extends Model
{
    protected $fillable = [
        'user_id',
        'ai_conversation_id',
        'ai_goal_id',
        'agent_type',
        'intent',
        'title',
        'description',
        'plan_data',
        'work_mode',
        'status',
        'execution_summary',
        'total_steps',
        'completed_steps',
        'failed_steps',
        'approved_at',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'plan_data' => 'array',
        'execution_summary' => 'array',
        'total_steps' => 'integer',
        'completed_steps' => 'integer',
        'failed_steps' => 'integer',
        'approved_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }

    public function goal(): BelongsTo
    {
        return $this->belongsTo(AiGoal::class, 'ai_goal_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(AiActionPlanStep::class)->orderBy('step_order');
    }

    public function pendingApproval(): HasOne
    {
        return $this->hasOne(AiPendingApproval::class)->where('status', 'pending');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(AiPendingApproval::class);
    }

    public function executionLogs(): HasMany
    {
        return $this->hasMany(AiExecutionLog::class);
    }

    /**
     * Check if the plan is ready to execute.
     */
    public function isReadyToExecute(): bool
    {
        return in_array($this->status, ['approved', 'draft']) &&
               ($this->work_mode === 'autonomous' || $this->status === 'approved');
    }

    /**
     * Check if plan needs approval.
     */
    public function needsApproval(): bool
    {
        return $this->work_mode === 'semi_auto' && $this->status === 'draft';
    }

    /**
     * Get progress percentage.
     */
    public function getProgressAttribute(): float
    {
        if ($this->total_steps === 0) return 0;
        return round(($this->completed_steps / $this->total_steps) * 100, 1);
    }

    /**
     * Mark as approved.
     */
    public function approve(): void
    {
        $this->update([
            'status' => 'approved',
            'approved_at' => now(),
        ]);
    }

    /**
     * Mark as started.
     */
    public function markStarted(): void
    {
        $this->update([
            'status' => 'executing',
            'started_at' => now(),
        ]);
    }

    /**
     * Mark as completed.
     */
    public function markCompleted(array $summary = []): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'execution_summary' => $summary,
        ]);

        // Propagate to parent goal
        $this->goal?->updateProgress();
    }

    /**
     * Mark as failed.
     */
    public function markFailed(array $summary = []): void
    {
        $this->update([
            'status' => 'failed',
            'completed_at' => now(),
            'execution_summary' => $summary,
        ]);

        // Propagate to parent goal
        $this->goal?->updateProgress();
    }

    // Scopes
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending_approval');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['draft', 'pending_approval', 'approved', 'executing']);
    }
}
