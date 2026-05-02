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
];
