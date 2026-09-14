<?php

namespace Tests\Feature\Api;

use App\Jobs\SendEmailJob;
use App\Models\ApiKey;
use App\Models\ContactList;
use App\Models\Mailbox;
use App\Models\Message;
use App\Models\MessageQueueEntry;
use App\Models\Subscriber;
use App\Models\Tag;
use App\Models\User;
use App\Services\CronScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * `POST /api/v1/email/send` and `POST /api/v1/email/batch` (when targeting
 * subscriber_ids or tag_ids only) create a broadcast addressed by its queue
 * entries alone — no contact list, no CRM contact. The per-minute CRON syncs
 * every due broadcast against its audience before dispatching, and such a
 * message has no audience to sync against: the sync used to mark every entry
 * "skipped" and the broadcast was then auto-completed as sent with nothing
 * delivered.
 */
class EmailApiDispatchTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $apiKey;
    private ContactList $list;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->user = User::factory()->create();
        $this->apiKey = ApiKey::generate($this->user->id, 'Test Key', ['email:read', 'email:write'])['key'];

        $this->list = ContactList::create([
            'user_id' => $this->user->id,
            'name' => 'Readers',
            'type' => 'email',
            'is_public' => true,
        ]);

        Mailbox::create([
            'user_id' => $this->user->id,
            'name' => 'Test Mailbox',
            'provider' => 'smtp',
            'from_email' => 'hello@example.com',
            'from_name' => 'Tester',
            'is_default' => true,
            'is_active' => true,
            'allowed_types' => ['broadcast', 'autoresponder', 'system'],
            'credentials' => ['host' => 'localhost', 'port' => 1025],
        ]);
    }

    private function subscriber(string $email, bool $onList = false): Subscriber
    {
        $subscriber = Subscriber::create([
            'user_id' => $this->user->id,
            'email' => $email,
            'status' => 'active',
            'is_active_global' => true,
        ]);

        if ($onList) {
            $subscriber->contactLists()->attach($this->list->id, [
                'status' => 'active',
                'subscribed_at' => now(),
            ]);
        }

        return $subscriber;
    }

    private function api()
    {
        return $this->withHeaders(['Authorization' => 'Bearer ' . $this->apiKey]);
    }

    private function runCron(): array
    {
        return app(CronScheduleService::class)->processQueue();
    }

    private function assertDispatchedTo(Message $message, int $expected): void
    {
        $entries = $message->queueEntries()->get();

        $this->assertCount($expected, $entries);
        $this->assertTrue(
            $entries->every(fn ($entry) => $entry->status === MessageQueueEntry::STATUS_QUEUED),
            'Entries were not dispatched: ' . $entries->map(fn ($entry) => "{$entry->status} ({$entry->error_message})")->implode(', ')
        );
        Queue::assertPushed(SendEmailJob::class, $expected);

        $message->refresh();
        $this->assertSame('scheduled', $message->status, 'Broadcast must not be auto-completed while its entries are in flight');
        $this->assertSame($expected, $message->planned_recipients_count);
    }

    public function test_single_api_email_is_dispatched_by_cron(): void
    {
        $id = $this->api()
            ->postJson('/api/v1/email/send', [
                'email' => 'reader@example.com',
                'subject' => 'Hello',
                'content' => '<p>Body</p>',
            ])
            ->assertStatus(202)
            ->json('data.id');

        $stats = $this->runCron();

        $this->assertSame(1, $stats['dispatched']);
        $this->assertDispatchedTo(Message::findOrFail($id), 1);
    }

    public function test_batch_to_subscriber_ids_is_dispatched_by_cron(): void
    {
        $a = $this->subscriber('a@example.com');
        $b = $this->subscriber('b@example.com');

        $id = $this->api()
            ->postJson('/api/v1/email/batch', [
                'subject' => 'Hello',
                'content' => '<p>Body</p>',
                'subscriber_ids' => [$a->id, $b->id],
            ])
            ->assertStatus(202)
            ->json('data.id');

        $this->runCron();

        $this->assertDispatchedTo(Message::findOrFail($id), 2);
    }

    public function test_batch_to_tag_ids_is_dispatched_by_cron(): void
    {
        $tag = Tag::create(['user_id' => $this->user->id, 'name' => 'VIP']);
        $this->subscriber('a@example.com')->tags()->attach($tag->id);
        $this->subscriber('b@example.com')->tags()->attach($tag->id);
        $this->subscriber('untagged@example.com');

        $id = $this->api()
            ->postJson('/api/v1/email/batch', [
                'subject' => 'Hello',
                'content' => '<p>Body</p>',
                'tag_ids' => [$tag->id],
            ])
            ->assertStatus(202)
            ->json('data.id');

        $this->runCron();

        $this->assertDispatchedTo(Message::findOrFail($id), 2);
    }

    public function test_list_based_batch_still_drops_recipients_who_left_the_list(): void
    {
        $stays = $this->subscriber('stays@example.com', onList: true);
        $leaves = $this->subscriber('leaves@example.com', onList: true);

        $id = $this->api()
            ->postJson('/api/v1/email/batch', [
                'subject' => 'Hello',
                'content' => '<p>Body</p>',
                'list_id' => $this->list->id,
            ])
            ->assertStatus(202)
            ->json('data.id');

        $leaves->contactLists()->updateExistingPivot($this->list->id, ['status' => 'unsubscribed']);

        $this->runCron();

        $message = Message::findOrFail($id);
        $this->assertSame(MessageQueueEntry::STATUS_QUEUED, $message->queueEntries()->where('subscriber_id', $stays->id)->value('status'));
        $this->assertSame(MessageQueueEntry::STATUS_SKIPPED, $message->queueEntries()->where('subscriber_id', $leaves->id)->value('status'));
        Queue::assertPushed(SendEmailJob::class, 1);
    }

    public function test_broadcast_whose_list_was_deleted_still_skips_its_pending_entries(): void
    {
        $subscriber = $this->subscriber('reader@example.com', onList: true);

        $message = Message::create([
            'user_id' => $this->user->id,
            'channel' => 'email',
            'type' => 'broadcast',
            'subject' => 'Hello',
            'content' => '<p>Body</p>',
            'status' => 'scheduled',
            'scheduled_at' => now(),
        ]);
        $message->contactLists()->attach($this->list->id);
        $message->queueEntries()->create([
            'subscriber_id' => $subscriber->id,
            'status' => MessageQueueEntry::STATUS_PLANNED,
            'planned_at' => now(),
        ]);

        // Soft delete: the pivot row survives, the relation no longer sees the list
        $this->list->delete();

        $this->runCron();

        $this->assertSame(MessageQueueEntry::STATUS_SKIPPED, $message->queueEntries()->value('status'));
        Queue::assertNothingPushed();
        $this->assertSame('sent', $message->fresh()->status);
    }
}
