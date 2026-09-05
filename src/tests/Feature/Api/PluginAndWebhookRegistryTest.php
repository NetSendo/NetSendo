<?php

namespace Tests\Feature\Api;

use App\Models\ApiKey;
use App\Models\PluginConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The n8n community node registers its trigger through POST /api/v1/webhooks
 * and reports its version through plugin/heartbeat. Both surfaces used to be
 * narrower than what the product actually emits and ships:
 * `email.queued` was dispatched but not registrable, and the heartbeat only
 * accepted the two WordPress plugins, so an n8n instance stayed invisible.
 */
class PluginAndWebhookRegistryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $apiKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->apiKey = ApiKey::generate(
            $this->user->id,
            'Test Key',
            ['webhooks:read', 'webhooks:write']
        )['key'];
    }

    private function headers(): array
    {
        return ['Authorization' => 'Bearer ' . $this->apiKey];
    }

    public function test_email_queued_can_be_registered_as_a_webhook_event(): void
    {
        $this->withHeaders($this->headers())
            ->postJson('/api/v1/webhooks', [
                'name' => 'n8n Workflow: Test',
                'url' => 'https://n8n.example.com/webhook/abc',
                'events' => ['email.queued', 'subscriber.created'],
            ])
            ->assertStatus(201);

        $this->assertDatabaseCount('webhooks', 1);
    }

    public function test_available_events_include_every_dispatched_event(): void
    {
        $events = $this->withHeaders($this->headers())
            ->getJson('/api/v1/webhooks/events')
            ->assertStatus(200)
            ->json('events');

        foreach (['email.queued', 'subscriber.resubscribed', 'subscriber.tag_added'] as $event) {
            $this->assertContains($event, $events);
        }
    }

    public function test_n8n_instance_can_report_its_version_through_heartbeat(): void
    {
        $this->withHeaders($this->headers())
            ->postJson('/api/v1/plugin/heartbeat', [
                'plugin_type' => 'n8n',
                'site_url' => 'https://n8n.example.com/',
                'site_name' => 'n8n',
                'plugin_version' => '1.3.2',
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('update_available', false);

        $connection = PluginConnection::where('user_id', $this->user->id)->firstOrFail();

        $this->assertSame('n8n', $connection->plugin_type);
        // Trailing slash normalised, so a re-report updates the same row
        $this->assertSame('https://n8n.example.com', $connection->site_url);
    }

    public function test_heartbeat_reports_an_outdated_node(): void
    {
        $this->withHeaders($this->headers())
            ->postJson('/api/v1/plugin/heartbeat', [
                'plugin_type' => 'n8n',
                'site_url' => 'https://n8n.example.com',
                'plugin_version' => '1.0.0',
            ])
            ->assertStatus(200)
            ->assertJsonPath('update_available', true)
            ->assertJsonPath('latest_version', config('netsendo.plugins.n8n.version'));
    }

    public function test_connections_summary_counts_every_registered_integration(): void
    {
        $this->withHeaders($this->headers())
            ->postJson('/api/v1/plugin/heartbeat', [
                'plugin_type' => 'n8n',
                'site_url' => 'https://n8n.example.com',
                'plugin_version' => '1.3.2',
            ])->assertStatus(200);

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/plugin/connections')
            ->assertStatus(200)
            ->assertJsonPath('stats.n8n', 1)
            ->assertJsonPath('stats.wordpress', 0)
            ->assertJsonPath('stats.total', 1);
    }

    public function test_unknown_integration_type_is_still_rejected(): void
    {
        $this->withHeaders($this->headers())
            ->postJson('/api/v1/plugin/heartbeat', [
                'plugin_type' => 'zapier',
                'site_url' => 'https://example.com',
                'plugin_version' => '1.0.0',
            ])
            ->assertStatus(422);
    }

    public function test_check_version_answers_for_the_n8n_node(): void
    {
        $this->withHeaders($this->headers())
            ->getJson('/api/v1/plugin/check-version?type=n8n&version=1.0.0')
            ->assertStatus(200)
            ->assertJsonPath('plugin_type', 'n8n')
            ->assertJsonPath('update_available', true)
            ->assertJsonPath('download_url', 'https://www.npmjs.com/package/n8n-nodes-netsendo');
    }
}
