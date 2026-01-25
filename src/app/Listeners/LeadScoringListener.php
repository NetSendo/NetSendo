<?php

namespace App\Listeners;

use App\Events\EmailOpened;
use App\Events\EmailClicked;
use App\Events\EmailBounced;
use App\Events\FormSubmitted;
use App\Events\PageVisited;
use App\Events\PixelProductViewed;
use App\Events\PixelAddToCart;
use App\Events\PixelCheckoutStarted;
use App\Events\CrmContactReplied;
use App\Events\CrmContactCreated;
use App\Events\TagAdded;
use App\Events\TagRemoved;
use App\Services\LeadScoringService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class LeadScoringListener implements ShouldQueue
{
    /**
     * The queue connection that should handle the job.
     */
    public $connection = 'database';

    /**
     * The queue name.
     */
    public $queue = 'scoring';

    public function __construct(
        protected LeadScoringService $scoringService
    ) {}

    /**
     * Handle email opened event.
     */
    public function handleEmailOpened(EmailOpened $event): void
    {
        $this->scoringService->processEvent('email_opened', $event->subscriberId, [
            'message_id' => $event->messageId,
            'ip_address' => $event->ipAddress,
        ]);
    }

    /**
     * Handle email clicked event.
     */
    public function handleEmailClicked(EmailClicked $event): void
    {
        $this->scoringService->processEvent('email_clicked', $event->subscriberId, [
            'message_id' => $event->messageId,
            'clicked_url' => $event->url,
            'ip_address' => $event->ipAddress,
        ]);
    }

    /**
     * Handle email bounced event (negative scoring).
     */
    public function handleEmailBounced(EmailBounced $event): void
    {
        // Bounces don't add positive score but we log it for analytics
        Log::debug('LeadScoring: Email bounced for subscriber', [
            'subscriber_id' => $event->subscriberId,
        ]);
    }

    /**
     * Handle form submitted event.
     */
    public function handleFormSubmitted(FormSubmitted $event): void
    {
        $this->scoringService->processEvent('form_submitted', $event->subscriber->id, [
            'form_id' => $event->form->id,
            'form_name' => $event->form->name,
            'submission_id' => $event->submission->id,
        ]);
    }

    /**
     * Handle page visited event.
     */
    public function handlePageVisited(PageVisited $event): void
    {
        $this->scoringService->processEvent('page_visited', $event->subscriberId, [
            'page_url' => $event->url,
            'page_title' => $event->title ?? null,
        ]);
    }

    /**
     * Handle product viewed event.
     */
    public function handleProductViewed(PixelProductViewed $event): void
    {
        $this->scoringService->processEvent('product_viewed', $event->subscriberId, [
            'product_id' => $event->productId,
            'product_name' => $event->productName,
            'product_price' => $event->productPrice,
            'page_url' => $event->pageUrl,
        ]);
    }

    /**
     * Handle add to cart event.
     */
    public function handleAddToCart(PixelAddToCart $event): void
    {
        $this->scoringService->processEvent('add_to_cart', $event->subscriberId, [
            'product_id' => $event->productId,
            'product_name' => $event->productName,
            'product_price' => $event->productPrice,
            'quantity' => $event->quantity ?? 1,
        ]);
    }

    /**
     * Handle checkout started event.
     */
    public function handleCheckoutStarted(PixelCheckoutStarted $event): void
    {
        $this->scoringService->processEvent('checkout_started', $event->subscriberId, [
            'cart_value' => $event->cartValue ?? null,
        ]);
    }

    /**
     * Handle CRM contact replied event.
     */
    public function handleContactReplied(CrmContactReplied $event): void
    {
        $this->scoringService->processEvent('email_replied', $event->contact->subscriber_id, [
            'channel' => $event->channel,
            'message_id' => $event->messageId,
        ]);
    }

    /**
     * Handle CRM contact created event.
     */
    public function handleContactCreated(CrmContactCreated $event): void
    {
        $this->scoringService->processEvent('contact_created', $event->contact->subscriber_id, [
            'source' => $event->contact->source,
        ]);
    }

    /**
     * Handle tag added event.
     */
    public function handleTagAdded(TagAdded $event): void
    {
        $this->scoringService->processEvent('tag_added', $event->subscriber->id, [
            'tag_id' => $event->tag->id,
            'tag_name' => strtolower($event->tag->name),
        ]);
    }

    /**
     * Handle tag removed event.
     */
    public function handleTagRemoved(TagRemoved $event): void
    {
        $this->scoringService->processEvent('tag_removed', $event->subscriber->id, [
            'tag_id' => $event->tag->id,
            'tag_name' => strtolower($event->tag->name),
        ]);
    }

    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe($events): array
    {
        return [
            EmailOpened::class => 'handleEmailOpened',
            EmailClicked::class => 'handleEmailClicked',
            EmailBounced::class => 'handleEmailBounced',
            FormSubmitted::class => 'handleFormSubmitted',
            PageVisited::class => 'handlePageVisited',
            PixelProductViewed::class => 'handleProductViewed',
            PixelAddToCart::class => 'handleAddToCart',
            PixelCheckoutStarted::class => 'handleCheckoutStarted',
            CrmContactReplied::class => 'handleContactReplied',
            CrmContactCreated::class => 'handleContactCreated',
            TagAdded::class => 'handleTagAdded',
            TagRemoved::class => 'handleTagRemoved',
        ];
    }
}
