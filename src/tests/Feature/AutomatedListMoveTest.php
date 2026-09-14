<?php

namespace Tests\Feature;

use App\Events\SubscriberSignedUp;
use App\Models\ContactList;
use App\Models\Subscriber;
use App\Models\User;
use App\Services\Automation\AutomationActionExecutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Funnels (`Subscriber::moveToList()`) and automation rules
 * (`AutomationActionExecutor` "move_to_list") move a subscriber away from a
 * list they run for — a funnel step's source list, the list the trigger came
 * from. They run for whoever reaches them, so the move must only act on an
 * active membership of that list: someone who unsubscribed from it, bounced
 * on it or has not confirmed a double opt-in keeps that status and is not
 * signed up to the target list in its place.
 */
class AutomatedListMoveTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private ContactList $source;
    private ContactList $target;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();

        $this->user = User::factory()->create();

        $this->source = $this->makeList('Source');
        $this->target = $this->makeList('Target');
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

    private function makeSubscriber(string $email, array $memberships = []): Subscriber
    {
        $subscriber = Subscriber::create([
            'user_id' => $this->user->id,
            'email' => $email,
            'status' => 'active',
            'is_active_global' => true,
        ]);

        foreach ($memberships as $listId => $pivot) {
            $subscriber->contactLists()->attach($listId, $pivot + ['subscribed_at' => now()->subDays(60)]);
        }

        return $subscriber;
    }

    /**
     * One subscriber per non-active kind of source membership, plus one not on
     * the source list at all.
     *
     * @return array<string, Subscriber>
     */
    private function nonActiveSubscribers(): array
    {
        return [
            'unsubscribed' => $this->makeSubscriber('unsubscribed@example.com', [
                $this->source->id => ['status' => 'unsubscribed', 'unsubscribed_at' => now()->subDays(10)],
            ]),
            'bounced' => $this->makeSubscriber('bounced@example.com', [
                $this->source->id => ['status' => 'bounced', 'soft_bounce_count' => 3],
            ]),
            'pending' => $this->makeSubscriber('pending@example.com', [
                $this->source->id => ['status' => 'pending'],
            ]),
            'outsider' => $this->makeSubscriber('outsider@example.com'),
        ];
    }

    private function pivot(Subscriber $subscriber, ContactList $list): ?object
    {
        return DB::table('contact_list_subscriber')
            ->where('subscriber_id', $subscriber->id)
            ->where('contact_list_id', $list->id)
            ->first();
    }

    private function assertSourceMembershipUntouched(string $kind, Subscriber $subscriber): void
    {
        $pivot = $this->pivot($subscriber, $this->source);

        match ($kind) {
            'unsubscribed' => $this->assertSame('unsubscribed', $pivot?->status),
            'bounced' => $this->assertTrue($pivot?->status === 'bounced' && (int) $pivot->soft_bounce_count === 3),
            'pending' => $this->assertSame('pending', $pivot?->status),
            'outsider' => $this->assertNull($pivot),
        };
    }

    private function runAction(string $type, Subscriber $subscriber, array $context = []): mixed
    {
        return app(AutomationActionExecutor::class)->execute(
            ['type' => $type, 'config' => ['list_id' => $this->target->id]],
            $subscriber,
            $context
        );
    }

    // ------------------------------------------------------------------
    // Subscriber::moveToList() — funnel "move to list" step
    // ------------------------------------------------------------------

    public function test_move_to_list_moves_an_active_membership(): void
    {
        $subscriber = $this->makeSubscriber('active@example.com', [
            $this->source->id => ['status' => 'active'],
        ]);

        $this->assertTrue($subscriber->moveToList($this->source->id, $this->target->id, 'funnel_move'));

        $this->assertNull($this->pivot($subscriber, $this->source));
        $this->assertSame('active', $this->pivot($subscriber, $this->target)?->status);

        Event::assertDispatchedTimes(SubscriberSignedUp::class, 1);
        Event::assertDispatched(SubscriberSignedUp::class, fn (SubscriberSignedUp $event) =>
            $event->list->is($this->target) && $event->source === 'funnel_move');
    }

    public function test_move_to_list_skips_subscribers_not_active_on_the_source_list(): void
    {
        foreach ($this->nonActiveSubscribers() as $kind => $subscriber) {
            $this->assertFalse($subscriber->moveToList($this->source->id, $this->target->id, 'funnel_move'), $kind);

            $this->assertSourceMembershipUntouched($kind, $subscriber);
            $this->assertNull($this->pivot($subscriber, $this->target), "{$kind} must not be added to the target list");
        }

        Event::assertNotDispatched(SubscriberSignedUp::class);
    }

    public function test_move_to_a_missing_list_keeps_the_source_membership(): void
    {
        $subscriber = $this->makeSubscriber('active@example.com', [
            $this->source->id => ['status' => 'active'],
        ]);

        $this->assertFalse($subscriber->moveToList($this->source->id, 999999, 'funnel_move'));

        $this->assertSame('active', $this->pivot($subscriber, $this->source)?->status);
    }

    public function test_add_to_list_reactivates_a_bounced_membership_with_a_fresh_bounce_count(): void
    {
        $subscriber = $this->makeSubscriber('bounced@example.com', [
            $this->target->id => ['status' => 'bounced', 'soft_bounce_count' => 3],
        ]);

        $this->assertTrue($subscriber->addToList($this->target->id, 'funnel_copy'));

        $pivot = $this->pivot($subscriber, $this->target);
        $this->assertSame('active', $pivot?->status);
        $this->assertSame(0, (int) $pivot->soft_bounce_count);

        Event::assertDispatchedTimes(SubscriberSignedUp::class, 1);
    }

    public function test_add_to_list_leaves_an_active_membership_without_signing_up_again(): void
    {
        $subscriber = $this->makeSubscriber('active@example.com', [
            $this->target->id => ['status' => 'active', 'soft_bounce_count' => 1],
        ]);

        $this->assertTrue($subscriber->addToList($this->target->id, 'funnel_copy'));

        $pivot = $this->pivot($subscriber, $this->target);
        $this->assertSame('active', $pivot?->status);
        // Only a bounced membership starts counting afresh
        $this->assertSame(1, (int) $pivot->soft_bounce_count);

        Event::assertNotDispatched(SubscriberSignedUp::class);
    }

    // ------------------------------------------------------------------
    // AutomationActionExecutor — automation rule actions
    // ------------------------------------------------------------------

    public function test_automation_move_unsubscribes_an_active_source_membership_and_subscribes_the_target(): void
    {
        $subscriber = $this->makeSubscriber('active@example.com', [
            $this->source->id => ['status' => 'active'],
        ]);

        $result = $this->runAction('move_to_list', $subscriber, ['list_id' => $this->source->id]);

        $this->assertTrue($result['moved']);

        $source = $this->pivot($subscriber, $this->source);
        $this->assertSame('unsubscribed', $source?->status);
        $this->assertNotNull($source->unsubscribed_at);
        $this->assertSame('active', $this->pivot($subscriber, $this->target)?->status);

        Event::assertDispatchedTimes(SubscriberSignedUp::class, 1);
        Event::assertDispatched(SubscriberSignedUp::class, fn (SubscriberSignedUp $event) =>
            $event->list->is($this->target) && $event->source === 'automation_move');
    }

    public function test_automation_move_skips_subscribers_not_active_on_the_trigger_list(): void
    {
        foreach ($this->nonActiveSubscribers() as $kind => $subscriber) {
            $result = $this->runAction('move_to_list', $subscriber, ['list_id' => $this->source->id]);

            $this->assertFalse($result['moved'], $kind);
            $this->assertSame('Subscriber is not active on the source list', $result['skipped']);

            // A bounced membership is not relabelled "unsubscribed" either
            $this->assertSourceMembershipUntouched($kind, $subscriber);
            $this->assertNull($this->pivot($subscriber, $this->target), "{$kind} must not be added to the target list");
        }

        Event::assertNotDispatched(SubscriberSignedUp::class);
    }

    public function test_automation_move_without_a_trigger_list_adds_to_the_target(): void
    {
        $subscriber = $this->makeSubscriber('tagged@example.com');

        $result = $this->runAction('move_to_list', $subscriber);

        $this->assertTrue($result['moved']);
        $this->assertSame('active', $this->pivot($subscriber, $this->target)?->status);

        Event::assertDispatchedTimes(SubscriberSignedUp::class, 1);
    }

    public function test_automation_move_and_copy_onto_an_active_membership_do_not_sign_up_again(): void
    {
        $subscriber = $this->makeSubscriber('active@example.com', [
            $this->source->id => ['status' => 'active'],
            $this->target->id => ['status' => 'active'],
        ]);

        $this->runAction('copy_to_list', $subscriber);
        $this->assertTrue($this->runAction('move_to_list', $subscriber, ['list_id' => $this->source->id])['moved']);

        $this->assertSame('unsubscribed', $this->pivot($subscriber, $this->source)?->status);
        $this->assertSame('active', $this->pivot($subscriber, $this->target)?->status);

        Event::assertNotDispatched(SubscriberSignedUp::class);
    }

    public function test_automation_copy_onto_a_bounced_membership_reactivates_it_with_a_fresh_bounce_count(): void
    {
        $subscriber = $this->makeSubscriber('bounced@example.com', [
            $this->target->id => ['status' => 'bounced', 'soft_bounce_count' => 3],
        ]);

        $this->runAction('copy_to_list', $subscriber);

        $pivot = $this->pivot($subscriber, $this->target);
        $this->assertSame('active', $pivot?->status);
        $this->assertSame(0, (int) $pivot->soft_bounce_count);

        Event::assertDispatchedTimes(SubscriberSignedUp::class, 1);
        Event::assertDispatched(SubscriberSignedUp::class, fn (SubscriberSignedUp $event) =>
            $event->source === 'automation_copy');
    }
}
