<?php

namespace Tests\Feature;

use App\Jobs\SendEmailJob;
use App\Models\ApiKey;
use App\Models\ContactList;
use App\Models\CrmContact;
use App\Models\Mailbox;
use App\Models\Message;
use App\Models\MessageQueueEntry;
use App\Models\Subscriber;
use App\Models\User;
use App\Services\CronScheduleService;
use App\Services\Segmentation\SubscriberFieldFilterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * A subscriber marked "Inactive" (`is_active_global = false`) keeps their active
 * list memberships, but is no recipient: not planned, not counted in any
 * audience figure. An entry planned before the deactivation is left to the
 * CRON send gate (InactiveSubscriberDeliveryTest), so reactivating the contact
 * before it is due still delivers it.
 */
class InactiveSubscriberAudienceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private ContactList $list;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->user = User::factory()->create();

        $this->list = ContactList::create([
            'user_id' => $this->user->id,
            'name' => 'Readers',
            'type' => 'email',
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

    private function subscriber(string $email, bool $active = true, bool $onList = true): Subscriber
    {
        $subscriber = Subscriber::create([
            'user_id' => $this->user->id,
            'email' => $email,
            'status' => 'active',
            'is_active_global' => $active,
        ]);

        if ($onList) {
            $subscriber->contactLists()->attach($this->list->id, [
                'status' => 'active',
                'subscribed_at' => now(),
            ]);
        }

        return $subscriber;
    }

    private function message(array $attributes = []): Message
    {
        return Message::create(array_merge([
            'user_id' => $this->user->id,
            'channel' => 'email',
            'type' => 'broadcast',
            'subject' => 'Hello',
            'content' => '<p>Body</p>',
            'status' => 'scheduled',
            'scheduled_at' => now(),
        ], $attributes));
    }

    private function listMessage(array $attributes = []): Message
    {
        $message = $this->message($attributes);
        $message->contactLists()->attach($this->list->id);

        return $message->fresh();
    }

    private function api()
    {
        $apiKey = ApiKey::generate($this->user->id, 'Test Key', ['email:read', 'email:write'])['key'];

        return $this->withHeaders(['Authorization' => 'Bearer ' . $apiKey]);
    }

    private function runCron(): array
    {
        return app(CronScheduleService::class)->processQueue();
    }

    private function entry(Message $message, Subscriber $subscriber): ?MessageQueueEntry
    {
        return $message->queueEntries()->where('subscriber_id', $subscriber->id)->first();
    }

    public function test_list_broadcast_is_not_planned_for_an_inactive_member(): void
    {
        $active = $this->subscriber('active@example.com');
        $inactive = $this->subscriber('inactive@example.com', active: false);
        $message = $this->listMessage();

        $this->assertSame([$active->id], $message->getUniqueRecipients()->pluck('id')->all());

        $stats = $this->runCron();

        $this->assertSame(1, $stats['dispatched']);
        $this->assertSame(MessageQueueEntry::STATUS_QUEUED, $this->entry($message, $active)->status);
        $this->assertNull($this->entry($message, $inactive));
        $this->assertSame(1, $message->fresh()->planned_recipients_count);
        Queue::assertPushed(SendEmailJob::class, 1);
    }

    public function test_audience_estimate_leaves_inactive_members_out(): void
    {
        $this->subscriber('a@example.com');
        $this->subscriber('b@example.com');
        $this->subscriber('inactive@example.com', active: false);

        $estimate = app(SubscriberFieldFilterService::class)
            ->estimate([$this->list->id], [], [], 'all', [], 'all');

        $this->assertSame(['base' => 2, 'total' => 2, 'excluded' => 0], $estimate);
    }

    public function test_an_inactive_crm_pick_is_not_a_recipient(): void
    {
        $picked = $this->subscriber('picked@example.com', onList: false);
        $inactive = $this->subscriber('inactive@example.com', active: false, onList: false);

        $message = $this->message();
        foreach ([$picked, $inactive] as $subscriber) {
            $contact = CrmContact::create([
                'user_id' => $this->user->id,
                'subscriber_id' => $subscriber->id,
                'status' => 'lead',
                'source' => 'manual',
                'score' => 0,
            ]);
            $message->crmContacts()->attach($contact->id);
        }

        $this->assertSame([$picked->id], $message->fresh()->getUniqueRecipients()->pluck('id')->all());
    }

    public function test_deactivated_member_is_not_counted_but_their_planned_entry_is_left_to_the_send_gate(): void
    {
        $active = $this->subscriber('active@example.com');
        $deactivated = $this->subscriber('later@example.com');

        $message = $this->listMessage(['scheduled_at' => now()->addDay()]);
        $message->syncPlannedRecipients();
        $deactivated->update(Subscriber::adminStatusAttributes(Subscriber::STATUS_INACTIVE));

        // Saving the message in the editor syncs it again before it is due
        $this->assertSame(0, $message->fresh()->syncPlannedRecipients()['skipped']);
        $this->assertSame(MessageQueueEntry::STATUS_PLANNED, $this->entry($message, $deactivated)->status);
        $this->assertSame(1, $message->fresh()->planned_recipients_count);

        $message->update(['scheduled_at' => now()]);
        $this->runCron();

        $this->assertSame(MessageQueueEntry::STATUS_QUEUED, $this->entry($message, $active)->status);
        $skipped = $this->entry($message, $deactivated);
        $this->assertSame(MessageQueueEntry::STATUS_SKIPPED, $skipped->status);
        // Not the sync's "removed from list or unsubscribed", which Brain counts as an unsubscribe
        $this->assertSame(CronScheduleService::SKIP_REASON_INACTIVE, $skipped->error_message);
        Queue::assertPushed(SendEmailJob::class, 1);
    }

    public function test_member_who_left_the_list_is_still_dropped_by_the_sync(): void
    {
        $this->subscriber('stays@example.com');
        $leaves = $this->subscriber('leaves@example.com');
        $message = $this->listMessage(['scheduled_at' => now()->addDay()]);
        $message->syncPlannedRecipients();

        $leaves->contactLists()->updateExistingPivot($this->list->id, ['status' => 'unsubscribed']);

        $this->assertSame(1, $message->fresh()->syncPlannedRecipients()['skipped']);
        $this->assertSame(MessageQueueEntry::STATUS_SKIPPED, $this->entry($message, $leaves)->status);
    }

    public function test_autoresponder_counts_leave_out_an_inactive_member_with_a_waiting_step(): void
    {
        $this->subscriber('active@example.com');
        $paused = $this->subscriber('paused@example.com', active: false);

        $message = $this->listMessage([
            'type' => 'autoresponder',
            'day' => 3,
            'is_active' => true,
            'scheduled_at' => null,
        ]);
        $message->queueEntries()->create([
            'subscriber_id' => $paused->id,
            'status' => MessageQueueEntry::STATUS_PLANNED,
            'planned_at' => now(),
            'scheduled_for' => now()->addDays(3),
        ]);

        $message->syncPlannedRecipients();
        $this->assertSame(MessageQueueEntry::STATUS_PLANNED, $this->entry($message, $paused)->status);
        $this->assertSame(1, $message->fresh()->planned_recipients_count);

        // The sync backfilled the active member's step: that one entry is
        // pending, the inactive member's waiting entry is not
        $stats = $message->fresh()->getQueueScheduleStats();

        $this->assertSame(1, $stats['pending']);
        $this->assertSame(1, $stats['total_scheduled']);
        $this->assertSame(0, $stats['missed']);
    }

    public function test_reactivated_member_is_planned_again(): void
    {
        $inactive = $this->subscriber('back@example.com', active: false);
        $message = $this->listMessage();

        $this->assertSame(0, $message->syncPlannedRecipients()['added']);

        $inactive->update(Subscriber::adminStatusAttributes(Subscriber::STATUS_ACTIVE));

        $this->assertSame(1, $message->fresh()->syncPlannedRecipients()['added']);
        $this->assertSame(MessageQueueEntry::STATUS_PLANNED, $this->entry($message, $inactive)->status);
    }

    public function test_cron_sync_does_not_add_inactive_members_back_to_an_api_list_batch(): void
    {
        $active = $this->subscriber('active@example.com');
        $inactive = $this->subscriber('inactive@example.com', active: false);

        $id = $this->api()
            ->postJson('/api/v1/email/batch', [
                'subject' => 'Hello',
                'content' => '<p>Body</p>',
                'list_id' => $this->list->id,
            ])
            ->assertStatus(202)
            ->json('data.id');

        $this->runCron();

        $message = Message::findOrFail($id);
        $this->assertSame(MessageQueueEntry::STATUS_QUEUED, $this->entry($message, $active)->status);
        $this->assertNull($this->entry($message, $inactive));
        Queue::assertPushed(SendEmailJob::class, 1);
    }

    public function test_email_addressed_directly_through_the_api_skips_an_inactive_subscriber(): void
    {
        $inactive = $this->subscriber('inactive@example.com', active: false, onList: false);

        $id = $this->api()
            ->postJson('/api/v1/email/send', [
                'email' => $inactive->email,
                'subject' => 'Your invoice',
                'content' => '<p>Body</p>',
            ])
            ->assertStatus(202)
            ->json('data.id');

        $stats = $this->runCron();

        $this->assertSame(0, $stats['dispatched']);
        $entry = $this->entry(Message::findOrFail($id), $inactive);
        $this->assertSame(MessageQueueEntry::STATUS_SKIPPED, $entry->status);
        $this->assertSame(CronScheduleService::SKIP_REASON_INACTIVE, $entry->error_message);
        Queue::assertNotPushed(SendEmailJob::class);
    }
}
