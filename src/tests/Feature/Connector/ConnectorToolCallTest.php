<?php

namespace Tests\Feature\Connector;

use App\Models\ApiKey;
use App\Models\ApiRequestLog;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tool calls on /mcp. A tool is a set of API v1 requests made for the
 * connected user inside the application, so the permissions granted on the
 * consent screen are enforced by the same checks as for an API key.
 *
 * These tests use a stand-in tool engine (no Node.js needed);
 * ConnectorNodeEngineTest runs the real tools.
 */
class ConnectorToolCallTest extends TestCase
{
    use ConnectsToConnector;
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->fakeToolEngine();
    }

    public function test_the_server_describes_itself_and_lists_its_tools(): void
    {
        $token = $this->connect($this->user)['access_token'];

        $this->mcp($token, 'server/discover')
            ->assertOk()
            ->assertJsonPath('result.supportedVersions', ['2026-07-28'])
            ->assertJsonStructure(['result' => ['capabilities' => ['tools'], 'instructions']])
            ->assertJsonMissingPath('result.capabilities.prompts');

        $this->assertSame('NetSendo', $this->mcp($token, 'server/discover')->json('result._meta')['io.modelcontextprotocol/serverInfo']['name']);

        $this->mcp($token, 'tools/list')
            ->assertOk()
            ->assertJsonCount(3, 'result.tools')
            ->assertJsonPath('result.tools.0.name', 'list_tags')
            ->assertJsonPath('result.tools.0.annotations.readOnlyHint', true)
            ->assertJsonPath('result.tools.1.inputSchema.required', ['name']);

        // The schema of a tool without arguments stays an object, not a list.
        $this->assertStringContainsString('"properties":{}', $this->mcp($token, 'tools/list')->getContent());
    }

    public function test_hosts_on_the_older_handshake_protocol_are_served_too(): void
    {
        $token = $this->connect($this->user)['access_token'];
        $legacy = fn (string $method, array $params = []) => $this
            ->withHeaders(['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json, text/event-stream'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 7, 'method' => $method, 'params' => (object) $params]);

        $legacy('initialize', [
            'protocolVersion' => '2025-06-18',
            'capabilities' => (object) [],
            'clientInfo' => ['name' => 'old-host', 'version' => '1.0'],
        ])
            ->assertOk()
            ->assertJsonPath('result.protocolVersion', '2025-06-18')
            ->assertJsonPath('result.serverInfo.name', 'NetSendo');

        // "notifications/initialized" has no id and gets 202 with no body.
        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'])
            ->assertStatus(202);

        $legacy('tools/list')->assertOk()->assertJsonCount(3, 'result.tools');
    }

    public function test_a_read_tool_and_a_write_tool_work_within_the_granted_permissions(): void
    {
        Tag::create(['user_id' => $this->user->id, 'name' => 'vip']);
        $token = $this->connect($this->user, ['tags:read', 'lists:write'])['access_token'];

        $read = $this->callTool($token, 'list_tags')->assertOk()->assertJsonPath('result.isError', false);
        $this->assertStringContainsString('"name":"vip"', $this->toolText($read));

        $write = $this->callTool($token, 'create_tag', ['name' => 'webinar'])->assertOk()->assertJsonPath('result.isError', false);
        $this->assertStringStartsWith('201 ', $this->toolText($write));

        $this->assertDatabaseHas('tags', ['user_id' => $this->user->id, 'name' => 'webinar']);
    }

    public function test_a_write_tool_is_refused_when_the_write_permission_was_not_granted(): void
    {
        $token = $this->connect($this->user, ['tags:read', 'lists:read'])['access_token'];

        $response = $this->callTool($token, 'create_tag', ['name' => 'webinar'])
            ->assertOk()
            ->assertJsonPath('result.isError', true);

        $this->assertStringStartsWith('403 ', $this->toolText($response));
        $this->assertStringContainsString('lists:write', $this->toolText($response));
        $this->assertDatabaseMissing('tags', ['name' => 'webinar']);

        // Reading still works with what was granted.
        $this->callTool($token, 'list_tags')->assertJsonPath('result.isError', false);
    }

    public function test_every_style_of_permission_check_in_the_api_sees_the_connected_app(): void
    {
        // The SMS, funnel, A/B test and webhook controllers read the key with
        // \$request->get('api_key'), the newer ones with input(): both must find
        // the connection, also on a request with a JSON body.
        $token = $this->connect($this->user, ['tags:read', 'sms:read'])['access_token'];

        $refused = $this->callTool($token, 'send_sms', ['phone' => '+48500100200', 'message' => 'Hello'])
            ->assertOk()
            ->assertJsonPath('result.isError', true);
        $this->assertStringStartsWith('403 ', $this->toolText($refused));

        \Illuminate\Support\Facades\Queue::fake();
        $allowed = $this->connect($this->user, ['sms:write'])['access_token'];
        $this->assertStringStartsNotWith('403 ', $this->toolText($this->callTool($allowed, 'send_sms', ['phone' => '+48500100200', 'message' => 'Hello'])));
    }

    public function test_a_team_members_app_does_not_reach_the_owners_or_another_accounts_data(): void
    {
        $owner = $this->user;
        $member = User::factory()->create(['admin_user_id' => $owner->id]);
        $stranger = User::factory()->create();

        Tag::create(['user_id' => $owner->id, 'name' => 'owner-tag']);
        Tag::create(['user_id' => $member->id, 'name' => 'member-tag']);
        Tag::create(['user_id' => $stranger->id, 'name' => 'stranger-tag']);

        $token = $this->connect($member)['access_token'];
        $text = $this->toolText($this->callTool($token, 'list_tags')->assertOk());

        $this->assertStringContainsString('member-tag', $text);
        $this->assertStringNotContainsString('owner-tag', $text);
        $this->assertStringNotContainsString('stranger-tag', $text);

        // What the member's app creates belongs to the member.
        $this->callTool($token, 'create_tag', ['name' => 'from-app'])->assertJsonPath('result.isError', false);
        $this->assertDatabaseHas('tags', ['user_id' => $member->id, 'name' => 'from-app']);
        $this->assertSame($member->id, $this->connectionOf($member)->user_id);
    }

    public function test_disconnecting_in_the_panel_stops_the_next_call(): void
    {
        $tokens = $this->connect($this->user);
        $this->callTool($tokens['access_token'], 'list_tags')->assertOk();

        $this->actingAs($this->user)
            ->delete('/settings/connector/connections/'.$this->connectionOf($this->user)->id)
            ->assertRedirect();

        $this->callTool($tokens['access_token'], 'list_tags')
            ->assertStatus(401)
            ->assertHeader('WWW-Authenticate');

        $this->post('/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $tokens['client_id'],
            'refresh_token' => $tokens['refresh_token'],
        ])->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
    }

    public function test_tool_calls_are_recorded_in_the_api_log_and_mark_the_connection_as_used(): void
    {
        $token = $this->connect($this->user)['access_token'];

        $this->callTool($token, 'list_tags')->assertOk();

        $log = ApiRequestLog::where('user_id', $this->user->id)->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame('api/v1/tags', $log->endpoint);
        $this->assertStringContainsString('NetSendo-Connector (Connector: Test Host)', $log->user_agent);

        $this->assertNotNull($this->connectionOf($this->user)->last_used_at);
    }

    public function test_an_api_key_can_be_used_on_the_endpoint_by_hosts_without_sign_in(): void
    {
        Tag::create(['user_id' => $this->user->id, 'name' => 'vip']);
        $readOnly = ApiKey::generate($this->user->id, 'Reader', ['tags:read'])['key'];

        $this->mcp($readOnly, 'tools/list')->assertOk()->assertJsonCount(3, 'result.tools');
        $this->assertStringContainsString('vip', $this->toolText($this->callTool($readOnly, 'list_tags')));
        $this->callTool($readOnly, 'create_tag', ['name' => 'x'])->assertJsonPath('result.isError', true);

        $expired = ApiKey::generate($this->user->id, 'Old', ['tags:read']);
        $expired['model']->update(['expires_at' => now()->subDay()]);
        $this->mcp($expired['key'], 'tools/list')->assertStatus(401);

        $this->mcp('ns_live_'.str_repeat('a', 32), 'tools/list')->assertStatus(401);
    }

    public function test_an_access_token_is_not_an_api_key(): void
    {
        $token = $this->connect($this->user)['access_token'];

        // The REST API keeps accepting API keys only.
        $this->withHeaders(['Authorization' => 'Bearer '.$token])->getJson('/api/v1/tags')->assertStatus(401);

        // …and nothing a caller sends can stand in for the in-process identity.
        $this->withHeaders(['X-Netsendo-Connector-Api-Key' => '1'])
            ->getJson('/api/v1/tags?netsendo.connector_api_key=1')
            ->assertStatus(401);
    }

    public function test_unknown_tools_and_methods_are_reported_as_protocol_errors(): void
    {
        $token = $this->connect($this->user)['access_token'];

        $this->callTool($token, 'drop_database')->assertStatus(400)->assertJsonPath('error.code', -32602);
        $this->mcp($token, 'resources/list')->assertOk();
        $this->mcp($token, 'nonexistent/method')->assertStatus(404)->assertJsonPath('error.code', -32601);
    }
}
