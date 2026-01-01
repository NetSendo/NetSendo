<?php

namespace App\Services\Webinar;

use App\Jobs\SendWebinarEmail;
use App\Models\Mailbox;
use App\Models\Webinar;
use App\Models\WebinarRegistration;
use App\Services\Mail\MailProviderService;
use Illuminate\Support\Facades\Log;

class WebinarNotificationService
{
    public function __construct(
        protected MailProviderService $mailProviderService
    ) {}

    /**
     * Send registration confirmation email.
     */
    public function sendRegistrationConfirmation(WebinarRegistration $registration): void
    {
        $webinar = $registration->webinar;
        $mailbox = $this->resolveMailbox($webinar);

        if (!$mailbox) {
            Log::warning('WebinarNotificationService: No mailbox available for registration confirmation', [
                'registration_id' => $registration->id,
                'webinar_id' => $webinar->id,
            ]);
            return;
        }

        SendWebinarEmail::dispatch(
            $registration,
            SendWebinarEmail::TYPE_REGISTRATION,
            $mailbox->id
        );
    }

    /**
     * Send reminder emails for upcoming webinars.
     */
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

        $emailType = match ($reminderType) {
            '24h' => SendWebinarEmail::TYPE_REMINDER_24H,
            '1h' => SendWebinarEmail::TYPE_REMINDER_1H,
            '15min' => SendWebinarEmail::TYPE_REMINDER_15MIN,
            default => null,
        };

        $registrations = WebinarRegistration::query()
            ->where($column, false)
            ->where('status', WebinarRegistration::STATUS_REGISTERED)
            ->whereHas('webinar', fn($q) => $q->where('status', Webinar::STATUS_SCHEDULED))
            ->with('webinar')
            ->get();

        foreach ($registrations as $registration) {
            $mailbox = $this->resolveMailbox($registration->webinar);

            if (!$mailbox) {
                Log::warning('WebinarNotificationService: No mailbox for reminder', [
                    'registration_id' => $registration->id,
                ]);
                continue;
            }

            try {
                SendWebinarEmail::dispatch($registration, $emailType, $mailbox->id);
                $registration->update([$column => true]);
                $sent++;
            } catch (\Exception $e) {
                Log::error('Failed to dispatch webinar reminder', [
                    'registration_id' => $registration->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $sent;
    }

    /**
     * Send notifications when webinar starts.
     */
    public function sendWebinarStartedNotifications(Webinar $webinar): int
    {
        $sent = 0;
        $mailbox = $this->resolveMailbox($webinar);

        if (!$mailbox) {
            Log::warning('WebinarNotificationService: No mailbox for started notification', [
                'webinar_id' => $webinar->id,
            ]);
            return 0;
        }

        $registrations = $webinar->registrations()
            ->where('status', WebinarRegistration::STATUS_REGISTERED)
            ->get();

        foreach ($registrations as $registration) {
            try {
                SendWebinarEmail::dispatch(
                    $registration,
                    SendWebinarEmail::TYPE_STARTED,
                    $mailbox->id
                );
                $sent++;
            } catch (\Exception $e) {
                Log::error('Failed to dispatch webinar started notification', [
                    'registration_id' => $registration->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $sent;
    }

    /**
     * Schedule replay notifications to be sent later.
     */
    public function scheduleReplayNotifications(Webinar $webinar): void
    {
        $mailbox = $this->resolveMailbox($webinar);

        if (!$mailbox) {
            Log::warning('WebinarNotificationService: No mailbox for replay notifications', [
                'webinar_id' => $webinar->id,
            ]);
            return;
        }

        $registrations = $webinar->registrations()
            ->where('replay_email_sent', false)
            ->get();

        foreach ($registrations as $registration) {
            SendWebinarEmail::dispatch(
                $registration,
                SendWebinarEmail::TYPE_REPLAY,
                $mailbox->id
            )->delay(now()->addHour());

            $registration->update(['replay_email_sent' => true]);
        }
    }

    /**
     * Resolve the correct mailbox for a webinar.
     * Priority: List's default mailbox → User's default mailbox
     */
    protected function resolveMailbox(Webinar $webinar): ?Mailbox
    {
        return $this->mailProviderService->getMailboxForWebinar($webinar);
    }
}
