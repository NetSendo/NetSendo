<?php

namespace Tests\Feature;

use App\Models\ContactList;
use App\Models\Message;
use App\Models\MessageQueueEntry;
use App\Models\Subscriber;
use App\Models\User;
use App\Services\CronScheduleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CronScheduleServiceTimezoneTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private ContactList $list;
    private CronScheduleService $cronService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['timezone' => 'UTC']);

        $this->list = ContactList::create([
            'user_id' => $this->user->id,
            'is_public' => true,
            'type' => 'email',
            'name' => 'Test List',
            'settings' => [],
            'webhook_events' => [],
            'sync_settings' => [],
            'required_fields' => [],
        ]);

        $this->cronService = app(CronScheduleService::class);
    }

    /**
     * Helper: create a subscriber with optional timezone
     */
    private function createSubscriber(?string $timezone = null): Subscriber
    {
        $subscriber = Subscriber::create([
            'user_id' => $this->user->id,
            'email' => fake()->unique()->safeEmail(),
            'status' => 'active',
            'is_active_global' => true,
            'timezone' => $timezone,
        ]);

        $subscriber->contactLists()->attach($this->list->id, [
            'status' => 'active',
            'subscribed_at' => now()->subDays(5),
        ]);

        return $subscriber;
    }

    /**
     * Helper: create a message and attach to list
     */
    private function createMessage(array $attributes = []): Message
    {
        $defaults = [
            'user_id' => $this->user->id,
            'channel' => 'email',
            'type' => 'autoresponder',
            'subject' => 'Test message',
            'content' => '<p>Hello</p>',
            'status' => 'scheduled',
            'is_active' => true,
            'day' => 1,
            'time_of_day' => '10:00',
            'timezone' => null,
            'send_in_subscriber_timezone' => false,
        ];

        $message = Message::create(array_merge($defaults, $attributes));
        $message->contactLists()->attach($this->list->id);

        return $message;
    }

    /**
     * Helper: create a queue entry
     */
    private function createQueueEntry(Message $message, Subscriber $subscriber): MessageQueueEntry
    {
        return MessageQueueEntry::create([
            'message_id' => $message->id,
            'subscriber_id' => $subscriber->id,
            'status' => MessageQueueEntry::STATUS_PLANNED,
        ]);
    }

    /**
     * Test: Autoresponder with subscriber timezone enabled skips entry
     * when it's not yet time in the subscriber's timezone.
     *
     * Subscriber in America/New_York (UTC-5).
     * time_of_day = 10:00 (should be 15:00 UTC).
     * Current UTC time = 14:59 → should be skipped.
     */
    public function test_autoresponder_skips_when_not_time_in_subscriber_timezone(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 2, 20, 14, 59, 0, 'UTC'));

        $subscriber = $this->createSubscriber('America/New_York');

        // Subscribed 5 days ago, day=1, so eligible by day count.
        // But 10:00 AM New York = 15:00 UTC, and it's 14:59 UTC now.
        $message = $this->createMessage([
            'day' => 1,
            'time_of_day' => '10:00',
            'send_in_subscriber_timezone' => true,
        ]);

        $entry = $this->createQueueEntry($message, $subscriber);

        $stats = $this->cronService->processQueue();

        // Entry should still be planned (skipped, not dispatched)
        $entry->refresh();
        $this->assertEquals(MessageQueueEntry::STATUS_PLANNED, $entry->status);
        $this->assertEquals(0, $stats['dispatched']);
    }

    /**
     * Test: Autoresponder with subscriber timezone enabled dispatches entry
     * when it IS time in the subscriber's timezone.
     *
     * Subscriber in America/New_York (UTC-5).
     * time_of_day = 10:00 (should be 15:00 UTC).
     * Current UTC time = 15:01 → should be dispatched.
     */
    public function test_autoresponder_dispatches_when_time_in_subscriber_timezone(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 2, 20, 15, 1, 0, 'UTC'));

        $subscriber = $this->createSubscriber('America/New_York');

        $message = $this->createMessage([
            'day' => 1,
            'time_of_day' => '10:00',
            'send_in_subscriber_timezone' => true,
        ]);

        $entry = $this->createQueueEntry($message, $subscriber);

        $stats = $this->cronService->processQueue();

        $entry->refresh();
        $this->assertNotEquals(MessageQueueEntry::STATUS_PLANNED, $entry->status);
        $this->assertGreaterThanOrEqual(1, $stats['dispatched']);
    }

    /**
     * Test: Autoresponder without subscriber timezone uses old behavior.
     * With send_in_subscriber_timezone=false, the time_of_day is in UTC.
     *
     * time_of_day = 10:00 UTC.
     * Current UTC time = 10:01 → should be dispatched.
     */
    public function test_autoresponder_without_subscriber_timezone_uses_utc(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 2, 20, 10, 1, 0, 'UTC'));

        $subscriber = $this->createSubscriber('America/New_York');

        $message = $this->createMessage([
            'day' => 1,
            'time_of_day' => '10:00',
            'send_in_subscriber_timezone' => false, // feature disabled
        ]);

        $entry = $this->createQueueEntry($message, $subscriber);

        $stats = $this->cronService->processQueue();

        $entry->refresh();
        $this->assertNotEquals(MessageQueueEntry::STATUS_PLANNED, $entry->status);
        $this->assertGreaterThanOrEqual(1, $stats['dispatched']);
    }

    /**
     * Test: Subscriber without timezone falls back to message's effective timezone.
     * Message has no explicit timezone, user defaults to UTC.
     * So 10:00 should be treated as 10:00 UTC.
     */
    public function test_autoresponder_subscriber_without_timezone_falls_back(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 2, 20, 10, 1, 0, 'UTC'));

        $subscriber = $this->createSubscriber(null); // no timezone set

        $message = $this->createMessage([
            'day' => 1,
            'time_of_day' => '10:00',
            'send_in_subscriber_timezone' => true,
        ]);

        $entry = $this->createQueueEntry($message, $subscriber);

        $stats = $this->cronService->processQueue();

        // Should fall back to message's effective timezone (UTC) → 10:00 UTC
        $entry->refresh();
        $this->assertNotEquals(MessageQueueEntry::STATUS_PLANNED, $entry->status);
        $this->assertGreaterThanOrEqual(1, $stats['dispatched']);
    }

    /**
     * Test: Subscriber.getEffectiveTimezone returns own timezone when set.
     */
    public function test_subscriber_effective_timezone_returns_own_timezone(): void
    {
        $subscriber = $this->createSubscriber('Europe/Warsaw');
        $this->assertEquals('Europe/Warsaw', $subscriber->getEffectiveTimezone());
    }

    /**
     * Test: Subscriber.getEffectiveTimezone returns fallback when not set.
     */
    public function test_subscriber_effective_timezone_returns_fallback(): void
    {
        $subscriber = $this->createSubscriber(null);
        $this->assertEquals('UTC', $subscriber->getEffectiveTimezone());
        $this->assertEquals('America/New_York', $subscriber->getEffectiveTimezone('America/New_York'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(); // Reset time
        parent::tearDown();
    }
}
