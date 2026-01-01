<?php

return [
    'title' => 'Marktplatz',
    'subtitle' => 'Entdecken Sie Integrationen und Erweiterungen, um Ihr E-Mail-Marketing zu verbessern.',
    'active_integrations' => 'Aktive Integrationen',
    'active' => 'Aktiv',
    'coming_soon' => 'Demnächst verfügbar',
    'soon' => 'Bald',
    'banner_title' => 'Weitere Integrationen folgen bald',
    'banner_desc' => 'Wir arbeiten ständig daran, neue Integrationen hinzuzufügen, um Ihnen zu helfen, Ihr Geschäft auszubauen.',
    'features' => [
        'one_click' => 'Ein-Klick-Installation',
        'auto_sync' => 'Auto-Sync',
        'no_code' => 'Kein Code erforderlich',
    ],
    'categories' => [
        'ecommerce' => [
            'title' => 'E-Commerce',
            'desc' => 'Verbinden Sie Ihren Shop',
        ],
        'crm' => [
            'title' => 'CRM',
            'desc' => 'Kundenbeziehungen',
        ],
        'forms' => [
            'title' => 'Formulare & Umfragen',
            'desc' => 'Lead-Generierung',
        ],
        'automation' => [
            'title' => 'Automatisierung',
            'desc' => 'Workflows verbinden',
        ],
        'payments' => [
            'title' => 'Zahlungen',
            'desc' => 'Zahlungen verarbeiten',
        ],
        'analytics' => [
            'title' => 'Analytik',
            'desc' => 'Leistung verfolgen',
        ],
    ],
    'request_title' => "Nicht gefunden, was Sie suchen?",
    'request_desc' => "Lassen Sie uns wissen, welche Integration Sie als nächstes sehen möchten. Wir priorisieren unsere Roadmap basierend auf Benutzerfeedback.",
    'request_button' => 'Integration anfragen',
    'request_modal_title' => 'Integration anfragen',
    'request_success_title' => 'Anfrage erhalten!',
    'request_success_desc' => "Danke für Ihr Feedback. Wir informieren Sie, sobald diese Integration verfügbar ist.",
    'request_integration_name' => 'Name der Integration',
    'request_integration_name_placeholder' => 'z.B. Shopify, Salesforce...',
    'request_description' => 'Beschreibung (Optional)',
    'request_description_placeholder' => 'Wie möchten Sie diese Integration nutzen?',
    'request_priority' => 'Priorität',
    'priority_low' => 'Niedrig',
    'priority_normal' => 'Normal',
    'priority_high' => 'Hoch',
    'request_submitted_as' => 'Eingereicht als',
    'request_submit' => 'Anfrage senden',
    'request_error' => 'Fehler beim Senden der Anfrage. Bitte versuchen Sie es erneut.',

    'wordpress' => [
        'title' => 'WordPress',
        'hero_title' => 'WordPress',
        'hero_subtitle' => 'Professionelle Abonnementformulare und Content Gating für Blogger und Content-Ersteller auf WordPress.',
        'hero_description' => 'Das NetSendo für WordPress Plugin ist eine Komplettlösung für Blogger und Content Creators. Fügen Sie professionelle Anmeldeformulare zu Ihrem Newsletter hinzu, beschränken Sie die Sichtbarkeit von Artikeln nur auf Abonnenten und bauen Sie Ihre Mailingliste direkt von WordPress aus auf.',
        'features_title' => 'Funktionen',
        'features' => [
            'forms' => [
                'title' => 'Abonnementformulare',
                'description' => 'Professionelle Anmeldeformulare mit verschiedenen Stilen: Inline, Minimal, Karte.',
            ],
            'gating' => [
                'title' => 'Content Gating',
                'description' => 'Beschränken Sie den Zugriff auf Inhalte nur für Abonnenten.',
            ],
            'blocks' => [
                'title' => 'Gutenberg Blöcke',
                'description' => 'Spezielle Blöcke für einfache Inhaltsbearbeitung.',
            ],
            'widget' => [
                'title' => 'Sidebar Widget',
                'description' => 'Vorgefertigtes Anmeldeformular-Widget für jede Sidebar.',
            ],
            'gdpr' => [
                'title' => 'DSGVO Bereit',
                'description' => 'Eingebaute DSGVO-Zustimmungsbox mit konfigurierbarem Text.',
            ],
            'settings' => [
                'title' => 'Einfache Konfiguration',
                'description' => 'Einfaches Einstellungsfeld im WP-Admin.',
            ],
        ],
        'setup_title' => 'Einrichtung',
        'setup_steps' => [
            'download' => [
                'title' => 'Plugin herunterladen',
                'description' => 'Laden Sie die Plugin-Zip-Datei auf Ihren Computer herunter.',
            ],
            'install' => [
                'title' => 'Installieren',
                'description' => 'Laden Sie das Plugin in Ihrem WordPress-Admin hoch und aktivieren Sie es.',
            ],
            'configure' => [
                'title' => 'Konfigurieren',
                'description' => 'Geben Sie Ihren API-Schlüssel und Ihre URL in den Plugin-Einstellungen ein.',
            ],
            'add_forms' => [
                'title' => 'Formulare hinzufügen',
                'description' => 'Verwenden Sie Shortcodes oder Blöcke, um Formulare zu Ihren Beiträgen hinzuzufügen.',
            ],
        ],
        'download_button' => 'Plugin herunterladen',
        'shortcodes_title' => 'Shortcodes',
        'shortcodes' => [
            'form_basic' => 'Basis-Formular',
            'form_styled' => 'Gestaltetes Formular',
            'gate_percentage' => 'Inhalt nach X% sperren',
            'gate_subscribers' => 'Nur für Abonnenten sperren',
        ],
        'api_config_title' => 'API Konfiguration',
        'api_url_label' => 'API URL',
        'api_url_help' => 'Kopieren Sie diese URL in die Plugin-Einstellungen.',
        'api_key_label' => 'API Schlüssel',
        'api_key_desc' => 'Sie benötigen einen API-Schlüssel, um das Plugin zu verbinden.',
        'manage_api_keys' => 'API Schlüssel verwalten',
        'requirements_title' => 'Anforderungen',
        'requirements' => [
            'wp' => 'WordPress 5.8 oder höher',
            'php' => 'PHP 7.4 oder höher',
            'account' => 'Aktives NetSendo-Konto',
        ],
        'content_gate_types_title' => 'Content Gating Typen',
        'content_gate_types' => [
            'percentage_desc' => 'Inhalt nach Lesen eines gewissen Prozentsatzes ausblenden.',
            'subscribers_only_desc' => 'Inhalt nur für aktive Abonnenten sichtbar.',
            'logged_in_desc' => 'Inhalt nur für eingeloggte Benutzer sichtbar.',
        ],
        'resources_title' => 'Ressourcen',
        'docs_link' => 'WordPress Dokumentation',
        'lists_link' => 'Listen verwalten',
        'help_title' => 'Brauchen Sie Hilfe?',
        'help_desc' => 'Überprüfen Sie unsere Dokumentation oder kontaktieren Sie den Support.',
        'documentation_button' => 'Dokumentation',
    ],

    'woocommerce' => [
        'title' => 'WooCommerce',
        'hero_title' => 'WooCommerce Integration',
        'hero_subtitle' => 'Verbinden Sie Ihren Shop und steigern Sie den Umsatz.',
        'hero_description' => 'Integrieren Sie Ihren WooCommerce-Shop nahtlos in NetSendo. Synchronisieren Sie Kunden automatisch, stellen Sie verlorene Warenkörbe wieder her und verfolgen Sie Einnahmen.',
        'features_title' => 'Funktionen',
        'features' => [
            'auto_subscribe' => [
                'title' => 'Automatisch abonnieren',
                'description' => 'Fügen Sie Kunden während des Checkouts automatisch zu Ihren Mailinglisten hinzu.',
            ],
            'cart_recovery' => [
                'title' => 'Warenkorb-Wiederherstellung',
                'description' => 'Stellen Sie verlorene Verkäufe mit automatisierten E-Mails zu abgebrochenen Warenkörben wieder her.',
            ],
            'product_settings' => [
                'title' => 'Produktsynchronisierung',
                'description' => 'Verknüpfen Sie WooCommerce-Produkte mit NetSendo-Tags und -Listen.',
            ],
            'external_pages' => [
                'title' => 'Externe Seiten',
                'description' => 'Verfolgen Sie Besuche und Ereignisse auf Ihren Shop-Seiten.',
            ],
        ],
        'setup_title' => 'Einrichtung',
        'setup_steps' => [
            'download' => [
                'title' => 'Herunterladen',
                'description' => 'Holen Sie sich die Plugin-Datei.',
            ],
            'install' => [
                'title' => 'Installieren',
                'description' => 'In WordPress/WooCommerce hochladen.',
            ],
            'configure' => [
                'title' => 'Konfigurieren',
                'description' => 'API verbinden.',
            ],
            'lists' => [
                'title' => 'Listen zuordnen',
                'description' => 'Listen für Kunden auswählen.',
            ],
        ],
        'download_button' => 'Plugin herunterladen',
        'api_config_title' => 'API Konfiguration',
        'api_url_label' => 'API URL',
        'api_url_help' => 'Endpunkt für Webhooks.',
        'api_key_label' => 'API Schlüssel',
        'api_key_desc' => 'Generieren Sie einen Schlüssel für Ihren Shop.',
        'manage_api_keys' => 'API Schlüssel verwalten',
        'requirements_title' => 'Anforderungen',
        'requirements' => [
            'wp' => 'WordPress 5.8+',
            'wc' => 'WooCommerce 6.0+',
            'php' => 'PHP 7.4+',
            'account' => 'NetSendo Konto',
        ],
        'docs_link' => 'WooCommerce Dokumentation',
        'lists_link' => 'Listen verwalten',
        'funnels_link' => 'Verkaufstrichter',
        'help_title' => 'Hilfe',
        'help_desc' => 'Lesen Sie die Anleitung oder kontaktieren Sie den Support.',
        'documentation_button' => 'Dokumentation',
    ],

    'shopify' => [
        'title' => 'Shopify',
        'hero_title' => 'Shopify Integration',
        'hero_subtitle' => 'Verbinden Sie Ihren Shopify-Shop und synchronisieren Sie Kunden automatisch.',
        'hero_description' => 'Integrieren Sie Ihren Shopify-Shop mit NetSendo über Webhooks. Fügen Sie Kunden automatisch zu Ihren Mailinglisten hinzu, wenn sie Bestellungen aufgeben, Konten erstellen oder Käufe abschließen.',
        'features_title' => 'Funktionen',
        'features' => [
            'auto_subscribe' => [
                'title' => 'Automatisch Abonnieren',
                'description' => 'Kunden automatisch bei Einkäufen zu Listen hinzufügen.',
            ],
            'customer_sync' => [
                'title' => 'Kundensynchronisierung',
                'description' => 'Neue Registrierungen direkt zu Listen synchronisieren.',
            ],
            'order_tracking' => [
                'title' => 'Bestellungsverfolgung',
                'description' => 'Bestelldaten als benutzerdefinierte Felder speichern.',
            ],
            'real_time' => [
                'title' => 'Echtzeit-Updates',
                'description' => 'Sofortige Webhook-Benachrichtigungen.',
            ],
        ],
        'setup_title' => 'Einrichtung',
        'setup_steps' => [
            'api_key' => [
                'title' => 'API-Schlüssel Generieren',
                'description' => 'Erstellen Sie einen API-Schlüssel in den NetSendo-Einstellungen.',
            ],
            'shopify_admin' => [
                'title' => 'Shopify Admin Öffnen',
                'description' => 'Gehen Sie zu Einstellungen > Benachrichtigungen > Webhooks.',
            ],
            'create_webhook' => [
                'title' => 'Webhook Erstellen',
                'description' => 'Fügen Sie die Webhook-URL hinzu und wählen Sie zu verfolgenden Ereignisse.',
            ],
            'test' => [
                'title' => 'Verbindung Testen',
                'description' => 'Geben Sie eine Testbestellung auf, um die Integration zu überprüfen.',
            ],
        ],
        'webhook_config_title' => 'Webhook Konfiguration',
        'webhook_url_label' => 'Webhook URL',
        'webhook_url_help' => 'Fügen Sie diese URL in Ihren Shopify-Webhook-Einstellungen hinzu.',
        'api_key_label' => 'API Schlüssel',
        'api_key_desc' => 'Fügen Sie Ihren API-Schlüssel als Bearer-Token in Webhook-Headern hinzu.',
        'manage_api_keys' => 'API Schlüssel Verwalten',
        'supported_events' => 'Unterstützte Ereignisse',
        'list_id_note_title' => 'Wichtig: Listen-ID Erforderlich',
        'list_id_note_desc' => 'Fügen Sie netsendo_list_id zum Webhook-Payload hinzu oder verwenden Sie Shopify Flow.',
        'requirements_title' => 'Anforderungen',
        'requirements' => [
            'store' => 'Aktiver Shopify-Shop',
            'account' => 'NetSendo-Konto',
            'api_key' => 'API-Schlüssel zur Authentifizierung',
        ],
        'resources_title' => 'Ressourcen',
        'docs_link' => 'Shopify Webhook Dokumentation',
        'lists_link' => 'Listen Verwalten',
        'help_title' => 'Brauchen Sie Hilfe?',
        'help_desc' => 'Überprüfen Sie die Dokumentation oder kontaktieren Sie uns.',
        'documentation_button' => 'Dokumentation',
    ],
];
