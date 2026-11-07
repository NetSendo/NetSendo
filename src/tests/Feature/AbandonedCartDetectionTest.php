<?php

namespace Tests\Feature;

use App\Events\PixelCartAbandoned;
use App\Models\PixelEvent;
use App\Models\Subscriber;
use App\Models\SubscriberDevice;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * pixel:detect-abandoned-carts feeds the pixel_cart_abandoned automation
 * trigger. It was never scheduled, and when run it re-triggered the same cart
 * every 24 hours for as long as the cart stayed in its 7-day window.
 */
class AbandonedCartDetectionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Subscriber $subscriber;
    private SubscriberDevice $device;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([PixelCartAbandoned::class]);

        $this->user = User::factory()->create();
        $this->subscriber = Subscriber::create([
            'user_id' => $this->user->id,
            'email' => 'shopper@example.com',
            'status' => 'active',
            'is_active_global' => true,
        ]);
        $this->device = SubscriberDevice::create([
            'subscriber_id' => $this->subscriber->id,
            'user_id' => $this->user->id,
            'visitor_token' => 'visitor-token-1',
            'first_seen_at' => now()->subDays(2),
            'last_seen_at' => now()->subHours(2),
        ]);
    }

    private function pixelEvent(string $type, $at, array $extra = []): PixelEvent
    {
        return PixelEvent::create(array_merge([
            'subscriber_device_id' => $this->device->id,
            'subscriber_id' => $this->subscriber->id,
            'user_id' => $this->user->id,
            'event_type' => $type,
            'event_category' => 'ecommerce',
            'page_url' => 'https://shop.example.com/cart',
            'occurred_at' => $at,
        ], $extra));
    }

    private function detect(): void
    {
        $this->artisan('pixel:detect-abandoned-carts')->assertSuccessful();
    }

    public function test_the_command_is_scheduled(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event) => str_contains((string) $event->command, 'pixel:detect-abandoned-carts'));

        $this->assertCount(1, $events);
        $this->assertSame('*/15 * * * *', $events->first()->expression);
        $this->assertTrue($events->first()->withoutOverlapping);
    }

    public function test_an_abandoned_cart_triggers_once(): void
    {
        $this->pixelEvent('add_to_cart', now()->subHours(2), ['cart_value' => 199, 'product_id' => 'SKU-1', 'product_name' => 'Kettle']);

        $this->detect();

        Event::assertDispatchedTimes(PixelCartAbandoned::class, 1);
        Event::assertDispatched(PixelCartAbandoned::class, fn ($e) => $e->subscriberId === $this->subscriber->id
            && $e->userId === $this->user->id
            && $e->deviceId === $this->device->id);

        // The next runs, also a day later, do not trigger the same cart again
        $this->detect();
        $this->travel(25)->hours();
        $this->detect();

        Event::assertDispatchedTimes(PixelCartAbandoned::class, 1);
    }

    public function test_new_cart_activity_after_a_trigger_can_trigger_again(): void
    {
        $this->pixelEvent('add_to_cart', now()->subHours(5));
        $this->detect();

        $this->travel(1)->hours();
        $this->pixelEvent('checkout_started', now()->subMinutes(5));
        $this->travel(2)->hours();
        $this->detect();

        Event::assertDispatchedTimes(PixelCartAbandoned::class, 2);
    }

    public function test_a_cart_followed_by_a_purchase_is_not_abandoned(): void
    {
        $this->pixelEvent('add_to_cart', now()->subHours(3));
        $this->pixelEvent('purchase', now()->subHours(2));

        $this->detect();

        Event::assertNotDispatched(PixelCartAbandoned::class);
    }

    public function test_a_cart_with_recent_activity_is_not_abandoned_yet(): void
    {
        $this->pixelEvent('add_to_cart', now()->subHours(2));
        $this->pixelEvent('page_view', now()->subMinutes(10));

        $this->detect();

        Event::assertNotDispatched(PixelCartAbandoned::class);
    }

    public function test_carts_older_than_the_max_age_are_ignored(): void
    {
        // Stays out of the first scheduled run instead of mailing about week-old carts
        $this->pixelEvent('add_to_cart', now()->subDays(3));

        $this->detect();

        Event::assertNotDispatched(PixelCartAbandoned::class);
    }
}
