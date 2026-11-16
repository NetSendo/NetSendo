<?php

/*
|--------------------------------------------------------------------------
| NetSendo connector (remote MCP server with OAuth)
|--------------------------------------------------------------------------
|
| Every installation serves an MCP endpoint at /mcp that AI hosts (Claude,
| Hermes, ChatGPT, Cursor, …) connect to by URL and sign-in, without an API
| key. Self-hosting notes: docs/MCP_CONNECTOR.md.
|
*/

return [

    // MCP_CONNECTOR_ENABLED=false removes the endpoint, the OAuth server and
    // the discovery documents (they answer 404). API keys are not affected.
    'enabled' => (bool) env('MCP_CONNECTOR_ENABLED', true),

    /*
    | The tools are the ones of the @netsendo/mcp-client package (mcp/ in the
    | repository). The application runs them with Node.js through
    | mcp/dist/bridge.js; the image ships both.
    */
    'engine' => [
        'node' => env('MCP_CONNECTOR_NODE', 'node'),

        // Absolute path of bridge.js. Empty: the first of the known locations
        // that exists (the image path, then the repository checkout).
        'bridge' => env('MCP_CONNECTOR_BRIDGE'),

        // Seconds one tool call may take, including its API calls.
        'timeout' => (int) env('MCP_CONNECTOR_TOOL_TIMEOUT', 150),
    ],

    // Lifetimes in seconds.
    'auth_code_ttl' => 120,
    'access_token_ttl' => (int) env('MCP_CONNECTOR_ACCESS_TOKEN_TTL', 3600),
    // Sliding: every refresh starts a new period. An app that has not been
    // used for this long has to sign in again.
    'refresh_token_ttl' => (int) env('MCP_CONNECTOR_REFRESH_TOKEN_TTL', 60 * 60 * 24 * 90),
    // A refresh token that was already exchanged may be exchanged once more
    // within this window (a host retrying after a lost response). Later reuse
    // is treated as a stolen token and ends the whole connection.
    'refresh_reuse_grace' => 60,

    'registration' => [
        // RFC 7591 dynamic client registration (POST /oauth/register)
        'dynamic' => (bool) env('MCP_CONNECTOR_DYNAMIC_REGISTRATION', true),
        // Client ID Metadata Documents: the client_id is an https URL of a
        // JSON document that NetSendo downloads (Claude's "published identity").
        'metadata_documents' => (bool) env('MCP_CONNECTOR_METADATA_DOCUMENTS', true),

        // Registration needs no sign-in, so what it may store is bounded: this
        // many self-registered clients may exist without a connected app. When
        // the limit is reached the oldest unused ones make room; if all of them
        // are fresh (a flood), registration answers "try again later".
        'max_unconnected_clients' => (int) env('MCP_CONNECTOR_MAX_UNCONNECTED_CLIENTS', 500),

        // A self-registered client that was connected once is remembered this
        // many days after its last use, so a host that kept its client_id can
        // reconnect. One that was never connected is removed after a day.
        'keep_used_clients_days' => 90,
    ],

    // Redirect URIs must be https, or http on a loopback address (desktop and
    // CLI hosts). Native apps that return through their own URL scheme are
    // listed here.
    'redirect_schemes' => array_values(array_filter(array_map('trim', explode(',', (string) env(
        'MCP_CONNECTOR_REDIRECT_SCHEMES',
        'cursor,vscode,vscode-insiders,windsurf,zed,claude'
    ))))),

    // API-key permissions that no connector tool uses; they are not offered
    // on the consent screen.
    'excluded_scopes' => ['brain:read', 'brain:write'],

    // Requests per minute for one connected app.
    'rate_limit' => (int) env('MCP_CONNECTOR_RATE_LIMIT', 120),

    /*
    | A tool call starts a Node.js process and holds a PHP worker until it ends.
    | So that one app cannot occupy the installation, only this many tool calls
    | run at the same time; a call over the limit is answered at once with
    | "busy, try again". The stock PHP-FPM pool has 5 workers: 3 leaves room
    | for the panel.
    */
    'concurrency' => [
        'per_connection' => (int) env('MCP_CONNECTOR_CONCURRENT_CALLS_PER_APP', 2),
        'total' => (int) env('MCP_CONNECTOR_CONCURRENT_CALLS', 3),
    ],
];
