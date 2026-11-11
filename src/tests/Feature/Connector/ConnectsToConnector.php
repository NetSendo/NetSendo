<?php

namespace Tests\Feature\Connector;

use App\Models\ConnectorConnection;
use App\Models\User;
use App\Services\Connector\ToolEngine;
use Closure;
use Illuminate\Testing\TestResponse;

/**
 * What an MCP host does against the connector, as test helpers: register,
 * send the user through the consent screen, exchange the code, call /mcp.
 */
trait ConnectsToConnector
{
    protected string $redirectUri = 'https://host.example/callback';

    protected function registerClient(array $overrides = []): string
    {
        return $this->postJson('/oauth/register', $overrides + [
            'client_name' => 'Test Host',
            'redirect_uris' => [$this->redirectUri],
            'token_endpoint_auth_method' => 'none',
        ])->assertCreated()->json('client_id');
    }

    /** @return array{verifier: string, challenge: string} */
    protected function pkce(): array
    {
        $verifier = bin2hex(random_bytes(32));

        return [
            'verifier' => $verifier,
            'challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
        ];
    }

    protected function authorizeUrl(string $clientId, string $challenge, array $overrides = []): string
    {
        return '/oauth/authorize?'.http_build_query($overrides + [
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $this->redirectUri,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
            'state' => 'state-123',
            'resource' => 'http://localhost/mcp',
        ]);
    }

    /**
     * The user opens the consent screen and approves; returns the query
     * parameters the host receives on its redirect URI.
     *
     * @param  list<string>|null  $grant  permissions to tick (null: everything requested)
     * @return array<string, string>
     */
    protected function approve(User $user, string $clientId, string $challenge, ?array $grant = null, array $overrides = []): array
    {
        $page = $this->actingAs($user)->get($this->authorizeUrl($clientId, $challenge, $overrides))->assertOk();
        $props = $page->viewData('page')['props'];

        $response = $this->actingAs($user)->post('/oauth/authorize', [
            'request_id' => $props['requestId'],
            'decision' => 'approve',
            'scopes' => $grant ?? $props['requestedScopes'],
        ]);

        return $this->redirectParameters($response);
    }

    /** @return array<string, string> */
    protected function redirectParameters(TestResponse $response): array
    {
        $location = $response->headers->get('X-Inertia-Location') ?? $response->headers->get('Location');
        $this->assertNotNull($location, 'The consent screen did not redirect back to the application.');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $parameters);

        return ['_url' => $location] + $parameters;
    }

    /**
     * The whole sign-in: register, approve, exchange.
     *
     * @param  list<string>|null  $grant
     * @return array{access_token: string, refresh_token: string, client_id: string, scope: string}
     */
    protected function connect(User $user, ?array $grant = null): array
    {
        $clientId = $this->registerClient();
        $pkce = $this->pkce();
        $code = $this->approve($user, $clientId, $pkce['challenge'], $grant)['code'];

        // The host is not signed in to the panel.
        $this->app['auth']->forgetGuards();

        $tokens = $this->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'code' => $code,
            'redirect_uri' => $this->redirectUri,
            'code_verifier' => $pkce['verifier'],
            'resource' => 'http://localhost/mcp',
        ])->assertOk()->json();

        return $tokens + ['client_id' => $clientId];
    }

    /** A JSON-RPC request to /mcp in the current protocol revision (2026-07-28, stateless). */
    protected function mcp(?string $token, string $method, array $params = []): TestResponse
    {
        $params['_meta'] = [
            'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
            'io.modelcontextprotocol/clientCapabilities' => (object) [],
        ];

        $headers = [
            'Accept' => 'application/json, text/event-stream',
            'MCP-Protocol-Version' => '2026-07-28',
            'Mcp-Method' => $method,
        ];

        if (isset($params['name'])) {
            $headers['Mcp-Name'] = $params['name'];
        }

        if ($token !== null) {
            $headers['Authorization'] = 'Bearer '.$token;
        }

        // Each call is a fresh request from the host: no panel session.
        $this->app['auth']->forgetGuards();

        return $this->withHeaders($headers)->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => $method,
            'params' => $params,
        ]);
    }

    protected function callTool(string $token, string $name, array $arguments = []): TestResponse
    {
        return $this->mcp($token, 'tools/call', ['name' => $name, 'arguments' => (object) $arguments]);
    }

    protected function toolText(TestResponse $response): string
    {
        return (string) $response->json('result.content.0.text');
    }

    protected function connectionOf(User $user): ConnectorConnection
    {
        return ConnectorConnection::where('user_id', $user->id)->latest('id')->firstOrFail();
    }

    /**
     * A tool engine that needs no Node.js: two tools that make the same API
     * requests as their namesakes in the MCP client package.
     */
    protected function fakeToolEngine(): void
    {
        $this->app->instance(ToolEngine::class, new class implements ToolEngine
        {
            public function available(): bool
            {
                return true;
            }

            public function tools(): array
            {
                return json_decode(json_encode([
                    [
                        'name' => 'list_tags',
                        'description' => 'List tags',
                        'inputSchema' => ['type' => 'object', 'properties' => (object) []],
                        'annotations' => ['title' => 'List tags', 'readOnlyHint' => true, 'destructiveHint' => false],
                    ],
                    [
                        'name' => 'create_tag',
                        'description' => 'Create a tag',
                        'inputSchema' => ['type' => 'object', 'properties' => ['name' => ['type' => 'string']], 'required' => ['name']],
                        'annotations' => ['title' => 'Create tag', 'readOnlyHint' => false, 'destructiveHint' => false],
                    ],
                ]));
            }

            public function call(string $name, object $arguments, Closure $api): array
            {
                $response = match ($name) {
                    'list_tags' => $api('get', '/tags', ['per_page' => 100], null),
                    'create_tag' => $api('post', '/tags', null, $arguments),
                };

                return [
                    'content' => [['type' => 'text', 'text' => $response['status'].' '.$response['body']]],
                    'isError' => $response['status'] >= 400,
                ];
            }
        });
    }
}
