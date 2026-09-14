<?php

namespace Tests\Feature\Api;

use App\Events\SubscriberSignedUp;
use App\Models\ApiKey;
use App\Models\ContactList;
use App\Models\Subscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * `POST /api/v1/lists/{id}/members/move` and `/copy` transfer members of the
 * list in the URL. The selection can name any contact — subscriber_ids,
 * emails, filter.status "all" — so the transfer itself must only act on
 * active memberships of that list: moving or copying someone who unsubscribed
 * from it, bounced on it or has not confirmed a double opt-in would turn the
 * opt-out into an active subscription on the target list.
 */
class ListMembershipTransferTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $apiKey;
    private ContactList $source;
    private ContactList $target;
    private Subscriber $active;
    private Subscriber $unsubscribed;
    private Subscriber $bounced;
    private Subscriber $pending;
    private Subscriber $outsider;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();

        $this->user = User::factory()->create();
        $this->apiKey = ApiKey::generate($this->user->id, 'Test Key', ['lists:read', 'lists:write'])['key'];

        $this->source = $this->makeList('Source');
        $this->target = $this->makeList('Target');

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
        $this->outsider = $this->makeSubscriber('outsider@example.com', null);
    }

    private function makeList(string $name): ContactList
    {
        return ContactList::create([
            'user_id' => $this->user->id,
            'name' => $name,
            'type' => 'email',
            'is_public' => true,
        ]);
    }

    private function makeSubscriber(string $email, ?array $sourcePivot): Subscriber
    {
        $subscriber = Subscriber::create([
            'user_id' => $this->user->id,
            'email' => $email,
            'status' => 'active',
            'is_active_global' => true,
        ]);

        if ($sourcePivot !== null) {
            $subscriber->contactLists()->attach($this->source->id, $sourcePivot);
        }

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
        return [
            $this->active->id,
            $this->unsubscribed->id,
            $this->bounced->id,
            $this->pending->id,
            $this->outsider->id,
        ];
    }

    private function transfer(string $mode, array $payload): TestResponse
    {
        return $this->withHeaders(['Authorization' => 'Bearer ' . $this->apiKey])
            ->postJson("/api/v1/lists/{$this->source->id}/members/{$mode}", $payload + [
                'target_list_id' => $this->target->id,
            ])
            ->assertOk();
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

    private function assertOnlyActiveMemberReachedTarget(): void
    {
        $this->assertSame('active', $this->pivot($this->active, $this->target)?->status);

        foreach ([$this->unsubscribed, $this->bounced, $this->pending, $this->outsider] as $subscriber) {
            $this->assertNull($this->pivot($subscriber, $this->target), "{$subscriber->email} must not be added to the target list");
        }
    }

    public function test_move_by_ids_transfers_only_active_members_of_the_source_list(): void
    {
        $this->transfer('move', ['subscriber_ids' => $this->allIds()])
            ->assertJsonPath('data.mode', 'move')
            ->assertJsonPath('data.selected', 5)
            ->assertJsonPath('data.transferred', 1)
            ->assertJsonPath('data.already_on_target', 0)
            ->assertJsonPath('data.not_active_on_source', 4)
            ->assertJsonPath('message', '1 member(s) moved to list "Target", 0 already active there, 4 skipped as not active on the source list.');

        $this->assertNull($this->pivot($this->active, $this->source));
        $this->assertNonActiveSourceMembershipsUntouched();
        $this->assertOnlyActiveMemberReachedTarget();

        Event::assertDispatchedTimes(SubscriberSignedUp::class, 1);
        Event::assertDispatched(SubscriberSignedUp::class, fn (SubscriberSignedUp $event) =>
            $event->subscriber->is($this->active)
            && $event->list->is($this->target)
            && $event->source === 'api_move');
    }

    public function test_move_by_emails_and_by_a_filter_over_all_statuses_skips_non_active_members(): void
    {
        $this->transfer('move', [
            'emails' => ['unsubscribed@example.com', 'outsider@example.com'],
            'filter' => ['status' => 'all'],
        ])
            ->assertJsonPath('data.selected', 5)
            ->assertJsonPath('data.transferred', 1)
            ->assertJsonPath('data.not_active_on_source', 4);

        $this->assertNull($this->pivot($this->active, $this->source));
        $this->assertNonActiveSourceMembershipsUntouched();
        $this->assertOnlyActiveMemberReachedTarget();
    }

    public function test_copy_transfers_only_active_members_and_keeps_the_source_list(): void
    {
        $this->transfer('copy', ['subscriber_ids' => $this->allIds()])
            ->assertJsonPath('data.mode', 'copy')
            ->assertJsonPath('data.transferred', 1)
            ->assertJsonPath('data.not_active_on_source', 4);

        $this->assertSame('active', $this->pivot($this->active, $this->source)?->status);
        $this->assertNonActiveSourceMembershipsUntouched();
        $this->assertOnlyActiveMemberReachedTarget();

        Event::assertDispatchedTimes(SubscriberSignedUp::class, 1);
        Event::assertDispatched(SubscriberSignedUp::class, fn (SubscriberSignedUp $event) =>
            $event->subscriber->is($this->active) && $event->source === 'api_copy');
    }

    public function test_move_onto_a_list_the_subscriber_is_already_active_on_does_not_sign_them_up_again(): void
    {
        $this->active->contactLists()->attach($this->target->id, [
            'status' => 'active',
            'subscribed_at' => now()->subDays(30),
        ]);

        $this->transfer('move', ['subscriber_ids' => [$this->active->id]])
            ->assertJsonPath('data.transferred', 0)
            ->assertJsonPath('data.already_on_target', 1)
            ->assertJsonPath('data.not_active_on_source', 0);

        $this->assertNull($this->pivot($this->active, $this->source));
        $this->assertSame('active', $this->pivot($this->active, $this->target)?->status);

        Event::assertNotDispatched(SubscriberSignedUp::class);
    }

    public function test_move_onto_a_bounced_membership_reactivates_it_with_a_fresh_bounce_count(): void
    {
        $this->active->contactLists()->attach($this->target->id, [
            'status' => 'bounced',
            'subscribed_at' => now()->subDays(30),
            'soft_bounce_count' => 3,
        ]);

        $this->transfer('move', ['subscriber_ids' => [$this->active->id]])
            ->assertJsonPath('data.transferred', 1);

        $target = $this->pivot($this->active, $this->target);
        $this->assertSame('active', $target?->status);
        $this->assertSame(0, (int) $target->soft_bounce_count);

        Event::assertDispatchedTimes(SubscriberSignedUp::class, 1);
    }

    public function test_quiet_copy_onto_a_bounced_membership_resets_the_bounce_count_without_signing_up(): void
    {
        $this->active->contactLists()->attach($this->target->id, [
            'status' => 'bounced',
            'subscribed_at' => now()->subDays(30),
            'soft_bounce_count' => 3,
        ]);

        $this->transfer('copy', [
            'subscriber_ids' => $this->allIds(),
            'trigger_automations' => false,
        ])
            ->assertJsonPath('data.transferred', 1)
            ->assertJsonPath('data.not_active_on_source', 4);

        $target = $this->pivot($this->active, $this->target);
        $this->assertSame('active', $target?->status);
        $this->assertSame(0, (int) $target->soft_bounce_count);
        $this->assertNotNull($target->resubscribed_at);
        $this->assertOnlyActiveMemberReachedTarget();

        Event::assertNotDispatched(SubscriberSignedUp::class);
    }
}
