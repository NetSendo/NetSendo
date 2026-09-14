<?php

namespace Tests\Feature;

use App\Events\SubscriberSignedUp;
use App\Models\ContactList;
use App\Models\Subscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Deleting an email or SMS list with "transfer subscribers to another list"
 * must transfer its active members only. The delete dialog counts active
 * members, but the transfer used to take every row of the list — so everyone
 * who had unsubscribed from it, bounced on it or not confirmed a double
 * opt-in became an active subscriber of the target list and started its
 * sequences.
 */
class ListDeleteTransferTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();

        $this->user = User::factory()->create();
    }

    private function makeList(string $name, string $type): ContactList
    {
        return ContactList::create([
            'user_id' => $this->user->id,
            'name' => $name,
            'type' => $type,
            'is_public' => true,
        ]);
    }

    /**
     * @return array<string, Subscriber>
     */
    private function populate(ContactList $list): array
    {
        $memberships = [
            'active' => ['status' => 'active'],
            'unsubscribed' => ['status' => 'unsubscribed', 'unsubscribed_at' => now()->subDays(10)],
            'bounced' => ['status' => 'bounced', 'soft_bounce_count' => 3],
            'pending' => ['status' => 'pending'],
        ];

        $subscribers = [];

        foreach ($memberships as $kind => $pivot) {
            $subscribers[$kind] = Subscriber::create([
                'user_id' => $this->user->id,
                'email' => "{$kind}@example.com",
                'phone' => '+4850000000' . count($subscribers),
                'status' => 'active',
                'is_active_global' => true,
            ]);

            $subscribers[$kind]->contactLists()->attach($list->id, $pivot + ['subscribed_at' => now()->subDays(60)]);
        }

        return $subscribers;
    }

    private function pivot(Subscriber $subscriber, ContactList $list): ?object
    {
        return DB::table('contact_list_subscriber')
            ->where('subscriber_id', $subscriber->id)
            ->where('contact_list_id', $list->id)
            ->first();
    }

    /**
     * @param array<string, Subscriber> $subscribers
     */
    private function assertOnlyActiveMembersTransferred(array $subscribers, ContactList $deleted, ContactList $target): void
    {
        $this->assertSoftDeleted($deleted);

        $this->assertNull($this->pivot($subscribers['active'], $deleted));
        $this->assertSame('active', $this->pivot($subscribers['active'], $target)?->status);

        foreach (['unsubscribed', 'bounced', 'pending'] as $kind) {
            $this->assertNull($this->pivot($subscribers[$kind], $target), "{$kind} must not be added to the target list");
            // The row stays with the soft-deleted list, keeping its history
            $this->assertSame($kind, $this->pivot($subscribers[$kind], $deleted)?->status);
        }

        $this->assertSame(3, (int) $this->pivot($subscribers['bounced'], $deleted)->soft_bounce_count);

        Event::assertDispatchedTimes(SubscriberSignedUp::class, 1);
        Event::assertDispatched(SubscriberSignedUp::class, fn (SubscriberSignedUp $event) =>
            $event->subscriber->is($subscribers['active'])
            && $event->list->is($target)
            && $event->source === 'list_transfer');
    }

    public function test_deleting_a_mailing_list_transfers_only_its_active_members(): void
    {
        $list = $this->makeList('Old', 'email');
        $target = $this->makeList('New', 'email');
        $subscribers = $this->populate($list);

        $this->actingAs($this->user)
            ->delete(route('mailing-lists.destroy', $list), ['transfer_to_id' => $target->id])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('mailing-lists.index'));

        $this->assertOnlyActiveMembersTransferred($subscribers, $list, $target);
    }

    public function test_deleting_an_sms_list_transfers_only_its_active_members(): void
    {
        $list = $this->makeList('Old SMS', 'sms');
        $target = $this->makeList('New SMS', 'sms');
        $subscribers = $this->populate($list);

        $this->actingAs($this->user)
            ->delete(route('sms-lists.destroy', $list), ['transfer_to_id' => $target->id])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('sms-lists.index'));

        $this->assertOnlyActiveMembersTransferred($subscribers, $list, $target);
    }

    public function test_transfer_onto_an_active_membership_does_not_sign_up_again_and_a_bounced_one_starts_afresh(): void
    {
        $list = $this->makeList('Old', 'email');
        $target = $this->makeList('New', 'email');

        $alreadyActive = $this->populate($list)['active'];
        $alreadyActive->contactLists()->attach($target->id, ['status' => 'active', 'subscribed_at' => now()->subDays(30)]);

        $bouncedOnTarget = Subscriber::create([
            'user_id' => $this->user->id,
            'email' => 'bounced-on-target@example.com',
            'status' => 'active',
            'is_active_global' => true,
        ]);
        $bouncedOnTarget->contactLists()->attach($list->id, ['status' => 'active', 'subscribed_at' => now()->subDays(60)]);
        $bouncedOnTarget->contactLists()->attach($target->id, ['status' => 'bounced', 'soft_bounce_count' => 3]);

        $this->actingAs($this->user)
            ->delete(route('mailing-lists.destroy', $list), ['transfer_to_id' => $target->id])
            ->assertSessionHasNoErrors();

        $this->assertSame('active', $this->pivot($alreadyActive, $target)?->status);

        $pivot = $this->pivot($bouncedOnTarget, $target);
        $this->assertSame('active', $pivot?->status);
        $this->assertSame(0, (int) $pivot->soft_bounce_count);

        Event::assertDispatchedTimes(SubscriberSignedUp::class, 1);
        Event::assertDispatched(SubscriberSignedUp::class, fn (SubscriberSignedUp $event) =>
            $event->subscriber->is($bouncedOnTarget));
    }
}
