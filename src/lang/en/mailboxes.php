<?php

return [
    'title' => 'Mailboxes',
    'created' => 'Mailbox has been created.',
    'updated' => 'Mailbox has been updated.',
    'deleted' => 'Mailbox has been deleted.',
    'set_default' => 'Mailbox set as default.',

    'types' => [
        'broadcast' => 'One-time (Broadcast)',
        'autoresponder' => 'Autoresponder (Queue)',
        'system' => 'System',
    ],

    'bounce' => [
        'section_title' => 'Bounce Mailbox Monitoring',
        'section_desc' => 'Monitor a dedicated IMAP mailbox for bounce-back emails and automatically mark subscribers as bounced.',
        'enabled' => 'Enable bounce monitoring',
        'imap_host' => 'IMAP Host',
        'imap_port' => 'IMAP Port',
        'imap_encryption' => 'Encryption',
        'imap_username' => 'IMAP Username',
        'imap_password' => 'IMAP Password',
        'imap_folder' => 'IMAP Folder',
        'test_connection' => 'Test IMAP Connection',
        'test_success' => 'Successfully connected to bounce mailbox',
        'test_failed' => 'Failed to connect to bounce mailbox',
        'last_scanned' => 'Last scanned',
        'last_scan_count' => 'Bounces found in last scan',
        'never_scanned' => 'Never scanned',
        'processed' => ':count bounce(s) processed',
    ],

    'bounce_webhooks' => [
        'token_generated' => 'Webhook token generated. Paste it at your provider – any previous token no longer works.',
        'invalid_sendgrid_key' => 'This is not a valid SendGrid verification key. Copy the public key shown after enabling Signed Event Webhook in SendGrid.',
    ],

    'replies' => [
        'test_success' => 'Successfully connected to the reply mailbox',
        'test_failed' => 'Failed to connect to the reply mailbox',
        'not_configured' => 'Reply detection is not configured. Save the mailbox settings and try again.',
    ],

    'custom_headers' => [
        'section_title' => 'Custom SMTP Headers',
        'section_desc' => 'Add custom headers to all emails sent from this sending server (e.g. Feedback-ID for Google Postmaster Tools).',
        'key' => 'Header Name',
        'value' => 'Header Value',
        'add' => 'Add Header',
        'remove' => 'Remove',
        'key_placeholder' => 'e.g. Feedback-ID',
        'value_placeholder' => 'e.g. campaign1:user123:netsendo',
    ],

    // NetSendo MTA (NMI) mailboxes: a verified domain and, optionally, a dedicated IP
    'nmi' => [
        'errors' => [
            'domain_required' => 'Choose one of your verified domains for the NetSendo MTA mailbox.',
            'domain_not_verified' => 'The domain :domain is not verified yet. Verify it in Deliverability first.',
            'domain_missing' => 'This NetSendo MTA mailbox has no sending domain any more (the domain was removed). Edit the mailbox and choose a verified domain.',
            'from_domain_mismatch' => 'The sender address must be in the chosen domain (@:domain).',
            'ip_invalid' => 'Choose an active dedicated IP from one of your IP pools, or none.',
            'ip_other_domain' => 'The IP address :ip is already assigned to another domain.',
            'mta_login_file' => 'NetSendo could not write the MTA login file (:file). Check that the directory exists and is writable, then save again.',
            'mta_login_refused' => 'The MTA does not know this domain\'s login: it must read the login file NetSendo writes (the nmi-auth volume in docker-compose.yml).',
        ],
    ],

    // Field names in validation messages of the mailbox form
    'attributes' => [
        'name' => 'mailbox name',
        'provider' => 'provider',
        'from_email' => 'sender address',
        'from_name' => 'sender name',
        'reply_to' => 'Reply-To address',
        'allowed_types' => 'allowed message types',
        'credentials' => 'server login details',
        'daily_limit' => 'daily sending limit',
        'time_restriction' => 'minimum interval',
        'custom_headers.*.key' => 'header name',
        'custom_headers.*.value' => 'header value',
        'domain_configuration_id' => 'sending domain',
        'dedicated_ip_id' => 'dedicated IP',
        'reply_imap_host' => 'reply IMAP host',
    ],
];
