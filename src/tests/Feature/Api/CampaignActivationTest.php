<?php

namespace Tests\Feature\Api;

use App\Events\SubscriberSignedUp;
use App\Models\ApiKey;
use App\Models\ContactList;
use App\Models\Mailbox;
use App\Models\Message;
use App\Models\MessageQueueEntry;
use App\Models\Subscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Activating a campaign through the public API used to write statuses the send
 * pipeline does not know ('active' for autoresponders, 'sending' for
 * broadcasts). Both the signup listener and the cron processor only ever look
 * at `scheduled` messages, so those campaigns went silent: an autoresponder
 * never created a queue entry for anybody who subscribed, and a broadcast kept
 * its planned entries but was never dispatched.
 */
class CampaignActivationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $apiKey;
    private ContactList $list;
    private Mailbox $mailbox;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->apiKey = ApiKey::generate($this->user->id, 'Test Key', ['messages:read', 'messages:write'])['key'];

        $this->list = ContactList::create([
            'user_id' => $this->user->id,
            'name' => 'Readers',
            'type' => 'email',
            'is_public' => true,
        ]);

        $this->mailbox = Mailbox::create([
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

    private function createMessage(array $attributes = []): Message
    {
        $message = Message::create(array_merge([
            'user_id' => $this->user->id,
            'channel' => 'email',
            'type' => 'autoresponder',
            'subject' => 'Welcome',
            'content' => '<p>Hello</p>',
            'status' => 'draft',
            'is_active' => false,
            'day' => 0,
            'mailbox_id' => $this->mailbox->id,
        ], $attributes));

        $message->contactLists()->attach($this->list->id);

        return $message->fresh('contactLists');
    }

    private function subscribe(string $email, ?\Carbon\Carbon $subscribedAt = null): Subscriber
    {
        $subscriber = Subscriber::create([
            'user_id' => $this->user->id,
            'email' => $email,
            'status' => 'active',
            'is_active_global' => true,
        ]);

        $subscriber->contactLists()->attach($this->list->id, [
            'status' => 'active',
            'subscribed_at' => $subscribedAt ?? now(),
        ]);

        return $subscriber;
    }

    public function test_api_activation_leaves_autoresponder_in_a_status_the_pipeline_processes(): void
    {
        $message = $this->createMessage();

        $this->withHeaders(['Authorization' => 'Bearer ' . $this->apiKey])
            ->postJson("/api/v1/messages/{$message->id}/send")
            ->assertStatus(200);

        $message->refresh();

        $this->assertSame('scheduled', $message->status);
        $this->assertTrue((bool) $message->is_active);
        $this->assertNotNull($message->scheduled_at);
    }

    public function test_subscriber_signing_up_to_an_api_activated_autoresponder_gets_a_queue_entry(): void
    {
        $message = $this->createMessage();

        $this->withHeaders(['Authorization' => 'Bearer ' . $this->apiKey])
            ->postJson("/api/v1/messages/{$message->id}/send")
            ->assertStatus(200);

        $subscriber = $this->subscribe('reader@example.com');
        event(new SubscriberSignedUp($subscriber, $this->list, null, 'test'));

        $entry = MessageQueueEntry::where('message_id', $message->id)
            ->where('subscriber_id', $subscriber->id)
            ->first();

        $this->assertNotNull($entry, 'Signing up must schedule the day-0 autoresponder');
        $this->assertSame(MessageQueueEntry::STATUS_PLANNED, $entry->status);
    }

    public function test_broadcast_sent_via_api_is_scheduled_for_the_cron_processor(): void
    {
        $message = $this->createMessage(['type' => 'broadcast', 'day' => null]);
        $this->subscribe('reader@example.com');

        $this->withHeaders(['Authorization' => 'Bearer ' . $this->apiKey])
            ->postJson("/api/v1/messages/{$message->id}/send")
            ->assertStatus(200);

        $message->refresh();

        $this->assertSame('scheduled', $message->status);
        $this->assertNotNull($message->scheduled_at);
        $this->assertSame(1, $message->queueEntries()->count());
    }

    public function test_activating_a_draft_queue_message_in_the_ui_promotes_it_to_scheduled(): void
    {
        $message = $this->createMessage();

        $this->actingAs($this->user)
            ->postJson(route('messages.toggle-active', $message->id))
            ->assertStatus(200)
            ->assertJsonPath('is_active', true);

        $message->refresh();

        $this->assertTrue((bool) $message->is_active);
        $this->assertSame('scheduled', $message->status);
    }

    public function test_repair_migration_restores_statuses_written_by_the_old_endpoint(): void
    {
        $autoresponder = $this->createMessage(['status' => 'active', 'is_active' => true]);
        $undelivered = $this->createMessage(['type' => 'broadcast', 'status' => 'sending', 'sent_count' => 0]);
        $partiallySent = $this->createMessage(['type' => 'broadcast', 'status' => 'sending', 'sent_count' => 3]);

        $migration = require database_path('migrations/2026_09_05_120000_repair_api_activated_message_statuses.php');
        $migration->up();

        $this->assertSame('scheduled', $autoresponder->fresh()->status);
        $this->assertTrue((bool) $autoresponder->fresh()->is_active);
        $this->assertNotNull($autoresponder->fresh()->scheduled_at);

        // Broadcasts are never auto-dispatched by a migration — the owner decides.
        $this->assertSame('draft', $undelivered->fresh()->status);
        $this->assertSame('sent', $partiallySent->fresh()->status);
    }

    public function test_backfill_schedules_a_recipient_whose_send_time_only_just_passed(): void
    {
        $message = $this->createMessage(['status' => 'scheduled', 'is_active' => true]);

        // Day 0 is due at the signup instant, so by the time cron runs the send
        // time has always just passed.
        $justNow = $this->subscribe('just-now@example.com', now()->subMinutes(5));
        $longAgo = $this->subscribe('long-ago@example.com', now()->subDays(3));

        $message->fresh(['contactLists', 'excludedLists'])->syncPlannedRecipients();

        $this->assertSame(
            1,
            MessageQueueEntry::where('message_id', $message->id)->where('subscriber_id', $justNow->id)->count(),
            'A recipient due minutes ago must still be backfilled'
        );
        $this->assertSame(
            0,
            MessageQueueEntry::where('message_id', $message->id)->where('subscriber_id', $longAgo->id)->count(),
            'A long overdue recipient stays "missed" for the explicit send-to-missed action'
        );
    }
}
