<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Version
    |--------------------------------------------------------------------------
    |
    | Current version of NetSendo application. This is used for version
    | checking and update notifications. DO NOT modify this value manually.
    |
    */

    'version' => '1.6.7',

    /*
    |--------------------------------------------------------------------------
    | GitHub Repository
    |--------------------------------------------------------------------------
    |
    | GitHub repository for checking available updates.
    |
    */

    'github_repo' => 'NetSendo/NetSendo',
    'github_releases_url' => 'https://github.com/NetSendo/NetSendo/releases',

    /*
    |--------------------------------------------------------------------------
    | License Webhooks
    |--------------------------------------------------------------------------
    |
    | Webhook URLs for license operations. These endpoints handle license
    | requests and validation through the external license server.
    |
    */

    'license_webhook_url' => 'https://a.gregciupek.com/webhook/ddae7ce5-2a11-40f1-aa03-5da2e294777d',

    /*
    |--------------------------------------------------------------------------
    | Stripe Payment Link
    |--------------------------------------------------------------------------
    |
    | Payment link for GOLD subscription ($97/month).
    | Set to null when not yet available.
    |
    */

    'stripe_gold_payment_link' => null, // Coming soon

    /*
    |--------------------------------------------------------------------------
    | License Plans
    |--------------------------------------------------------------------------
    |
    | Available license plans configuration.
    |
    */

    'plans' => [
        'SILVER' => [
            'name' => 'SILVER',
            'price' => 0,
            'price_display' => 'Darmowa',
            'duration' => 'lifetime',
            'features' => [
                'Wszystkie podstawowe funkcje',
                'Nieograniczone kontakty',
                'Szablony email',
                'Wsparcie społeczności',
            ],
        ],
        'GOLD' => [
            'name' => 'GOLD',
            'price' => 97,
            'price_display' => '$97/miesiąc',
            'duration' => 'monthly',
            'features' => [
                'Wszystko z SILVER',
                'Zaawansowane automatyzacje',
                'Priorytetowe wsparcie',
                'Dostęp API',
                'White-label',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Plugin Versions
    |--------------------------------------------------------------------------
    |
    | Plugin version configuration for WordPress and WooCommerce integrations.
    | Update these values when releasing new plugin versions.
    |
    */

    'plugins' => [
        'wordpress' => [
            'version' => '1.1.0',
            'download_url' => '/plugins/wordpress/netsendo-wordpress.zip',
            'min_wp_version' => '5.8',
            'min_php_version' => '7.4',
        ],
        'woocommerce' => [
            'version' => '1.1.0',
            'download_url' => '/plugins/woocommerce/netsendo-woocommerce.zip',
            'min_wp_version' => '5.8',
            'min_wc_version' => '5.0',
            'min_php_version' => '7.4',
        ],
    ],

];
