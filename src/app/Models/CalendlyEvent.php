<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendlyEvent extends Model
{
    protected $fillable = [
        'calendly_integration_id',
        'user_id',
        'calendly_event_uri',
        'calendly_invitee_uri',
        'event_type_uri',
        'event_type_name',
        'event_type_slug',
        'invitee_email',
        'invitee_name',
        'invitee_timezone',
        'start_time',
        'end_time',
        'status',
        'location',
        'questions_and_answers',
        'cancellation_reason',
        'canceled_by',
        'canceled_at',
        'marked_no_show_at',
        'subscriber_id',
        'crm_contact_id',
        'crm_task_id',
        'raw_payload',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'canceled_at' => 'datetime',
        'marked_no_show_at' => 'datetime',
        'location' => 'array',
        'questions_and_answers' => 'array',
        'raw_payload' => 'array',
    ];

    /**
     * Status constants
     */
    const STATUS_SCHEDULED = 'scheduled';
    const STATUS_CANCELED = 'canceled';
    const STATUS_NO_SHOW = 'no_show';
    const STATUS_COMPLETED = 'completed';

    /**
     * Get the integration this event belongs to.
     */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(CalendlyIntegration::class, 'calendly_integration_id');
    }

    /**
     * Get the user that owns this event.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the linked subscriber.
     */
    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class);
    }

    /**
     * Get the linked CRM contact.
     */
    public function crmContact(): BelongsTo
    {
        return $this->belongsTo(CrmContact::class);
    }

    /**
     * Get the linked CRM task.
     */
    public function crmTask(): BelongsTo
    {
        return $this->belongsTo(CrmTask::class);
    }

    /**
     * Get the meeting location URL (Zoom, Google Meet, etc.)
     */
    public function getMeetingUrlAttribute(): ?string
    {
        if (!$this->location) {
            return null;
        }

        return $this->location['join_url'] ?? $this->location['location'] ?? null;
    }

    /**
     * Get the meeting location type.
     */
    public function getMeetingTypeAttribute(): ?string
    {
        if (!$this->location) {
            return null;
        }

        return $this->location['type'] ?? null;
    }

    /**
     * Parse invitee name into first and last name.
     */
    public function getParsedNameAttribute(): array
    {
        if (!$this->invitee_name) {
            return ['first_name' => null, 'last_name' => null];
        }

        $parts = explode(' ', $this->invitee_name, 2);

        return [
            'first_name' => $parts[0] ?? null,
            'last_name' => $parts[1] ?? null,
        ];
    }

    /**
     * Get custom field value from questions and answers.
     */
    public function getAnswerForQuestion(string $question): ?string
    {
        if (!$this->questions_and_answers) {
            return null;
        }

        foreach ($this->questions_and_answers as $qa) {
            if (isset($qa['question']) && stripos($qa['question'], $question) !== false) {
                return $qa['answer'] ?? null;
            }
        }

        return null;
    }

    /**
     * Check if the event is upcoming.
     */
    public function isUpcoming(): bool
    {
        return $this->status === self::STATUS_SCHEDULED && $this->start_time->isFuture();
    }

    /**
     * Check if the event has passed.
     */
    public function hasPassed(): bool
    {
        return $this->end_time->isPast();
    }

    /**
     * Check if the event is currently happening.
     */
    public function isNow(): bool
    {
        return now()->between($this->start_time, $this->end_time);
    }

    /**
     * Mark the event as canceled.
     */
    public function markAsCanceled(?string $reason = null, ?string $canceledBy = null): void
    {
        $this->update([
            'status' => self::STATUS_CANCELED,
            'cancellation_reason' => $reason,
            'canceled_by' => $canceledBy,
            'canceled_at' => now(),
        ]);
    }

    /**
     * Mark the event as no-show.
     */
    public function markAsNoShow(): void
    {
        $this->update([
            'status' => self::STATUS_NO_SHOW,
            'marked_no_show_at' => now(),
        ]);
    }

    /**
     * Scope for scheduled events.
     */
    public function scopeScheduled($query)
    {
        return $query->where('status', self::STATUS_SCHEDULED);
    }

    /**
     * Scope for canceled events.
     */
    public function scopeCanceled($query)
    {
        return $query->where('status', self::STATUS_CANCELED);
    }

    /**
     * Scope for upcoming events.
     */
    public function scopeUpcoming($query)
    {
        return $query->where('status', self::STATUS_SCHEDULED)
            ->where('start_time', '>', now());
    }

    /**
     * Scope for a specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for a specific email.
     */
    public function scopeForEmail($query, string $email)
    {
        return $query->where('invitee_email', $email);
    }
}
