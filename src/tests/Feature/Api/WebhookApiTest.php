<?php

namespace Tests\Feature\Api;

use App\Models\ApiKey;
use App\Models\User;
use App\Models\Webhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The webhook CRUD the MCP webhook tools drive: ownership, the write scope,
 * the secret being returned only on creation / regeneration, and partial
 * updates.
 */
class WebhookApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $key;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->key = ApiKey::generate($this->user->id, 'Hooks', ['webhooks:write'])['key'];
    }

    private function api(string $method, string $uri, array $payload = [], ?string $key = null): TestResponse
    {
        return $this->withHeaders(['Authorization' => 'Bearer ' . ($key ?? $this->key)])
            ->json($method, "/api/v1/{$uri}", $payload);
    }

    public function test_webhook_lifecycle(): void
    {
        $created = $this->api('POST', 'webhooks', [
            'name' => 'CRM sync',
            'url' => 'https://hooks.example.com/netsendo',
            'events' => ['subscriber.created'],
        ])->assertCreated()->json('data');

        $this->assertSame(64, strlen($created['secret']));
        $this->assertTrue($created['is_active']);

        $this->api('GET', "webhooks/{$created['id']}")
            ->assertOk()
            ->assertJsonMissingPath('data.secret');

        $this->api('PUT', "webhooks/{$created['id']}", ['is_active' => false, 'events' => ['subscriber.unsubscribed', 'sms.failed']])
            ->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.events', ['subscriber.unsubscribed', 'sms.failed'])
            ->assertJsonPath('data.name', 'CRM sync');

        $secret = $this->api('POST', "webhooks/{$created['id']}/regenerate-secret")->assertOk()->json('secret');
        $this->assertNotSame($created['secret'], $secret);

        $this->api('DELETE', "webhooks/{$created['id']}")->assertOk();
        $this->assertDatabaseCount('webhooks', 0);
    }

    public function test_null_is_active_is_rejected_instead_of_failing(): void
    {
        $webhook = Webhook::createWithSecret([
            'user_id' => $this->user->id, 'name' => 'x', 'url' => 'https://a.example.com', 'events' => ['sms.sent'], 'is_active' => true,
        ]);

        $this->api('PATCH', "webhooks/{$webhook->id}", ['is_active' => null])
            ->assertStatus(422)
            ->assertJsonValidationErrors('is_active');
    }

    public function test_unknown_event_is_rejected(): void
    {
        $this->api('POST', 'webhooks', ['name' => 'x', 'url' => 'https://a.example.com', 'events' => ['subscriber.exploded']])
            ->assertStatus(422);
    }

    public function test_other_accounts_webhook_is_not_found_and_writes_need_the_scope(): void
    {
        $foreign = Webhook::createWithSecret([
            'user_id' => User::factory()->create()->id, 'name' => 'theirs', 'url' => 'https://a.example.com', 'events' => ['sms.sent'], 'is_active' => true,
        ]);

        $this->api('GET', "webhooks/{$foreign->id}")->assertNotFound();
        $this->api('PATCH', "webhooks/{$foreign->id}", ['name' => 'mine'])->assertNotFound();
        $this->api('DELETE', "webhooks/{$foreign->id}")->assertNotFound();
        $this->api('POST', "webhooks/{$foreign->id}/regenerate-secret")->assertNotFound();

        $readOnly = ApiKey::generate($this->user->id, 'ro', ['webhooks:read'])['key'];
        $this->api('GET', 'webhooks', [], $readOnly)->assertOk();
        $this->api('POST', 'webhooks', ['name' => 'x', 'url' => 'https://a.example.com', 'events' => ['sms.sent']], $readOnly)
            ->assertForbidden();
    }

    public function test_switching_a_disabled_webhook_back_on_resets_its_failure_count(): void
    {
        // Switched off by NetSendo after 10 failed deliveries
        $webhook = Webhook::createWithSecret([
            'user_id' => $this->user->id, 'name' => 'CRM', 'url' => 'https://a.example.com', 'events' => ['sms.sent'],
            'is_active' => false, 'failure_count' => 10,
        ]);

        $this->api('PATCH', "webhooks/{$webhook->id}", ['is_active' => true])
            ->assertOk()
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.failure_count', 0);

        // One more failure must not switch it off again at once
        $webhook->refresh()->incrementFailure();
        $this->assertTrue($webhook->fresh()->is_active);
        $this->assertSame(1, $webhook->fresh()->failure_count);
    }

    public function test_renaming_an_active_webhook_keeps_its_failure_count(): void
    {
        $webhook = Webhook::createWithSecret([
            'user_id' => $this->user->id, 'name' => 'CRM', 'url' => 'https://a.example.com', 'events' => ['sms.sent'],
            'is_active' => true, 'failure_count' => 3,
        ]);

        $this->api('PATCH', "webhooks/{$webhook->id}", ['name' => 'CRM 2', 'is_active' => true])
            ->assertOk()
            ->assertJsonPath('data.failure_count', 3);
    }

    public function test_a_url_of_2048_characters_is_accepted_and_stored_whole(): void
    {
        // The column was VARCHAR(255): on MySQL a longer URL ended in a 500 (SQLite ignores the length)
        $url = 'https://hooks.example.com/netsendo?sig=' . str_repeat('a', 2048 - 39);
        $this->assertSame(2048, strlen($url));

        $id = $this->api('POST', 'webhooks', ['name' => 'Signed', 'url' => $url, 'events' => ['sms.sent']])
            ->assertCreated()
            ->json('data.id');

        $this->assertSame($url, Webhook::find($id)->url);
    }
}
