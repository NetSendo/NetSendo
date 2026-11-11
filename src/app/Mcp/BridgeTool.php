<?php

namespace App\Mcp;

use App\Http\Middleware\AuthenticateConnector;
use App\Models\ApiKey;
use App\Services\Connector\ApiDispatcher;
use App\Services\Connector\ToolEngine;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use RuntimeException;
use stdClass;

/**
 * One tool of the connector. The definition (name, description, input schema)
 * and the behaviour both come from the tool engine, i.e. from the
 * @netsendo/mcp-client package; this class only passes them through.
 */
class BridgeTool extends Tool
{
    private const API_KEY_HINT = 'The API key is missing a permission. Ask the user to enable it in NetSendo → API keys.';

    private const PERMISSION_HINT = 'If NetSendo is connected through the AI connector (sign-in), this permission was not granted: ask the user to disconnect the connector and connect it again, ticking the permission on the NetSendo consent screen. If an API key is used, the key needs that permission.';

    public function __construct(private object $definition)
    {
    }

    public function name(): string
    {
        return (string) $this->definition->name;
    }

    public function title(): string
    {
        return (string) ($this->definition->annotations->title ?? $this->definition->name);
    }

    public function description(): string
    {
        return (string) ($this->definition->description ?? '');
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $schema = $this->definition->inputSchema ?? new stdClass;
        $schema->type ??= 'object';
        $schema->properties ??= new stdClass;

        return [
            'name' => $this->name(),
            'title' => $this->title(),
            'description' => $this->description(),
            'inputSchema' => $schema,
            'annotations' => $this->definition->annotations ?? new stdClass,
        ];
    }

    public function handle(Request $request, ToolEngine $engine, ApiDispatcher $api): Response|ResponseFactory
    {
        $identity = request()->attributes->get(AuthenticateConnector::IDENTITY_ATTRIBUTE);

        if (! $identity instanceof ApiKey) {
            return Response::error('This tool needs a signed-in connector session.');
        }

        try {
            $result = $engine->call(
                $this->name(),
                $this->arguments($request),
                fn (string $method, string $path, ?array $query, mixed $data) => $api->dispatch($identity, $method, $path, $query, $data),
            );
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        $responses = [];

        foreach ($result['content'] as $item) {
            $text = ($item['type'] ?? null) === 'text' && is_string($item['text'] ?? null)
                ? $item['text']
                : json_encode($item, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            // The tools and the API were written for API keys: a missing permission
            // is reported as a problem with "the API key". For a connected app the
            // way out is the consent screen, so say that.
            if ($result['isError']) {
                $text = str_replace(self::API_KEY_HINT, '', $text);

                if (preg_match('/does not have .*permission/i', $text)) {
                    $text = rtrim($text)."\n".self::PERMISSION_HINT;
                }
            }

            $responses[] = $result['isError'] ? Response::error($text) : Response::text($text);
        }

        // A factory, not a plain array: an array would be sent as an event stream.
        return match (count($responses)) {
            0 => Response::text(''),
            1 => $responses[0],
            default => Response::make($responses),
        };
    }

    /**
     * The arguments exactly as the host sent them. They are read from the raw
     * request body, because the decoded array cannot tell an empty object from
     * an empty list and the tool schemas can.
     */
    private function arguments(Request $request): object
    {
        $body = json_decode((string) request()->getContent());
        $arguments = $body->params->arguments ?? null;

        if (is_object($arguments)) {
            return $arguments;
        }

        return (object) $request->all();
    }
}
