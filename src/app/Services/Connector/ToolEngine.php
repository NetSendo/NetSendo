<?php

namespace App\Services\Connector;

use Closure;

/**
 * Runs the connector's tools. The implementation in use is NodeToolEngine,
 * which executes the tools of the @netsendo/mcp-client package; tests may bind
 * another one.
 */
interface ToolEngine
{
    /** Can tools be listed and run on this installation? */
    public function available(): bool;

    /**
     * Every tool: name, description, JSON Schema of the input and annotations.
     *
     * @return list<object{name: string, description: string, inputSchema: object, annotations: object}>
     */
    public function tools(): array;

    /**
     * Run one tool.
     *
     * @param  object  $arguments  the tool arguments as sent by the host
     * @param  Closure(string $method, string $path, ?array $query, mixed $data): array{status: int, body: string}  $api
     *                                                                                                                  performs an API v1 request for the caller
     * @return array{content: list<array<string, mixed>>, isError: bool}
     */
    public function call(string $name, object $arguments, Closure $api): array;
}
