<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebinarSession extends Model
{
    use HasFactory;

    // Status constants
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_LIVE = 'live';
    public const STATUS_ENDED = 'ended';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'webinar_id',
        'scheduled_at',
        'started_at',
        'ended_at',
        'status',
        'is_replay',
        'session_number',
        'attendees_count',
        'peak_viewers',
        'chat_messages_count',
        'current_position_seconds',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'is_replay' => 'boolean',
        'session_number' => 'integer',
        'attendees_count' => 'integer',
        'peak_viewers' => 'integer',
        'chat_messages_count' => 'integer',
        'current_position_seconds' => 'integer',
    ];

    // =====================================
    // Relationships
    // =====================================

    public function webinar(): BelongsTo
    {
        return $this->belongsTo(Webinar::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(WebinarRegistration::class);
    }

    public function chatMessages(): HasMany
    {
        return $this->hasMany(WebinarChatMessage::class);
    }

    public function analytics(): HasMany
    {
        return $this->hasMany(WebinarAnalytic::class);
    }

    // =====================================
    // Scopes
    // =====================================

    public function scopeLive($query)
    {
        return $query->where('status', self::STATUS_LIVE);
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', self::STATUS_SCHEDULED);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('scheduled_at', '>', now())->where('status', self::STATUS_SCHEDULED);
    }

    // =====================================
    // Accessors
    // =====================================

    /**
     * Get duration in minutes.
     */
    public function getDurationMinutesAttribute(): ?int
    {
        if (!$this->started_at || !$this->ended_at) {
            return null;
        }

        return $this->started_at->diffInMinutes($this->ended_at);
    }

    /**
     * Get attendance rate.
     */
    public function getAttendanceRateAttribute(): float
    {
        $registrations = $this->registrations()->count();
        if ($registrations === 0) {
            return 0;
        }

        return round(($this->attendees_count / $registrations) * 100, 1);
    }

    // =====================================
    // Methods
    // =====================================

    /**
     * Check if session is live.
     */
    public function isLive(): bool
    {
        return $this->status === self::STATUS_LIVE;
    }

    /**
     * Start the session.
     */
    public function start(): bool
    {
        if ($this->status !== self::STATUS_SCHEDULED) {
            return false;
        }

        $this->status = self::STATUS_LIVE;
        $this->started_at = now();
        $result = $this->save();

        // Also update parent webinar status
        if ($result && $this->webinar) {
            $this->webinar->start();
        }

        return $result;
    }

    /**
     * End the session.
     */
    public function end(): bool
    {
        if ($this->status !== self::STATUS_LIVE) {
            return false;
        }

        $this->status = self::STATUS_ENDED;
        $this->ended_at = now();
        return $this->save();
    }

    /**
     * Cancel the session.
     */
    public function cancel(): bool
    {
        if ($this->status === self::STATUS_ENDED) {
            return false;
        }

        $this->status = self::STATUS_CANCELLED;
        return $this->save();
    }

    /**
     * Update video position (for auto-webinars).
     */
    public function updatePosition(int $seconds): void
    {
        $this->update(['current_position_seconds' => $seconds]);
    }

    /**
     * Increment attendees count.
     */
    public function incrementAttendees(): void
    {
        $this->increment('attendees_count');

        // Update peak viewers if needed
        if ($this->attendees_count > $this->peak_viewers) {
            $this->update(['peak_viewers' => $this->attendees_count]);
        }

        // Update parent webinar
        if ($this->webinar) {
            $this->webinar->incrementAttendees();
            $this->webinar->updatePeakViewers($this->attendees_count);
        }
    }

    /**
     * Increment chat messages count.
     */
    public function incrementChatMessages(): void
    {
        $this->increment('chat_messages_count');
    }

    /**
     * Get current active viewers (real-time).
     */
    public function getCurrentViewers(): int
    {
        return $this->registrations()
            ->whereNotNull('joined_at')
            ->whereNull('left_at')
            ->count();
    }
}
