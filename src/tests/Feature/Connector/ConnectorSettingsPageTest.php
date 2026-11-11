<?php

namespace Tests\Feature\Connector;

use App\Models\ConnectorConnection;
use App\Models\ConnectorToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/** Settings → AI connector: the address, the connected apps and who may disconnect them. */
class ConnectorSettingsPageTest extends TestCase
{
    use ConnectsToConnector;
    use RefreshDatabase;

    private User $owner;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['name' => 'Olga Owner']);
        $this->member = User::factory()->create(['name' => 'Marek Member', 'admin_user_id' => $this->owner->id]);
        $this->fakeToolEngine();
    }

    public function test_the_page_shows_the_address_and_the_users_connected_apps(): void
    {
        $this->connect($this->owner, ['lists:read', 'tags:read']);

        $this->actingAs($this->owner)
            ->get('/settings/connector')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Settings/Connector/Index')
                ->where('enabled', true)
                ->where('connectorUrl', 'http://localhost/mcp')
                ->where('toolCount', 3)
                ->has('connections', 1)
                ->where('connections.0.name', 'Test Host')
                ->where('connections.0.scopes', ['lists:read', 'tags:read'])
                ->where('connections.0.full_access', false)
                ->where('connections.0.is_own', true)
                ->where('connections.0.verified_host', null)
            );
    }

    public function test_the_owner_sees_and_can_disconnect_apps_of_team_members_but_not_the_other_way(): void
    {
        $this->connect($this->owner);
        $memberToken = $this->connect($this->member)['access_token'];
        $ownerConnection = $this->connectionOf($this->owner);
        $memberConnection = $this->connectionOf($this->member);

        $this->actingAs($this->owner)->get('/settings/connector')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('connections', 2)->where('isAdmin', true));

        $this->actingAs($this->member)->get('/settings/connector')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('connections', 1)
                ->where('connections.0.id', $memberConnection->id)
                ->where('isAdmin', false)
            );

        // A member cannot disconnect the owner's app…
        $this->actingAs($this->member)->delete('/settings/connector/connections/'.$ownerConnection->id)->assertNotFound();
        $this->assertFalse($ownerConnection->fresh()->isRevoked());

        // …a stranger cannot disconnect anything of this account…
        $this->actingAs(User::factory()->create())->delete('/settings/connector/connections/'.$memberConnection->id)->assertNotFound();

        // …the owner can disconnect a member's app.
        $this->actingAs($this->owner)->delete('/settings/connector/connections/'.$memberConnection->id)->assertRedirect();
        $this->assertTrue($memberConnection->fresh()->isRevoked());
        $this->mcp($memberToken, 'tools/list')->assertStatus(401);

        $this->actingAs($this->owner)->get('/settings/connector')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('connections', 1));
    }

    public function test_the_page_says_when_the_connector_is_turned_off(): void
    {
        config(['connector.enabled' => false]);

        $this->actingAs($this->owner)->get('/settings/connector')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('enabled', false));
    }

    public function test_expired_connections_and_leftovers_are_pruned(): void
    {
        $this->connect($this->owner);
        $this->connect($this->member);
        $this->registerClient(); // registered, never authorised
        $this->connectionOf($this->member)->revoke();

        $this->travel(100)->days();
        Artisan::call('connector:prune');

        // The owner's refresh token ran out 10 days ago; the member's app was disconnected.
        $this->assertSame(0, ConnectorToken::count());
        $this->assertSame(0, ConnectorConnection::count());
        $this->assertDatabaseCount('connector_auth_codes', 0);
        $this->assertDatabaseCount('connector_clients', 0);
    }

    public function test_pruning_keeps_what_is_in_use(): void
    {
        $tokens = $this->connect($this->owner);

        $this->travel(30)->days();
        $this->post('/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $tokens['client_id'],
            'refresh_token' => $tokens['refresh_token'],
        ])->assertOk();

        $this->travel(80)->days();
        Artisan::call('connector:prune');

        $this->assertSame(1, ConnectorConnection::count());
        $this->assertSame(1, ConnectorToken::count());
        $this->assertDatabaseCount('connector_clients', 1);
    }

    public function test_every_text_of_the_connector_pages_exists_in_all_four_languages(): void
    {
        $keys = [];
        foreach (['js/Pages/Settings/Connector/Index.vue', 'js/Pages/Connector/Authorize.vue', 'js/Pages/Marketplace/MCP.vue'] as $file) {
            preg_match_all('/\bt\(\s*["\']((?:connector|navigation|api_keys)\.[A-Za-z0-9_.]+)["\']/', File::get(resource_path($file)), $matches);
            preg_match_all('/keypath="([a-z_.0-9]+)"/', File::get(resource_path($file)), $paths);
            $keys = [...$keys, ...$matches[1], ...$paths[1]];
        }

        // Keys the pages build at run time: guide steps and permission labels
        foreach (['claude' => 6, 'chatgpt' => 4] as $host => $steps) {
            for ($i = 1; $i <= $steps; $i++) {
                $keys[] = "connector.guide.{$host}.step_{$i}";
            }
        }
        foreach (\App\Services\Connector\Scopes::all() as $scope) {
            $keys[] = 'api_keys.permissions.'.str_replace(':', '_', $scope);
        }

        $this->assertGreaterThan(60, count(array_unique($keys)));

        foreach (['en', 'pl', 'de', 'es'] as $locale) {
            $messages = json_decode(File::get(resource_path("js/locales/{$locale}.json")), true);
            foreach (array_unique($keys) as $key) {
                $this->assertIsString(data_get($messages, $key), "{$locale}: {$key}");
            }
        }
    }
}
