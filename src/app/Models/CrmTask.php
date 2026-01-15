<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class CrmTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'owner_id',
        'crm_contact_id',
        'crm_deal_id',
        'title',
        'description',
        'type',
        'priority',
        'status',
        'due_date',
        'completed_at',
        'overdue_notified',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'completed_at' => 'datetime',
        'overdue_notified' => 'boolean',
    ];

    /**
     * Get the user (account owner) for this task.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the salesperson assigned to this task.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get the contact associated with this task.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(CrmContact::class, 'crm_contact_id');
    }

    /**
     * Get the deal associated with this task.
     */
    public function deal(): BelongsTo
    {
        return $this->belongsTo(CrmDeal::class, 'crm_deal_id');
    }

    // ==================== SCOPES ====================

    /**
     * Scope a query to only include tasks for a specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include tasks owned by a specific user.
     */
    public function scopeOwnedBy($query, $ownerId)
    {
        return $query->where('owner_id', $ownerId);
    }

    /**
     * Scope a query to only include pending tasks.
     */
    public function scopePending($query)
    {
        return $query->whereIn('status', ['pending', 'in_progress']);
    }

    /**
     * Scope a query to only include overdue tasks.
     */
    public function scopeOverdue($query)
    {
        return $query->pending()
            ->whereNotNull('due_date')
            ->where('due_date', '<', Carbon::today());
    }

    /**
     * Scope a query to only include tasks due today.
     */
    public function scopeToday($query)
    {
        return $query->pending()
            ->whereDate('due_date', Carbon::today());
    }

    /**
     * Scope a query to only include upcoming tasks (next 7 days).
     */
    public function scopeUpcoming($query)
    {
        return $query->pending()
            ->whereNotNull('due_date')
            ->where('due_date', '>', Carbon::today())
            ->where('due_date', '<=', Carbon::today()->addDays(7));
    }

    /**
     * Scope a query to only include completed tasks.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    // ==================== ACTIONS ====================

    /**
     * Mark the task as completed.
     */
    public function complete(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        // Log activity on contact if exists
        if ($this->crm_contact_id) {
            $this->contact->activities()->create([
                'user_id' => $this->user_id,
                'created_by_id' => auth()->id(),
                'type' => 'task_completed',
                'content' => "Ukończono zadanie: {$this->title}",
                'metadata' => [
                    'task_id' => $this->id,
                    'task_type' => $this->type,
                ],
            ]);
        }
    }

    /**
     * Reschedule the task.
     */
    public function reschedule(Carbon $newDate): void
    {
        $this->update([
            'due_date' => $newDate,
            'status' => 'pending',
        ]);
    }

    /**
     * Check if the task is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->due_date
            && $this->due_date->isPast()
            && !in_array($this->status, ['completed', 'cancelled']);
    }

    /**
     * Check if the task is due today.
     */
    public function isDueToday(): bool
    {
        return $this->due_date && $this->due_date->isToday();
    }

    /**
     * Get the priority color.
     */
    public function getPriorityColorAttribute(): string
    {
        return match($this->priority) {
            'high' => '#ef4444',
            'medium' => '#f59e0b',
            'low' => '#10b981',
            default => '#6b7280',
        };
    }

    /**
     * Get the type icon name.
     */
    public function getTypeIconAttribute(): string
    {
        return match($this->type) {
            'call' => 'phone',
            'email' => 'mail',
            'meeting' => 'users',
            'follow_up' => 'clock',
            default => 'check-square',
        };
    }
}
