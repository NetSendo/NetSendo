<?php

namespace Tests\Feature;

use App\Events\SubscriberSignedUp;
use App\Models\ContactList;
use App\Models\Funnel;
use App\Models\FunnelStep;
use App\Models\FunnelSubscriber;
use App\Models\Subscriber;
use App\Models\User;
use App\Services\Funnels\FunnelExecutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * A funnel "Move to list" step. The builder stored the target as `list_id`
 * while the executor only read `from_list_id` and `to_list_id`, so every step
 * saved in the builder moved nobody. The builder now stores the target as
 * `list_id` and an optional `from_list_id`; without one the subscriber leaves
 * the trigger list of a list signup funnel. Steps saved with the older
 * from/to keys keep working. Only an active membership of the source list is
 * moved, and a step that moves nobody says why in the enrollment history.
 */
class FunnelMoveToListTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private ContactList $trigger;
    private ContactList $other;
    private ContactList $target;

    protected function setUp(): void
    {
        parent::setUp();

        // Only the signup event: a blanket fake would also stop model events such as the funnel slug
        Event::fake([SubscriberSignedUp::class]);

        $this->user = User::factory()->create();

        $this->trigger = $this->makeList('Trigger');
        $this->other = $this->makeList('Other');
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

    private function makeFunnel(array $attributes = []): Funnel
    {
        return Funnel::create($attributes + [
            'user_id' => $this->user->id,
            'name' => 'Move funnel',
            'status' => Funnel::STATUS_ACTIVE,
            'trigger_type' => Funnel::TRIGGER_LIST_SIGNUP,
            'trigger_list_id' => $this->trigger->id,
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

    private function makeMoveStep(Funnel $funnel, array $config): FunnelStep
    {
        return $funnel->steps()->create([
            'type' => FunnelStep::TYPE_ACTION,
            'action_type' => FunnelStep::ACTION_MOVE_TO_LIST,
            'action_config' => $config,
            'order' => 1,
        ]);
    }

    /**
     * Put the subscriber on the step and process it. The step has no next
     * step, so the enrollment completes right after it.
     */
    private function runStep(FunnelStep $step, Subscriber $subscriber): FunnelSubscriber
    {
        $enrollment = FunnelSubscriber::create([
            'funnel_id' => $step->funnel_id,
            'subscriber_id' => $subscriber->id,
            'current_step_id' => $step->id,
            'status' => FunnelSubscriber::STATUS_ACTIVE,
            'entered_at' => now(),
            'steps_completed' => 0,
            'data' => [],
        ]);

        app(FunnelExecutionService::class)->processNextStep($enrollment);

        return $enrollment->fresh();
    }

    private function pivot(Subscriber $subscriber, ContactList $list): ?object
    {
        return DB::table('contact_list_subscriber')
            ->where('subscriber_id', $subscriber->id)
            ->where('contact_list_id', $list->id)
            ->first();
    }

    private function historyEntry(FunnelSubscriber $enrollment, string $action): ?array
    {
        return collect($enrollment->getHistory())->firstWhere('action', $action);
    }

    private function assertSkipped(FunnelSubscriber $enrollment, string $reason, string $message = ''): void
    {
        $this->assertNull($this->historyEntry($enrollment, 'list_moved'), $message);
        $this->assertSame($reason, $this->historyEntry($enrollment, 'list_move_skipped')['details']['reason'] ?? null, $message);

        // The funnel carries on past a move that moved nobody
        $this->assertSame(FunnelSubscriber::STATUS_COMPLETED, $enrollment->status, $message);
    }

    public function test_a_step_saved_in_the_builder_moves_an_active_member_off_the_trigger_list(): void
    {
        $funnel = $this->makeFunnel(['status' => Funnel::STATUS_DRAFT]);

        // The payload the builder sends: select values are strings, and a move
        // step without its own source list stores only the target
        $this->actingAs($this->user)->put(route('funnels.update', $funnel), [
            'name' => $funnel->name,
            'trigger_type' => 'list_signup',
            'trigger_list_id' => (string) $this->trigger->id,
            'nodes' => [
                ['id' => 'new-1', 'type' => 'start', 'position' => ['x' => 250, 'y' => 50], 'data' => []],
                ['id' => 'new-2', 'type' => 'action', 'position' => ['x' => 250, 'y' => 170], 'data' => [
                    'action_type' => 'move_to_list',
                    'action_config' => ['list_id' => (string) $this->target->id],
                ]],
            ],
            'edges' => [['source' => 'new-1', 'target' => 'new-2']],
        ])->assertRedirect();

        $step = $funnel->steps()->where('type', FunnelStep::TYPE_ACTION)->firstOrFail();
        $funnel->update(['status' => Funnel::STATUS_ACTIVE]);

        $subscriber = $this->makeSubscriber('active@example.com', [
            $this->trigger->id => ['status' => 'active'],
        ]);

        $enrollment = $this->runStep($step, $subscriber);

        $this->assertNull($this->pivot($subscriber, $this->trigger));
        $this->assertSame('active', $this->pivot($subscriber, $this->target)?->status);

        Event::assertDispatchedTimes(SubscriberSignedUp::class, 1);
        Event::assertDispatched(SubscriberSignedUp::class, fn (SubscriberSignedUp $event) =>
            $event->list->is($this->target) && $event->source === 'funnel_move');

        $this->assertSame(
            ['from_list_id' => $this->trigger->id, 'to_list_id' => $this->target->id],
            $this->historyEntry($enrollment, 'list_moved')['details'] ?? null
        );
    }

    public function test_a_source_list_chosen_in_the_step_is_used_instead_of_the_trigger_list(): void
    {
        $step = $this->makeMoveStep($this->makeFunnel(), [
            'from_list_id' => (string) $this->other->id,
            'list_id' => (string) $this->target->id,
        ]);

        $subscriber = $this->makeSubscriber('active@example.com', [
            $this->trigger->id => ['status' => 'active'],
            $this->other->id => ['status' => 'active'],
        ]);

        $this->runStep($step, $subscriber);

        $this->assertNull($this->pivot($subscriber, $this->other));
        $this->assertSame('active', $this->pivot($subscriber, $this->trigger)?->status);
        $this->assertSame('active', $this->pivot($subscriber, $this->target)?->status);
    }

    public function test_steps_saved_with_from_and_to_list_ids_still_move(): void
    {
        $step = $this->makeMoveStep(
            $this->makeFunnel(['trigger_type' => Funnel::TRIGGER_MANUAL, 'trigger_list_id' => null]),
            ['from_list_id' => $this->other->id, 'to_list_id' => $this->target->id]
        );

        $subscriber = $this->makeSubscriber('active@example.com', [
            $this->other->id => ['status' => 'active'],
        ]);

        $enrollment = $this->runStep($step, $subscriber);

        $this->assertNull($this->pivot($subscriber, $this->other));
        $this->assertSame('active', $this->pivot($subscriber, $this->target)?->status);
        $this->assertNotNull($this->historyEntry($enrollment, 'list_moved'));
    }

    public function test_subscribers_not_active_on_the_source_list_are_not_moved(): void
    {
        $step = $this->makeMoveStep($this->makeFunnel(), ['list_id' => $this->target->id]);

        $subscribers = [
            'unsubscribed' => $this->makeSubscriber('unsubscribed@example.com', [
                $this->trigger->id => ['status' => 'unsubscribed', 'unsubscribed_at' => now()->subDays(10)],
            ]),
            'bounced' => $this->makeSubscriber('bounced@example.com', [
                $this->trigger->id => ['status' => 'bounced', 'soft_bounce_count' => 3],
            ]),
            'pending' => $this->makeSubscriber('pending@example.com', [
                $this->trigger->id => ['status' => 'pending'],
            ]),
            'outsider' => $this->makeSubscriber('outsider@example.com'),
        ];

        foreach ($subscribers as $kind => $subscriber) {
            $enrollment = $this->runStep($step, $subscriber);

            $pivot = $this->pivot($subscriber, $this->trigger);
            match ($kind) {
                'unsubscribed' => $this->assertSame('unsubscribed', $pivot?->status),
                'bounced' => $this->assertTrue($pivot?->status === 'bounced' && (int) $pivot->soft_bounce_count === 3),
                'pending' => $this->assertSame('pending', $pivot?->status),
                'outsider' => $this->assertNull($pivot),
            };

            $this->assertNull($this->pivot($subscriber, $this->target), "{$kind} must not be added to the target list");
            $this->assertSkipped($enrollment, 'Subscriber is not active on the source list', $kind);
        }

        Event::assertNotDispatched(SubscriberSignedUp::class);
    }

    public function test_a_trigger_list_kept_after_switching_the_trigger_type_is_not_used(): void
    {
        // Switching to a tag trigger keeps trigger_list_id, which the builder no longer shows
        $funnel = $this->makeFunnel(['trigger_type' => Funnel::TRIGGER_TAG_ADDED, 'trigger_tag' => 'vip']);
        $step = $this->makeMoveStep($funnel, ['list_id' => $this->target->id]);

        $subscriber = $this->makeSubscriber('active@example.com', [
            $this->trigger->id => ['status' => 'active'],
        ]);

        $enrollment = $this->runStep($step, $subscriber);

        $this->assertSame('active', $this->pivot($subscriber, $this->trigger)?->status);
        $this->assertNull($this->pivot($subscriber, $this->target));
        $this->assertSkipped($enrollment, 'No source list: the step names none and the funnel is not triggered by a list signup');

        Event::assertNotDispatched(SubscriberSignedUp::class);
    }

    public function test_a_move_onto_the_source_list_itself_leaves_the_membership_alone(): void
    {
        $step = $this->makeMoveStep($this->makeFunnel(), ['list_id' => $this->trigger->id]);

        $subscriber = $this->makeSubscriber('active@example.com', [
            $this->trigger->id => ['status' => 'active'],
        ]);
        $subscribedAt = $this->pivot($subscriber, $this->trigger)->subscribed_at;

        $enrollment = $this->runStep($step, $subscriber);

        $this->assertSame($subscribedAt, $this->pivot($subscriber, $this->trigger)?->subscribed_at);
        $this->assertSkipped($enrollment, 'Source and target list are the same');

        Event::assertNotDispatched(SubscriberSignedUp::class);
    }

    public function test_a_missing_target_list_keeps_the_source_membership(): void
    {
        $step = $this->makeMoveStep($this->makeFunnel(), ['list_id' => 999999]);

        $subscriber = $this->makeSubscriber('active@example.com', [
            $this->trigger->id => ['status' => 'active'],
        ]);

        $enrollment = $this->runStep($step, $subscriber);

        $this->assertSame('active', $this->pivot($subscriber, $this->trigger)?->status);
        $this->assertSkipped($enrollment, 'Target list not found');
    }
}
