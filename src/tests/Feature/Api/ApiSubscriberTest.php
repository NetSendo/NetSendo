<?php

namespace Tests\Feature\Api;

use App\Models\ApiKey;
use App\Models\ContactList;
use App\Models\ContactListGroup;
use App\Models\Subscriber;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ApiSubscriberTest extends TestCase
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

    public function test_api_requires_authentication()
    {
        $response = $this->getJson('/api/v1/subscribers');
        $response->assertStatus(401);
    }

    public function test_api_rejects_invalid_key()
    {
        $response = $this->withHeaders(['Authorization' => 'Bearer invalid_key'])
            ->getJson('/api/v1/subscribers');
        $response->assertStatus(401);
    }

    public function test_can_list_subscribers()
    {
        Subscriber::create([
            'email' => 'test@example.com',
            'contact_list_id' => $this->list->id,
            'status' => 'active',
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->apiKey])
            ->getJson('/api/v1/subscribers');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'test@example.com');
    }

    public function test_can_create_subscriber()
    {
        $payload = [
            'email' => 'new@example.com',
            'contact_list_id' => $this->list->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'custom_fields' => [
                'city' => 'Warsaw' // Assuming 'city' field might exist or be handled gracefully
            ]
        ];

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->apiKey])
            ->postJson('/api/v1/subscribers', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.email', 'new@example.com');

        $this->assertDatabaseHas('subscribers', [
            'email' => 'new@example.com',
            'contact_list_id' => $this->list->id
        ]);
    }

    public function test_cannot_create_duplicate_subscriber_in_same_list()
    {
        Subscriber::create([
            'email' => 'test@example.com',
            'contact_list_id' => $this->list->id,
            'status' => 'active',
        ]);

        $payload = [
            'email' => 'test@example.com',
            'contact_list_id' => $this->list->id,
        ];

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->apiKey])
            ->postJson('/api/v1/subscribers', $payload);

        $response->assertStatus(409);
    }

    public function test_can_update_subscriber()
    {
        $subscriber = Subscriber::create([
            'email' => 'update@example.com',
            'contact_list_id' => $this->list->id,
            'first_name' => 'OldName',
            'status' => 'active',
        ]);

        $payload = [
            'first_name' => 'NewName',
            'status' => 'unsubscribed',
        ];

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->apiKey])
            ->putJson("/api/v1/subscribers/{$subscriber->id}", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.first_name', 'NewName')
            ->assertJsonPath('data.status', 'unsubscribed');
    }

    public function test_can_delete_subscriber()
    {
        $subscriber = Subscriber::create([
            'email' => 'delete@example.com',
            'contact_list_id' => $this->list->id,
            'status' => 'active',
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->apiKey])
            ->deleteJson("/api/v1/subscribers/{$subscriber->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('subscribers', ['id' => $subscriber->id]);
    }

    public function test_permission_enforcement()
    {
        // Generate Read-Only Key
        $readResult = ApiKey::generate($this->user->id, 'Read Only', ['subscribers:read']);
        $readKey = $readResult['key'];

        // Try to create (should fail)
        $payload = [
            'email' => 'forbidden@example.com',
            'contact_list_id' => $this->list->id,
        ];

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $readKey])
            ->postJson('/api/v1/subscribers', $payload);

        $response->assertStatus(403);
    }

    public function test_can_sync_tags()
    {
        $subscriber = Subscriber::create([
            'email' => 'tags@example.com',
            'contact_list_id' => $this->list->id,
            'status' => 'active',
        ]);

        $tag1 = Tag::create(['name' => 'Tag 1', 'user_id' => $this->user->id]);
        $tag2 = Tag::create(['name' => 'Tag 2', 'user_id' => $this->user->id]);

        $payload = [
            'tags' => [$tag1->id, $tag2->id]
        ];

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->apiKey])
            ->postJson("/api/v1/subscribers/{$subscriber->id}/tags", $payload);

        $response->assertStatus(200);
        
        $subscriber->refresh();
        $this->assertCount(2, $subscriber->tags);
        $this->assertTrue($subscriber->tags->contains($tag1->id));
        $this->assertTrue($subscriber->tags->contains($tag2->id));
    }
}
