<?php

namespace App\Listeners;

use App\Events\SubscriberUnsubscribed;
use App\Models\SystemEmail;
use App\Services\SystemEmailService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Sends confirmation email when a subscriber unsubscribes.
 */
class SendUnsubscribeConfirmation implements ShouldQueue
{
    use InteractsWithQueue;

    public string $connection = 'database';
    public string $queue = 'notifications';

    public function __construct(
        protected SystemEmailService $emailService
    ) {}

    public function handle(SubscriberUnsubscribed $event): void
    {
        try {
            // Check if email should be sent
            $systemEmail = SystemEmail::getBySlug('unsubscribed_confirmation', $event->list->id);

            if (!$systemEmail) {
                Log::debug('Unsubscribe confirmation email not configured or disabled.', [
                    'list_id' => $event->list->id,
                ]);
                return;
            }

            // Send confirmation email
            $this->emailService->sendUnsubscribeConfirmation(
                $event->subscriber,
                $event->list
            );

            Log::info('Unsubscribe confirmation email sent.', [
                'subscriber_id' => $event->subscriber->id,
                'list_id' => $event->list->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send unsubscribe confirmation: ' . $e->getMessage(), [
                'subscriber_id' => $event->subscriber->id ?? null,
                'list_id' => $event->list->id ?? null,
                'exception' => $e,
            ]);
        }
    }

    public function backoff(): array
    {
        return [1, 5, 10];
    }

    public function failed(SubscriberUnsubscribed $event, \Throwable $exception): void
    {
        Log::error('SendUnsubscribeConfirmation failed permanently', [
            'subscriber_id' => $event->subscriber->id ?? null,
            'list_id' => $event->list->id ?? null,
            'error' => $exception->getMessage(),
        ]);
    }
}
