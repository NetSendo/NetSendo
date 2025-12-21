<?php

namespace App\Helpers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class DateHelper
{
    /**
     * Format a date using the authenticated user's timezone.
     *
     * @param Carbon|string|null $date The date to format
     * @param string|null $format The format string (default: 'Y-m-d H:i')
     * @return string|null
     */
    public static function formatForUser(Carbon|string|null $date, ?string $format = null): ?string
    {
        if (!$date) {
            return null;
        }

        if (is_string($date)) {
            $date = Carbon::parse($date);
        }

        $timezone = static::getUserTimezone();
        $format = $format ?? 'Y-m-d H:i';

        return $date->setTimezone($timezone)->format($format);
    }

    /**
     * Format a date with only date part (Y-m-d) using user's timezone.
     *
     * @param Carbon|string|null $date
     * @return string|null
     */
    public static function formatDateForUser(Carbon|string|null $date): ?string
    {
        return static::formatForUser($date, 'Y-m-d');
    }

    /**
     * Format a date with full datetime including seconds.
     *
     * @param Carbon|string|null $date
     * @return string|null
     */
    public static function formatFullForUser(Carbon|string|null $date): ?string
    {
        return static::formatForUser($date, 'Y-m-d H:i:s');
    }

    /**
     * Get the current user's timezone.
     * Falls back to UTC if no user is authenticated or no timezone is set.
     *
     * @return string
     */
    public static function getUserTimezone(): string
    {
        $user = Auth::user();
        
        if ($user && !empty($user->timezone)) {
            return $user->timezone;
        }

        return config('app.timezone', 'UTC');
    }

    /**
     * Get the current time in user's timezone.
     *
     * @return Carbon
     */
    public static function now(): Carbon
    {
        return Carbon::now(static::getUserTimezone());
    }
}
