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
 * The edit form only shows active list memberships, so saving it must not
 * remove the unsubscribed, bounced or unconfirmed ones it never displayed —
 * they carry the unsubscribe history and the per-list bounce records.
 */
class SubscriberUpdateMembershipTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Subscriber $subscriber;
    private ContactList $active;
    private ContactList $unsubscribed;
    private ContactList $bounced;
    private ContactList $pending;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();

        $this->user = User::factory()->create();

        $this->active = $this->makeList('Active');
        $this->unsubscribed = $this->makeList('Unsubscribed');
        $this->bounced = $this->makeList('Bounced');
        $this->pending = $this->makeList('Pending');

        $this->subscriber = Subscriber::create([
            'user_id' => $this->user->id,
            'email' => 'member@example.com',
            'is_active_global' => true,
        ]);

        // One attach per row: the pivot columns differ between rows
        $this->subscriber->contactLists()->attach($this->active->id, [
            'status' => 'active',
            'subscribed_at' => now()->subDays(60),
        ]);
        $this->subscriber->contactLists()->attach($this->unsubscribed->id, [
            'status' => 'unsubscribed',
            'subscribed_at' => now()->subDays(60),
            'unsubscribed_at' => now()->subDays(10),
        ]);
        $this->subscriber->contactLists()->attach($this->bounced->id, [
            'status' => 'bounced',
            'subscribed_at' => now()->subDays(60),
            'soft_bounce_count' => 3,
        ]);
        $this->subscriber->contactLists()->attach($this->pending->id, [
            'status' => 'pending',
            'subscribed_at' => now()->subDays(1),
        ]);
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

    private function pivot(ContactList $list): ?object
    {
        return DB::table('contact_list_subscriber')
            ->where('subscriber_id', $this->subscriber->id)
            ->where('contact_list_id', $list->id)
            ->first();
    }

    private function listIdsOnTheEditForm(): array
    {
        $ids = [];

        $this->actingAs($this->user)
            ->get(route('subscribers.edit', $this->subscriber))
            ->assertOk()
            ->assertInertia(function ($page) use (&$ids) {
                $ids = $page->toArray()['props']['subscriber']['contact_list_ids'];
            });

        return $ids;
    }

    private function save(array $listIds): void
    {
        $this->actingAs($this->user)
            ->put(route('subscribers.update', $this->subscriber), [
                'email' => $this->subscriber->email,
                'first_name' => 'Renamed',
                'last_name' => null,
                'phone' => null,
                'gender' => null,
                'contact_list_ids' => $listIds,
                'status' => 'active',
            ])
            ->assertSessionHasNoErrors();
    }

    public function test_saving_the_form_unchanged_keeps_memberships_it_does_not_show(): void
    {
        $listIds = $this->listIdsOnTheEditForm();
        $this->assertSame([$this->active->id], $listIds);

        $this->save($listIds);

        $this->assertSame('Renamed', $this->subscriber->fresh()->first_name);
        $this->assertSame('active', $this->pivot($this->active)?->status);

        $unsubscribed = $this->pivot($this->unsubscribed);
        $this->assertSame('unsubscribed', $unsubscribed?->status);
        $this->assertNotNull($unsubscribed->unsubscribed_at);

        $bounced = $this->pivot($this->bounced);
        $this->assertSame('bounced', $bounced?->status);
        $this->assertSame(3, (int) $bounced->soft_bounce_count);

        $this->assertSame('pending', $this->pivot($this->pending)?->status);

        Event::assertNotDispatched(SubscriberSignedUp::class);
    }

    public function test_unticking_an_active_list_still_removes_it(): void
    {
        $this->save([$this->unsubscribed->id]);

        $this->assertNull($this->pivot($this->active));
        $this->assertNotNull($this->pivot($this->bounced));
        $this->assertNotNull($this->pivot($this->pending));
    }

    public function test_ticking_a_list_the_subscriber_left_reactivates_it(): void
    {
        $this->save([$this->active->id, $this->unsubscribed->id, $this->bounced->id]);

        $unsubscribed = $this->pivot($this->unsubscribed);
        $this->assertSame('active', $unsubscribed?->status);
        $this->assertNull($unsubscribed->unsubscribed_at);

        $bounced = $this->pivot($this->bounced);
        $this->assertSame('active', $bounced?->status);
        $this->assertSame(0, (int) $bounced->soft_bounce_count);

        $this->assertSame('pending', $this->pivot($this->pending)?->status);

        $signedUp = [];
        Event::assertDispatched(SubscriberSignedUp::class, function (SubscriberSignedUp $event) use (&$signedUp) {
            $signedUp[] = $event->list->id;

            return true;
        });
        sort($signedUp);
        $this->assertSame([$this->unsubscribed->id, $this->bounced->id], $signedUp);
    }
}
