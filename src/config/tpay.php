<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tpay API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Tpay payment processor integration.
    | API credentials are stored in database (user_settings) per user.
    |
    */

    'api_url' => env('TPAY_API_URL', 'https://api.tpay.com'),
    'sandbox_api_url' => env('TPAY_SANDBOX_API_URL', 'https://api.sandbox.tpay.com'),

    'panel_url' => env('TPAY_PANEL_URL', 'https://panel.tpay.com'),
    'sandbox_panel_url' => env('TPAY_SANDBOX_PANEL_URL', 'https://panel.sandbox.tpay.com'),

    // JWS signature verification certificates
    'jws_cert_url' => 'https://secure.tpay.com/x509/notifications-jws.pem',
    'jws_ca_url' => 'https://secure.tpay.com/x509/tpay-jws-root.pem',

    // OAuth token TTL (in seconds, default 2 hours)
    'token_ttl' => env('TPAY_TOKEN_TTL', 7200),
];
