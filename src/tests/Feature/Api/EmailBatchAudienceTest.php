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
 * `POST /api/v1/email/batch` resolves its recipients when the request comes
 * in, but the per-minute CRON re-plans every due broadcast from the lists
 * attached to it. A list narrowed by tag_ids or subscriber_ids cannot be
 * expressed through those lists, and excluded_list_ids were not stored at
 * all — so the first CRON run used to widen such a batch to the whole list.
 */
class EmailBatchAudienceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $apiKey;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->user = User::factory()->create();
        $this->apiKey = ApiKey::generate($this->user->id, 'Test Key', ['email:read', 'email:write'])['key'];

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

    private function list(string $name, string $type = 'email', ?User $owner = null): ContactList
    {
        return ContactList::create([
            'user_id' => ($owner ?? $this->user)->id,
            'name' => $name,
            'type' => $type,
            'is_public' => true,
        ]);
    }

    /**
     * @param  ContactList[]  $lists
     */
    private function subscriber(string $email, array $lists = [], ?User $owner = null): Subscriber
    {
        $subscriber = Subscriber::create([
            'user_id' => ($owner ?? $this->user)->id,
            'email' => $email,
            'status' => 'active',
            'is_active_global' => true,
        ]);

        foreach ($lists as $list) {
            $subscriber->contactLists()->attach($list->id, [
                'status' => 'active',
                'subscribed_at' => now(),
            ]);
        }

        return $subscriber;
    }

    private function batch(array $targeting, int $expectedQueued): Message
    {
        $id = $this->withHeaders(['Authorization' => 'Bearer ' . $this->apiKey])
            ->postJson('/api/v1/email/batch', [
                'subject' => 'Hello',
                'content' => '<p>Body</p>',
            ] + $targeting)
            ->assertStatus(202)
            ->assertJsonPath('data.queued_count', $expectedQueued)
            ->json('data.id');

        return Message::findOrFail($id);
    }

    private function runCron(): array
    {
        return app(CronScheduleService::class)->processQueue();
    }

    /**
     * Subscriber IDs of the message's entries, keyed by status.
     */
    private function entriesByStatus(Message $message): array
    {
        return $message->queueEntries()
            ->orderBy('subscriber_id')
            ->get()
            ->groupBy('status')
            ->map(fn ($entries) => $entries->pluck('subscriber_id')->all())
            ->all();
    }

    public function test_list_narrowed_by_subscriber_ids_and_exclusions_is_not_widened_by_cron(): void
    {
        $readers = $this->list('Readers');
        $blocked = $this->list('Blocked');
        $picked = $this->subscriber('picked@example.com', [$readers]);
        $this->subscriber('other@example.com', [$readers]);
        $excluded = $this->subscriber('excluded@example.com', [$readers, $blocked]);

        $message = $this->batch([
            'list_id' => $readers->id,
            'subscriber_ids' => [$picked->id, $excluded->id],
            'excluded_list_ids' => [$blocked->id],
        ], expectedQueued: 1);

        $this->runCron();

        $this->assertSame([MessageQueueEntry::STATUS_QUEUED => [$picked->id]], $this->entriesByStatus($message));
        Queue::assertPushed(SendEmailJob::class, 1);

        $message->refresh();
        $this->assertSame('scheduled', $message->status);
        $this->assertSame(1, $message->planned_recipients_count);

        // The selection is still recorded on the message: the list scopes the
        // unsubscribe link and the list's sending limits, the exclusion is
        // what the sync drops recipients by
        $this->assertTrue($message->recipients_snapshot);
        $this->assertSame([$readers->id], $message->contactLists->pluck('id')->all());
        $this->assertSame([$blocked->id], $message->excludedLists->pluck('id')->all());
    }

    public function test_list_narrowed_by_tags_does_not_pick_up_later_subscribers(): void
    {
        $readers = $this->list('Readers');
        $vip = Tag::create(['user_id' => $this->user->id, 'name' => 'VIP']);
        $tagged = $this->subscriber('tagged@example.com', [$readers]);
        $tagged->tags()->attach($vip->id);
        $this->subscriber('untagged@example.com', [$readers]);

        $message = $this->batch([
            'list_id' => $readers->id,
            'tag_ids' => [$vip->id],
        ], expectedQueued: 1);

        // Joins the list (tagged, even) after the batch was resolved
        $this->subscriber('latecomer@example.com', [$readers])->tags()->attach($vip->id);

        $this->runCron();

        $this->assertSame([MessageQueueEntry::STATUS_QUEUED => [$tagged->id]], $this->entriesByStatus($message));
        Queue::assertPushed(SendEmailJob::class, 1);
        $this->assertSame(1, $message->fresh()->planned_recipients_count);
    }

    public function test_narrowed_batch_still_drops_recipients_who_leave_its_audience_before_sending(): void
    {
        $readers = $this->list('Readers');
        $blocked = $this->list('Blocked');
        $stays = $this->subscriber('stays@example.com', [$readers]);
        $leaves = $this->subscriber('leaves@example.com', [$readers]);
        $getsExcluded = $this->subscriber('excluded-later@example.com', [$readers]);

        $message = $this->batch([
            'list_id' => $readers->id,
            'subscriber_ids' => [$stays->id, $leaves->id, $getsExcluded->id],
            'excluded_list_ids' => [$blocked->id],
        ], expectedQueued: 3);

        $leaves->contactLists()->updateExistingPivot($readers->id, ['status' => 'unsubscribed']);
        $getsExcluded->contactLists()->attach($blocked->id, ['status' => 'active', 'subscribed_at' => now()]);

        $this->runCron();

        $this->assertSame([
            MessageQueueEntry::STATUS_QUEUED => [$stays->id],
            MessageQueueEntry::STATUS_SKIPPED => [$leaves->id, $getsExcluded->id],
        ], $this->entriesByStatus($message));
        Queue::assertPushed(SendEmailJob::class, 1);
        $this->assertSame(1, $message->fresh()->planned_recipients_count);
    }

    public function test_list_batch_with_exclusions_picks_up_new_members_but_keeps_the_exclusion(): void
    {
        $readers = $this->list('Readers');
        $blocked = $this->list('Blocked');
        $member = $this->subscriber('member@example.com', [$readers]);

        $message = $this->batch([
            'list_id' => $readers->id,
            'excluded_list_ids' => [$blocked->id],
        ], expectedQueued: 1);

        // A plain list batch follows its list until sending starts, like a
        // broadcast created in the editor — exclusions included
        $joiner = $this->subscriber('joiner@example.com', [$readers]);
        $this->subscriber('blocked-joiner@example.com', [$readers, $blocked]);

        $this->runCron();

        $this->assertSame([MessageQueueEntry::STATUS_QUEUED => [$member->id, $joiner->id]], $this->entriesByStatus($message));
        Queue::assertPushed(SendEmailJob::class, 2);

        $message->refresh();
        $this->assertFalse($message->recipients_snapshot);
        $this->assertSame(2, $message->planned_recipients_count);
    }

    public function test_batch_attaches_only_the_lists_it_may_target(): void
    {
        $readers = $this->list('Readers');
        $texts = $this->list('Texts', 'sms');
        $stranger = User::factory()->create();
        $foreign = $this->list('Foreign', owner: $stranger);
        $foreignExclusion = $this->list('Foreign exclusion', owner: $stranger);

        $reader = $this->subscriber('reader@example.com', [$readers]);
        $this->subscriber('texter@example.com', [$texts]);
        $this->subscriber('someone-elses@example.com', [$foreign], owner: $stranger);

        $message = $this->batch([
            'contact_list_ids' => [$readers->id, $texts->id, $foreign->id],
            'excluded_list_ids' => [$foreignExclusion->id],
        ], expectedQueued: 1);

        $this->runCron();

        $this->assertSame([MessageQueueEntry::STATUS_QUEUED => [$reader->id]], $this->entriesByStatus($message));
        Queue::assertPushed(SendEmailJob::class, 1);

        $message->refresh();
        $this->assertSame([$readers->id], $message->contactLists->pluck('id')->all());
        $this->assertSame([], $message->excludedLists->pluck('id')->all());
    }

    public function test_duplicating_a_narrowed_batch_gives_a_regular_draft(): void
    {
        $readers = $this->list('Readers');
        $picked = $this->subscriber('picked@example.com', [$readers]);

        $message = $this->batch([
            'list_id' => $readers->id,
            'subscriber_ids' => [$picked->id],
        ], expectedQueued: 1);

        $this->actingAs($this->user)
            ->postJson(route('messages.duplicate', $message))
            ->assertOk();

        // The copy has no queue entries of its own: as a snapshot it would
        // reach nobody, so it targets its lists like any other draft
        $copy = Message::whereKeyNot($message->id)->sole();
        $this->assertFalse($copy->recipients_snapshot);
        $this->assertSame([$readers->id], $copy->contactLists->pluck('id')->all());
    }
}
