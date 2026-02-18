<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiPendingApproval extends Model
{
    protected $fillable = [
        'ai_action_plan_id',
        'user_id',
        'channel',
        'status',
        'summary',
        'approval_options',
        'telegram_message_id',
        'rejection_reason',
        'expires_at',
        'decided_at',
    ];

    protected $casts = [
        'approval_options' => 'array',
        'expires_at' => 'datetime',
        'decided_at' => 'datetime',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(AiActionPlan::class, 'ai_action_plan_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Approve the plan.
     */
    public function approve(): void
    {
        $this->update([
            'status' => 'approved',
            'decided_at' => now(),
        ]);

        $this->plan->approve();
    }

    /**
     * Reject the plan.
     */
    public function reject(?string $reason = null): void
    {
        $this->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'decided_at' => now(),
        ]);

        $this->plan->update(['status' => 'cancelled']);
    }

    /**
     * Check if expired.
     */
    public function isExpired(): bool
    {
        return $this->status === 'pending' && $this->expires_at->lt(now());
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending')
            ->where('expires_at', '>', now());
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'pending')
            ->where('expires_at', '<=', now());
    }
}
