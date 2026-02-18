<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiActionPlanStep extends Model
{
    protected $fillable = [
        'ai_action_plan_id',
        'step_order',
        'action_type',
        'title',
        'description',
        'config',
        'status',
        'result',
        'error_message',
        'retry_count',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'config' => 'array',
        'result' => 'array',
        'step_order' => 'integer',
        'retry_count' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(AiActionPlan::class, 'ai_action_plan_id');
    }

    /**
     * Mark step as executing.
     */
    public function markExecuting(): void
    {
        $this->update([
            'status' => 'executing',
            'started_at' => now(),
        ]);
    }

    /**
     * Mark step as completed with result.
     */
    public function markCompleted(array $result = []): void
    {
        $this->update([
            'status' => 'completed',
            'result' => $result,
            'completed_at' => now(),
        ]);

        // Update parent plan counters
        $this->plan->increment('completed_steps');
    }

    /**
     * Mark step as failed.
     */
    public function markFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $error,
            'completed_at' => now(),
        ]);

        $this->plan->increment('failed_steps');
    }

    /**
     * Check if step can be retried.
     */
    public function canRetry(): bool
    {
        return $this->status === 'failed' && $this->retry_count < 3;
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}
