<?php

namespace App\Listeners;

use App\Events\SubscriberSignedUp;
use App\Mail\NewSubscriberNotificationMail;
use App\Models\SystemEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendNewSubscriberNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The queue connection that should be used.
     */
    public string $connection = 'database';

    /**
     * The queue name.
     */
    public string $queue = 'notifications';

    public function __construct(
        protected \App\Services\SystemEmailService $systemEmailService
    ) {}

    /**
     * Handle the event.
     */
    public function handle(SubscriberSignedUp $event): void
    {
        try {
            // Get notification email address with fallback logic
            $notificationEmail = SystemEmail::getNotificationEmail($event->list);

            if (!$notificationEmail) {
                Log::debug('No notification email configured for list or user, skipping notification.', [
                    'list_id' => $event->list->id,
                ]);
                return;
            }

            // Send using SystemEmailService which handles provider selection correctly
            $this->systemEmailService->send(
                'new_subscriber_notification',
                $event->subscriber,
                $event->list,
                [], // No extra data
                $notificationEmail // Override recipient
            );

            Log::info('New subscriber notification sent.', [
                'subscriber_id' => $event->subscriber->id,
                'list_id' => $event->list->id,
                'notification_email' => $notificationEmail,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send new subscriber notification: ' . $e->getMessage(), [
                'subscriber_id' => $event->subscriber->id ?? null,
                'list_id' => $event->list->id ?? null,
                'exception' => $e,
            ]);
        }
    }

    /**
     * Determine number of seconds before retrying a failed job.
     */
    public function backoff(): array
    {
        return [1, 5, 10];
    }

    /**
     * Handle a job failure.
     */
    public function failed(SubscriberSignedUp $event, \Throwable $exception): void
    {
        Log::error('SendNewSubscriberNotification failed permanently', [
            'subscriber_id' => $event->subscriber->id ?? null,
            'list_id' => $event->list->id ?? null,
            'error' => $exception->getMessage(),
        ]);
    }
}
