<?php

namespace Tests\Feature\Connector;

use App\Models\ContactList;
use App\Models\Tag;
use App\Models\User;
use App\Services\Connector\NodeToolEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The connector end to end with the real tools: /mcp → Node.js bridge of the
 * @netsendo/mcp-client package (mcp/dist/bridge.js) → API v1 inside the
 * application → database.
 *
 * Needs Node.js and the built package with its dependencies
 * (`npm ci && npm run build` in mcp/); skipped where they are missing.
 */
class ConnectorNodeEngineTest extends TestCase
{
    use ConnectsToConnector;
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->app->make(NodeToolEngine::class)->available()) {
            $this->markTestSkipped('Node.js or mcp/dist/bridge.js (with mcp/node_modules) is not available.');
        }

        $this->user = User::factory()->create(['name' => 'Anna Owner']);
    }

    public function test_the_tool_list_is_the_one_of_the_mcp_client_package(): void
    {
        $token = $this->connect($this->user)['access_token'];

        $tools = collect($this->mcp($token, 'tools/list')->assertOk()->json('result.tools'))->keyBy('name');

        // One page, every tool; the count follows the package (140 in 1.6.0).
        $this->assertGreaterThanOrEqual(140, $tools->count());
        $this->assertNull($this->mcp($token, 'tools/list')->json('result.nextCursor'));

        foreach (['list_contact_lists', 'create_subscriber', 'create_campaign', 'send_campaign', 'create_tag', 'get_account_info'] as $name) {
            $this->assertTrue($tools->has($name), "Missing tool: {$name}");
        }

        foreach ($tools as $tool) {
            $this->assertSame('object', $tool['inputSchema']['type'], $tool['name']);
            $this->assertNotSame('', $tool['description'], $tool['name']);
            $this->assertMatchesRegularExpression('/^[a-zA-Z0-9_-]{1,64}$/', $tool['name']);
            $this->assertIsBool($tool['annotations']['readOnlyHint'], $tool['name']);
        }

        $this->assertTrue($tools['list_contact_lists']['annotations']['readOnlyHint']);
        $this->assertFalse($tools['create_tag']['annotations']['readOnlyHint']);
        $this->assertTrue($tools['delete_tag']['annotations']['destructiveHint']);
        $this->assertSame(['name'], $tools['create_tag']['inputSchema']['required']);
    }

    public function test_real_read_and_write_tools_run_as_the_connected_user(): void
    {
        ContactList::create(['user_id' => $this->user->id, 'name' => 'Readers', 'type' => 'email', 'is_public' => true]);
        ContactList::create(['user_id' => User::factory()->create()->id, 'name' => 'Somebody else', 'type' => 'email', 'is_public' => true]);

        $token = $this->connect($this->user)['access_token'];

        $account = $this->callTool($token, 'get_account_info')->assertOk()->assertJsonPath('result.isError', false);
        $this->assertStringContainsString($this->user->email, $this->toolText($account));

        $lists = $this->toolText($this->callTool($token, 'list_contact_lists')->assertOk()->assertJsonPath('result.isError', false));
        $this->assertStringContainsString('Readers', $lists);
        $this->assertStringNotContainsString('Somebody else', $lists);

        $created = $this->callTool($token, 'create_tag', ['name' => 'webinar-2026', 'color' => '#22c55e'])
            ->assertOk()
            ->assertJsonPath('result.isError', false);
        $this->assertStringContainsString('"success": true', $this->toolText($created));
        $this->assertDatabaseHas('tags', ['user_id' => $this->user->id, 'name' => 'webinar-2026', 'color' => '#22c55e']);

        // A tool with a path parameter and a JSON body.
        $tag = Tag::where('name', 'webinar-2026')->firstOrFail();
        $this->callTool($token, 'update_tag', ['tag_id' => $tag->id, 'description' => 'Autumn webinar'])
            ->assertJsonPath('result.isError', false);
        $this->assertSame('Autumn webinar', $tag->fresh()->description);
    }

    public function test_a_real_write_tool_is_refused_without_the_permission_and_says_how_to_fix_it(): void
    {
        $token = $this->connect($this->user, ['lists:read', 'tags:read'])['access_token'];

        $response = $this->callTool($token, 'create_tag', ['name' => 'not-allowed'])
            ->assertOk()
            ->assertJsonPath('result.isError', true);

        $this->assertStringContainsString('Error 403', $this->toolText($response));
        $this->assertStringContainsString('lists:write', $this->toolText($response));
        $this->assertStringContainsString('connect it again', $this->toolText($response));
        $this->assertStringNotContainsString('enable it in NetSendo', $this->toolText($response));
        $this->assertDatabaseMissing('tags', ['name' => 'not-allowed']);

        // A controller that checks the permission the older way, on a JSON request.
        $sms = $this->callTool($token, 'send_sms', ['phone' => '+48500100200', 'content' => 'Hello'])
            ->assertJsonPath('result.isError', true);
        $this->assertStringContainsString('sms:write', $this->toolText($sms));
        $this->assertStringContainsString('connect it again', $this->toolText($sms));
    }

    public function test_arguments_that_do_not_match_the_tool_schema_come_back_as_a_readable_error(): void
    {
        $token = $this->connect($this->user)['access_token'];

        $response = $this->callTool($token, 'create_tag', ['color' => 'green'])
            ->assertOk()
            ->assertJsonPath('result.isError', true);

        $this->assertStringContainsString('name', $this->toolText($response));
        $this->assertDatabaseCount('tags', 0);
    }

    public function test_api_validation_errors_reach_the_model(): void
    {
        Tag::create(['user_id' => $this->user->id, 'name' => 'vip']);
        $token = $this->connect($this->user)['access_token'];

        // A duplicate tag name is refused by the API with 422.
        $response = $this->callTool($token, 'create_tag', ['name' => 'vip'])->assertJsonPath('result.isError', true);

        $this->assertStringContainsString('Error 422', $this->toolText($response));
    }
}
