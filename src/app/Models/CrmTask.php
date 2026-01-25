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
        'notes',
        'type',
        'priority',
        'status',
        'due_date',
        'end_date',
        'completed_at',
        'overdue_notified',
        'reminder_at',
        'reminder_sent',
        'parent_task_id',
        'is_follow_up',
        'no_response_action',
        'no_response_days',
        'follow_up_enrollment_id',
        // Google Calendar sync
        'google_calendar_event_id',
        'google_calendar_id',
        'google_calendar_synced_at',
        'sync_to_calendar',
        'selected_calendar_id',
        // Recurrence fields
        'is_recurring',
        'recurrence_rule',
        'recurrence_type',
        'recurrence_interval',
        'recurrence_days',
        'recurrence_end_date',
        'recurrence_count',
        'recurring_parent_id',
        // Conflict resolution
        'google_calendar_etag',
        'local_updated_at',
        'has_conflict',
        'conflict_data',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'end_date' => 'datetime',
        'completed_at' => 'datetime',
        'reminder_at' => 'datetime',
        'overdue_notified' => 'boolean',
        'reminder_sent' => 'boolean',
        'is_follow_up' => 'boolean',
        'google_calendar_synced_at' => 'datetime',
        'sync_to_calendar' => 'boolean',
        // Recurrence
        'is_recurring' => 'boolean',
        'recurrence_days' => 'array',
        'recurrence_end_date' => 'date',
        // Conflict resolution
        'local_updated_at' => 'datetime',
        'has_conflict' => 'boolean',
        'conflict_data' => 'array',
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

    /**
     * Get the parent task (if this is a follow-up).
     */
    public function parentTask(): BelongsTo
    {
        return $this->belongsTo(CrmTask::class, 'parent_task_id');
    }

    /**
     * Get child follow-up tasks.
     */
    public function childTasks()
    {
        return $this->hasMany(CrmTask::class, 'parent_task_id');
    }

    /**
     * Get the follow-up enrollment this task belongs to.
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(CrmFollowUpEnrollment::class, 'follow_up_enrollment_id');
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

    /**
     * Scope a query to only include follow-up tasks.
     */
    public function scopeFollowUps($query)
    {
        return $query->pending()->where('is_follow_up', true);
    }

    /**
     * Scope a query to only include tasks that need a reminder sent.
     */
    public function scopeNeedsReminder($query)
    {
        return $query->pending()
            ->whereNotNull('reminder_at')
            ->where('reminder_at', '<=', now())
            ->where('reminder_sent', false);
    }

    /**
     * Scope a query to only include tasks awaiting response.
     */
    public function scopeAwaitingResponse($query)
    {
        return $query->pending()
            ->whereIn('no_response_action', ['reminder', 'escalate'])
            ->whereNotNull('no_response_days');
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

        // Dispatch event for automation triggers
        event(new \App\Events\CrmTaskCompleted($this, auth()->id()));
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

    /**
     * Create a follow-up task based on this task.
     */
    public function createFollowUp(int $daysLater, ?string $title = null, ?string $type = null): self
    {
        return self::create([
            'user_id' => $this->user_id,
            'owner_id' => $this->owner_id,
            'crm_contact_id' => $this->crm_contact_id,
            'crm_deal_id' => $this->crm_deal_id,
            'parent_task_id' => $this->id,
            'title' => $title ?? "Follow-up: {$this->title}",
            'type' => $type ?? 'follow_up',
            'priority' => $this->priority,
            'status' => 'pending',
            'due_date' => now()->addDays($daysLater),
            'is_follow_up' => true,
        ]);
    }

    /**
     * Snooze the task reminder.
     */
    public function snoozeReminder(int $hours = 1): void
    {
        $this->update([
            'reminder_at' => now()->addHours($hours),
            'reminder_sent' => false,
        ]);
    }

    /**
     * Mark reminder as sent.
     */
    public function markReminderSent(): void
    {
        $this->update(['reminder_sent' => true]);
    }

    /**
     * Get the category label for display.
     */
    public function getCategoryLabelAttribute(): ?string
    {
        if ($this->is_follow_up) {
            return 'Follow-up';
        }
        if ($this->reminder_at) {
            return 'Przypomnienie';
        }
        if ($this->priority === 'high') {
            return 'Ważne';
        }
        return null;
    }

    // ==================== GOOGLE CALENDAR SYNC ====================

    /**
     * Check if task is synced to Google Calendar.
     */
    public function isSyncedToCalendar(): bool
    {
        return !empty($this->google_calendar_event_id);
    }

    /**
     * Check if task should be synced to Calendar.
     */
    public function shouldSyncToCalendar(): bool
    {
        return $this->sync_to_calendar && !in_array($this->status, ['cancelled']);
    }

    /**
     * Mark task as synced to Google Calendar.
     */
    public function markCalendarSynced(string $eventId, string $calendarId): void
    {
        $this->update([
            'google_calendar_event_id' => $eventId,
            'google_calendar_id' => $calendarId,
            'google_calendar_synced_at' => now(),
        ]);
    }

    /**
     * Clear Calendar sync data (e.g., when disconnecting or deleting event).
     */
    public function clearCalendarSync(): void
    {
        $this->update([
            'google_calendar_event_id' => null,
            'google_calendar_id' => null,
            'google_calendar_synced_at' => null,
        ]);
    }

    /**
     * Get the Google Calendar event URL.
     */
    public function getCalendarEventUrl(): ?string
    {
        if (!$this->google_calendar_event_id) {
            return null;
        }

        return "https://calendar.google.com/calendar/event?eid=" . base64_encode($this->google_calendar_event_id);
    }

    /**
     * Update task from Calendar event data.
     */
    public function updateFromCalendarEvent(array $eventData): void
    {
        $updates = [];

        if (isset($eventData['summary']) && $eventData['summary'] !== $this->title) {
            $updates['title'] = $eventData['summary'];
        }

        if (isset($eventData['description']) && $eventData['description'] !== $this->description) {
            $updates['description'] = $eventData['description'];
        }

        if (isset($eventData['start'])) {
            $startDateTime = $eventData['start']['dateTime'] ?? $eventData['start']['date'] ?? null;
            if ($startDateTime) {
                $newDueDate = \Carbon\Carbon::parse($startDateTime);
                if (!$this->due_date || !$this->due_date->equalTo($newDueDate)) {
                    $updates['due_date'] = $newDueDate;
                }
            }
        }

        if (isset($eventData['status'])) {
            if ($eventData['status'] === 'cancelled' && $this->status !== 'cancelled') {
                $updates['status'] = 'cancelled';
            }
        }

        if (!empty($updates)) {
            $updates['google_calendar_synced_at'] = now();
            $this->update($updates);
        }
    }

    /**
     * Scope for tasks that need Calendar sync.
     */
    public function scopeNeedsCalendarSync($query)
    {
        return $query->where('sync_to_calendar', true)
            ->whereNull('google_calendar_event_id')
            ->whereNotIn('status', ['cancelled']);
    }

    /**
     * Scope for tasks synced to Calendar.
     */
    public function scopeSyncedToCalendar($query)
    {
        return $query->whereNotNull('google_calendar_event_id');
    }

    // ==================== RECURRENCE ====================

    /**
     * Get relationship to parent recurring task.
     */
    public function recurringParent(): BelongsTo
    {
        return $this->belongsTo(CrmTask::class, 'recurring_parent_id');
    }

    /**
     * Get all child occurrences of this recurring task.
     */
    public function recurringChildren()
    {
        return $this->hasMany(CrmTask::class, 'recurring_parent_id');
    }

    /**
     * Build RRULE string for Google Calendar.
     */
    public function buildRecurrenceRule(): ?string
    {
        if (!$this->is_recurring || !$this->recurrence_type) {
            return null;
        }

        $freq = match($this->recurrence_type) {
            'daily' => 'DAILY',
            'weekly' => 'WEEKLY',
            'monthly' => 'MONTHLY',
            'yearly' => 'YEARLY',
            default => 'DAILY',
        };

        $parts = ["FREQ={$freq}"];

        if ($this->recurrence_interval > 1) {
            $parts[] = "INTERVAL={$this->recurrence_interval}";
        }

        // For weekly recurrence, include BYDAY
        if ($this->recurrence_type === 'weekly' && !empty($this->recurrence_days)) {
            $dayMap = [1 => 'MO', 2 => 'TU', 3 => 'WE', 4 => 'TH', 5 => 'FR', 6 => 'SA', 0 => 'SU'];
            $days = array_map(fn($d) => $dayMap[$d] ?? '', $this->recurrence_days);
            $days = array_filter($days);
            if (!empty($days)) {
                $parts[] = "BYDAY=" . implode(',', $days);
            }
        }

        // End condition
        if ($this->recurrence_count) {
            $parts[] = "COUNT={$this->recurrence_count}";
        } elseif ($this->recurrence_end_date) {
            $parts[] = "UNTIL=" . $this->recurrence_end_date->format('Ymd') . "T235959Z";
        }

        return "RRULE:" . implode(';', $parts);
    }

    /**
     * Parse RRULE from Google Calendar and set recurrence fields.
     */
    public function parseRecurrenceRule(string $rrule): void
    {
        // Remove RRULE: prefix
        $rule = str_replace('RRULE:', '', $rrule);
        $parts = [];

        foreach (explode(';', $rule) as $part) {
            [$key, $value] = explode('=', $part, 2);
            $parts[$key] = $value;
        }

        $this->is_recurring = true;
        $this->recurrence_rule = $rrule;

        // Map frequency to type
        $this->recurrence_type = match($parts['FREQ'] ?? 'DAILY') {
            'DAILY' => 'daily',
            'WEEKLY' => 'weekly',
            'MONTHLY' => 'monthly',
            'YEARLY' => 'yearly',
            default => 'daily',
        };

        $this->recurrence_interval = (int) ($parts['INTERVAL'] ?? 1);

        // Parse BYDAY for weekly
        if (isset($parts['BYDAY'])) {
            $dayMap = ['MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6, 'SU' => 0];
            $days = array_map(fn($d) => $dayMap[$d] ?? null, explode(',', $parts['BYDAY']));
            $this->recurrence_days = array_filter($days, fn($d) => $d !== null);
        }

        if (isset($parts['COUNT'])) {
            $this->recurrence_count = (int) $parts['COUNT'];
        }

        if (isset($parts['UNTIL'])) {
            $this->recurrence_end_date = Carbon::parse($parts['UNTIL']);
        }
    }

    /**
     * Get next occurrence date.
     */
    public function getNextOccurrence(): ?Carbon
    {
        if (!$this->is_recurring || !$this->due_date) {
            return null;
        }

        $nextDate = $this->due_date->copy();

        return match($this->recurrence_type) {
            'daily' => $nextDate->addDays($this->recurrence_interval),
            'weekly' => $nextDate->addWeeks($this->recurrence_interval),
            'monthly' => $nextDate->addMonths($this->recurrence_interval),
            'yearly' => $nextDate->addYears($this->recurrence_interval),
            default => $nextDate->addDays(1),
        };
    }

    /**
     * Scope for recurring tasks.
     */
    public function scopeRecurring($query)
    {
        return $query->where('is_recurring', true);
    }

    /**
     * Scope for non-recurring (single) tasks.
     */
    public function scopeSingle($query)
    {
        return $query->where('is_recurring', false);
    }

    // ==================== CONFLICT RESOLUTION ====================

    /**
     * Check if task has unresolved conflict.
     */
    public function hasUnresolvedConflict(): bool
    {
        return $this->has_conflict && !empty($this->conflict_data);
    }

    /**
     * Mark task as having a conflict.
     */
    public function markConflict(array $localData, array $remoteData): void
    {
        $this->update([
            'has_conflict' => true,
            'conflict_data' => [
                'detected_at' => now()->toISOString(),
                'local' => $localData,
                'remote' => $remoteData,
            ],
        ]);
    }

    /**
     * Resolve conflict by accepting local version.
     */
    public function resolveConflictWithLocal(): void
    {
        $this->update([
            'has_conflict' => false,
            'conflict_data' => null,
        ]);

        // Trigger sync to overwrite remote
        if ($this->sync_to_calendar) {
            \App\Jobs\SyncTaskToCalendar::dispatch($this);
        }
    }

    /**
     * Resolve conflict by accepting remote version.
     */
    public function resolveConflictWithRemote(): void
    {
        if (!$this->conflict_data || !isset($this->conflict_data['remote'])) {
            return;
        }

        $remoteData = $this->conflict_data['remote'];

        $this->update([
            'title' => $remoteData['title'] ?? $this->title,
            'description' => $remoteData['description'] ?? $this->description,
            'due_date' => isset($remoteData['due_date']) ? Carbon::parse($remoteData['due_date']) : $this->due_date,
            'has_conflict' => false,
            'conflict_data' => null,
            'google_calendar_synced_at' => now(),
        ]);
    }

    /**
     * Update etag from Google Calendar response.
     */
    public function updateEtag(?string $etag): void
    {
        $this->update([
            'google_calendar_etag' => $etag,
            'local_updated_at' => now(),
        ]);
    }

    /**
     * Check if local version is newer than synced version.
     */
    public function isLocallyModified(): bool
    {
        if (!$this->local_updated_at || !$this->google_calendar_synced_at) {
            return false;
        }

        return $this->local_updated_at->isAfter($this->google_calendar_synced_at);
    }

    /**
     * Scope for tasks with conflicts.
     */
    public function scopeWithConflicts($query)
    {
        return $query->where('has_conflict', true);
    }
}

