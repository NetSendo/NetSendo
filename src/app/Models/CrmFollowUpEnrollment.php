<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmFollowUpEnrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'sequence_id',
        'crm_contact_id',
        'current_step_id',
        'status',
        'started_at',
        'next_action_at',
        'completed_at',
        'paused_at',
        'steps_completed',
        'metadata',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'next_action_at' => 'datetime',
        'completed_at' => 'datetime',
        'paused_at' => 'datetime',
        'steps_completed' => 'integer',
        'metadata' => 'array',
    ];

    // ==================== RELATIONSHIPS ====================

    /**
     * Get the sequence this enrollment belongs to.
     */
    public function sequence(): BelongsTo
    {
        return $this->belongsTo(CrmFollowUpSequence::class, 'sequence_id');
    }

    /**
     * Get the contact enrolled.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(CrmContact::class, 'crm_contact_id');
    }

    /**
     * Get the current step.
     */
    public function currentStep(): BelongsTo
    {
        return $this->belongsTo(CrmFollowUpStep::class, 'current_step_id');
    }

    /**
     * Get all tasks created by this enrollment.
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(CrmTask::class, 'follow_up_enrollment_id');
    }

    // ==================== SCOPES ====================

    /**
     * Scope to only include active enrollments.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to include enrollments that need processing.
     */
    public function scopeNeedsProcessing($query)
    {
        return $query->active()
            ->whereNotNull('next_action_at')
            ->where('next_action_at', '<=', now());
    }

    /**
     * Scope to only include paused enrollments.
     */
    public function scopePaused($query)
    {
        return $query->where('status', 'paused');
    }

    /**
     * Scope to only include completed enrollments.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    // ==================== ACTIONS ====================

    /**
     * Pause this enrollment.
     */
    public function pause(): void
    {
        $this->update([
            'status' => 'paused',
            'paused_at' => now(),
        ]);
    }

    /**
     * Resume this enrollment.
     */
    public function resume(): void
    {
        $this->update([
            'status' => 'active',
            'paused_at' => null,
            'next_action_at' => now(),
        ]);
    }

    /**
     * Cancel this enrollment.
     */
    public function cancel(): void
    {
        $this->update([
            'status' => 'cancelled',
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark as completed.
     */
    public function markCompleted(): void
    {
        // Remove any older completed enrollments for the same contact+sequence
        // to prevent potential unique constraint violations
        self::where('sequence_id', $this->sequence_id)
            ->where('crm_contact_id', $this->crm_contact_id)
            ->where('status', 'completed')
            ->where('id', '!=', $this->id)
            ->delete();

        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'current_step_id' => null,
            'next_action_at' => null,
        ]);
    }

    /**
     * Process the current step and move to the next.
     */
    public function processCurrentStep(): ?CrmTask
    {
        if ($this->status !== 'active' || !$this->current_step_id) {
            return null;
        }

        $currentStep = $this->currentStep;
        if (!$currentStep) {
            $this->markCompleted();
            return null;
        }

        // Execute the step
        $task = $currentStep->execute($this);

        // Move to next step
        $nextStep = $currentStep->getNextStep();

        if ($nextStep) {
            $this->update([
                'current_step_id' => $nextStep->id,
                'steps_completed' => $this->steps_completed + 1,
                'next_action_at' => now()
                    ->addDays($nextStep->delay_days)
                    ->addHours($nextStep->delay_hours),
            ]);
        } else {
            // No more steps - mark as completed
            $this->update([
                'steps_completed' => $this->steps_completed + 1,
            ]);
            $this->markCompleted();
        }

        return $task;
    }

    /**
     * Get progress percentage.
     */
    public function getProgressAttribute(): int
    {
        $totalSteps = $this->sequence->steps()->count();

        if ($totalSteps === 0) {
            return 100;
        }

        return (int) round(($this->steps_completed / $totalSteps) * 100);
    }

    /**
     * Get status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'active' => 'Aktywny',
            'paused' => 'Wstrzymany',
            'completed' => 'Zakończony',
            'cancelled' => 'Anulowany',
            default => $this->status,
        };
    }
}
