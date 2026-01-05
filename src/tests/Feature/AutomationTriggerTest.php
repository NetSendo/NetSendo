<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Subscriber;
use App\Models\ContactList;
use App\Models\AutomationRule;
use App\Models\AutomationRuleLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use App\Events\SubscriberSignedUp;

class AutomationTriggerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected ContactList $listA;
    protected ContactList $listB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->listA = ContactList::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'List A',
            'type' => 'email',
        ]);
        $this->listB = ContactList::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'List B',
            'type' => 'email',
        ]);
    }

    /** @test */
    public function test_bulk_move_triggers_automation()
    {
        // Create automation: when subscribing to List B, unsubscribe from List A
        $automation = AutomationRule::create([
            'user_id' => $this->user->id,
            'name' => 'Remove from A when added to B',
            'trigger_event' => 'subscriber_signup',
            'trigger_config' => ['list_id' => $this->listB->id],
            'conditions' => [],
            'actions' => [
                [
                    'type' => 'unsubscribe',
                    'config' => ['list_id' => $this->listA->id],
                ]
            ],
            'is_active' => true,
        ]);

        // Create subscriber on List A
        $subscriber = Subscriber::factory()->create([
            'user_id' => $this->user->id,
            'email' => 'test@example.com',
        ]);
        $subscriber->contactLists()->attach($this->listA->id, [
            'status' => 'active',
            'subscribed_at' => now(),
        ]);

        // Verify subscriber is on List A
        $this->assertTrue($subscriber->contactLists->contains($this->listA));

        // Bulk move subscriber from List A to List B
        $this->actingAs($this->user)->post(route('subscribers.bulk-move'), [
            'ids' => [$subscriber->id],
            'source_list_id' => $this->listA->id,
            'target_list_id' => $this->listB->id,
        ]);

        // Wait for queue jobs to process
        $this->artisan('queue:work --stop-when-empty');

        // Refresh subscriber
        $subscriber->refresh();

        // Verify subscriber is on List B but NOT on List A (automation worked)
        $this->assertTrue($subscriber->contactLists->contains($this->listB));
        $this->assertFalse($subscriber->contactLists->contains($this->listA));

        // Verify automation log exists
        $this->assertDatabaseHas('automation_rule_logs', [
            'automation_rule_id' => $automation->id,
            'subscriber_id' => $subscriber->id,
            'trigger_event' => 'subscriber_signup',
        ]);
    }

    /** @test */
    public function test_bulk_copy_triggers_automation()
    {
        // Create automation: when subscribing to List B, add a tag
        $tag = \App\Models\Tag::create([
            'user_id' => $this->user->id,
            'name' => 'Auto Tag',
        ]);

        $automation = AutomationRule::create([
            'user_id' => $this->user->id,
            'name' => 'Add tag when added to B',
            'trigger_event' => 'subscriber_signup',
            'trigger_config' => ['list_id' => $this->listB->id],
            'conditions' => [],
            'actions' => [
                [
                    'type' => 'add_tag',
                    'config' => ['tag_id' => $tag->id],
                ]
            ],
            'is_active' => true,
        ]);

        // Create subscriber on List A
        $subscriber = Subscriber::factory()->create([
            'user_id' => $this->user->id,
            'email' => 'test2@example.com',
        ]);
        $subscriber->contactLists()->attach($this->listA->id, [
            'status' => 'active',
            'subscribed_at' => now(),
        ]);

        // Verify subscriber doesn't have the tag
        $this->assertFalse($subscriber->tags->contains($tag));

        // Bulk copy subscriber to List B
        $this->actingAs($this->user)->post(route('subscribers.bulk-copy'), [
            'ids' => [$subscriber->id],
            'target_list_id' => $this->listB->id,
        ]);

        // Wait for queue jobs to process
        $this->artisan('queue:work --stop-when-empty');

        // Refresh subscriber
        $subscriber->refresh();

        // Verify subscriber is on both lists
        $this->assertTrue($subscriber->contactLists->contains($this->listA));
        $this->assertTrue($subscriber->contactLists->contains($this->listB));

        // Verify tag was added (automation worked)
        $this->assertTrue($subscriber->tags->contains($tag));
    }

    /** @test */
    public function test_bulk_add_triggers_automation()
    {
        // Create automation similar to bulk_copy test
        $tag = \App\Models\Tag::create([
            'user_id' => $this->user->id,
            'name' => 'Bulk Add Tag',
        ]);

        $automation = AutomationRule::create([
            'user_id' => $this->user->id,
            'name' => 'Add tag when added to B',
            'trigger_event' => 'subscriber_signup',
            'trigger_config' => ['list_id' => $this->listB->id],
            'conditions' => [],
            'actions' => [
                [
                    'type' => 'add_tag',
                    'config' => ['tag_id' => $tag->id],
                ]
            ],
            'is_active' => true,
        ]);

        // Create subscriber on List A
        $subscriber = Subscriber::factory()->create([
            'user_id' => $this->user->id,
            'email' => 'test3@example.com',
        ]);
        $subscriber->contactLists()->attach($this->listA->id, [
            'status' => 'active',
            'subscribed_at' => now(),
        ]);

        // Bulk add subscriber to List B
        $this->actingAs($this->user)->post(route('subscribers.bulk-add-to-list'), [
            'ids' => [$subscriber->id],
            'target_list_id' => $this->listB->id,
        ]);

        // Wait for queue jobs to process
        $this->artisan('queue:work --stop-when-empty');

        // Refresh subscriber
        $subscriber->refresh();

        // Verify subscriber is on both lists
        $this->assertTrue($subscriber->contactLists->contains($this->listA));
        $this->assertTrue($subscriber->contactLists->contains($this->listB));

        // Verify tag was added (automation worked)
        $this->assertTrue($subscriber->tags->contains($tag));
    }

    /** @test */
    public function test_manual_add_always_triggers_automation_without_welcome_email()
    {
        // Create automation
        $tag = \App\Models\Tag::create([
            'user_id' => $this->user->id,
            'name' => 'Manual Add Tag',
        ]);

        $automation = AutomationRule::create([
            'user_id' => $this->user->id,
            'name' => 'Add tag on manual signup',
            'trigger_event' => 'subscriber_signup',
            'trigger_config' => ['list_id' => $this->listB->id],
            'conditions' => [],
            'actions' => [
                [
                    'type' => 'add_tag',
                    'config' => ['tag_id' => $tag->id],
                ]
            ],
            'is_active' => true,
        ]);

        // Manually create subscriber WITHOUT send_welcome_email checked
        $this->actingAs($this->user)->post(route('subscribers.store'), [
            'email' => 'manual@example.com',
            'first_name' => 'Manual',
            'last_name' => 'Test',
            'contact_list_ids' => [$this->listB->id],
            'status' => 'active',
            // Note: send_welcome_email is NOT set (false)
        ]);

        // Wait for queue jobs to process
        $this->artisan('queue:work --stop-when-empty');

        // Find the subscriber
        $subscriber = Subscriber::where('email', 'manual@example.com')->first();
        $this->assertNotNull($subscriber);

        // Verify tag was added even without welcome email (automation worked)
        $this->assertTrue($subscriber->tags->contains($tag));
    }

    /** @test */
    public function test_subscriber_signed_up_event_is_dispatched_on_bulk_operations()
    {
        Event::fake([SubscriberSignedUp::class]);

        $subscriber = Subscriber::factory()->create([
            'user_id' => $this->user->id,
            'email' => 'event@example.com',
        ]);
        $subscriber->contactLists()->attach($this->listA->id);

        // Test bulk move
        $this->actingAs($this->user)->post(route('subscribers.bulk-move'), [
            'ids' => [$subscriber->id],
            'source_list_id' => $this->listA->id,
            'target_list_id' => $this->listB->id,
        ]);

        Event::assertDispatched(SubscriberSignedUp::class, function ($event) use ($subscriber) {
            return $event->subscriber->id === $subscriber->id
                && $event->source === 'bulk_move';
        });
    }
}
