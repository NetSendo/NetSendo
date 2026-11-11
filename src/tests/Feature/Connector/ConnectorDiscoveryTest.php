<?php

namespace Tests\Feature\Connector;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An MCP host is given only the instance URL. Everything else it finds itself:
 * /mcp answers 401 with a pointer to the protected-resource metadata, which
 * names the authorization server, whose metadata lists the OAuth endpoints.
 */
class ConnectorDiscoveryTest extends TestCase
{
    use ConnectsToConnector;
    use RefreshDatabase;

    public function test_mcp_endpoint_challenges_an_anonymous_host_with_the_metadata_address(): void
    {
        $response = $this->mcp(null, 'tools/list')->assertStatus(401);

        $this->assertSame(
            'Bearer resource_metadata="http://localhost/.well-known/oauth-protected-resource/mcp"',
            $response->headers->get('WWW-Authenticate'),
        );
    }

    public function test_an_invalid_token_is_answered_with_401_and_invalid_token(): void
    {
        $challenge = $this->mcp('ns_oat_'.str_repeat('x', 48), 'tools/list')
            ->assertStatus(401)
            ->headers->get('WWW-Authenticate');

        $this->assertStringContainsString('resource_metadata="http://localhost/.well-known/oauth-protected-resource/mcp"', $challenge);
        $this->assertStringContainsString('error="invalid_token"', $challenge);
    }

    public function test_protected_resource_metadata_names_the_endpoint_and_its_authorization_server(): void
    {
        foreach (['/.well-known/oauth-protected-resource/mcp', '/.well-known/oauth-protected-resource'] as $path) {
            $this->getJson($path)
                ->assertOk()
                ->assertJsonPath('resource', 'http://localhost/mcp')
                ->assertJsonPath('authorization_servers', ['http://localhost'])
                ->assertJsonPath('bearer_methods_supported', ['header'])
                ->assertJsonFragment(['lists:read'])
                ->assertJsonMissing(['brain:write']);
        }
    }

    public function test_authorization_server_metadata_describes_what_hosts_need(): void
    {
        foreach ([
            '/.well-known/oauth-authorization-server',
            '/.well-known/oauth-authorization-server/mcp',
            '/.well-known/openid-configuration',
        ] as $path) {
            $this->getJson($path)
                ->assertOk()
                ->assertJsonPath('issuer', 'http://localhost')
                ->assertJsonPath('authorization_endpoint', 'http://localhost/oauth/authorize')
                ->assertJsonPath('token_endpoint', 'http://localhost/oauth/token')
                ->assertJsonPath('registration_endpoint', 'http://localhost/oauth/register')
                ->assertJsonPath('revocation_endpoint', 'http://localhost/oauth/revoke')
                ->assertJsonPath('response_types_supported', ['code'])
                ->assertJsonPath('grant_types_supported', ['authorization_code', 'refresh_token'])
                ->assertJsonPath('code_challenge_methods_supported', ['S256'])
                // Claude picks its published identity only with both of these.
                ->assertJsonPath('token_endpoint_auth_methods_supported', ['none'])
                ->assertJsonPath('client_id_metadata_document_supported', true)
                ->assertJsonPath('authorization_response_iss_parameter_supported', true);
        }
    }

    public function test_the_endpoint_has_no_get_stream_and_no_sessions(): void
    {
        $this->get('/mcp', ['Accept' => 'text/event-stream'])->assertStatus(405)->assertHeader('Allow', 'POST');
        $this->delete('/mcp')->assertStatus(405);
    }

    public function test_a_person_opening_the_address_in_a_browser_is_sent_to_the_instructions(): void
    {
        $this->get('/mcp', ['Accept' => 'text/html,application/xhtml+xml'])->assertRedirect('/settings/connector');
    }

    public function test_a_foreign_browser_origin_is_refused(): void
    {
        $this->withHeaders(['Origin' => 'https://evil.example'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
            ->assertStatus(403);
    }

    public function test_turning_the_connector_off_removes_every_endpoint(): void
    {
        config(['connector.enabled' => false]);

        $this->mcp(null, 'tools/list')->assertNotFound();
        $this->getJson('/.well-known/oauth-protected-resource/mcp')->assertNotFound();
        $this->getJson('/.well-known/oauth-authorization-server')->assertNotFound();
        $this->postJson('/oauth/register', ['redirect_uris' => ['https://host.example/cb']])->assertNotFound();
        $this->post('/oauth/token', ['grant_type' => 'refresh_token'])->assertNotFound();
    }

    public function test_dynamic_registration_can_be_turned_off_on_its_own(): void
    {
        config(['connector.registration.dynamic' => false]);

        $this->getJson('/.well-known/oauth-authorization-server')
            ->assertOk()
            ->assertJsonMissingPath('registration_endpoint');

        $this->postJson('/oauth/register', ['redirect_uris' => ['https://host.example/cb']])->assertStatus(403);
    }
}
