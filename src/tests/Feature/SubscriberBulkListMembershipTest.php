<?php

namespace Tests\Feature;

use App\Events\SubscriberSignedUp;
use App\Models\ContactList;
use App\Models\Subscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The bulk "move to list" and "remove from list" actions act on a list chosen
 * independently of the selection, so they must only touch active memberships
 * of that list — unsubscribed, bounced and unconfirmed ones carry the
 * unsubscribe history, the per-list bounce records and pending double opt-ins,
 * and moving them must not sign the person up to another list.
 *
 * Every action that lands subscribers on a target list — move, copy, add —
 * starts its sequences only for a new or reactivated membership, and a
 * membership that had bounced there starts with a fresh bounce count.
 */
class SubscriberBulkListMembershipTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private ContactList $source;
    private ContactList $target;
    private ContactList $other;
    private Subscriber $active;
    private Subscriber $unsubscribed;
    private Subscriber $bounced;
    private Subscriber $pending;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();

        $this->user = User::factory()->create();

        $this->source = $this->makeList('Source');
        $this->target = $this->makeList('Target');
        $this->other = $this->makeList('Other');

        $this->active = $this->makeSubscriber('active@example.com', [
            'status' => 'active',
            'subscribed_at' => now()->subDays(60),
        ]);
        $this->unsubscribed = $this->makeSubscriber('unsubscribed@example.com', [
            'status' => 'unsubscribed',
            'subscribed_at' => now()->subDays(60),
            'unsubscribed_at' => now()->subDays(10),
        ]);
        $this->bounced = $this->makeSubscriber('bounced@example.com', [
            'status' => 'bounced',
            'subscribed_at' => now()->subDays(60),
            'soft_bounce_count' => 3,
        ]);
        $this->pending = $this->makeSubscriber('pending@example.com', [
            'status' => 'pending',
            'subscribed_at' => now()->subDays(1),
        ]);

        // The index lists everyone active on any list, so the non-active
        // members of the source list can still be selected there
        foreach ([$this->unsubscribed, $this->bounced, $this->pending] as $subscriber) {
            $subscriber->contactLists()->attach($this->other->id, [
                'status' => 'active',
                'subscribed_at' => now()->subDays(30),
            ]);
        }
    }

    private function makeList(string $name): ContactList
    {
        return ContactList::create([
            'user_id' => $this->user->id,
            'name' => $name,
            'type' => 'email',
            'is_public' => true,
            'settings' => [],
            'webhook_events' => [],
            'sync_settings' => [],
            'required_fields' => [],
        ]);
    }

    private function makeSubscriber(string $email, array $sourcePivot): Subscriber
    {
        $subscriber = Subscriber::create([
            'user_id' => $this->user->id,
            'email' => $email,
            'is_active_global' => true,
        ]);

        $subscriber->contactLists()->attach($this->source->id, $sourcePivot);

        return $subscriber;
    }

    private function pivot(Subscriber $subscriber, ContactList $list): ?object
    {
        return DB::table('contact_list_subscriber')
            ->where('subscriber_id', $subscriber->id)
            ->where('contact_list_id', $list->id)
            ->first();
    }

    private function allIds(): array
    {
        return [$this->active->id, $this->unsubscribed->id, $this->bounced->id, $this->pending->id];
    }

    private function move(array $ids)
    {
        return $this->actingAs($this->user)
            ->from(route('subscribers.index'))
            ->post(route('subscribers.bulk-move'), [
                'ids' => $ids,
                'source_list_id' => $this->source->id,
                'target_list_id' => $this->target->id,
            ])
            ->assertSessionHasNoErrors();
    }

    private function assertNonActiveSourceMembershipsUntouched(): void
    {
        $unsubscribed = $this->pivot($this->unsubscribed, $this->source);
        $this->assertSame('unsubscribed', $unsubscribed?->status);
        $this->assertNotNull($unsubscribed->unsubscribed_at);

        $bounced = $this->pivot($this->bounced, $this->source);
        $this->assertSame('bounced', $bounced?->status);
        $this->assertSame(3, (int) $bounced->soft_bounce_count);

        $this->assertSame('pending', $this->pivot($this->pending, $this->source)?->status);
    }

    public function test_move_skips_subscribers_not_active_on_the_source_list(): void
    {
        $response = $this->move($this->allIds());

        $this->assertNull($this->pivot($this->active, $this->source));
        $this->assertSame('active', $this->pivot($this->active, $this->target)?->status);

        $this->assertNonActiveSourceMembershipsUntouched();

        foreach ([$this->unsubscribed, $this->bounced, $this->pending] as $subscriber) {
            $this->assertNull($this->pivot($subscriber, $this->target));
        }

        $response->assertSessionHas('success', 'Przeniesiono 1 subskrybentów. Pominięto 3 — nie są aktywni na liście "Source".');

        Event::assertDispatchedTimes(SubscriberSignedUp::class, 1);
        Event::assertDispatched(SubscriberSignedUp::class, fn (SubscriberSignedUp $event) =>
            $event->subscriber->is($this->active)
            && $event->list->is($this->target)
            && $event->source === 'bulk_move');
    }

    public function test_move_with_no_active_member_reports_that_nothing_was_moved(): void
    {
        $response = $this->move([$this->unsubscribed->id, $this->bounced->id, $this->pending->id]);

        $this->assertNonActiveSourceMembershipsUntouched();
        $this->assertSame(0, DB::table('contact_list_subscriber')->where('contact_list_id', $this->target->id)->count());

        $response->assertSessionMissing('success');
        $response->assertSessionHas('error');

        Event::assertNotDispatched(SubscriberSignedUp::class);
    }

    public function test_moving_onto_a_list_the_subscriber_is_already_active_on_does_not_sign_them_up_again(): void
    {
        $this->active->contactLists()->attach($this->target->id, [
            'status' => 'active',
            'subscribed_at' => now()->subDays(30),
        ]);

        $response = $this->move([$this->active->id]);

        $this->assertNull($this->pivot($this->active, $this->source));
        $this->assertSame('active', $this->pivot($this->active, $this->target)?->status);

        $response->assertSessionHas('success', 'Przeniesiono 1 subskrybentów.');

        Event::assertNotDispatched(SubscriberSignedUp::class);
    }

    public function test_moving_onto_a_bounced_membership_reactivates_it_with_a_fresh_bounce_count(): void
    {
        $this->active->contactLists()->attach($this->target->id, [
            'status' => 'bounced',
            'subscribed_at' => now()->subDays(30),
            'soft_bounce_count' => 3,
        ]);

        $this->move([$this->active->id]);

        $target = $this->pivot($this->active, $this->target);
        $this->assertSame('active', $target?->status);
        $this->assertSame(0, (int) $target->soft_bounce_count);

        Event::assertDispatchedTimes(SubscriberSignedUp::class, 1);
    }

    public function test_delete_from_list_removes_only_active_memberships(): void
    {
        $this->actingAs($this->user)
            ->from(route('subscribers.index'))
            ->post(route('subscribers.bulk-delete-from-list'), [
                'ids' => $this->allIds(),
                'list_id' => $this->source->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Usunięto 1 subskrybentów z listy "Source".');

        $this->assertNull($this->pivot($this->active, $this->source));
        $this->assertNonActiveSourceMembershipsUntouched();

        // Memberships on other lists are never touched
        $this->assertSame('active', $this->pivot($this->unsubscribed, $this->other)?->status);
    }

    /**
     * @return array<string, array{0: string, 1: string}> route name, event source
     */
    public static function addingActions(): array
    {
        return [
            'copy' => ['subscribers.bulk-copy', 'bulk_copy'],
            'add to list' => ['subscribers.bulk-add-to-list', 'bulk_add'],
        ];
    }

    private function addToTarget(string $route, array $ids)
    {
        return $this->actingAs($this->user)
            ->from(route('subscribers.index'))
            ->post(route($route), [
                'ids' => $ids,
                'target_list_id' => $this->target->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');
    }

    #[DataProvider('addingActions')]
    public function test_copy_and_add_sign_up_only_new_or_reactivated_target_memberships(string $route, string $source): void
    {
        $alreadyActive = $this->active;
        $alreadyActive->contactLists()->attach($this->target->id, [
            'status' => 'active',
            'subscribed_at' => now()->subDays(30),
        ]);

        $bouncedOnTarget = $this->bounced;
        $bouncedOnTarget->contactLists()->attach($this->target->id, [
            'status' => 'bounced',
            'subscribed_at' => now()->subDays(30),
            'soft_bounce_count' => 3,
        ]);

        $newOnTarget = $this->pending;

        $this->addToTarget($route, [$alreadyActive->id, $bouncedOnTarget->id, $newOnTarget->id]);

        $this->assertSame('active', $this->pivot($alreadyActive, $this->target)?->status);
        $this->assertSame('active', $this->pivot($newOnTarget, $this->target)?->status);

        $bounced = $this->pivot($bouncedOnTarget, $this->target);
        $this->assertSame('active', $bounced?->status);
        $this->assertSame(0, (int) $bounced->soft_bounce_count);

        // The source list is not involved
        $this->assertSame(3, (int) $this->pivot($bouncedOnTarget, $this->source)->soft_bounce_count);

        Event::assertDispatchedTimes(SubscriberSignedUp::class, 2);
        Event::assertNotDispatched(SubscriberSignedUp::class, fn (SubscriberSignedUp $event) =>
            $event->subscriber->is($alreadyActive));
        foreach ([$bouncedOnTarget, $newOnTarget] as $subscriber) {
            Event::assertDispatched(SubscriberSignedUp::class, fn (SubscriberSignedUp $event) =>
                $event->subscriber->is($subscriber)
                && $event->list->is($this->target)
                && $event->source === $source);
        }
    }
}
