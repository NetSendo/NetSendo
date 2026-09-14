<?php

namespace Tests\Feature;

use App\Jobs\SendEmailJob;
use App\Jobs\SendSmsJob;
use App\Models\ApiKey;
use App\Models\ContactList;
use App\Models\Message;
use App\Models\MessageQueueEntry;
use App\Models\Subscriber;
use App\Models\User;
use App\Services\CronScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * "Inactive" — in the interface, the API and imports — lowers only
 * `subscribers.is_active_global`, while the CRON send gate used to check only
 * `subscribers.status`. An inactive subscriber kept receiving broadcasts and
 * autoresponders.
 */
class InactiveSubscriberDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private ContactList $list;

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
    }

    private function subscriber(string $email, bool $activeGlobal = true): Subscriber
    {
        $subscriber = Subscriber::create([
            'user_id' => $this->user->id,
            'email' => $email,
            'phone' => '+48500' . random_int(100000, 999999),
            'status' => 'active',
            'is_active_global' => $activeGlobal,
        ]);

        $subscriber->contactLists()->attach($this->list->id, [
            'status' => 'active',
            'subscribed_at' => now()->subDays(5),
        ]);

        return $subscriber;
    }

    private function message(array $attributes = []): Message
    {
        $message = Message::create(array_merge([
            'user_id' => $this->user->id,
            'channel' => 'email',
            'type' => 'broadcast',
            'subject' => 'Hello',
            'content' => '<p>Body</p>',
            'status' => 'scheduled',
            'scheduled_at' => now()->subMinute(),
        ], $attributes));

        $message->contactLists()->attach($this->list->id);

        return $message->fresh();
    }

    private function entryFor(Message $message, Subscriber $subscriber): MessageQueueEntry
    {
        return $message->queueEntries()->where('subscriber_id', $subscriber->id)->firstOrFail();
    }

    private function assertSkippedAsInactive(MessageQueueEntry $entry): void
    {
        $entry->refresh();

        $this->assertSame(MessageQueueEntry::STATUS_SKIPPED, $entry->status);
        $this->assertSame(CronScheduleService::SKIP_REASON_INACTIVE, $entry->error_message);
        // Brain's PerformanceTracker counts skipped entries matching this as unsubscribes
        $this->assertStringNotContainsStringIgnoringCase('unsubscri', $entry->error_message);
    }

    public function test_email_broadcast_skips_an_inactive_subscriber(): void
    {
        $active = $this->subscriber('active@example.com');
        $inactive = $this->subscriber('inactive@example.com', activeGlobal: false);
        $message = $this->message();

        $stats = app(CronScheduleService::class)->processQueue();

        $this->assertSame(1, $stats['dispatched']);
        $this->assertSame(MessageQueueEntry::STATUS_QUEUED, $this->entryFor($message, $active)->status);
        $this->assertSkippedAsInactive($this->entryFor($message, $inactive));
        Queue::assertPushed(SendEmailJob::class, 1);
        Queue::assertPushed(SendEmailJob::class, fn ($job) => $job->subscriber->is($active));
    }

    public function test_due_email_autoresponder_skips_an_inactive_subscriber(): void
    {
        $inactive = $this->subscriber('inactive@example.com', activeGlobal: false);
        $message = $this->message([
            'type' => 'autoresponder',
            'is_active' => true,
            'day' => 1,
            'scheduled_at' => null,
        ]);

        $entry = $message->queueEntries()->create([
            'subscriber_id' => $inactive->id,
            'status' => MessageQueueEntry::STATUS_PLANNED,
            'planned_at' => now()->subDay(),
            'scheduled_for' => now()->subHour(),
        ]);

        $stats = app(CronScheduleService::class)->processQueue();

        $this->assertSame(0, $stats['dispatched']);
        $this->assertSkippedAsInactive($entry);
        Queue::assertNotPushed(SendEmailJob::class);
    }

    public function test_future_autoresponder_waits_so_reactivation_before_it_is_due_resumes_the_sequence(): void
    {
        $subscriber = $this->subscriber('paused@example.com', activeGlobal: false);
        $message = $this->message([
            'type' => 'autoresponder',
            'is_active' => true,
            'day' => 7,
            'scheduled_at' => null,
        ]);

        $entry = $message->queueEntries()->create([
            'subscriber_id' => $subscriber->id,
            'status' => MessageQueueEntry::STATUS_PLANNED,
            'planned_at' => now(),
            'scheduled_for' => now()->addDays(2),
        ]);

        app(CronScheduleService::class)->processQueue();
        $this->assertSame(MessageQueueEntry::STATUS_PLANNED, $entry->fresh()->status);

        $subscriber->update(Subscriber::adminStatusAttributes(Subscriber::STATUS_ACTIVE));
        $this->travel(3)->days();

        app(CronScheduleService::class)->processQueue();
        $this->assertSame(MessageQueueEntry::STATUS_QUEUED, $entry->fresh()->status);
        Queue::assertPushed(SendEmailJob::class, 1);
    }

    public function test_sms_broadcast_skips_an_inactive_subscriber(): void
    {
        $this->list->update(['type' => 'sms']);

        $active = $this->subscriber('active@example.com');
        $inactive = $this->subscriber('inactive@example.com', activeGlobal: false);
        $message = $this->message(['channel' => 'sms', 'subject' => 'SMS campaign', 'content' => 'Hello']);

        $stats = app(CronScheduleService::class)->processSmsQueue();

        $this->assertSame(1, $stats['dispatched']);
        $this->assertSame(MessageQueueEntry::STATUS_QUEUED, $this->entryFor($message, $active)->status);
        $this->assertSkippedAsInactive($this->entryFor($message, $inactive));
        Queue::assertPushed(SendSmsJob::class, 1);
    }

    public function test_marking_inactive_in_the_interface_stops_delivery_and_active_resumes_it(): void
    {
        $subscriber = $this->subscriber('reader@example.com');

        $this->actingAs($this->user)
            ->post(route('subscribers.bulk-status'), ['ids' => [$subscriber->id], 'status' => 'inactive'])
            ->assertSessionHasNoErrors();

        $first = $this->message();
        app(CronScheduleService::class)->processQueue();

        $this->assertSkippedAsInactive($this->entryFor($first, $subscriber));
        Queue::assertNotPushed(SendEmailJob::class);

        $this->actingAs($this->user)
            ->post(route('subscribers.bulk-status'), ['ids' => [$subscriber->id], 'status' => 'active'])
            ->assertSessionHasNoErrors();

        $second = $this->message(['subject' => 'Second']);
        app(CronScheduleService::class)->processQueue();

        $this->assertSame(MessageQueueEntry::STATUS_QUEUED, $this->entryFor($second, $subscriber)->status);
        Queue::assertPushed(SendEmailJob::class, 1);
    }

    public function test_api_status_update_drives_the_flag_queued_sends_check(): void
    {
        $key = ApiKey::generate($this->user->id, 'Test Key', ['subscribers:read', 'subscribers:write'])['key'];
        $subscriber = $this->subscriber('reader@example.com', activeGlobal: false);
        $api = $this->withHeaders(['Authorization' => 'Bearer ' . $key]);

        $api->putJson("/api/v1/subscribers/{$subscriber->id}", ['status' => 'active'])
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $subscriber->refresh();
        $this->assertTrue($subscriber->is_active_global);
        $this->assertSame('active', $subscriber->status);

        $api->putJson("/api/v1/subscribers/{$subscriber->id}", ['status' => 'inactive'])
            ->assertOk()
            ->assertJsonPath('data.status', 'inactive');

        $subscriber->refresh();
        $this->assertFalse($subscriber->is_active_global);
        $this->assertSame('active', $subscriber->status, '"inactive" is a flag, not a value of the status column');
        $this->assertSame('inactive', $subscriber->display_status);
    }
}
