<?php

namespace App\Listeners;

use App\Events\SubscriberSignedUp;
use App\Events\EmailOpened;
use App\Events\EmailClicked;
use App\Events\SubscriberUnsubscribed;
use App\Events\EmailBounced;
use App\Events\FormSubmitted;
use App\Events\TagAdded;
use App\Events\TagRemoved;
use App\Services\Automation\AutomationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class TriggerAutomationsListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The queue connection that should be used.
     */
    public string $connection = 'database';

    /**
     * The queue name.
     */
    public string $queue = 'automations';

    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        try {
            $automationService = app(AutomationService::class);
            
            $triggerEvent = $this->mapEventToTrigger($event);
            $context = $this->getEventContext($event);
            
            if ($triggerEvent && $context) {
                $automationService->processEvent($triggerEvent, $context);
            }
        } catch (\Exception $e) {
            Log::error('TriggerAutomationsListener error: ' . $e->getMessage(), [
                'event' => get_class($event),
                'exception' => $e,
            ]);
        }
    }

    /**
     * Map event class to trigger type string.
     */
    protected function mapEventToTrigger(object $event): ?string
    {
        return match (get_class($event)) {
            SubscriberSignedUp::class => 'subscriber_signup',
            EmailOpened::class => 'email_opened',
            EmailClicked::class => 'email_clicked',
            SubscriberUnsubscribed::class => 'subscriber_unsubscribed',
            EmailBounced::class => 'email_bounced',
            FormSubmitted::class => 'form_submitted',
            TagAdded::class => 'tag_added',
            TagRemoved::class => 'tag_removed',
            default => null,
        };
    }

    /**
     * Extract context from event.
     */
    protected function getEventContext(object $event): ?array
    {
        if (method_exists($event, 'getContext')) {
            return $event->getContext();
        }
        return null;
    }

    /**
     * Get subscriber from event.
     */
    protected function getSubscriberFromEvent(object $event): ?\App\Models\Subscriber
    {
        // Direct subscriber property
        if (property_exists($event, 'subscriber') && $event->subscriber instanceof \App\Models\Subscriber) {
            return $event->subscriber;
        }

        // Method to get subscriber
        if (method_exists($event, 'getSubscriber')) {
            return $event->getSubscriber();
        }

        // Try subscriber_id property
        if (property_exists($event, 'subscriberId')) {
            return \App\Models\Subscriber::find($event->subscriberId);
        }

        return null;
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
    public function failed(object $event, \Throwable $exception): void
    {
        Log::error('TriggerAutomationsListener failed permanently', [
            'event' => get_class($event),
            'error' => $exception->getMessage(),
        ]);
    }
}
