<?php

namespace Tests\Feature;

use App\Models\ContactList;
use App\Models\Message;
use App\Models\MessageQueueEntry;
use App\Models\Subscriber;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Populating a broadcast's queue must be idempotent: saving a message in the
 * editor and the per-minute CRON can sync the same message at the same time
 * (issue #30 — SQL 1062 on message_queue_entries).
 */
class MessageQueueSyncTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected ContactList $list;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->list = ContactList::create([
            'user_id' => $this->user->id,
            'name' => 'Main list',
        ]);
    }

    protected function subscriber(string $email): Subscriber
    {
        $subscriber = Subscriber::create([
            'user_id' => $this->user->id,
            'email' => $email,
            'is_active_global' => true,
        ]);

        $subscriber->contactLists()->attach($this->list->id, [
            'status' => 'active',
            'subscribed_at' => now(),
        ]);

        return $subscriber;
    }

    protected function broadcast(): Message
    {
        $message = Message::create([
            'user_id' => $this->user->id,
            'channel' => 'email',
            'type' => 'broadcast',
            'subject' => 'Hello',
            'content' => 'Body',
            'status' => 'scheduled',
            'scheduled_at' => now(),
        ]);

        $message->contactLists()->attach($this->list->id);

        return $message->fresh();
    }

    public function test_sync_is_idempotent(): void
    {
        $this->subscriber('a@example.com');
        $this->subscriber('b@example.com');
        $message = $this->broadcast();

        $this->assertSame(2, $message->syncPlannedRecipients()['added']);
        $this->assertSame(0, $message->fresh()->syncPlannedRecipients()['added']);

        $entries = $message->queueEntries()->get();
        $this->assertCount(2, $entries);
        $this->assertTrue($entries->every(fn ($entry) => $entry->status === MessageQueueEntry::STATUS_PLANNED));
        $this->assertTrue($entries->every(fn ($entry) => $entry->planned_at !== null));
    }

    public function test_sync_survives_a_concurrent_sync_of_the_same_message(): void
    {
        $this->subscriber('a@example.com');
        $raced = $this->subscriber('b@example.com');
        $this->subscriber('c@example.com');
        $message = $this->broadcast();

        // Right after this sync has read the existing entries, a second
        // process (CRON) plans one of the recipients — the exact interleaving
        // that used to crash the save with a duplicate-entry error.
        $racedIn = false;
        DB::listen(function (QueryExecuted $query) use (&$racedIn, $message, $raced) {
            if ($racedIn || !preg_match('/^select\s+\W?subscriber_id\W?\s+from\s+\W?message_queue_entries/i', $query->sql)) {
                return;
            }
            $racedIn = true;
            DB::table('message_queue_entries')->insert([
                'message_id' => $message->id,
                'subscriber_id' => $raced->id,
                'status' => MessageQueueEntry::STATUS_PLANNED,
                'planned_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $result = $message->syncPlannedRecipients();

        $this->assertTrue($racedIn, 'The concurrent insert was never simulated');
        $this->assertSame(2, $result['added'], 'Only rows this sync inserted count as added');
        $this->assertSame(3, $message->queueEntries()->count());
        $this->assertSame(1, $message->queueEntries()->where('subscriber_id', $raced->id)->count());
    }
}
