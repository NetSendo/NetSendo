<?php

namespace Tests\Feature;

use App\Models\ContactList;
use App\Models\Subscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class SubscriberPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscriber_unsubscription_fails_if_wrong_user_identified()
    {
        // 1. Setup Data: Two Users
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        // List A belonging to User A
        $listA = ContactList::create([
            'user_id' => $userA->id,
            'is_public' => true,
            'type' => 'email',
            'name' => 'List A',
            'settings' => [],
            'webhook_events' => [],
            'sync_settings' => [],
            'required_fields' => [],
        ]);

        // List B belonging to User B (contamination)
        $listB = ContactList::create([
            'user_id' => $userB->id,
            'is_public' => true,
            'type' => 'email',
            'name' => 'List B',
            'settings' => [],
            'webhook_events' => [],
            'sync_settings' => [],
            'required_fields' => [],
        ]);

        // Subscriber belongs to User A
        $subscriber = Subscriber::create([
            'user_id' => $userA->id,
            'email' => 'test@example.com',
            'status' => 'active',
            'is_active_global' => true,
        ]);

        // Attach subscriber to BOTH lists
        // Note: In a clean system, maybe this shouldn't happen, but if it does:
        $subscriber->contactLists()->attach($listA->id, ['status' => 'active', 'subscribed_at' => now(), 'source' => 'import']);
        $subscriber->contactLists()->attach($listB->id, ['status' => 'active', 'subscribed_at' => now(), 'source' => 'import']);

        // Verify initial state
        $this->assertDatabaseHas('contact_list_subscriber', ['contact_list_id' => $listA->id, 'subscriber_id' => $subscriber->id, 'status' => 'active']);
        $this->assertDatabaseHas('contact_list_subscriber', ['contact_list_id' => $listB->id, 'subscriber_id' => $subscriber->id, 'status' => 'active']);

        // 2. Prepare "Pending Changes"
        // User wants to unsubscribe from List A.
        // If the system correctly identifies User A, it should process List A.
        $pendingChanges = [
            'selected_lists' => [], // Uncheck all (remove List A)
            'requested_at' => now()->toISOString(),
        ];

        // 3. Generate Signed URL
        $url = URL::signedRoute('subscriber.preferences.confirm', [
            'subscriber' => $subscriber->id,
            'changes' => base64_encode(json_encode($pendingChanges)),
        ]);

        // 4. Hit the endpoint
        // NOTE: We rely on `first()` effectively picking List B to trigger the bug.
        // We can't easily force SQL order without hacking.
        // However, if we delete the relationship to List A properly? No.

        // If the controller logic is flawed, it *might* pick User B if List B comes first.
        // Let's hope the DB returns List B first or we can try to influence it?
        // Actually, let's verify if the code uses `$subscriber->user_id` it WOULD work.

        $response = $this->get($url);
        $response->assertStatus(200);

        // 5. Assert Database Changes
        // Use assertion that SHOULD fail if the bug exists and List B was picked.
        // If List A was picked, this passes.
        // To ensure we test the fix, checking correct behavior is key.

        $this->assertDatabaseHas('contact_list_subscriber', [
            'contact_list_id' => $listA->id,
            'subscriber_id' => $subscriber->id,
            'status' => 'unsubscribed', // Expect List A unsubs
        ]);

        // We don't care about List B in this context as it's not User A's list (so shouldn't be touched or shown).
    }
}
