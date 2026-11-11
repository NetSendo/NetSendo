<?php

namespace Tests\Feature\Connector;

use App\Models\ConnectorAuthCode;
use App\Models\ConnectorClient;
use App\Models\ConnectorConnection;
use App\Models\ConnectorToken;
use App\Models\User;
use App\Services\Connector\ClientMetadataFetcher;
use App\Services\Connector\OAuthException;
use App\Services\Connector\Scopes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The OAuth side of the connector: authorization code with PKCE, client
 * registration (dynamic and by metadata document), refresh and revocation.
 */
class ConnectorOAuthFlowTest extends TestCase
{
    use ConnectsToConnector;
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['name' => 'Anna Owner']);
    }

    // ------------------------------------------------------------------
    // The whole flow
    // ------------------------------------------------------------------

    public function test_authorization_code_flow_with_pkce_ends_in_working_tokens(): void
    {
        $clientId = $this->registerClient();
        $pkce = $this->pkce();

        // The consent screen says who asks, which account is signed in and what is requested.
        $this->actingAs($this->user)
            ->get($this->authorizeUrl($clientId, $pkce['challenge'], ['scope' => 'lists:read subscribers:write']))
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Connector/Authorize')
                ->where('client.name', 'Test Host')
                ->where('client.verified_host', null)
                ->where('client.redirect_host', 'host.example')
                ->where('account.name', 'Anna Owner')
                ->where('account.email', $this->user->email)
                ->where('account.is_team_member', false)
                ->where('requestedScopes', ['subscribers:write', 'lists:read'])
            );

        $redirect = $this->approve($this->user, $clientId, $pkce['challenge'], null, ['scope' => 'lists:read subscribers:write']);

        $this->assertStringStartsWith('https://host.example/callback?', $redirect['_url']);
        $this->assertSame('state-123', $redirect['state']);
        $this->assertSame('http://localhost', $redirect['iss']);
        $this->assertStringStartsWith('ns_oac_', $redirect['code']);

        $tokens = $this->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'code' => $redirect['code'],
            'redirect_uri' => $this->redirectUri,
            'code_verifier' => $pkce['verifier'],
            'resource' => 'http://localhost/mcp',
        ])
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('expires_in', 3600)
            ->assertJsonPath('scope', 'subscribers:write lists:read')
            ->json();

        $this->assertStringStartsWith('ns_oat_', $tokens['access_token']);
        $this->assertStringStartsWith('ns_ort_', $tokens['refresh_token']);

        // Only hashes are stored.
        $this->assertDatabaseMissing('connector_tokens', ['access_token_hash' => $tokens['access_token']]);
        $this->assertDatabaseHas('connector_tokens', ['access_token_hash' => hash('sha256', $tokens['access_token'])]);

        $connection = $this->connectionOf($this->user);
        $this->assertSame('Test Host', $connection->client_name);
        $this->assertSame(['subscribers:write', 'lists:read'], $connection->scopes);

        $this->fakeToolEngine();
        $this->mcp($tokens['access_token'], 'tools/list')->assertOk()->assertJsonPath('result.tools.0.name', 'list_tags');
    }

    public function test_the_user_can_grant_fewer_permissions_than_requested_but_not_more(): void
    {
        $clientId = $this->registerClient();
        $pkce = $this->pkce();

        // Asked for lists:read only; the form is tampered with to add a write permission.
        $code = $this->approve($this->user, $clientId, $pkce['challenge'], ['lists:read', 'messages:write'], ['scope' => 'lists:read tags:read'])['code'];

        $this->assertSame(['lists:read'], ConnectorAuthCode::where('code_hash', hash('sha256', $code))->firstOrFail()->scopes);
    }

    public function test_without_a_requested_scope_everything_is_offered_and_the_user_decides(): void
    {
        $tokens = $this->connect($this->user, ['lists:read', 'tags:read']);

        $this->assertSame('lists:read tags:read', $tokens['scope']);

        $all = $this->connect($this->user);
        $this->assertSame(implode(' ', Scopes::all()), $all['scope']);
    }

    public function test_approving_with_nothing_ticked_is_refused_and_can_be_corrected(): void
    {
        $clientId = $this->registerClient();
        $pkce = $this->pkce();

        $page = $this->actingAs($this->user)->get($this->authorizeUrl($clientId, $pkce['challenge']));
        $requestId = $page->viewData('page')['props']['requestId'];

        $this->actingAs($this->user)
            ->post('/oauth/authorize', ['request_id' => $requestId, 'decision' => 'approve', 'scopes' => []])
            ->assertSessionHasErrors('scopes');

        $this->assertSame(0, ConnectorAuthCode::count());

        $redirect = $this->redirectParameters($this->actingAs($this->user)
            ->post('/oauth/authorize', ['request_id' => $requestId, 'decision' => 'approve', 'scopes' => ['lists:read']]));

        $this->assertArrayHasKey('code', $redirect);
    }

    public function test_unanswered_requests_do_not_pile_up_in_the_session(): void
    {
        $clientId = $this->registerClient();
        $challenge = $this->pkce()['challenge'];

        for ($i = 0; $i < 14; $i++) {
            $page = $this->actingAs($this->user)->get($this->authorizeUrl($clientId, $challenge));
        }

        $this->assertCount(10, session('connector.authorize'));

        // The one on screen still works.
        $redirect = $this->redirectParameters($this->actingAs($this->user)->post('/oauth/authorize', [
            'request_id' => $page->viewData('page')['props']['requestId'],
            'decision' => 'approve',
            'scopes' => ['lists:read'],
        ]));
        $this->assertArrayHasKey('code', $redirect);

        // An old one is gone after it expired and another request was shown.
        $this->travel(20)->minutes();
        $this->actingAs($this->user)->get($this->authorizeUrl($clientId, $challenge));
        $this->assertCount(1, session('connector.authorize'));
    }

    public function test_denying_returns_access_denied_to_the_application(): void
    {
        $clientId = $this->registerClient();
        $pkce = $this->pkce();

        $page = $this->actingAs($this->user)->get($this->authorizeUrl($clientId, $pkce['challenge']));

        $redirect = $this->redirectParameters($this->actingAs($this->user)->post('/oauth/authorize', [
            'request_id' => $page->viewData('page')['props']['requestId'],
            'decision' => 'deny',
        ]));

        $this->assertSame('access_denied', $redirect['error']);
        $this->assertSame('state-123', $redirect['state']);
        $this->assertSame(0, ConnectorAuthCode::count());
    }

    public function test_a_signed_out_user_is_sent_to_the_login_page_first(): void
    {
        $clientId = $this->registerClient();

        $this->get($this->authorizeUrl($clientId, $this->pkce()['challenge']))->assertRedirect('/login');
    }

    public function test_an_approval_form_cannot_be_replayed(): void
    {
        $clientId = $this->registerClient();
        $pkce = $this->pkce();

        $page = $this->actingAs($this->user)->get($this->authorizeUrl($clientId, $pkce['challenge']));
        $form = ['request_id' => $page->viewData('page')['props']['requestId'], 'decision' => 'approve', 'scopes' => ['lists:read']];

        $this->assertArrayHasKey('code', $this->redirectParameters($this->actingAs($this->user)->post('/oauth/authorize', $form)));
        $this->actingAs($this->user)->post('/oauth/authorize', $form)->assertSessionHasErrors('request_id');

        // The panel's own (Inertia) request gets the redirect as a full-page visit.
        $page = $this->actingAs($this->user)->get($this->authorizeUrl($clientId, $pkce['challenge']));
        $this->actingAs($this->user)
            ->withHeaders(['X-Inertia' => 'true'])
            ->post('/oauth/authorize', ['request_id' => $page->viewData('page')['props']['requestId']] + $form)
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location');

        $this->assertSame(2, ConnectorAuthCode::count());
    }

    // ------------------------------------------------------------------
    // The authorization request
    // ------------------------------------------------------------------

    public function test_an_unknown_client_or_redirect_uri_is_shown_as_an_error_and_never_redirected(): void
    {
        $clientId = $this->registerClient();
        $challenge = $this->pkce()['challenge'];

        $this->actingAs($this->user)
            ->get($this->authorizeUrl('nsc_unknown', $challenge))
            ->assertStatus(400)
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Connector/Authorize')->where('error', 'invalid_client'));

        $this->actingAs($this->user)
            ->get($this->authorizeUrl($clientId, $challenge, ['redirect_uri' => 'https://attacker.example/callback']))
            ->assertStatus(400)
            ->assertInertia(fn (AssertableInertia $page) => $page->where('error', 'invalid_request')->missing('requestId'));
    }

    public function test_pkce_s256_is_required(): void
    {
        $url = $this->publishedClient();

        $withoutChallenge = $this->redirectParameters(
            $this->actingAs($this->user)->get($this->authorizeUrl($url, '', ['code_challenge' => null]))
        );
        $this->assertSame('invalid_request', $withoutChallenge['error']);
        $this->assertSame('state-123', $withoutChallenge['state']);
        $this->assertSame('http://localhost', $withoutChallenge['iss']);

        $plain = $this->redirectParameters(
            $this->actingAs($this->user)->get($this->authorizeUrl($url, $this->pkce()['challenge'], ['code_challenge_method' => 'plain']))
        );
        $this->assertSame('invalid_request', $plain['error']);
    }

    public function test_a_request_for_another_resource_or_only_unknown_scopes_is_refused(): void
    {
        $url = $this->publishedClient();
        $challenge = $this->pkce()['challenge'];

        $target = $this->redirectParameters(
            $this->actingAs($this->user)->get($this->authorizeUrl($url, $challenge, ['resource' => 'https://other.example/mcp']))
        );
        $this->assertSame('invalid_target', $target['error']);

        $scope = $this->redirectParameters(
            $this->actingAs($this->user)->get($this->authorizeUrl($url, $challenge, ['scope' => 'admin everything']))
        );
        $this->assertSame('invalid_scope', $scope['error']);
    }

    public function test_a_broken_request_of_a_self_registered_client_is_never_redirected(): void
    {
        // Anyone can register any redirect address. A link to this endpoint with a
        // broken request must not bounce the visitor from NetSendo to that address.
        $this->redirectUri = 'https://phishing.example/landing';
        $clientId = $this->registerClient();
        $challenge = $this->pkce()['challenge'];

        foreach ([
            ['response_type' => 'token'],
            ['code_challenge' => null],
            ['code_challenge_method' => 'plain'],
            ['resource' => 'https://other.example/mcp'],
            ['scope' => 'admin everything'],
        ] as $broken) {
            $response = $this->actingAs($this->user)->get($this->authorizeUrl($clientId, $challenge, $broken));

            $response->assertStatus(400)
                ->assertHeaderMissing('Location')
                ->assertInertia(fn (AssertableInertia $page) => $page->component('Connector/Authorize')->has('error')->missing('requestId'));
        }

        // Once the user has seen the consent screen, the answer goes to the application.
        $page = $this->actingAs($this->user)->get($this->authorizeUrl($clientId, $challenge))->assertOk();
        $denied = $this->redirectParameters($this->actingAs($this->user)->post('/oauth/authorize', [
            'request_id' => $page->viewData('page')['props']['requestId'],
            'decision' => 'deny',
        ]));
        $this->assertStringStartsWith('https://phishing.example/landing?error=access_denied', $denied['_url']);
    }

    // ------------------------------------------------------------------
    // The token endpoint
    // ------------------------------------------------------------------

    public function test_the_code_is_useless_without_the_pkce_verifier(): void
    {
        $clientId = $this->registerClient();
        $pkce = $this->pkce();
        $code = $this->approve($this->user, $clientId, $pkce['challenge'])['code'];

        $exchange = fn (array $overrides) => $this->post('/oauth/token', $overrides + [
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'code' => $code,
            'redirect_uri' => $this->redirectUri,
            'code_verifier' => $pkce['verifier'],
        ]);

        $exchange(['code_verifier' => $this->pkce()['verifier']])->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
        $exchange(['redirect_uri' => 'https://host.example/other'])->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
        $exchange(['client_id' => $this->registerClient()])->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
        $exchange(['code_verifier' => null])->assertStatus(400)->assertJsonPath('error', 'invalid_request');

        $this->assertSame(0, ConnectorToken::count());

        $exchange([])->assertOk();
    }

    public function test_a_code_used_twice_ends_the_connection_it_created(): void
    {
        $clientId = $this->registerClient();
        $pkce = $this->pkce();
        $code = $this->approve($this->user, $clientId, $pkce['challenge'])['code'];

        $form = [
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'code' => $code,
            'redirect_uri' => $this->redirectUri,
            'code_verifier' => $pkce['verifier'],
        ];

        $tokens = $this->post('/oauth/token', $form)->assertOk()->json();
        $this->post('/oauth/token', $form)->assertStatus(400)->assertJsonPath('error', 'invalid_grant');

        $this->assertTrue($this->connectionOf($this->user)->isRevoked());
        $this->fakeToolEngine();
        $this->mcp($tokens['access_token'], 'tools/list')->assertStatus(401);
    }

    public function test_an_expired_code_is_refused(): void
    {
        $clientId = $this->registerClient();
        $pkce = $this->pkce();
        $code = $this->approve($this->user, $clientId, $pkce['challenge'])['code'];

        $this->travel(3)->minutes();

        $this->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'code' => $code,
            'redirect_uri' => $this->redirectUri,
            'code_verifier' => $pkce['verifier'],
        ])->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
    }

    public function test_unknown_grant_types_and_foreign_resources_are_refused(): void
    {
        $clientId = $this->registerClient();

        $this->post('/oauth/token', ['grant_type' => 'client_credentials', 'client_id' => $clientId])
            ->assertStatus(400)->assertJsonPath('error', 'unsupported_grant_type');

        $this->post('/oauth/token', ['grant_type' => 'refresh_token', 'client_id' => $clientId, 'refresh_token' => 'x', 'resource' => 'https://other.example/mcp'])
            ->assertStatus(400)->assertJsonPath('error', 'invalid_target');
    }

    // ------------------------------------------------------------------
    // Refresh and revocation
    // ------------------------------------------------------------------

    public function test_refreshing_rotates_the_refresh_token(): void
    {
        $this->fakeToolEngine();
        $tokens = $this->connect($this->user);

        $refreshed = $this->post('/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $tokens['client_id'],
            'refresh_token' => $tokens['refresh_token'],
        ])->assertOk()->json();

        $this->assertNotSame($tokens['access_token'], $refreshed['access_token']);
        $this->assertNotSame($tokens['refresh_token'], $refreshed['refresh_token']);
        $this->assertSame($tokens['scope'], $refreshed['scope']);

        $this->mcp($refreshed['access_token'], 'tools/list')->assertOk();
        $this->assertSame(1, ConnectorConnection::count());
    }

    public function test_an_expired_access_token_stops_working_until_it_is_refreshed(): void
    {
        $this->fakeToolEngine();
        $tokens = $this->connect($this->user);

        $this->travel(61)->minutes();
        $this->mcp($tokens['access_token'], 'tools/list')->assertStatus(401);

        $refreshed = $this->post('/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $tokens['client_id'],
            'refresh_token' => $tokens['refresh_token'],
        ])->assertOk()->json();

        $this->mcp($refreshed['access_token'], 'tools/list')->assertOk();
    }

    public function test_a_refresh_token_reused_later_ends_the_connection(): void
    {
        $this->fakeToolEngine();
        $tokens = $this->connect($this->user);
        $form = ['grant_type' => 'refresh_token', 'client_id' => $tokens['client_id'], 'refresh_token' => $tokens['refresh_token']];

        $current = $this->post('/oauth/token', $form)->assertOk()->json();

        // A retry right away (lost response) is tolerated…
        $this->post('/oauth/token', $form)->assertOk();

        // …the same old token minutes later is a copy in someone else's hands.
        $this->travel(5)->minutes();
        $this->post('/oauth/token', $form)->assertStatus(400)->assertJsonPath('error', 'invalid_grant');

        $this->assertTrue($this->connectionOf($this->user)->isRevoked());
        $this->mcp($current['access_token'], 'tools/list')->assertStatus(401);
        $this->post('/oauth/token', ['refresh_token' => $current['refresh_token']] + $form)
            ->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
    }

    public function test_a_refresh_token_belongs_to_the_client_it_was_issued_to(): void
    {
        $tokens = $this->connect($this->user);

        $this->post('/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $this->registerClient(),
            'refresh_token' => $tokens['refresh_token'],
        ])->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
    }

    public function test_an_unused_connection_expires_with_its_refresh_token(): void
    {
        $tokens = $this->connect($this->user);

        $this->travel(91)->days();

        $this->post('/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $tokens['client_id'],
            'refresh_token' => $tokens['refresh_token'],
        ])->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
    }

    public function test_the_host_can_revoke_its_tokens(): void
    {
        $this->fakeToolEngine();
        $tokens = $this->connect($this->user);

        $this->post('/oauth/revoke', ['token' => $tokens['refresh_token']])->assertOk();

        $this->mcp($tokens['access_token'], 'tools/list')->assertStatus(401);
        $this->post('/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $tokens['client_id'],
            'refresh_token' => $tokens['refresh_token'],
        ])->assertStatus(400)->assertJsonPath('error', 'invalid_grant');

        // Unknown tokens are answered the same way.
        $this->post('/oauth/revoke', ['token' => 'nope'])->assertOk();
    }

    // ------------------------------------------------------------------
    // Client registration
    // ------------------------------------------------------------------

    public function test_registration_accepts_https_loopback_and_listed_app_schemes_only(): void
    {
        $this->postJson('/oauth/register', [
            'client_name' => 'Desktop Host',
            'redirect_uris' => ['http://127.0.0.1:33418/', 'http://localhost/callback', 'cursor://anysphere.cursor-mcp/oauth/callback'],
        ])
            ->assertCreated()
            ->assertJsonPath('token_endpoint_auth_method', 'none')
            ->assertJsonPath('grant_types', ['authorization_code', 'refresh_token'])
            ->assertJsonMissingPath('client_secret');

        foreach ([
            'javascript:alert(1)',
            'http://host.example/callback',
            'https://user:pass@host.example/callback',
            'https://host.example/callback#fragment',
            'data:text/html,hi',
            'unknownapp://callback',
        ] as $uri) {
            $this->postJson('/oauth/register', ['redirect_uris' => [$uri]])
                ->assertStatus(400)
                ->assertJsonPath('error', 'invalid_redirect_uri');
        }

        $this->postJson('/oauth/register', ['client_name' => 'No redirects'])->assertStatus(400);
    }

    public function test_a_loopback_redirect_matches_on_any_port(): void
    {
        $this->redirectUri = 'http://localhost:54321/callback';
        $clientId = $this->registerClient(['redirect_uris' => ['http://localhost/callback']]);
        $pkce = $this->pkce();

        $redirect = $this->approve($this->user, $clientId, $pkce['challenge']);

        $this->assertStringStartsWith('http://localhost:54321/callback?code=', $redirect['_url']);

        // …but not on another path or host.
        $this->actingAs($this->user)
            ->get($this->authorizeUrl($clientId, $pkce['challenge'], ['redirect_uri' => 'http://localhost:54321/other']))
            ->assertStatus(400);
    }

    public function test_a_client_can_identify_itself_with_a_metadata_document(): void
    {
        $url = 'https://claude.example/oauth/client-metadata';
        $this->redirectUri = 'https://claude.example/api/mcp/auth_callback';
        $this->fakeMetadataDocument([
            'client_id' => $url,
            'client_name' => 'Claude',
            'redirect_uris' => [$this->redirectUri],
            'grant_types' => ['authorization_code', 'refresh_token', 'urn:ietf:params:oauth:grant-type:jwt-bearer'],
            'token_endpoint_auth_method' => 'none',
        ]);
        $pkce = $this->pkce();

        $this->actingAs($this->user)
            ->get($this->authorizeUrl($url, $pkce['challenge']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('client.name', 'Claude')
                ->where('client.verified_host', 'claude.example')
            );

        $code = $this->approve($this->user, $url, $pkce['challenge'])['code'];

        $this->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $url,
            'code' => $code,
            'redirect_uri' => $this->redirectUri,
            'code_verifier' => $pkce['verifier'],
        ])->assertOk();

        // The document was downloaded once and is kept.
        $this->assertSame(1, ConnectorClient::where('registration_type', 'metadata')->count());
        $this->assertSame(1, $this->app->make(ClientMetadataFetcher::class)->fetches);
    }

    public function test_a_metadata_document_must_name_its_own_url_and_use_allowed_redirects(): void
    {
        $url = 'https://claude.example/oauth/client-metadata';
        $challenge = $this->pkce()['challenge'];

        $this->fakeMetadataDocument(['client_id' => 'https://someone-else.example/client', 'redirect_uris' => [$this->redirectUri]]);
        $this->actingAs($this->user)->get($this->authorizeUrl($url, $challenge))->assertStatus(400);

        \Illuminate\Support\Facades\Cache::flush();
        $this->fakeMetadataDocument(['client_id' => $url, 'redirect_uris' => ['javascript:alert(1)']]);
        $this->actingAs($this->user)->get($this->authorizeUrl($url, $challenge))->assertStatus(400);

        $this->assertSame(0, ConnectorClient::count());
    }

    public function test_metadata_documents_are_only_fetched_from_public_https_addresses(): void
    {
        $fetcher = new ClientMetadataFetcher;

        foreach ([
            'http://claude.example/client',          // not https
            'https://claude.example',                // no path
            'https://claude.example/',
            'https://user@claude.example/client',    // credentials
            'https://claude.example:8443/client',    // unusual port
            'https://claude.example/a/../client',    // dot segments
            'https://claude.example/client#x',
        ] as $url) {
            try {
                $fetcher->validatedHost($url);
                $this->fail("Accepted {$url}");
            } catch (OAuthException $e) {
                $this->assertSame('invalid_client', $e->error);
            }
        }

        $this->assertSame('claude.example', $fetcher->validatedHost('https://Claude.example/oauth/client'));

        foreach (['127.0.0.1', '10.0.0.5', '192.168.1.10', '172.16.0.1', '169.254.169.254', '100.64.0.1', '0.0.0.0', '::1', 'fd00::1'] as $address) {
            $this->assertFalse(ClientMetadataFetcher::isPublicAddress($address), $address);
        }
        $this->assertTrue(ClientMetadataFetcher::isPublicAddress('160.79.104.10'));
    }

    /** A client with a published identity that returns to its own host, like Claude. */
    private function publishedClient(): string
    {
        $url = 'https://claude.example/oauth/client-metadata';
        $this->redirectUri = 'https://claude.example/api/mcp/auth_callback';
        $this->fakeMetadataDocument(['client_id' => $url, 'client_name' => 'Claude', 'redirect_uris' => [$this->redirectUri]]);

        return $url;
    }

    /** @param  array<string, mixed>  $document */
    private function fakeMetadataDocument(array $document): void
    {
        $this->app->instance(ClientMetadataFetcher::class, new class($document) extends ClientMetadataFetcher
        {
            public int $fetches = 0;

            public function __construct(private array $document)
            {
            }

            public function fetch(string $url): array
            {
                $this->validatedHost($url);
                $this->fetches++;

                return $this->document;
            }
        });

        $this->app->forgetInstance(\App\Services\Connector\ClientRegistry::class);
    }
}
