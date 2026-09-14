<?php

namespace Tests\Feature;

use App\Models\ContactList;
use App\Models\Subscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * The interface must show the status every send checks — a bounced address
 * used to be reported as "active" while CRON skipped it — and an admin must be
 * able to bring such an address back (issue #31).
 */
class SubscriberStatusTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private ContactList $list;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();

        $this->user = User::factory()->create();

        $this->list = ContactList::create([
            'user_id' => $this->user->id,
            'name' => 'Newsletter',
            'type' => 'email',
            'is_public' => true,
            'settings' => [],
            'webhook_events' => [],
            'sync_settings' => [],
            'required_fields' => [],
        ]);
    }

    private function makeSubscriber(string $email, array $attributes = [], int $softBounces = 0): Subscriber
    {
        $subscriber = Subscriber::create(array_merge([
            'user_id' => $this->user->id,
            'email' => $email,
            'is_active_global' => true,
        ], $attributes));

        $subscriber->contactLists()->attach($this->list->id, [
            'status' => 'active',
            'subscribed_at' => now()->subDays(30),
            'soft_bounce_count' => $softBounces,
        ]);

        return $subscriber->fresh();
    }

    private function softBounces(Subscriber $subscriber): int
    {
        return (int) DB::table('contact_list_subscriber')
            ->where('subscriber_id', $subscriber->id)
            ->where('contact_list_id', $this->list->id)
            ->value('soft_bounce_count');
    }

    private function updatePayload(Subscriber $subscriber, string $status): array
    {
        return [
            'email' => $subscriber->email,
            'first_name' => null,
            'last_name' => null,
            'phone' => null,
            'gender' => null,
            'contact_list_ids' => [$this->list->id],
            'status' => $status,
        ];
    }

    private function statusesOnIndex(array $query = []): array
    {
        $statuses = [];

        $this->actingAs($this->user)
            ->get(route('subscribers.index', $query))
            ->assertOk()
            ->assertInertia(function ($page) use (&$statuses) {
                foreach ($page->toArray()['props']['subscribers']['data'] as $row) {
                    $statuses[$row['email']] = $row['status'];
                }
            });

        ksort($statuses);

        return $statuses;
    }

    private function seedEveryStatus(): void
    {
        $this->makeSubscriber('active@example.com');
        $this->makeSubscriber('inactive@example.com', ['is_active_global' => false]);
        $this->makeSubscriber('bounced@example.com', ['status' => 'bounced']);
        $this->makeSubscriber('unsubscribed@example.com', ['status' => 'unsubscribed']);
    }

    public function test_the_list_shows_the_stored_status_instead_of_the_flag(): void
    {
        $this->seedEveryStatus();

        $this->assertSame([
            'active@example.com' => 'active',
            'bounced@example.com' => 'bounced',
            'inactive@example.com' => 'inactive',
            'unsubscribed@example.com' => 'unsubscribed',
        ], $this->statusesOnIndex());
    }

    public function test_the_list_filters_by_status(): void
    {
        $this->seedEveryStatus();

        $this->assertSame(['bounced@example.com' => 'bounced'], $this->statusesOnIndex(['status' => 'bounced']));
        $this->assertSame(['inactive@example.com' => 'inactive'], $this->statusesOnIndex(['status' => 'inactive']));
        $this->assertSame(['active@example.com' => 'active'], $this->statusesOnIndex(['status' => 'active']));
        $this->assertCount(4, $this->statusesOnIndex(['status' => 'nonsense']));
    }

    public function test_the_edit_form_receives_the_bounced_status(): void
    {
        $subscriber = $this->makeSubscriber('bounced@example.com', ['status' => 'bounced']);

        $this->actingAs($this->user)
            ->get(route('subscribers.edit', $subscriber))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('subscriber.status', 'bounced'));
    }

    public function test_choosing_active_reactivates_a_bounced_subscriber(): void
    {
        $subscriber = $this->makeSubscriber('bounced@example.com', ['status' => 'bounced'], softBounces: 3);

        $this->actingAs($this->user)
            ->put(route('subscribers.update', $subscriber), $this->updatePayload($subscriber, 'active'))
            ->assertSessionHasNoErrors();

        $subscriber->refresh();
        $this->assertSame('active', $subscriber->status);
        $this->assertTrue($subscriber->is_active_global);
        $this->assertSame(0, $this->softBounces($subscriber));
    }

    public function test_keeping_the_bounced_status_changes_nothing(): void
    {
        $subscriber = $this->makeSubscriber('bounced@example.com', ['status' => 'bounced'], softBounces: 3);

        $this->actingAs($this->user)
            ->put(route('subscribers.update', $subscriber), $this->updatePayload($subscriber, 'bounced'))
            ->assertSessionHasNoErrors();

        $subscriber->refresh();
        $this->assertSame('bounced', $subscriber->status);
        $this->assertSame(3, $this->softBounces($subscriber));
    }

    public function test_choosing_inactive_never_clears_a_bounce(): void
    {
        $subscriber = $this->makeSubscriber('bounced@example.com', ['status' => 'bounced']);

        $this->actingAs($this->user)
            ->put(route('subscribers.update', $subscriber), $this->updatePayload($subscriber, 'inactive'))
            ->assertSessionHasNoErrors();

        $subscriber->refresh();
        $this->assertSame('bounced', $subscriber->status);
        $this->assertFalse($subscriber->is_active_global);
    }

    public function test_a_bounce_cannot_be_set_by_hand(): void
    {
        $subscriber = $this->makeSubscriber('active@example.com');

        $this->actingAs($this->user)
            ->put(route('subscribers.update', $subscriber), $this->updatePayload($subscriber, 'bounced'))
            ->assertSessionHasErrors('status');

        $this->assertSame('active', $subscriber->fresh()->status);
    }

    public function test_bulk_active_reactivates_bounced_subscribers_and_leaves_other_counters_alone(): void
    {
        $bounced = $this->makeSubscriber('bounced@example.com', ['status' => 'bounced'], softBounces: 3);
        $active = $this->makeSubscriber('active@example.com', ['is_active_global' => false], softBounces: 2);

        $this->actingAs($this->user)
            ->post(route('subscribers.bulk-status'), [
                'ids' => [$bounced->id, $active->id],
                'status' => 'active',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('active', $bounced->fresh()->display_status);
        $this->assertSame(0, $this->softBounces($bounced));

        $this->assertSame('active', $active->fresh()->display_status);
        $this->assertSame(2, $this->softBounces($active));
    }

    public function test_the_export_follows_the_status_filter_and_reports_the_real_status(): void
    {
        $this->seedEveryStatus();

        $body = $this->actingAs($this->user)
            ->post(route('subscribers.export'), [
                'preset' => 'custom',
                'fields' => ['email', 'status'],
                'format' => 'csv',
                'scope' => 'filtered',
                'status' => 'bounced',
            ])
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('bounced@example.com,bounced', $body);
        $this->assertStringNotContainsString('active@example.com', $body);
        $this->assertStringNotContainsString('unsubscribed@example.com', $body);
    }
}
