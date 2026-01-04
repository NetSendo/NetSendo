<?php

namespace Tests\Feature\Api;

use App\Models\ApiKey;
use App\Models\ContactList;
use App\Models\ContactListGroup;
use App\Models\Subscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriberDuplicationTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $apiKey;
    protected $list;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        // Generate API Key
        $result = ApiKey::generate($this->user->id, 'Test Key', ['subscribers:read', 'subscribers:write']);
        $this->apiKey = $result['key'];

        // Create a contact list
        $group = ContactListGroup::create(['name' => 'Default', 'user_id' => $this->user->id]);
        $this->list = ContactList::create([
            'name' => 'Test List',
            'type' => 'email',
            'contact_list_group_id' => $group->id,
            'user_id' => $this->user->id,
            'is_public' => true,
        ]);
    }

    public function test_cannot_create_duplicate_of_soft_deleted_subscriber()
    {
        // 1. Create a subscriber
        $subscriber = Subscriber::create([
            'user_id' => $this->user->id,
            'email' => 'duplicate@example.com',
            'first_name' => 'John',
            'is_active_global' => true,
            'source' => 'api',
            'subscribed_at' => now(),
        ]);

        // Attach to list
        $subscriber->contactLists()->attach($this->list->id, ['status' => 'active']);

        // 2. Soft delete the subscriber
        $subscriber->delete();
        $this->assertSoftDeleted('subscribers', ['id' => $subscriber->id]);

        // 3. Try to create the same subscriber via API
        $payload = [
            'email' => 'duplicate@example.com',
            'contact_list_id' => $this->list->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
        ];

        // This is expected to fail currently with 500 or 409 or some SQL error
        // But for the test to be useful/pass initially we might want to catch it or assert it fails.
        // However, I want to prove it fails first.

        try {
            $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->apiKey])
                ->postJson('/api/v1/subscribers', $payload);

            // If it succeeds (which it shouldn't if bug exists), we print status
            if ($response->status() !== 500) {
                 // It might return 201 if my assumption is wrong, or 500 if SQL error
            }

            $response->assertStatus(201); // We WANT this to eventually pass
        } catch (\Exception $e) {
            // If it throws exception during test execution (unlikely with Laravel testing, usually returns 500 response)
            throw $e;
        }
    }
}
