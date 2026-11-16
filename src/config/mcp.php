<?php

// Settings of the laravel/mcp package that the NetSendo connector uses
// (config/connector.php holds the connector's own settings).

return [

    // Browser-based MCP hosts send an Origin header; the instance URL and
    // loopback addresses are always allowed. Add other origins as a
    // comma-separated list, or "*" for any.
    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env('MCP_ALLOWED_ORIGINS', ''))))),

    'authorization_server' => null,

    'tool_search' => [
        'max_tool_calls' => 10,
        'max_output_bytes' => 65_536,
    ],

    'clients' => [],
];
