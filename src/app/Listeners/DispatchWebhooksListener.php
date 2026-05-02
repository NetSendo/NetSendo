<?php

namespace App\Listeners;

use App\Events\SubscriberSignedUp;
use App\Events\SubscriberUnsubscribed;
use App\Events\EmailBounced;
use App\Events\TagAdded;
use App\Events\TagRemoved;
use App\Models\Subscriber;
use App\Services\WebhookDispatcher;
use Illuminate\Support\Facades\Log;

/**
 * Central webhook dispatcher listener.
 *
 * Listens to all subscriber-lifecycle Laravel events and dispatches
 * the corresponding webhook calls via WebhookDispatcher.
 *
 * This ensures webhooks fire regardless of the entry point (Web UI,
 * API, form submission, unsubscribe link, bounce, CSV import, etc.).
 *
 * Previously, WebhookDispatcher::dispatch() was only called inline
 * from specific API controllers and FormSubmissionService, meaning
 * events triggered via the web UI, unsubscribe links, bounce
 * processing, or tag management never produced webhook deliveries.
 *
 * @see \App\Services\WebhookDispatcher
 * @see \App\Providers\EventServiceProvider
 */
class DispatchWebhooksListener
{
    public function __construct(
        protected WebhookDispatcher $webhookDispatcher
    ) {}

    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        try {
            match (true) {
                $event instanceof SubscriberSignedUp => $this->handleSubscriberSignedUp($event),
                $event instanceof SubscriberUnsubscribed => $this->handleSubscriberUnsubscribed($event),
                $event instanceof EmailBounced => $this->handleEmailBounced($event),
                $event instanceof TagAdded => $this->handleTagAdded($event),
                $event instanceof TagRemoved => $this->handleTagRemoved($event),
                default => null,
            };
        } catch (\Exception $e) {
            Log::error('DispatchWebhooksListener: Failed to dispatch webhook', [
                'event' => get_class($event),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle subscriber signup — dispatches subscriber.created and subscriber.subscribed.
     */
    protected function handleSubscriberSignedUp(SubscriberSignedUp $event): void
    {
        $subscriber = $event->subscriber;
        $userId = $event->list->user_id;

        $subscriberData = $this->formatSubscriberData($subscriber, $event->source);

        // subscriber.created — a new subscriber record was made
        $this->webhookDispatcher->dispatch($userId, 'subscriber.created', [
            'subscriber' => $subscriberData,
            'list_id' => $event->list->id,
            'list_name' => $event->list->name,
            'source' => $event->source,
        ]);

        // subscriber.subscribed — subscriber was added to a list
        $this->webhookDispatcher->dispatch($userId, 'subscriber.subscribed', [
            'subscriber' => $subscriberData,
            'list_id' => $event->list->id,
            'list_name' => $event->list->name,
            'source' => $event->source,
        ]);
    }

    /**
     * Handle subscriber unsubscribe — dispatches subscriber.unsubscribed.
     */
    protected function handleSubscriberUnsubscribed(SubscriberUnsubscribed $event): void
    {
        $subscriber = $event->subscriber;
        $userId = $event->list->user_id;

        $this->webhookDispatcher->dispatch($userId, 'subscriber.unsubscribed', [
            'subscriber' => $this->formatSubscriberData($subscriber, $event->reason),
            'list_id' => $event->list->id,
            'list_name' => $event->list->name,
            'reason' => $event->reason,
        ]);
    }

    /**
     * Handle email bounce — dispatches subscriber.bounced.
     */
    protected function handleEmailBounced(EmailBounced $event): void
    {
        $subscriber = $event->getSubscriber();

        if (!$subscriber) {
            return;
        }

        $userId = $subscriber->user_id;

        $this->webhookDispatcher->dispatch($userId, 'subscriber.bounced', [
            'subscriber' => $this->formatSubscriberData($subscriber, 'bounce'),
            'bounce_type' => $event->bounceType,
            'bounce_reason' => $event->bounceReason,
            'message_id' => $event->messageId,
        ]);
    }

    /**
     * Handle tag added — dispatches subscriber.tag_added.
     */
    protected function handleTagAdded(TagAdded $event): void
    {
        $subscriber = $event->subscriber;
        $userId = $subscriber->user_id;

        $this->webhookDispatcher->dispatch($userId, 'subscriber.tag_added', [
            'subscriber' => $this->formatSubscriberData($subscriber, 'tag'),
            'tag_id' => $event->tag->id,
            'tag_name' => $event->tag->name,
        ]);
    }

    /**
     * Handle tag removed — dispatches subscriber.tag_removed.
     */
    protected function handleTagRemoved(TagRemoved $event): void
    {
        $subscriber = $event->subscriber;
        $userId = $subscriber->user_id;

        $this->webhookDispatcher->dispatch($userId, 'subscriber.tag_removed', [
            'subscriber' => $this->formatSubscriberData($subscriber, 'tag'),
            'tag_id' => $event->tag->id,
            'tag_name' => $event->tag->name,
        ]);
    }

    /**
     * Format subscriber data for webhook payload.
     */
    protected function formatSubscriberData(Subscriber $subscriber, string $source = 'system'): array
    {
        return [
            'id' => $subscriber->id,
            'email' => $subscriber->email,
            'first_name' => $subscriber->first_name,
            'last_name' => $subscriber->last_name,
            'phone' => $subscriber->phone,
            'source' => $source,
        ];
    }
}
