<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmFollowUpStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'sequence_id',
        'position',
        'delay_days',
        'delay_hours',
        'action_type',
        'task_type',
        'task_title',
        'task_description',
        'task_priority',
        'email_template_id',
        'condition_if_no_response',
        'wait_days_for_response',
    ];

    protected $casts = [
        'delay_days' => 'integer',
        'delay_hours' => 'integer',
        'wait_days_for_response' => 'integer',
    ];

    // ==================== RELATIONSHIPS ====================

    /**
     * Get the sequence this step belongs to.
     */
    public function sequence(): BelongsTo
    {
        return $this->belongsTo(CrmFollowUpSequence::class, 'sequence_id');
    }

    /**
     * Get the email template for this step.
     */
    public function emailTemplate(): BelongsTo
    {
        return $this->belongsTo(Template::class, 'email_template_id');
    }

    // ==================== HELPERS ====================

    /**
     * Get the total delay in hours.
     */
    public function getTotalDelayHoursAttribute(): int
    {
        return ($this->delay_days * 24) + $this->delay_hours;
    }

    /**
     * Get the delay as a human-readable string.
     */
    public function getDelayLabelAttribute(): string
    {
        $parts = [];

        if ($this->delay_days > 0) {
            $parts[] = $this->delay_days . ' ' . trans_choice('dni|dzień|dni', $this->delay_days);
        }

        if ($this->delay_hours > 0) {
            $parts[] = $this->delay_hours . ' ' . trans_choice('godzin|godzina|godziny', $this->delay_hours);
        }

        return empty($parts) ? 'Natychmiast' : implode(' ', $parts);
    }

    /**
     * Get the action type label.
     */
    public function getActionTypeLabelAttribute(): string
    {
        return match($this->action_type) {
            'task' => 'Zadanie',
            'email' => 'Email',
            'sms' => 'SMS',
            'wait_for_response' => 'Czekaj na odpowiedź',
            default => $this->action_type,
        };
    }

    /**
     * Get the task type label.
     */
    public function getTaskTypeLabelAttribute(): ?string
    {
        if ($this->action_type !== 'task') {
            return null;
        }

        return match($this->task_type) {
            'call' => 'Telefon',
            'email' => 'Email',
            'meeting' => 'Spotkanie',
            'task' => 'Zadanie',
            'follow_up' => 'Follow-up',
            default => $this->task_type,
        };
    }

    /**
     * Get the next step in the sequence.
     */
    public function getNextStep(): ?self
    {
        return self::where('sequence_id', $this->sequence_id)
            ->where('position', '>', $this->position)
            ->orderBy('position')
            ->first();
    }

    /**
     * Get the previous step in the sequence.
     */
    public function getPreviousStep(): ?self
    {
        return self::where('sequence_id', $this->sequence_id)
            ->where('position', '<', $this->position)
            ->orderBy('position', 'desc')
            ->first();
    }

    /**
     * Execute this step for an enrollment.
     */
    public function execute(CrmFollowUpEnrollment $enrollment): ?CrmTask
    {
        if ($this->action_type !== 'task') {
            return null;
        }

        $contact = $enrollment->contact;
        $sequence = $enrollment->sequence;

        return CrmTask::create([
            'user_id' => $sequence->user_id,
            'owner_id' => $sequence->user_id,
            'crm_contact_id' => $contact->id,
            'follow_up_enrollment_id' => $enrollment->id,
            'title' => $this->task_title ?? "Follow-up: {$contact->full_name}",
            'description' => $this->task_description,
            'type' => $this->task_type ?? 'follow_up',
            'priority' => $this->task_priority ?? 'medium',
            'status' => 'pending',
            'due_date' => now(),
            'is_follow_up' => true,
            'no_response_action' => $this->condition_if_no_response === 'stop' ? 'close' : 'none',
            'no_response_days' => $this->wait_days_for_response,
        ]);
    }
}
