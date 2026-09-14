<?php

namespace Tests\Feature;

use App\Events\SubscriberSignedUp;
use App\Events\TagAdded;
use App\Models\ContactList;
use App\Models\Subscriber;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Events\DiscoverEvents;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Laravel 11+ `Application::configure()` registers the framework's own event
 * provider, which auto-discovers every `handle*` method with a typed event in
 * app/Listeners — on top of the explicit map in App\Providers\EventServiceProvider.
 * Each such listener ran twice per event: two new-subscriber notifications,
 * two autoresponder runs per signup, double lead scoring.
 */
class EventListenerRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private ContactList $list;
    private Subscriber $subscriber;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->user = User::factory()->create(['timezone' => 'UTC']);

        $this->list = ContactList::create([
            'user_id' => $this->user->id,
            'name' => 'Newsletter',
            'type' => 'email',
            'is_public' => true,
            'settings' => [],
            'webhook_events' => [],
            'sync_settings' => [],
            'required_fields' => [],
        ]);

        $this->subscriber = Subscriber::create([
            'user_id' => $this->user->id,
            'email' => 'jan@example.com',
            'status' => 'active',
        ]);
    }

    public function test_signup_queues_each_listener_once(): void
    {
        event(new SubscriberSignedUp($this->subscriber, $this->list));

        $this->assertSame([
            'CreateAutoresponderQueueEntries@handle' => 1,
            'EnrollInTriggeredFunnels@handle' => 1,
            'SendNewSubscriberNotification@handle' => 1,
        ], $this->queuedListenerCounts());
    }

    public function test_tag_added_scores_the_lead_once(): void
    {
        $tag = Tag::create(['user_id' => $this->user->id, 'name' => 'VIP']);

        event(new TagAdded($this->subscriber, $tag));

        $this->assertSame([
            'EnrollInTriggeredFunnels@handle' => 1,
            'LeadScoringListener@handleTagAdded' => 1,
        ], $this->queuedListenerCounts());
    }

    public function test_no_app_listener_is_registered_twice_for_an_event(): void
    {
        $duplicates = [];

        foreach ($this->registeredListeners() as $event => $listeners) {
            foreach (array_count_values($listeners) as $listener => $count) {
                if ($count > 1) {
                    $duplicates[] = "{$event} → {$listener} ×{$count}";
                }
            }
        }

        $this->assertSame([], $duplicates);
    }

    public function test_every_typed_listener_method_is_registered(): void
    {
        $registered = $this->registeredListeners();
        $missing = [];

        // What discovery would find must be covered by the explicit map, so
        // turning discovery off leaves no listener (e.g. CrmAutomationListener)
        // silently unregistered.
        foreach (DiscoverEvents::within(app_path('Listeners'), base_path()) as $event => $listeners) {
            foreach ($listeners as $listener) {
                if (! in_array($listener, $registered[$event] ?? [], true)) {
                    $missing[] = "{$event} → {$listener}";
                }
            }
        }

        $this->assertSame([], $missing);
    }

    /**
     * @return array<string, int> "Listener@method" => times queued
     */
    private function queuedListenerCounts(): array
    {
        $counts = Queue::pushed(CallQueuedListener::class)
            ->map(fn (CallQueuedListener $job) => class_basename($job->class).'@'.$job->method)
            ->countBy()
            ->all();

        ksort($counts);

        return $counts;
    }

    /**
     * App listeners per event, normalised to "Class@method" whether they came
     * from $listen ("Class"), $subscribe ([Class, method]) or discovery.
     *
     * @return array<string, list<string>>
     */
    private function registeredListeners(): array
    {
        $registered = [];

        foreach (Event::getRawListeners() as $event => $listeners) {
            foreach ($listeners as $listener) {
                if (is_string($listener)) {
                    [$class, $method] = array_pad(explode('@', $listener, 2), 2, 'handle');
                } elseif (is_array($listener) && is_string($listener[0] ?? null)) {
                    [$class, $method] = $listener;
                } else {
                    continue;
                }

                if (str_starts_with($class, 'App\\')) {
                    $registered[$event][] = "{$class}@{$method}";
                }
            }
        }

        return $registered;
    }
}
