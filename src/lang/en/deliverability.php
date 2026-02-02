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
    ],

    // Folders
    'folder' => [
        'inbox' => 'Primary Inbox',
        'promotions' => 'Promotions',
        'spam' => 'Spam',
    ],

    // Table headers
    'subject' => 'Subject',
    'domain' => 'Domain',
    'score' => 'Score',
    'folder' => 'Folder',

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
];
