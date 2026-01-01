<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class AutoWebinarSchedule extends Model
{
    use HasFactory;

    // Schedule type constants
    public const TYPE_FIXED = 'fixed';
    public const TYPE_RECURRING = 'recurring';
    public const TYPE_ON_DEMAND = 'on_demand';
    public const TYPE_EVERGREEN = 'evergreen';

    protected $fillable = [
        'webinar_id',
        'schedule_type',
        'days_of_week',
        'times_of_day',
        'fixed_dates',
        'start_delay_minutes',
        'available_slots',
        'start_date',
        'end_date',
        'max_sessions_per_day',
        'max_attendees_per_session',
        'timezone',
        'is_active',
    ];

    protected $casts = [
        'days_of_week' => 'array',
        'times_of_day' => 'array',
        'fixed_dates' => 'array',
        'available_slots' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
        'start_delay_minutes' => 'integer',
        'max_sessions_per_day' => 'integer',
        'max_attendees_per_session' => 'integer',
        'is_active' => 'boolean',
    ];

    // =====================================
    // Relationships
    // =====================================

    public function webinar(): BelongsTo
    {
        return $this->belongsTo(Webinar::class);
    }

    // =====================================
    // Scopes
    // =====================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRecurring($query)
    {
        return $query->where('schedule_type', self::TYPE_RECURRING);
    }

    public function scopeEvergreen($query)
    {
        return $query->where('schedule_type', self::TYPE_EVERGREEN);
    }

    // =====================================
    // Accessors
    // =====================================

    /**
     * Check if schedule is within date range.
     */
    public function getIsWithinDateRangeAttribute(): bool
    {
        $today = now()->toDateString();

        if ($this->start_date && $today < $this->start_date->toDateString()) {
            return false;
        }

        if ($this->end_date && $today > $this->end_date->toDateString()) {
            return false;
        }

        return true;
    }

    // =====================================
    // Methods
    // =====================================

    /**
     * Get next available session times.
     */
    public function getNextSessionTimes(int $count = 5): array
    {
        $times = [];
        $now = Carbon::now($this->timezone);

        switch ($this->schedule_type) {
            case self::TYPE_FIXED:
                $times = $this->getNextFixedTimes($now, $count);
                break;

            case self::TYPE_RECURRING:
                $times = $this->getNextRecurringTimes($now, $count);
                break;

            case self::TYPE_ON_DEMAND:
                $times = $this->getOnDemandSlots($now, $count);
                break;

            case self::TYPE_EVERGREEN:
                $times = $this->getEvergreenSlots($now, $count);
                break;
        }

        return $times;
    }

    /**
     * Get next fixed times.
     */
    protected function getNextFixedTimes(Carbon $now, int $count): array
    {
        if (!$this->fixed_dates) {
            return [];
        }

        $times = [];
        foreach ($this->fixed_dates as $dateString) {
            $date = Carbon::parse($dateString, $this->timezone);
            if ($date->greaterThan($now)) {
                $times[] = $date;
                if (count($times) >= $count) {
                    break;
                }
            }
        }

        return $times;
    }

    /**
     * Get next recurring times.
     */
    protected function getNextRecurringTimes(Carbon $now, int $count): array
    {
        if (!$this->days_of_week || !$this->times_of_day) {
            return [];
        }

        $times = [];
        $checkDate = $now->copy();
        $maxDays = 30; // Look ahead max 30 days

        for ($day = 0; $day < $maxDays && count($times) < $count; $day++) {
            $dayOfWeek = $checkDate->dayOfWeek;

            if (in_array($dayOfWeek, $this->days_of_week)) {
                // Check if within date range
                if ($this->start_date && $checkDate->lt($this->start_date)) {
                    $checkDate->addDay();
                    continue;
                }
                if ($this->end_date && $checkDate->gt($this->end_date)) {
                    break;
                }

                foreach ($this->times_of_day as $timeString) {
                    $sessionTime = $checkDate->copy()->setTimeFromTimeString($timeString);

                    if ($sessionTime->greaterThan($now)) {
                        $times[] = $sessionTime;
                        if (count($times) >= $count) {
                            break 2;
                        }
                    }
                }
            }

            $checkDate->addDay()->startOfDay();
        }

        return $times;
    }

    /**
     * Get on-demand slots (e.g., "starts in X minutes after registration").
     */
    protected function getOnDemandSlots(Carbon $now, int $count): array
    {
        $delay = $this->start_delay_minutes ?? 15;

        return [
            $now->copy()->addMinutes($delay),
        ];
    }

    /**
     * Get evergreen slots (e.g., "starting in 10 min, 30 min, 1 hour").
     */
    protected function getEvergreenSlots(Carbon $now, int $count): array
    {
        $slots = $this->available_slots ?? [10, 30, 60]; // Default: 10min, 30min, 1h
        $times = [];

        foreach ($slots as $minutes) {
            $times[] = $now->copy()->addMinutes($minutes);
            if (count($times) >= $count) {
                break;
            }
        }

        return $times;
    }

    /**
     * Calculate next session time for a registration.
     */
    public function calculateSessionTimeForRegistration(): Carbon
    {
        $now = Carbon::now($this->timezone);

        if ($this->schedule_type === self::TYPE_ON_DEMAND) {
            return $now->addMinutes($this->start_delay_minutes ?? 15);
        }

        if ($this->schedule_type === self::TYPE_EVERGREEN) {
            $slots = $this->available_slots ?? [15];
            return $now->addMinutes($slots[0] ?? 15);
        }

        // For fixed/recurring, get the next available time
        $nextTimes = $this->getNextSessionTimes(1);
        return $nextTimes[0] ?? $now->addHour();
    }

    /**
     * Check if a session can be scheduled for given time.
     */
    public function canScheduleAt(Carbon $time): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if (!$this->is_within_date_range) {
            return false;
        }

        // Check max sessions per day
        if ($this->max_sessions_per_day) {
            $sessionsToday = $this->webinar->sessions()
                ->whereDate('scheduled_at', $time->toDateString())
                ->count();

            if ($sessionsToday >= $this->max_sessions_per_day) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get type options.
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_FIXED => 'Stałe daty',
            self::TYPE_RECURRING => 'Powtarzalny',
            self::TYPE_ON_DEMAND => 'Na żądanie',
            self::TYPE_EVERGREEN => 'Evergreen',
        ];
    }

    /**
     * Get day of week options.
     */
    public static function getDaysOfWeek(): array
    {
        return [
            0 => 'Niedziela',
            1 => 'Poniedziałek',
            2 => 'Wtorek',
            3 => 'Środa',
            4 => 'Czwartek',
            5 => 'Piątek',
            6 => 'Sobota',
        ];
    }
}
