<?php

return [
    /*
    |--------------------------------------------------------------------------
    | NetSendo Mail Infrastructure (NMI) Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration for the built-in mail sending
    | infrastructure with dedicated IP support.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | MTA Connection Settings
    |--------------------------------------------------------------------------
    |
    | Connection details for the Haraka MTA server.
    |
    */
    // The MTA runs on the server's network and listens on netsendo-network's gateway
    // (10.58.0.1:2525), which the NetSendo containers know as netsendo-mta (MtaEndpoint)
    'mta_host' => env('NMI_MTA_HOST', 'netsendo-mta'),
    'mta_port' => env('NMI_MTA_PORT', 2525),
    'mta_encryption' => env('NMI_MTA_ENCRYPTION', 'none'),

    // Name the MTA gives itself to the recipients' mail servers (HELO) when it sends from
    // the server's main address. Empty: the panel's domain (the host of APP_URL), so no
    // second subdomain is needed. Mail from a dedicated IP goes out under that IP's name.
    'hostname' => env('NMI_HOSTNAME'),

    // Logins the MTA accepts (one per domain with NMI on), written by NetSendo and read by
    // the netsendo_auth Haraka plugin. In Docker: the nmi-auth volume, shared with nmi-mta.
    'mta_auth_file' => env('NMI_MTA_AUTH_FILE', storage_path('nmi/mta-users.json')),

    // Public mail server the dashboard connects to on port 25 to see whether outgoing
    // port 25 is open (nothing is sent). Empty: no check.
    'port25_check_host' => env('NMI_PORT25_CHECK_HOST', 'gmail-smtp-in.l.google.com'),

    /*
    |--------------------------------------------------------------------------
    | IP Provider Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for automatic IP provisioning via cloud providers.
    |
    */
    'ip_providers' => [
        'vultr' => [
            'enabled' => env('NMI_VULTR_ENABLED', false),
            'api_key' => env('NMI_VULTR_API_KEY'),
        ],
        'linode' => [
            'enabled' => env('NMI_LINODE_ENABLED', false),
            'api_key' => env('NMI_LINODE_API_KEY'),
        ],
        'digitalocean' => [
            'enabled' => env('NMI_DO_ENABLED', false),
            'api_key' => env('NMI_DO_API_KEY'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Warming Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for IP warming schedule.
    |
    */
    'warming' => [
        // Number of days for full warming cycle
        'duration_days' => env('NMI_WARMING_DAYS', 28),

        // Whether to auto-advance warming daily
        'auto_advance' => env('NMI_WARMING_AUTO_ADVANCE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | DKIM Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for DKIM key management.
    |
    */
    'dkim' => [
        // Key size (2048 recommended)
        'key_size' => env('NMI_DKIM_KEY_SIZE', 2048),

        // Key rotation period in months
        'rotation_months' => env('NMI_DKIM_ROTATION_MONTHS', 6),

        // Auto-rotate keys when due
        'auto_rotate' => env('NMI_DKIM_AUTO_ROTATE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Blacklist Monitoring
    |--------------------------------------------------------------------------
    |
    | Configuration for IP blacklist checking.
    |
    */
    'blacklist' => [
        // Check interval in hours
        'check_interval_hours' => env('NMI_BLACKLIST_CHECK_HOURS', 24),

        // Check interval when listed (more frequent)
        'check_interval_listed_hours' => env('NMI_BLACKLIST_CHECK_LISTED_HOURS', 6),

        // Send alert on listing
        'alert_on_listing' => env('NMI_BLACKLIST_ALERT', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Reputation Thresholds
    |--------------------------------------------------------------------------
    |
    | Thresholds for reputation monitoring and alerts.
    |
    */
    'reputation' => [
        // Score below which to pause sending
        'pause_threshold' => env('NMI_REPUTATION_PAUSE', 50),

        // Score below which to send warning
        'warning_threshold' => env('NMI_REPUTATION_WARNING', 70),

        // Maximum bounce rate before warning (percentage)
        'max_bounce_rate' => env('NMI_MAX_BOUNCE_RATE', 5),

        // Maximum complaint rate before warning (percentage)
        'max_complaint_rate' => env('NMI_MAX_COMPLAINT_RATE', 0.1),
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | Enable/disable NMI features.
    |
    */
    'features' => [
        // NMI_ENABLED=true keeps NetSendo MTA on; otherwise the switch in the panel decides
        // (App\Services\Nmi\NmiSwitch)
        'enabled' => env('NMI_ENABLED', false),

        // Allow new NMI mailbox creation
        'allow_new_mailboxes' => env('NMI_ALLOW_NEW', true),

        // Enable IP provisioning API
        'ip_provisioning' => env('NMI_IP_PROVISIONING', false),
    ],
];
