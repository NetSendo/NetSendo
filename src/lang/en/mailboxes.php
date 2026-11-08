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

    // NetSendo MTA (NMI) mailboxes
    'nmi' => [
        'disabled' => 'NetSendo MTA is switched off on this installation (NMI_ENABLED in .env).',
        'new_mailboxes_disabled' => 'New NetSendo MTA mailboxes are switched off on this installation (NMI_ALLOW_NEW in .env).',
        'domain_required' => 'Choose one of your domains for NetSendo MTA.',
        'ip_required' => 'Choose one of your dedicated IPs for NetSendo MTA.',
        'domain_not_verified' => 'The domain :domain is not verified yet. Add its CNAME record in Deliverability Shield first.',
        'domain_not_enabled' => 'NetSendo MTA is not switched on for :domain. Switch it on on the domain page in Deliverability Shield.',
        'ip_not_assigned' => 'The IP :ip is not assigned to :domain. Assign it on the IP page in Mail Infrastructure.',
        'ip_no_dkim' => 'The IP :ip has no DKIM keys yet. Generate them on the IP page in Mail Infrastructure.',
        'ip_inactive' => 'The IP :ip is switched off.',
        'from_email_domain' => 'The sender address must be on :domain or one of its subdomains, so that the DKIM signature matches it.',
        'no_domain' => 'The mailbox ":mailbox" has no NetSendo MTA domain. Edit it and choose a domain and an IP.',
        'test' => [
            'not_verified' => 'The domain :domain is not verified. Add its CNAME record in Deliverability Shield first.',
            'not_enabled' => 'NetSendo MTA is switched off for :domain.',
            'no_credentials' => 'The domain :domain has no NetSendo MTA login. Switch NetSendo MTA off and on again on the domain page.',
            'warming_limit' => 'The daily warming limit of this IP is reached. Try again tomorrow.',
            'success' => 'Connection works: a test email was sent through NetSendo MTA.',
            'failed' => 'Connection failed: :error',
        ],
    ],
];
