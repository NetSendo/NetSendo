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

    'version' => '2.1.0',

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
                'Publiczne API',
                'Serwer MCP',
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
                'Nielimitowane kampanie AI',
                'Zaawansowane AI (lokalne LLM)',
                'Testy A/B w kampaniach',
                'Auto-Webinary + Scenariusze',
                'Priorytetowe wsparcie',
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

    /*
    |--------------------------------------------------------------------------
    | Email Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for email processing and delivery.
    |
    */

    'email' => [
        // Convert images with class="img_to_b64" to inline base64
        'convert_inline_images' => env('EMAIL_CONVERT_INLINE_IMAGES', true),

        // Maximum size for inline images (in bytes, default 500KB)
        'max_inline_image_size' => env('EMAIL_MAX_INLINE_IMAGE_SIZE', 512000),

        // Timeout for fetching remote images (in seconds)
        'image_fetch_timeout' => env('EMAIL_IMAGE_FETCH_TIMEOUT', 10),

        /*
        | Transient SMTP failure handling (issue #21 — "421 Too many connections").
        | Transient 4xx responses (throttling, connection/rate limits, greylisting)
        | are released back to the queue with a backoff delay and retried instead
        | of being marked as permanently failed. Permanent 5xx errors (invalid
        | recipient, mailbox not found) still fail immediately.
        */
        'transient_retry' => env('EMAIL_TRANSIENT_RETRY', true),

        // Base backoff in seconds. Actual delay = base * attempt + random jitter,
        // so the first retry waits ~base..base+jitter, the second ~2*base.., etc.
        'transient_backoff_base' => env('EMAIL_TRANSIENT_BACKOFF_BASE', 15),

        // Upper bound (seconds) of the random jitter added to each backoff, so
        // retries from the same burst don't all reconnect at the same instant.
        'transient_backoff_jitter' => env('EMAIL_TRANSIENT_BACKOFF_JITTER', 15),

        /*
        | Staggered dispatch: spread each cron run's sends across the sending
        | window instead of dispatching them all at once. Dispatching the whole
        | minute's allowance simultaneously opens a burst of concurrent SMTP
        | connections, which many providers reject with "421 Too many connections".
        */
        'stagger_sends' => env('EMAIL_STAGGER_SENDS', true),

        // Window (seconds) across which a minute's worth of sends is spread.
        // Kept just under 60 so a run finishes before the next cron tick.
        'stagger_window_seconds' => env('EMAIL_STAGGER_WINDOW', 55),

        /*
        | Master HTML layout (issue #22 — HTML_MIME_NO_HTML_TAG).
        | Fragment content produced by the editor/API (e.g. starting straight
        | with a <div> or preheader) is wrapped in a full HTML document
        | (<!DOCTYPE html>, <html>, <head>, <body>) before sending, so messages
        | are not penalised by spam filters for lacking an <html> tag. Content
        | that is already a full document is left untouched. Disable to restore
        | the previous send-content-verbatim behaviour.
        */
        'wrap_html_document' => env('EMAIL_WRAP_HTML_DOCUMENT', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported Languages
    |--------------------------------------------------------------------------
    |
    | Available languages for subscriber preferences and message translations.
    |
    */

    'languages' => [
        'pl' => 'Polski',
        'en' => 'English',
        'de' => 'Deutsch',
        'fr' => 'Français',
        'es' => 'Español',
        'it' => 'Italiano',
        'pt' => 'Português',
        'nl' => 'Nederlands',
        'cs' => 'Čeština',
        'sk' => 'Slovenčina',
        'uk' => 'Українська',
        'ru' => 'Русский',
        'sv' => 'Svenska',
        'no' => 'Norsk',
        'da' => 'Dansk',
    ],

];
