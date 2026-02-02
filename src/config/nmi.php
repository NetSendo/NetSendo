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
    'mta_host' => env('NMI_MTA_HOST', 'netsendo-mta'),
    'mta_port' => env('NMI_MTA_PORT', 25),
    'mta_encryption' => env('NMI_MTA_ENCRYPTION', 'none'),

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
        // Master switch for NMI
        'enabled' => env('NMI_ENABLED', false),

        // Allow new NMI mailbox creation
        'allow_new_mailboxes' => env('NMI_ALLOW_NEW', true),

        // Enable IP provisioning API
        'ip_provisioning' => env('NMI_IP_PROVISIONING', false),
    ],
];
