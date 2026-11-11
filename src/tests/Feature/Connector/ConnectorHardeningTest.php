<?php

namespace Tests\Feature\Connector;

use App\Models\ConnectorClient;
use App\Models\ConnectorConnection;
use App\Models\ConnectorToken;
use App\Models\User;
use App\Services\Connector\ClientMetadataFetcher;
use App\Services\Connector\ClientRegistry;
use App\Services\Connector\OAuthException;
use App\Services\Connector\RedirectUri;
use App\Services\Connector\ToolCallSlots;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * What the connector does for requests that need no sign-in, and how much one
 * connected app may take: findings of the security review before the release.
 */
class ConnectorHardeningTest extends TestCase
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

    // ------------------------------------------------------------------
    // 1. The token endpoint never downloads anything
    // ------------------------------------------------------------------

    public function test_the_token_endpoint_does_not_download_a_metadata_document(): void
    {
        $fetcher = $this->countingFetcher(fn () => throw new OAuthException('invalid_client', 'unreachable', 400));

        foreach (['authorization_code', 'refresh_token'] as $grant) {
            $this->post('/oauth/token', [
                'grant_type' => $grant,
                'client_id' => 'https://slow-host.example/client.json',
                'code' => 'x',
                'redirect_uri' => 'https://slow-host.example/cb',
                'code_verifier' => str_repeat('a', 43),
                'refresh_token' => 'x',
            ])->assertStatus(400)->assertJsonPath('error', 'invalid_client');
        }

        $this->post('/oauth/revoke', ['token' => 'x', 'client_id' => 'https://slow-host.example/client.json'])->assertOk();

        $this->assertSame(0, $fetcher->fetches);
        $this->assertSame(0, ConnectorClient::count());
    }

    public function test_a_document_that_failed_is_not_downloaded_again_for_a_few_minutes(): void
    {
        $fetcher = $this->countingFetcher(fn () => throw new OAuthException('invalid_client', 'The client metadata document could not be downloaded.', 400));
        $url = $this->authorizeUrl('https://slow-host.example/client.json', $this->pkce()['challenge']);

        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($this->user)->get($url)->assertStatus(400);
        }
        $this->assertSame(1, $fetcher->fetches);

        $this->travel(6)->minutes();
        $this->actingAs($this->user)->get($url)->assertStatus(400);
        $this->assertSame(2, $fetcher->fetches);
    }

    public function test_a_known_document_client_exchanges_its_code_from_what_is_stored(): void
    {
        $url = 'https://claude.example/oauth/client-metadata';
        $this->redirectUri = 'https://claude.example/api/mcp/auth_callback';
        $fetcher = $this->countingFetcher(fn () => ['client_id' => $url, 'client_name' => 'Claude', 'redirect_uris' => [$this->redirectUri]]);
        $pkce = $this->pkce();

        $code = $this->approve($this->user, $url, $pkce['challenge'])['code'];
        $this->assertSame(1, $fetcher->fetches);

        // A day later the stored copy is due for a refresh; the token endpoint still does not fetch.
        $this->travel(2)->minutes();
        ConnectorClient::query()->update(['metadata_fetched_at' => now()->subDays(3)]);

        $this->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $url,
            'code' => $code,
            'redirect_uri' => $this->redirectUri,
            'code_verifier' => $pkce['verifier'],
        ])->assertStatus(400); // the code expired after two minutes: the client itself was found

        $this->assertSame(1, $fetcher->fetches);
    }

    public function test_the_dns_lookup_gives_up_after_a_few_seconds(): void
    {
        // A resolver that never answers: stand in a "node" that hangs.
        $hang = tempnam(sys_get_temp_dir(), 'ns-hang');
        file_put_contents($hang, "#!/bin/sh\nsleep 30\n");
        chmod($hang, 0700);
        config(['connector.engine.node' => $hang]);

        $fetcher = new class extends ClientMetadataFetcher
        {
            protected const RESOLVE_SECONDS = 1;

            public function lookup(string $host): array
            {
                return $this->resolve($host);
            }
        };

        $started = microtime(true);
        $addresses = $fetcher->lookup('never-answers.example');
        $elapsed = microtime(true) - $started;
        unlink($hang);

        $this->assertSame([], $addresses);
        $this->assertLessThan(3, $elapsed);
    }

    // ------------------------------------------------------------------
    // 2. Registration stores little, and not without limit
    // ------------------------------------------------------------------

    public function test_registration_stores_only_a_few_short_values(): void
    {
        $uris = fn (int $count) => array_map(fn ($i) => "https://host.example/callback/{$i}", range(1, $count));

        $this->postJson('/oauth/register', ['redirect_uris' => $uris(5)])->assertCreated();
        $this->postJson('/oauth/register', ['redirect_uris' => $uris(6)])
            ->assertStatus(400)->assertJsonPath('error', 'invalid_redirect_uri');

        $this->postJson('/oauth/register', ['redirect_uris' => ['https://host.example/'.str_repeat('a', 240)]])
            ->assertStatus(400)->assertJsonPath('error', 'invalid_redirect_uri');

        $client = $this->postJson('/oauth/register', [
            'client_name' => str_repeat('N', 5000),
            'redirect_uris' => ['https://host.example/cb'],
            'client_uri' => 'https://host.example/'.str_repeat('c', 300),
            'logo_uri' => 'https://host.example/logo.png',
        ])->assertCreated()->json();

        $this->assertLessThanOrEqual(81, mb_strlen($client['client_name']));
        $this->assertArrayNotHasKey('client_uri', $client);
        $this->assertSame('https://host.example/logo.png', $client['logo_uri']);

        // What the known hosts really register still fits (VS Code sends four).
        $this->postJson('/oauth/register', [
            'client_name' => 'Visual Studio Code',
            'client_uri' => 'https://code.visualstudio.com',
            'redirect_uris' => ['https://insiders.vscode.dev/redirect', 'https://vscode.dev/redirect', 'http://127.0.0.1/', 'http://127.0.0.1:33418/'],
        ])->assertCreated();
        $this->postJson('/oauth/register', [
            'redirect_uris' => ['https://www.cursor.com/agents/mcp/oauth/callback', 'http://localhost:8787/callback', 'cursor://anysphere.cursor-mcp/oauth/callback'],
        ])->assertCreated();
    }

    public function test_unconnected_registrations_are_limited_in_number(): void
    {
        config(['connector.registration.max_unconnected_clients' => 3]);

        // A client with a connected app does not count.
        $this->connect($this->user);

        $first = $this->registerClient();
        $this->registerClient();
        $this->registerClient();
        $this->assertSame(4, ConnectorClient::count());

        // Full, and every waiting registration is fresh: refused, with a retry hint.
        $this->postJson('/oauth/register', ['redirect_uris' => [$this->redirectUri]])
            ->assertStatus(503)
            ->assertJsonPath('error', 'temporarily_unavailable')
            ->assertHeader('Retry-After');
        $this->assertSame(4, ConnectorClient::count());

        // Later the oldest abandoned registration makes room for a new one.
        $this->travel(11)->minutes();
        $this->registerClient();

        $this->assertSame(4, ConnectorClient::count());
        $this->assertDatabaseMissing('connector_clients', ['identifier' => $first]);
        $this->assertSame(1, ConnectorConnection::count());
    }

    public function test_a_client_that_was_used_goes_after_the_ones_that_never_were(): void
    {
        config(['connector.registration.max_unconnected_clients' => 2]);

        // Connected once, disconnected since: the host still has this client_id.
        $used = $this->connect($this->user)['client_id'];
        $this->connectionOf($this->user)->delete();

        $abandoned = $this->registerClient();
        $this->travel(11)->minutes();
        $this->registerClient();

        $this->assertDatabaseHas('connector_clients', ['identifier' => $used]);
        $this->assertDatabaseMissing('connector_clients', ['identifier' => $abandoned]);
    }

    // ------------------------------------------------------------------
    // 3. Tool calls running at the same time
    // ------------------------------------------------------------------

    public function test_only_a_few_tool_calls_of_one_app_run_at_the_same_time(): void
    {
        $token = $this->connect($this->user, ['tags:read'])['access_token'];
        $slots = $this->app->make(ToolCallSlots::class);
        $owner = 'connection.'.$this->connectionOf($this->user)->id;

        // Two calls of this app are running (config: 2 per app).
        $running = [$slots->acquire($owner), $slots->acquire($owner)];
        $this->assertNotNull($running[1]);

        $busy = $this->callTool($token, 'list_tags')->assertOk()->assertJsonPath('result.isError', true);
        $this->assertStringContainsString('busy', $this->toolText($busy));
        $this->assertStringContainsString('call the tool again', $this->toolText($busy));

        // Listing tools starts no process and is not limited.
        $this->mcp($token, 'tools/list')->assertOk()->assertJsonCount(3, 'result.tools');

        // One of them ends: the next call runs, and frees its slot when done.
        $slots->release($running[0]);
        $this->callTool($token, 'list_tags')->assertJsonPath('result.isError', false);
        $this->callTool($token, 'list_tags')->assertJsonPath('result.isError', false);
    }

    public function test_all_apps_together_are_limited_too_and_a_failed_call_frees_its_slot(): void
    {
        $token = $this->connect($this->user, ['tags:read'])['access_token'];
        $slots = $this->app->make(ToolCallSlots::class);

        // Three calls of other apps are running (config: 3 per installation).
        $others = [$slots->acquire('connection.901'), $slots->acquire('connection.902'), $slots->acquire('key.903')];
        $this->assertNotContains(null, $others);

        $this->assertStringContainsString('busy', $this->toolText($this->callTool($token, 'list_tags')));

        // The refused call did not keep the app's own slot.
        $slots->release($others[0]);
        $this->callTool($token, 'list_tags')->assertJsonPath('result.isError', false);

        // A call that ends in an error gives its slots back as well.
        $this->callTool($token, 'create_tag', ['name' => 'x'])->assertJsonPath('result.isError', true);
        $this->callTool($token, 'list_tags')->assertJsonPath('result.isError', false);

        // 0 switches a limit off.
        config(['connector.concurrency.total' => 0, 'connector.concurrency.per_connection' => 0]);
        $this->assertSame([], $slots->acquire('connection.904'));
    }

    public function test_requests_of_one_app_are_rate_limited(): void
    {
        $this->assertSame(120, config('connector.rate_limit'));
        config(['connector.rate_limit' => 3]);

        $token = $this->connect($this->user)['access_token'];

        for ($i = 0; $i < 3; $i++) {
            $this->mcp($token, 'tools/list')->assertOk();
        }
        $this->mcp($token, 'tools/list')->assertStatus(429);

        // Another connection has its own allowance.
        $this->mcp($this->connect($this->user)['access_token'], 'tools/list')->assertOk();
    }

    // ------------------------------------------------------------------
    // 6. One token chain per connection
    // ------------------------------------------------------------------

    public function test_a_repeated_refresh_gets_the_pair_of_the_first_one(): void
    {
        $tokens = $this->connect($this->user);
        $form = ['grant_type' => 'refresh_token', 'client_id' => $tokens['client_id'], 'refresh_token' => $tokens['refresh_token']];

        $first = $this->post('/oauth/token', $form)->assertOk()->json();
        $again = $this->post('/oauth/token', $form)->assertOk()->json();

        $this->assertSame($first['access_token'], $again['access_token']);
        $this->assertSame($first['refresh_token'], $again['refresh_token']);

        // The original pair and one successor: no second chain.
        $this->assertSame(2, ConnectorToken::count());
        $this->mcp($first['access_token'], 'tools/list')->assertOk();

        // The stored answer is useless without the refresh token it belongs to.
        $stored = Cache::get('connector.refresh_response.'.hash('sha256', $tokens['refresh_token']));
        $this->assertIsString($stored);
        $this->assertStringNotContainsString($first['access_token'], $stored);
    }

    public function test_when_the_first_answer_is_gone_its_pair_is_withdrawn_and_a_later_use_ends_the_connection(): void
    {
        $tokens = $this->connect($this->user);
        $form = ['grant_type' => 'refresh_token', 'client_id' => $tokens['client_id'], 'refresh_token' => $tokens['refresh_token']];

        $first = $this->post('/oauth/token', $form)->assertOk()->json();
        Cache::flush();
        $second = $this->post('/oauth/token', $form)->assertOk()->json();

        $this->assertNotSame($first['access_token'], $second['access_token']);

        // Only the newest pair works.
        $this->mcp($first['access_token'], 'tools/list')->assertStatus(401);
        $this->mcp($second['access_token'], 'tools/list')->assertOk();
        $this->assertSame(1, ConnectorToken::whereNull('revoked_at')->whereNull('rotated_at')->count());

        // Whoever holds the withdrawn pair and comes back with it shows that two
        // parties have tokens of this connection: it ends.
        $this->post('/oauth/token', ['refresh_token' => $first['refresh_token']] + $form)
            ->assertStatus(400)->assertJsonPath('error', 'invalid_grant');

        $this->assertTrue($this->connectionOf($this->user)->isRevoked());
        $this->mcp($second['access_token'], 'tools/list')->assertStatus(401);
    }

    // ------------------------------------------------------------------
    // 7. "Identity confirmed" only when the app returns to its publisher
    // ------------------------------------------------------------------

    public function test_a_published_identity_that_returns_elsewhere_is_shown_as_a_warning(): void
    {
        // A document on a shared file host, redirecting to another site.
        $url = 'https://raw.files-host.example/someone/repo/client.json';
        $this->redirectUri = 'https://collector.example/callback';
        $this->countingFetcher(fn () => ['client_id' => $url, 'client_name' => 'Claude', 'redirect_uris' => [$this->redirectUri]]);
        $challenge = $this->pkce()['challenge'];

        $this->actingAs($this->user)->get($this->authorizeUrl($url, $challenge))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('client.verified_host', null)
                ->where('client.publisher_mismatch', true)
                ->where('client.publisher_host', 'raw.files-host.example')
                ->where('client.redirect_host', 'collector.example')
            );

        // Such a client is not trusted with error redirects either.
        $this->actingAs($this->user)->get($this->authorizeUrl($url, $challenge, ['response_type' => 'token']))
            ->assertStatus(400)
            ->assertHeaderMissing('Location');
    }

    public function test_returning_to_the_publisher_or_to_the_users_own_machine_counts_as_confirmed(): void
    {
        $this->assertTrue(RedirectUri::belongsToHost('https://claude.ai/api/mcp/auth_callback', 'claude.ai'));
        $this->assertTrue(RedirectUri::belongsToHost('https://app.example.com/cb', 'example.com'));
        $this->assertTrue(RedirectUri::belongsToHost('https://example.com/cb', 'auth.example.com'));
        $this->assertTrue(RedirectUri::belongsToHost('http://127.0.0.1:33418/', 'vscode.dev'));
        $this->assertTrue(RedirectUri::belongsToHost('http://localhost/callback', 'claude.ai'));

        $this->assertFalse(RedirectUri::belongsToHost('https://evil.example/cb', 'claude.ai'));
        $this->assertFalse(RedirectUri::belongsToHost('https://notclaude.ai/cb', 'claude.ai'));
        $this->assertFalse(RedirectUri::belongsToHost('https://victim.pages-host.example/cb', 'attacker.pages-host.example'));
        $this->assertFalse(RedirectUri::belongsToHost('cursor://anysphere.cursor-mcp/oauth/callback', 'cursor.com'));

        // Claude Code: identity at claude.ai, return to a loopback port.
        $url = 'https://claude.example/oauth/claude-code-client-metadata';
        $this->redirectUri = 'http://localhost:53999/callback';
        $this->countingFetcher(fn () => ['client_id' => $url, 'client_name' => 'Claude Code', 'redirect_uris' => ['http://localhost/callback']]);

        $this->actingAs($this->user)->get($this->authorizeUrl($url, $this->pkce()['challenge']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('client.verified_host', 'claude.example')
                ->where('client.publisher_mismatch', false)
            );
    }

    // ------------------------------------------------------------------
    // 8. A host can reconnect with the client_id it kept
    // ------------------------------------------------------------------

    public function test_a_client_that_was_connected_is_kept_for_reconnecting(): void
    {
        $tokens = $this->connect($this->user);
        $clientId = $tokens['client_id'];

        // Used for a month, then disconnected in the panel.
        $this->travel(30)->days();
        $this->post('/oauth/token', ['grant_type' => 'refresh_token', 'client_id' => $clientId, 'refresh_token' => $tokens['refresh_token']])->assertOk();
        $this->connectionOf($this->user)->revoke();

        $this->travel(5)->days();
        Artisan::call('connector:prune');

        $this->assertSame(0, ConnectorConnection::count());
        $this->assertDatabaseHas('connector_clients', ['identifier' => $clientId]);

        // The host starts again with the client_id it kept.
        $pkce = $this->pkce();
        $code = $this->approve($this->user, $clientId, $pkce['challenge'])['code'];
        $this->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'code' => $code,
            'redirect_uri' => $this->redirectUri,
            'code_verifier' => $pkce['verifier'],
        ])->assertOk();
    }

    public function test_an_unused_client_is_removed_after_ninety_days_without_use_and_a_never_used_one_after_a_day(): void
    {
        $used = $this->connect($this->user)['client_id'];
        $this->connectionOf($this->user)->revoke();
        $neverUsed = $this->registerClient();

        $this->travel(2)->days();
        Artisan::call('connector:prune');
        $this->assertDatabaseHas('connector_clients', ['identifier' => $used]);
        $this->assertDatabaseMissing('connector_clients', ['identifier' => $neverUsed]);

        $this->travel(80)->days();
        Artisan::call('connector:prune');
        $this->assertDatabaseHas('connector_clients', ['identifier' => $used]);

        $this->travel(10)->days();
        Artisan::call('connector:prune');
        $this->assertDatabaseMissing('connector_clients', ['identifier' => $used]);
    }

    /**
     * A metadata fetcher that counts its downloads and answers with the given callback.
     */
    private function countingFetcher(callable $answer): ClientMetadataFetcher
    {
        $fetcher = new class($answer) extends ClientMetadataFetcher
        {
            public int $fetches = 0;

            /** @var callable */
            private $answer;

            public function __construct(callable $answer)
            {
                $this->answer = $answer;
            }

            public function fetch(string $url): array
            {
                $this->validatedHost($url);
                $this->fetches++;

                return ($this->answer)($url);
            }
        };

        $this->app->instance(ClientMetadataFetcher::class, $fetcher);
        $this->app->forgetInstance(ClientRegistry::class);

        return $fetcher;
    }
}
