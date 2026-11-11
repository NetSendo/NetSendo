<?php

namespace App\Services\Connector;

use App\Http\Middleware\ApiKeyAuth;
use App\Models\ApiKey;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;

/**
 * Runs an API v1 request for a connected app without leaving the process.
 *
 * The connector's tools are written against the REST API. Instead of calling
 * the API over the network with a credential, each request is dispatched
 * through the application's own HTTP kernel: same routes, same middleware,
 * same permission checks and the same request log as a call with an API key.
 * ApiKeyAuth accepts the identity that is attached to the request here; a
 * request from outside cannot carry that attribute.
 */
class ApiDispatcher
{
    public function __construct(private Kernel $kernel)
    {
    }

    /**
     * @param  ApiKey  $identity  who is calling and with which permissions: the API key that was
     *                            presented on /mcp, or ConnectorConnection::asApiKey() for a connected app
     * @param  array<string, mixed>|null  $query
     * @return array{status: int, body: string} the HTTP status and the raw response body
     */
    public function dispatch(ApiKey $identity, string $method, string $path, ?array $query = null, mixed $data = null): array
    {
        $method = strtoupper($method);

        if (! in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)
            || ! str_starts_with($path, '/')
            || str_contains($path, '..')
            || str_contains($path, '//')
            || preg_match('/[\x00-\x20\\\\]/', $path)) {
            return ['status' => 400, 'body' => json_encode(['message' => 'Invalid API request from the connector tool.'])];
        }

        $uri = '/api/v1'.$path;
        $queryString = $query ? http_build_query($this->queryValues($query)) : '';

        if ($queryString !== '') {
            $uri .= (str_contains($uri, '?') ? '&' : '?').$queryString;
        }

        $outer = app('request');
        $hasBody = $data !== null && $method !== 'GET';

        $server = array_filter([
            'HTTP_HOST' => $outer->getHttpHost(),
            'HTTPS' => $outer->isSecure() ? 'on' : null,
            'SERVER_PORT' => $outer->getPort(),
            'REMOTE_ADDR' => $outer->ip(),
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_USER_AGENT' => 'NetSendo-Connector ('.$identity->name.')',
            'CONTENT_TYPE' => $hasBody ? 'application/json' : null,
        ], fn ($value) => $value !== null);

        $request = Request::create($uri, $method, [], [], [], $server, $hasBody ? json_encode($data) : null);
        $request->attributes->set(ApiKeyAuth::CONNECTOR_ATTRIBUTE, $identity);

        try {
            $response = $this->kernel->handle($request);
        } finally {
            // The kernel made the inner request the current one; hand the
            // application back to the MCP request that is still being served.
            app()->instance('request', $outer);
            Facade::clearResolvedInstance('request');
        }

        $body = $response->getContent();

        return [
            'status' => $response->getStatusCode(),
            'body' => is_string($body) ? $body : '',
        ];
    }

    /**
     * Query values as an HTTP client would send them: no nulls, booleans as
     * "true"/"false" (what the MCP client's HTTP library produces and the API
     * validators expect).
     *
     * @param  array<string|int, mixed>  $values
     * @return array<string|int, mixed>
     */
    private function queryValues(array $values): array
    {
        $out = [];

        foreach ($values as $key => $value) {
            if ($value === null) {
                continue;
            }

            $out[$key] = match (true) {
                is_bool($value) => $value ? 'true' : 'false',
                is_array($value) => $this->queryValues($value),
                default => $value,
            };
        }

        return $out;
    }
}
