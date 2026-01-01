<?php

namespace App\Services\Webinar;

use App\Models\Webinar;
use App\Models\WebinarRegistration;
use App\Mail\WebinarRegistrationConfirmation;
use App\Mail\WebinarReminder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class WebinarNotificationService
{
    public function sendRegistrationConfirmation(WebinarRegistration $registration): void
    {
        try {
            Mail::to($registration->email)->queue(
                new WebinarRegistrationConfirmation($registration)
            );
        } catch (\Exception $e) {
            Log::error("Failed to send webinar registration confirmation", [
                'registration_id' => $registration->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function sendReminderEmails(string $reminderType): int
    {
        $sent = 0;
        $column = match ($reminderType) {
            '24h' => 'reminder_24h_sent',
            '1h' => 'reminder_1h_sent',
            '15min' => 'reminder_15min_sent',
            default => null,
        };

        if (!$column) return 0;

        $registrations = WebinarRegistration::query()
            ->where($column, false)
            ->where('status', WebinarRegistration::STATUS_REGISTERED)
            ->whereHas('webinar', fn($q) => $q->where('status', Webinar::STATUS_SCHEDULED))
            ->with('webinar')
            ->get();

        foreach ($registrations as $registration) {
            try {
                Mail::to($registration->email)->queue(new WebinarReminder($registration, $reminderType));
                $registration->update([$column => true]);
                $sent++;
            } catch (\Exception $e) {
                Log::error("Failed to send webinar reminder", ['error' => $e->getMessage()]);
            }
        }

        return $sent;
    }

    public function sendWebinarStartedNotifications(Webinar $webinar): int
    {
        $sent = 0;
        $registrations = $webinar->registrations()->where('status', WebinarRegistration::STATUS_REGISTERED)->get();

        foreach ($registrations as $registration) {
            try {
                Mail::to($registration->email)->queue(new \App\Mail\WebinarStarted($registration));
                $sent++;
            } catch (\Exception $e) {
                Log::error("Failed to send webinar started notification", ['error' => $e->getMessage()]);
            }
        }

        return $sent;
    }

    public function scheduleReplayNotifications(Webinar $webinar): void
    {
        $registrations = $webinar->registrations()->where('replay_email_sent', false)->get();

        foreach ($registrations as $registration) {
            Mail::to($registration->email)->later(
                now()->addHour(),
                new \App\Mail\WebinarReplayAvailable($registration)
            );
            $registration->update(['replay_email_sent' => true]);
        }
    }
}
