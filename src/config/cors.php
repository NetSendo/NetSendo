<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
        't/pixel/*', // NetSendo Pixel tracking
        't/page', // Page visit tracking
        't/heartbeat',
        't/read-end',
        't/link-visitor',
        // NetSendo connector: browser-based MCP hosts (e.g. MCP Inspector) call these cross-origin
        'mcp',
        'oauth/token',
        'oauth/register',
        'oauth/revoke',
        '.well-known/oauth-*',
        '.well-known/openid-configuration',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    // WWW-Authenticate: a browser-based MCP host reads the sign-in challenge of /mcp from it
    'exposed_headers' => ['WWW-Authenticate'],

    'max_age' => 0,

    'supports_credentials' => false,

];
