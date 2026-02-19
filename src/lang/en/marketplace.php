<?php

return [
    'title' => 'Marketplace',
    'subtitle' => 'Discover integrations and extensions to supercharge your email marketing.',
    'active_integrations' => 'Active Integrations',
    'active' => 'Active',
    'coming_soon' => 'Coming Soon',
    'soon' => 'Soon',
    'banner_title' => 'More Integrations Coming Soon',
    'banner_desc' => 'We are constantly working on adding new integrations to help you grow your business.',
    'features' => [
        'one_click' => 'One-click install',
        'auto_sync' => 'Auto-sync',
        'no_code' => 'No-code setup',
    ],
    'categories' => [
        'ecommerce' => [
            'title' => 'E-commerce',
            'desc' => 'Connect your store',
        ],
        'crm' => [
            'title' => 'CRM',
            'desc' => 'Customer relationship',
        ],
        'forms' => [
            'title' => 'Forms & Surveys',
            'desc' => 'Lead generation',
        ],
        'automation' => [
            'title' => 'Automation',
            'desc' => 'Connect workflows',
        ],
        'payments' => [
            'title' => 'Payments',
            'desc' => 'Process payments',
        ],
        'analytics' => [
            'title' => 'Analytics',
            'desc' => 'Track performance',
        ],
        'ai' => [
            'title' => 'AI & Research',
            'desc' => 'Intelligence tools',
        ],
    ],
    'request_title' => "Don't see what you need?",
    'request_desc' => "Let us know which integration you'd like to see next. We prioritize our roadmap based on user feedback.",
    'request_button' => 'Request Integration',
    'request_modal_title' => 'Request Integration',
    'request_success_title' => 'Request received!',
    'request_success_desc' => "Thank you for your feedback. We'll let you know when this integration becomes available.",
    'request_integration_name' => 'Integration Name',
    'request_integration_name_placeholder' => 'e.g. Shopify, Salesforce...',
    'request_description' => 'Description (Optional)',
    'request_description_placeholder' => 'How would you like to use this integration?',
    'request_priority' => 'Priority',
    'priority_low' => 'Low',
    'priority_normal' => 'Normal',
    'priority_high' => 'High',
    'request_submitted_as' => 'Submitted as',
    'request_submit' => 'Submit Request',
    'request_error' => 'Failed to submit request. Please try again.',

    'wordpress' => [
        'title' => 'WordPress',
        'hero_title' => 'WordPress',
        'hero_subtitle' => 'Professional subscription forms and content gating for bloggers and content creators on WordPress.',
        'hero_description' => 'The NetSendo for WordPress plugin is a complete solution for bloggers and content creators. Add professional subscription forms to your newsletter, limit article visibility only to subscribers, and build your mailing list directly from WordPress.',
        'features_title' => 'Features',
        'features' => [
            'forms' => [
                'title' => 'Subscription Forms',
                'description' => 'Professional subscription forms with various styles: inline, minimal, card.',
            ],
            'gating' => [
                'title' => 'Content Gating',
                'description' => 'Limit access to content for subscribers only.',
            ],
            'blocks' => [
                'title' => 'Gutenberg Blocks',
                'description' => 'Dedicated blocks for easy content editing.',
            ],
            'widget' => [
                'title' => 'Sidebar Widget',
                'description' => 'Ready-made subscription form widget for any sidebar.',
            ],
            'gdpr' => [
                'title' => 'GDPR Ready',
                'description' => 'Built-in GDPR consent checkbox with configurable text.',
            ],
            'settings' => [
                'title' => 'Easy Configuration',
                'description' => 'Simple settings panel in WP admin.',
            ],
        ],
        'setup_title' => 'Setup',
        'setup_steps' => [
            'download' => [
                'title' => 'Download Plugin',
                'description' => 'Download the plugin zip file to your computer.',
            ],
            'install' => [
                'title' => 'Install',
                'description' => 'Upload and activate the plugin in your WordPress admin.',
            ],
            'configure' => [
                'title' => 'Configure',
                'description' => 'Enter your API key and URL in plugin settings.',
            ],
            'add_forms' => [
                'title' => 'Add Forms',
                'description' => 'Use shortcodes or blocks to add forms to your posts.',
            ],
        ],
        'download_button' => 'Download Plugin',
        'shortcodes_title' => 'Shortcodes',
        'shortcodes' => [
            'form_basic' => 'Basic form',
            'form_styled' => 'Styled form',
            'gate_percentage' => 'Gate content after X%',
            'gate_subscribers' => 'Gate for subscribers only',
        ],
        'api_config_title' => 'API Configuration',
        'api_url_label' => 'API URL',
        'api_url_help' => 'Copy this URL to the plugin settings.',
        'api_key_label' => 'API Key',
        'api_key_desc' => 'You need an API key to connect the plugin.',
        'manage_api_keys' => 'Manage API Keys',
        'requirements_title' => 'Requirements',
        'requirements' => [
            'wp' => 'WordPress 5.8 or higher',
            'php' => 'PHP 7.4 or higher',
            'account' => 'Active NetSendo account',
        ],
        'content_gate_types_title' => 'Content Gating Types',
        'content_gate_types' => [
            'percentage_desc' => 'Hide content after reading a certain percentage.',
            'subscribers_only_desc' => 'Content visible only to active subscribers.',
            'logged_in_desc' => 'Content visible only to logged-in users.',
        ],
        'resources_title' => 'Resources',
        'docs_link' => 'WordPress Docs',
        'lists_link' => 'Manage Lists',
        'help_title' => 'Need help?',
        'help_desc' => 'Check our documentation or contact support.',
        'documentation_button' => 'Documentation',
    ],

    'woocommerce' => [
        'title' => 'WooCommerce',
        'hero_title' => 'WooCommerce Integration',
        'hero_subtitle' => 'Connect your store and boost sales.',
        'hero_description' => 'Seamlessly integrate your WooCommerce store with NetSendo. Automatically sync customers, recover abandoned carts, and track revenue.',
        'features_title' => 'Features',
        'features' => [
            'auto_subscribe' => [
                'title' => 'Auto Subscribe',
                'description' => 'Automatically add customers to your mailing lists during checkout.',
            ],
            'cart_recovery' => [
                'title' => 'Cart Recovery',
                'description' => 'Recover lost sales with automated abandoned cart emails.',
            ],
            'product_settings' => [
                'title' => 'Product Sync',
                'description' => 'Map WooCommerce products to NetSendo tags and lists.',
            ],
            'external_pages' => [
                'title' => 'External Pages',
                'description' => 'Track visits and events on your store pages.',
            ],
        ],
        'setup_title' => 'Setup',
        'setup_steps' => [
            'download' => [
                'title' => 'Download',
                'description' => 'Get the plugin file.',
            ],
            'install' => [
                'title' => 'Install',
                'description' => 'Upload to WordPress/WooCommerce.',
            ],
            'configure' => [
                'title' => 'Configure',
                'description' => 'Connect API.',
            ],
            'lists' => [
                'title' => 'Map Lists',
                'description' => 'Select lists for customers.',
            ],
        ],
        'download_button' => 'Download Plugin',
        'api_config_title' => 'API Configuration',
        'api_url_label' => 'API URL',
        'api_url_help' => 'Endpoint for webhooks.',
        'api_key_label' => 'API Key',
        'api_key_desc' => 'Generate a key for your store.',
        'manage_api_keys' => 'Manage API Keys',
        'requirements_title' => 'Requirements',
        'requirements' => [
            'wp' => 'WordPress 5.8+',
            'wc' => 'WooCommerce 6.0+',
            'php' => 'PHP 7.4+',
            'account' => 'NetSendo Account',
        ],
        'docs_link' => 'WooCommerce Docs',
        'lists_link' => 'Manage Lists',
        'funnels_link' => 'Sales Funnels',
        'help_title' => 'Need help?',
        'help_desc' => 'Read the guide or contact support.',
        'documentation_button' => 'Documentation',
    ],

    'shopify' => [
        'title' => 'Shopify',
        'hero_title' => 'Shopify Integration',
        'hero_subtitle' => 'Connect your Shopify store and sync customers automatically.',
        'hero_description' => 'Integrate your Shopify store with NetSendo using webhooks. Automatically add customers to your mailing lists when they place orders, create accounts, or complete purchases.',
        'features_title' => 'Features',
        'features' => [
            'auto_subscribe' => [
                'title' => 'Auto Subscribe',
                'description' => 'Automatically add customers to mailing lists when they make purchases.',
            ],
            'customer_sync' => [
                'title' => 'Customer Sync',
                'description' => 'Sync new customer registrations directly to your lists.',
            ],
            'order_tracking' => [
                'title' => 'Order Tracking',
                'description' => 'Store order details as custom fields for segmentation.',
            ],
            'real_time' => [
                'title' => 'Real-time Updates',
                'description' => 'Instant webhook notifications when events occur.',
            ],
        ],
        'setup_title' => 'Setup',
        'setup_steps' => [
            'api_key' => [
                'title' => 'Generate API Key',
                'description' => 'Create an API key in NetSendo settings.',
            ],
            'shopify_admin' => [
                'title' => 'Open Shopify Admin',
                'description' => 'Go to Settings > Notifications > Webhooks.',
            ],
            'create_webhook' => [
                'title' => 'Create Webhook',
                'description' => 'Add the webhook URL and select events to track.',
            ],
            'test' => [
                'title' => 'Test Connection',
                'description' => 'Place a test order to verify the integration.',
            ],
        ],
        'webhook_config_title' => 'Webhook Configuration',
        'webhook_url_label' => 'Webhook URL',
        'webhook_url_help' => 'Add this URL in your Shopify webhook settings.',
        'api_key_label' => 'API Key',
        'api_key_desc' => 'Include your API key as a Bearer token in webhook headers.',
        'manage_api_keys' => 'Manage API Keys',
        'supported_events' => 'Supported Events',
        'list_id_note_title' => 'Important: List ID Required',
        'list_id_note_desc' => 'Add netsendo_list_id to your webhook payload or use Shopify Flow to include it.',
        'requirements_title' => 'Requirements',
        'requirements' => [
            'store' => 'Active Shopify store',
            'account' => 'NetSendo account',
            'api_key' => 'API key for authentication',
        ],
        'resources_title' => 'Resources',
        'docs_link' => 'Shopify Webhook Docs',
        'lists_link' => 'Manage Lists',
        'help_title' => 'Need help?',
        'help_desc' => 'Check our documentation or contact support.',
        'documentation_button' => 'Documentation',
    ],

    'gmail' => [
        'title' => 'Gmail',
        'hero_title' => 'Gmail Integration',
        'hero_subtitle' => 'Connect your Gmail accounts for email inbox management.',
        'hero_description' => 'Integrate Gmail with NetSendo to use your Google email accounts as sending mailboxes. OAuth 2.0 provides secure authentication without storing passwords.',
        'features_title' => 'Features',
        'features' => [
            'imap' => [
                'title' => 'IMAP Access',
                'description' => 'Read and sync emails from your Gmail inbox.',
            ],
            'smtp' => [
                'title' => 'SMTP Sending',
                'description' => 'Send emails directly through Gmail servers.',
            ],
            'oauth' => [
                'title' => 'OAuth 2.0',
                'description' => 'Secure authentication without storing passwords.',
            ],
            'tracking' => [
                'title' => 'Email Tracking',
                'description' => 'Track opens and clicks for sent emails.',
            ],
        ],
        'setup_title' => 'Setup',
        'setup_steps' => [
            'google_cloud' => [
                'title' => 'Create Google Cloud Project',
                'description' => 'Go to Google Cloud Console and create a new project.',
            ],
            'enable_api' => [
                'title' => 'Enable Gmail API',
                'description' => 'In your project, enable the Gmail API from the API Library.',
            ],
            'oauth' => [
                'title' => 'Configure OAuth Consent',
                'description' => 'Set up OAuth consent screen with required scopes.',
            ],
            'configure' => [
                'title' => 'Add Credentials to NetSendo',
                'description' => 'Enter Client ID and Client Secret in Settings → Integrations.',
            ],
            'authorize' => [
                'title' => 'Authorize Gmail Account',
                'description' => 'Connect your Gmail account through the OAuth flow.',
            ],
        ],
        'go_to_settings' => 'Go to Email Accounts',
        'resources_title' => 'Resources',
        'docs_link' => 'Gmail API Documentation',
        'manage_accounts' => 'Manage Email Accounts',
        'requirements_title' => 'Requirements',
        'requirements' => [
            'google_account' => 'Google Account',
            'cloud_project' => 'Google Cloud Project',
            'oauth_credentials' => 'OAuth 2.0 Credentials',
            'netsendo_account' => 'NetSendo Account',
        ],
        'help_title' => 'Need help?',
        'help_desc' => 'Check our documentation for detailed setup instructions.',
        'documentation_button' => 'Documentation',
    ],

    'google_calendar' => [
        'title' => 'Google Calendar',
        'hero_title' => 'Google Calendar Integration',
        'hero_subtitle' => 'Sync CRM tasks with Google Calendar for seamless scheduling.',
        'hero_description' => 'Two-way synchronization between NetSendo CRM tasks and Google Calendar. Create, update, and track tasks across both platforms automatically.',
        'features_title' => 'Features',
        'features' => [
            'two_way_sync' => [
                'title' => 'Two-Way Sync',
                'description' => 'Changes sync automatically in both directions.',
            ],
            'task_sync' => [
                'title' => 'Task Synchronization',
                'description' => 'CRM tasks appear as calendar events.',
            ],
            'reminders' => [
                'title' => 'Reminders',
                'description' => 'Get notified about upcoming tasks.',
            ],
            'webhooks' => [
                'title' => 'Real-Time Updates',
                'description' => 'Instant sync via Google webhooks.',
            ],
        ],
        'setup_title' => 'Setup',
        'setup_steps' => [
            'google_cloud' => [
                'title' => 'Create Google Cloud Project',
                'description' => 'Go to Google Cloud Console and create a new project.',
            ],
            'enable_api' => [
                'title' => 'Enable Calendar API',
                'description' => 'Enable Google Calendar API from the API Library.',
            ],
            'oauth' => [
                'title' => 'Configure OAuth Consent',
                'description' => 'Set up OAuth consent screen with calendar scopes.',
            ],
            'configure' => [
                'title' => 'Add Credentials to NetSendo',
                'description' => 'Enter Client ID and Client Secret in Settings → Integrations.',
            ],
            'connect' => [
                'title' => 'Connect Calendar',
                'description' => 'Authorize your Google Calendar in Settings → Calendar.',
            ],
        ],
        'go_to_settings' => 'Go to Calendar Settings',
        'resources_title' => 'Resources',
        'docs_link' => 'Calendar API Documentation',
        'manage_tasks' => 'Manage CRM Tasks',
        'requirements_title' => 'Requirements',
        'requirements' => [
            'google_account' => 'Google Account',
            'cloud_project' => 'Google Cloud Project',
            'calendar_api' => 'Calendar API Enabled',
            'netsendo_account' => 'NetSendo Account',
        ],
        'help_title' => 'Need help?',
        'help_desc' => 'Check our documentation for detailed setup instructions.',
        'documentation_button' => 'Documentation',
    ],

    'perplexity' => [
        'title' => 'Perplexity AI',
        'hero_title' => 'Perplexity AI',
        'hero_subtitle' => 'AI-powered deep research with real-time citations for your marketing intelligence.',
        'hero_description' => 'Integrate Perplexity AI with NetSendo Brain to unlock deep research capabilities. Get comprehensive answers with citations, analyze competitors, discover market trends, and generate content ideas — all powered by advanced AI that searches the internet in real-time.',
        'features_title' => 'Features',
        'features' => [
            'deep_research' => [
                'title' => 'Deep Research with Citations',
                'description' => 'Get comprehensive, AI-powered answers with source citations from across the web.',
            ],
            'company_intelligence' => [
                'title' => 'Company Intelligence',
                'description' => 'Research companies in-depth — products, market position, technology stack, and key contacts.',
            ],
            'trend_analysis' => [
                'title' => 'Market Trend Analysis',
                'description' => 'Discover industry trends, emerging opportunities, and market dynamics with AI analysis.',
            ],
            'content_research' => [
                'title' => 'Content Research Ideas',
                'description' => 'Generate data-driven email and SMS content ideas based on real-time web intelligence.',
            ],
        ],
        'setup_title' => 'Setup',
        'setup_steps' => [
            'get_key' => [
                'title' => 'Get API Key',
                'description' => 'Sign up at perplexity.ai and generate an API key from your account dashboard.',
            ],
            'configure' => [
                'title' => 'Configure in Brain Settings',
                'description' => 'Go to Brain Settings and paste your Perplexity API key in the Research section.',
            ],
            'research' => [
                'title' => 'Start Researching',
                'description' => 'Ask Brain to research any topic — it will use Perplexity for deep, cited answers.',
            ],
        ],
        'api_info' => 'Perplexity AI uses the Sonar model for fast, accurate research with web citations.',
        'use_cases_title' => 'Use Cases',
        'use_cases' => [
            'competitor' => [
                'title' => 'Competitor Analysis',
                'description' => 'Research competitors\' strategies, products, and market positioning.',
            ],
            'enrichment' => [
                'title' => 'CRM Data Enrichment',
                'description' => 'Automatically gather structured company data for your CRM contacts.',
            ],
            'campaigns' => [
                'title' => 'Campaign Research',
                'description' => 'Get data-driven insights to improve your email and SMS campaigns.',
            ],
        ],
        'go_to_settings' => 'Configure API Key',
        'requirements_title' => 'Requirements',
        'requirements' => [
            'account' => 'Perplexity AI account',
            'api_key' => 'Perplexity API Key',
            'brain' => 'NetSendo Brain enabled',
        ],
        'resources_title' => 'Resources',
        'docs_link' => 'Perplexity API Documentation',
        'help_title' => 'Need help?',
        'help_desc' => 'Check our documentation for detailed setup instructions.',
        'documentation_button' => 'Documentation',
    ],

    'serpapi' => [
        'title' => 'SerpAPI',
        'hero_title' => 'SerpAPI',
        'hero_subtitle' => 'Google Search results and knowledge graphs integrated into your marketing workflow.',
        'hero_description' => 'Connect SerpAPI to NetSendo Brain for fast, structured Google Search results. Search the web, discover news, access knowledge graphs, and find company data — all from within your Brain chat conversations.',
        'features_title' => 'Features',
        'features' => [
            'google_search' => [
                'title' => 'Google Search Results',
                'description' => 'Access structured Google Search results with titles, snippets, and links.',
            ],
            'news_search' => [
                'title' => 'News Search',
                'description' => 'Find the latest news articles on any topic for timely marketing content.',
            ],
            'knowledge_graph' => [
                'title' => 'Knowledge Graph',
                'description' => 'Get rich entity data from Google\'s Knowledge Graph for deeper insights.',
            ],
            'company_lookup' => [
                'title' => 'Company Data Lookup',
                'description' => 'Quickly find company information, websites, and key business details.',
            ],
        ],
        'setup_title' => 'Setup',
        'setup_steps' => [
            'get_key' => [
                'title' => 'Get API Key',
                'description' => 'Sign up at serpapi.com and get your API key from the dashboard.',
            ],
            'configure' => [
                'title' => 'Configure in Brain Settings',
                'description' => 'Go to Brain Settings and paste your SerpAPI key in the Research section.',
            ],
            'search' => [
                'title' => 'Start Searching',
                'description' => 'Ask Brain to search the web — it will use SerpAPI for fast Google results.',
            ],
        ],
        'search_types_title' => 'Supported Search Types',
        'search_types' => [
            'general' => 'General Web Search',
            'news' => 'News Search',
            'images' => 'Image Search',
        ],
        'use_cases_title' => 'Use Cases',
        'use_cases' => [
            'competitors' => [
                'title' => 'Competitor Monitoring',
                'description' => 'Track competitor activity and online presence in real-time.',
            ],
            'trends' => [
                'title' => 'Trend Discovery',
                'description' => 'Find trending topics and news for timely marketing campaigns.',
            ],
            'crm' => [
                'title' => 'Lead Research',
                'description' => 'Quickly research leads and companies before outreach.',
            ],
        ],
        'go_to_settings' => 'Configure API Key',
        'requirements_title' => 'Requirements',
        'requirements' => [
            'account' => 'SerpAPI account',
            'api_key' => 'SerpAPI API Key',
            'brain' => 'NetSendo Brain enabled',
        ],
        'resources_title' => 'Resources',
        'docs_link' => 'SerpAPI Documentation',
        'help_title' => 'Need help?',
        'help_desc' => 'Check our documentation for detailed setup instructions.',
        'documentation_button' => 'Documentation',
    ],
];
