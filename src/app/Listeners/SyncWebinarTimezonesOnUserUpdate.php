<?php

namespace App\Listeners;

use App\Models\Webinar;
use App\Models\AutoWebinarSchedule;
use Illuminate\Support\Facades\Log;

class SyncWebinarTimezonesOnUserUpdate
{
    /**
     * Handle user timezone update.
     *
     * When a user changes their timezone, update all webinars that:
     * 1. Don't have a custom timezone override
     * 2. Belong to this user
     *
     * The same applies to AutoWebinarSchedule.
     */
    public function handle($event): void
    {
        $user = $event->user;
        $oldTimezone = $event->oldTimezone ?? null;
        $newTimezone = $user->timezone;

        if (!$newTimezone || $oldTimezone === $newTimezone) {
            return;
        }

        Log::info("Syncing webinar timezones for user {$user->id}: {$oldTimezone} -> {$newTimezone}");

        // Update webinars that were using the old timezone
        $updatedWebinars = Webinar::where('user_id', $user->id)
            ->where('timezone', $oldTimezone)
            ->update(['timezone' => $newTimezone]);

        // Update auto webinar schedules
        $updatedSchedules = AutoWebinarSchedule::whereHas('webinar', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->where('timezone', $oldTimezone)
            ->update(['timezone' => $newTimezone]);

        Log::info("Updated {$updatedWebinars} webinars and {$updatedSchedules} schedules to timezone {$newTimezone}");
    }
}
