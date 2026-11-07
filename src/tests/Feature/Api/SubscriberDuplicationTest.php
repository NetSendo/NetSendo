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

    public function test_recreating_a_soft_deleted_subscriber_restores_it_instead_of_duplicating()
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

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->apiKey])
            ->postJson('/api/v1/subscribers', $payload);

        // The deleted contact is restored and reused instead of duplicated
        // (unique email per user). Like any existing address it answers 200,
        // not 201 (docs/API_DOCUMENTATION.md, POST /api/v1/subscribers).
        $response->assertStatus(200)
            ->assertJsonPath('data.id', $subscriber->id);

        $this->assertSame(1, Subscriber::withTrashed()->where('email', 'duplicate@example.com')->count());
        $this->assertNotSoftDeleted('subscribers', ['id' => $subscriber->id]);
        $this->assertTrue($subscriber->fresh()->contactLists()->whereKey($this->list->id)->exists());
    }
}
