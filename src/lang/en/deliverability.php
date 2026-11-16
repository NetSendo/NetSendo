<?php

return [
    // Page titles
    'title' => 'Deliverability Shield',
    'subtitle' => 'Ensure your emails reach the inbox, not spam',

    // Navigation
    'add_domain' => 'Add Domain',
    'verified' => 'Verified',
    'pending_verification' => 'Pending Verification',
    'never_checked' => 'Never checked',
    'last_check' => 'Last check',
    'refresh' => 'Refresh',

    // Stats
    'stats' => [
        'domains' => 'Domains',
        'verified' => 'Verified',
        'critical' => 'Critical Issues',
        'avg_score' => 'Avg. Score',
    ],

    // Domains
    'domains' => [
        'title' => 'Your Domains',
        'empty' => [
            'title' => 'No domains added yet',
            'description' => 'Add your first domain to start monitoring deliverability',
        ],
    ],

    // DMARC Wiz
    'dmarc_wiz' => [
        'title' => 'DMARC Wiz',
        'subtitle' => 'Add your domain in just a few seconds',
        'step_domain' => 'Domain',
        'step_verify' => 'Verify',
        'enter_domain_title' => 'Enter your domain',
        'enter_domain_description' => 'This is the domain from which you send emails',
        'add_record_title' => 'Add DNS record',
        'add_record_description' => 'Add this CNAME record to your domain\'s DNS settings',
        'dns_propagation_info' => 'DNS changes may take up to 48 hours to propagate. You can verify anytime.',
        'add_and_verify' => 'Add & Check Verification',
        'add_domain_btn' => 'Add Domain',
    ],

    // Domain fields
    'domain_name' => 'Domain name',
    'record_type' => 'Record Type',
    'host' => 'Host',
    'target' => 'Target Value',
    'type' => 'Type',

    // Status
    'status_overview' => 'Status Overview',
    'verification_required' => 'Verification Required',
    'verification_description' => 'Add the CNAME record below to your DNS settings to verify domain ownership.',
    'add_this_record' => 'Add this DNS record',
    'verify_now' => 'Verify Now',

    // Alerts
    'alerts' => [
        'title' => 'Email Alerts',
        'description' => 'Get notified when there are issues with your domain deliverability',
    ],

    // Domain issues
    'domain' => [
        'spf_warning' => 'SPF record has a warning status - may affect deliverability',
        'dmarc_policy_none' => 'DMARC policy is set to "none" - emails may land in spam',
        'not_verified' => 'The domain is not verified yet (CNAME record missing)',
        'spf_critical' => 'The SPF record is broken',
        'dkim_critical' => 'The DKIM record is invalid',
        'dkim_warning' => 'No DKIM signature found, or it needs attention',
        'dmarc_critical' => 'No DMARC record found',
        'dmarc_warning' => 'The DMARC record needs attention',
    ],

    // DNS Issues (detailed)
    'issues' => [
        'spf_missing' => 'No SPF record found for this domain',
        'spf_no_include' => 'SPF record is missing required include',
        'spf_no_provider_include' => 'SPF record is missing :provider include (:required)',
        'spf_missing_ip' => 'SPF record is missing the address NetSendo MTA sends from (:ips). Add :mechanisms to it',
        'spf_permissive' => 'SPF record is too permissive (+all or ?all)',
        'dkim_missing' => 'No DKIM record found (checked selectors: :selectors_checked)',
        'dkim_invalid' => 'DKIM record is invalid (missing public key)',
        'dmarc_missing' => 'No DMARC record found for this domain',
        'dmarc_none' => 'DMARC policy is set to "none"',
        'dkim_selector_needed' => ':provider signs mail with selectors of its own that cannot be guessed. Enter one of them under DKIM selector (the part before ._domainkey in the CNAME records the provider gave you).',
    ],

    // Test email
    'test_email' => 'Test Your Email',
    'test_email_description' => 'Run a simulation to check deliverability before sending',

    // Simulations
    'simulations' => [
        'recent' => 'Recent Simulations',
        'empty' => 'No simulations yet. Run your first InboxPassport AI test.',
        'history' => 'Simulation History',
        'no_history' => 'No simulation history',
        'no_history_desc' => 'Run your first InboxPassport AI simulation to see results here.',
    ],

    // InboxPassport
    'inbox_passport' => [
        'title' => 'InboxPassport AI',
        'subtitle' => 'Predict where your email will land before sending',
        'how_it_works' => 'How It Works',
        'step1_title' => 'Analyze Domain',
        'step1_desc' => 'We check your SPF, DKIM and DMARC configuration',
        'step2_title' => 'Scan Content',
        'step2_desc' => 'AI detects spam triggers, suspicious links and formatting issues',
        'step3_title' => 'Predict Delivery',
        'step3_desc' => 'Get inbox placement prediction for Gmail, Outlook and Yahoo',
        'what_we_check' => 'What We Analyze',
    ],

    // Simulation form
    'select_domain' => 'Select Domain',
    'no_verified_domains' => 'No verified domains. Add a domain first.',
    'email_subject' => 'Email Subject',
    'subject_placeholder' => 'Enter your email subject line...',
    'email_content' => 'Email Content (HTML)',
    'content_placeholder' => 'Paste your email HTML content here...',
    'analyzing' => 'Analyzing...',
    'run_simulation' => 'Run InboxPassport AI',

    // Analysis elements
    'spam_words' => 'Spam Words',
    'subject_analysis' => 'Subject Analysis',
    'link_check' => 'Link Verification',
    'html_structure' => 'HTML Structure',
    'formatting' => 'Formatting',

    // Results
    'simulation_result' => 'Simulation Result',
    'predicted_folder' => 'Predicted Folder',
    'provider_predictions' => 'Provider Predictions',
    'confidence' => 'confidence',
    'issues_found' => 'Issues Found',
    'recommendations' => 'Recommendations',
    'run_new_simulation' => 'Run New Simulation',
    'view_history' => 'View History',
    'new_simulation' => 'New Simulation',

    // Scores
    'score' => [
        'excellent' => 'Excellent',
        'good' => 'Good',
        'fair' => 'Fair',
        'poor' => 'Poor',
        'risky' => 'Risky',
    ],

    // Folders
    'folder' => [
        'inbox' => 'Primary Inbox',
        'promotions' => 'Promotions',
        'spam' => 'Spam',
        'unknown' => 'Unknown',
    ],

    // Table headers (a group of their own: plain strings named 'domain', 'score' and 'folder'
    // here replaced the groups of the same name above, so their keys were never found)
    'table' => [
        'subject' => 'Subject',
        'domain' => 'Domain',
        'score' => 'Score',
        'folder' => 'Folder',
    ],

    'status' => [
        'pending' => 'Pending',
        'safe' => 'Protected',
        'warning' => 'Needs attention',
        'critical' => 'Critical',
    ],

    'cname' => [
        'verified' => 'Domain verified',
        'pending' => 'Waiting for the CNAME record',
    ],

    'spf' => [
        'pending' => 'Not checked yet',
        'valid' => 'SPF record is valid',
        'warning' => 'SPF record needs attention',
        'critical' => 'SPF record is broken',
    ],

    'dkim' => [
        'pending' => 'No DKIM signature found yet',
        'valid' => 'DKIM record found',
        'warning' => 'DKIM record needs attention',
        'critical' => 'DKIM record is invalid',
    ],

    'dmarc' => [
        'pending' => 'Not checked yet',
        'valid' => 'DMARC policy is published',
        'warning' => 'DMARC needs attention',
        'critical' => 'No DMARC record',
    ],

    'dmarc_policy' => [
        'none' => 'Monitoring only (p=none)',
        'quarantine' => 'Quarantine (p=quarantine)',
        'reject' => 'Reject (p=reject)',
    ],

    'warnings' => [
        'spf_softfail' => 'The SPF record ends with ~all (soft fail).',
        'dmarc_quarantine' => 'The DMARC policy is quarantine; reject gives full protection.',
        'dmarc_no_reporting' => 'The DMARC record has no rua address, so you receive no reports.',
    ],

    'actions' => [
        'go_to_domain_settings' => 'Open domain settings',
    ],

    // Actions
    'confirm_delete' => 'Are you sure you want to remove this domain?',

    // Messages
    'messages' => [
        'domain_added' => 'Domain added successfully. Add the CNAME record to verify.',
        'cname_verified' => 'Domain verified successfully!',
        'cname_not_found' => 'CNAME record not found. Please check your DNS settings.',
        'status_refreshed' => 'Status refreshed successfully.',
        'domain_removed' => 'Domain removed successfully.',
        'alerts_updated' => 'Alert settings updated.',
        'simulation_complete' => 'Simulation completed!',
        'gmail_managed_dns' => 'Gmail automatically manages SPF/DKIM for your account. No additional DNS configuration required.',
        'domain_not_configured' => 'Domain :domain is not configured in DMARC Wiz. Add it for full deliverability analysis.',
        'no_domain_warning' => 'No domain configured. Analysis based on message content only.',
        'dkim_selector_saved' => 'DKIM selector saved. The DNS records were checked again.',
        'copied_to_clipboard' => 'Copied to clipboard!',
    ],

    // Validation
    'validation' => [
        'domain_format' => 'Please enter a valid domain name',
        'domain_exists' => 'This domain is already added',
    ],

    // Localhost/Development Environment Warning
    'localhost_warning' => [
        'title' => 'Development Environment Detected',
        'description' => 'You are running NetSendo on localhost. DNS verification requires a public domain. CNAME records pointing to localhost cannot be verified.',
    ],

    // HTML Analysis Issues
    'html' => [
        'ratio_low' => 'Low text to HTML ratio - your email is too heavy on HTML code',
        'hidden_text' => 'Hidden text detected (display:none) - this is a spam indicator',
        'tiny_font' => 'Very small font size detected - this is a spam indicator',
        'image_heavy' => 'Image-heavy email with little text - add more text content',
    ],

    // Subject Analysis Issues
    'subject' => [
        'too_long' => 'Subject line is too long (over 60 characters)',
        'too_short' => 'Subject line is too short (under 5 characters)',
        'all_caps' => 'Subject contains too many capital letters',
        'exclamations' => 'Subject contains excessive exclamation marks',
        'questions' => 'Subject contains too many question marks',
        'fake_reply' => 'Subject starts with RE: or FW: which looks like a fake reply',
    ],

    // Link Issues
    'links' => [
        'shortener' => 'URL shortener detected - use full URLs instead',
        'suspicious_tld' => 'Suspicious domain extension detected',
        'ip_address' => 'IP address in URL detected - use proper domain names',
        'too_many' => 'Too many links in email (over 20)',
    ],

    // Formatting Issues
    'formatting' => [
        'caps' => 'Content contains too many capital letters',
        'symbols' => 'Content contains excessive special symbols',
    ],

    // Content Issues
    'content' => [
        'spam_word' => 'Spam trigger word detected: ":word"',
    ],

    // Spam Words
    'spam' => [
        'word_detected' => 'Spam trigger word detected',
    ],

    // Recommendations
    'recommendations' => [
        'fix_domain' => 'Fix your domain DNS configuration issues',
        'upgrade_dmarc' => 'Upgrade your DMARC policy from "none" to "quarantine" or "reject"',
        'remove_spam_words' => 'Remove or replace spam trigger words in your content',
        'improve_subject' => 'Improve your subject line - avoid caps, excessive punctuation',
        'fix_html' => 'Fix HTML structure issues - improve text/HTML ratio',
        'fix_links' => 'Fix link issues - avoid URL shorteners and suspicious domains',
        'looks_good' => 'Your email looks good! No major issues detected',
        'add_domain' => 'Add and verify a domain in DMARC Wiz for full deliverability analysis',
    ],

    // Upsell for non-GOLD users
    'upsell' => [
        'title' => 'Unlock Deliverability Shield',
        'description' => 'Maximize your email deliverability with advanced tools. Ensure every email lands in the inbox, not spam.',
        'feature1' => 'DMARC Wiz - Easy domain setup',
        'feature2' => 'InboxPassport AI - Pre-send testing',
        'feature3' => 'DNS Monitoring 24/7',
        'feature4' => 'Automated alerts & recommendations',
        'cta' => 'Upgrade to GOLD',
    ],

    // DMARC Generator (One-Click Fix)
    'dmarc_generator' => [
        'title' => 'DMARC Generator',
        'subtitle' => 'Generate the optimal DMARC record in one click',
        'initial_explanation' => 'Start here: p=none only collects reports and blocks nothing. Read the reports for one to two weeks to make sure every service that sends for your domain passes SPF or DKIM.',
        'recommended_explanation' => 'Full protection: mail that fails both SPF and DKIM is rejected. Use it after a few weeks on quarantine without problems.',
        'minimal_explanation' => 'The shortest valid DMARC record: p=none without reports.',
        'upgrade_notice' => 'After one to two weeks of reports without unknown senders, change p=none to p=quarantine, and later to p=reject.',
        'copy_record' => 'Copy Record',
        'current_policy' => 'Current Policy',
        'recommended_policy' => 'Recommended Policy',
        'report_email' => 'Report Email',
        'report_email_hint' => 'You will receive DMARC reports at this address',
        'quarantine_explanation' => 'Mail that fails both SPF and DKIM goes to spam. Move here when the reports show only your own services.',
    ],

    // SPF Generator (One-Click Fix)
    'spf_generator' => [
        'title' => 'SPF Generator',
        'subtitle' => 'Generate an optimized SPF record',
        'optimal_explanation' => 'Your current record with the includes your sending providers need added. Everything already in it stays, so other services that send for the domain keep working. Ends with -all (hard fail).',
        'softfail_explanation' => 'SPF record with soft fail (~all). Less restrictive but may affect deliverability.',
        'lookup_warning' => 'Your current SPF record exceeds or approaches the 10 DNS lookup limit. We recommend simplifying.',
        'lookup_count' => 'DNS Lookup Count',
        'max_lookups' => 'Maximum Limit',
        'copy_record' => 'Copy Record',
        'current_record' => 'Current Record',
        'optimal_record' => 'Optimized Record',
        'provider_detected' => 'Detected Provider',
        'needs_provider_include' => 'NetSendo does not know which SPF include your SMTP provider needs. Take it from the provider\'s setup guide (for example include:spf.example.net) and add it to your SPF record.',
    ],

    // DNS Generator Common
    'dns_generator' => [
        'instructions_title' => 'How to Add DNS Record',
        'step1' => '1. Log in to your domain DNS panel (e.g., GoDaddy, Cloudflare, Namecheap)',
        'step2' => '2. Add a new TXT record with the above data',
        'step3' => '3. Wait for DNS propagation (up to 48h) and click "Verify"',
        'copy_success' => 'Copied to clipboard!',
        'copy_failed' => 'Copy failed. Please copy manually.',
        'show_generator' => 'Show Generator',
        'hide_generator' => 'Hide Generator',
        'one_click_fix' => 'One-Click Fix',
    ],
];

