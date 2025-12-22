<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

// Events
use App\Events\SubscriberSignedUp;
use App\Events\EmailOpened;
use App\Events\EmailClicked;
use App\Events\SubscriberUnsubscribed;
use App\Events\EmailBounced;
use App\Events\FormSubmitted;
use App\Events\TagAdded;
use App\Events\TagRemoved;
use App\Events\PageVisited;
use App\Events\ReadTimeThresholdReached;
use App\Events\SubscriberBirthday;
use App\Events\SubscriptionAnniversary;

// Listeners
use App\Listeners\TriggerAutomationsListener;
use App\Listeners\SendNewSubscriberNotification;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        // Automation trigger events
        SubscriberSignedUp::class => [
            TriggerAutomationsListener::class,
            SendNewSubscriberNotification::class,
        ],
        EmailOpened::class => [
            TriggerAutomationsListener::class,
        ],
        EmailClicked::class => [
            TriggerAutomationsListener::class,
        ],
        SubscriberUnsubscribed::class => [
            TriggerAutomationsListener::class,
        ],
        EmailBounced::class => [
            TriggerAutomationsListener::class,
        ],
        FormSubmitted::class => [
            TriggerAutomationsListener::class,
        ],
        TagAdded::class => [
            TriggerAutomationsListener::class,
        ],
        TagRemoved::class => [
            TriggerAutomationsListener::class,
        ],
        // New automation events
        PageVisited::class => [
            TriggerAutomationsListener::class,
        ],
        ReadTimeThresholdReached::class => [
            TriggerAutomationsListener::class,
        ],
        SubscriberBirthday::class => [
            TriggerAutomationsListener::class,
        ],
        SubscriptionAnniversary::class => [
            TriggerAutomationsListener::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
