<?php

namespace App\Mcp;

use App\Services\Connector\ToolEngine;
use Laravel\Mcp\Server;
use RuntimeException;

/**
 * The MCP server behind /mcp: the NetSendo connector.
 *
 * The protocol (current revision and the older handshake-based ones) is
 * handled by laravel/mcp; the tools come from the tool engine.
 */
class NetSendoServer extends Server
{
    protected string $name = 'NetSendo';

    protected string $instructions = <<<'MARKDOWN'
        NetSendo is an email and SMS marketing platform. These tools work on the account of the signed-in NetSendo user: contact lists and subscribers, tags and custom fields, campaigns (email and SMS), autoresponders, templates, A/B tests, funnels, automations, signup forms, webhooks, system emails and pages.

        Read before you write: look things up with the list_* and get_* tools and use the IDs they return. Sending tools (send_campaign, schedule_campaign, send_email, send_sms) reach real people: confirm the audience and the content with the user first. A tool that answers 409 needs the user's explicit confirmation before it is repeated with confirm=true.
    MARKDOWN;

    /** Only tools: the connector has no prompts or resources. */
    protected array $capabilities = [
        self::CAPABILITY_TOOLS => [
            'listChanged' => false,
        ],
    ];

    // Every tool in one page: hosts differ in how well they follow cursors.
    public int $maxPaginationLength = 500;

    public int $defaultPaginationLength = 500;

    protected function boot(): void
    {
        $this->version = (string) config('netsendo.version', '1.0.0');

        try {
            $definitions = app(ToolEngine::class)->tools();
        } catch (RuntimeException $e) {
            // The engine is not installed (see docs/MCP_CONNECTOR.md): the server
            // still answers, with no tools; the panel page shows the reason.
            report($e);
            $definitions = [];
        }

        $this->tools = array_map(fn (object $definition) => new BridgeTool($definition), $definitions);
    }
}
