<?php

namespace Tests\Feature\Api;

use App\Models\ApiKey;
use App\Models\ContactList;
use App\Models\Subscriber;
use App\Models\Tag;
use App\Models\User;
use App\Services\Automation\AutomationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An API key reaches only its own account: POST /api/v1/subscribers accepted (and returned)
 * tag IDs of another account, and POST /api/webhooks/purchase picked a contact with the
 * given email from any account.
 */
class ApiAccountScopeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $other;

    private string $key;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->other = User::factory()->create();
        $this->key = ApiKey::generate($this->user->id, 'Shop', ['subscribers:read', 'subscribers:write'])['key'];
    }

    private function api()
    {
        return $this->withHeaders(['Authorization' => 'Bearer ' . $this->key]);
    }

    private function list(): ContactList
    {
        return ContactList::create(['user_id' => $this->user->id, 'name' => 'Customers', 'type' => 'email', 'is_public' => true]);
    }

    public function test_a_tag_of_another_account_is_rejected(): void
    {
        $foreign = Tag::create(['user_id' => $this->other->id, 'name' => 'VIP elsewhere']);

        $this->api()->postJson('/api/v1/subscribers', [
            'email' => 'new@example.com',
            'contact_list_id' => $this->list()->id,
            'tags' => [$foreign->id],
        ])->assertStatus(422)->assertJsonValidationErrors('tags.0');

        $this->assertDatabaseMissing('subscribers', ['email' => 'new@example.com']);
    }

    public function test_an_own_tag_is_accepted(): void
    {
        $own = Tag::create(['user_id' => $this->user->id, 'name' => 'VIP']);

        $this->api()->postJson('/api/v1/subscribers', [
            'email' => 'new@example.com',
            'contact_list_id' => $this->list()->id,
            'tags' => [$own->id],
        ])->assertStatus(201);
    }

    public function test_the_purchase_webhook_uses_the_key_owners_contact(): void
    {
        Subscriber::create(['user_id' => $this->other->id, 'email' => 'buyer@example.com']);
        $mine = Subscriber::create(['user_id' => $this->user->id, 'email' => 'buyer@example.com']);

        $contexts = [];
        $this->mock(AutomationService::class, function ($service) use (&$contexts) {
            $service->shouldReceive('processEvent')->andReturnUsing(function (string $event, array $context) use (&$contexts) {
                $contexts[] = $context;
            });
        });

        $this->api()->postJson('/api/webhooks/purchase', [
            'event' => 'purchase',
            'subscriber_email' => 'buyer@example.com',
            'data' => ['order_id' => 'A-1', 'value' => 99],
        ])->assertOk();

        $this->assertCount(1, $contexts);
        $this->assertSame($mine->id, $contexts[0]['subscriber_id']);
        $this->assertSame($this->user->id, $contexts[0]['user_id']);
    }

    public function test_the_purchase_webhook_does_not_find_a_contact_of_another_account(): void
    {
        Subscriber::create(['user_id' => $this->other->id, 'email' => 'stranger@example.com']);

        $this->mock(AutomationService::class, fn ($service) => $service->shouldNotReceive('processEvent'));

        $this->api()->postJson('/api/webhooks/purchase', [
            'event' => 'purchase',
            'subscriber_email' => 'stranger@example.com',
            'data' => ['order_id' => 'A-2', 'value' => 10],
        ])->assertStatus(404);
    }
}
