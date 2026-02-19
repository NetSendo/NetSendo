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
        'ai' => [
            'title' => 'KI & Forschung',
            'desc' => 'Intelligenz-Tools',
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

    'gmail' => [
        'title' => 'Gmail',
        'hero_title' => 'Gmail Integration',
        'hero_subtitle' => 'Verbinden Sie Ihre Gmail-Konten für die E-Mail-Postfachverwaltung.',
        'hero_description' => 'Integrieren Sie Gmail mit NetSendo, um Ihre Google-E-Mail-Konten als Versand-Postfächer zu nutzen. OAuth 2.0 bietet sichere Authentifizierung ohne Passwort-Speicherung.',
        'features_title' => 'Funktionen',
        'features' => [
            'imap' => [
                'title' => 'IMAP-Zugriff',
                'description' => 'E-Mails aus Ihrem Gmail-Postfach lesen und synchronisieren.',
            ],
            'smtp' => [
                'title' => 'SMTP-Versand',
                'description' => 'E-Mails direkt über Gmail-Server versenden.',
            ],
            'oauth' => [
                'title' => 'OAuth 2.0',
                'description' => 'Sichere Authentifizierung ohne Passwort-Speicherung.',
            ],
            'tracking' => [
                'title' => 'E-Mail-Tracking',
                'description' => 'Öffnungen und Klicks für gesendete E-Mails verfolgen.',
            ],
        ],
        'setup_title' => 'Einrichtung',
        'setup_steps' => [
            'google_cloud' => [
                'title' => 'Google Cloud Projekt Erstellen',
                'description' => 'Gehen Sie zur Google Cloud Console und erstellen Sie ein neues Projekt.',
            ],
            'enable_api' => [
                'title' => 'Gmail API Aktivieren',
                'description' => 'Aktivieren Sie die Gmail API in Ihrem Projekt über die API-Bibliothek.',
            ],
            'oauth' => [
                'title' => 'OAuth-Zustimmung Konfigurieren',
                'description' => 'Richten Sie den OAuth-Zustimmungsbildschirm mit erforderlichen Bereichen ein.',
            ],
            'configure' => [
                'title' => 'Anmeldedaten zu NetSendo Hinzufügen',
                'description' => 'Geben Sie Client-ID und Client-Secret in Einstellungen → Integrationen ein.',
            ],
            'authorize' => [
                'title' => 'Gmail-Konto Autorisieren',
                'description' => 'Verbinden Sie Ihr Gmail-Konto über den OAuth-Flow.',
            ],
        ],
        'go_to_settings' => 'Zu E-Mail-Konten',
        'resources_title' => 'Ressourcen',
        'docs_link' => 'Gmail API Dokumentation',
        'manage_accounts' => 'E-Mail-Konten Verwalten',
        'requirements_title' => 'Anforderungen',
        'requirements' => [
            'google_account' => 'Google-Konto',
            'cloud_project' => 'Google Cloud Projekt',
            'oauth_credentials' => 'OAuth 2.0 Anmeldedaten',
            'netsendo_account' => 'NetSendo-Konto',
        ],
        'help_title' => 'Brauchen Sie Hilfe?',
        'help_desc' => 'Überprüfen Sie unsere Dokumentation für detaillierte Einrichtungsanweisungen.',
        'documentation_button' => 'Dokumentation',
    ],

    'google_calendar' => [
        'title' => 'Google Kalender',
        'hero_title' => 'Google Kalender Integration',
        'hero_subtitle' => 'CRM-Aufgaben mit Google Kalender synchronisieren.',
        'hero_description' => 'Bidirektionale Synchronisierung zwischen NetSendo CRM-Aufgaben und Google Kalender. Erstellen, aktualisieren und verfolgen Sie Aufgaben automatisch auf beiden Plattformen.',
        'features_title' => 'Funktionen',
        'features' => [
            'two_way_sync' => [
                'title' => 'Bidirektionale Synchronisierung',
                'description' => 'Änderungen werden automatisch in beide Richtungen synchronisiert.',
            ],
            'task_sync' => [
                'title' => 'Aufgaben-Synchronisierung',
                'description' => 'CRM-Aufgaben erscheinen als Kalendertermine.',
            ],
            'reminders' => [
                'title' => 'Erinnerungen',
                'description' => 'Benachrichtigungen über bevorstehende Aufgaben erhalten.',
            ],
            'webhooks' => [
                'title' => 'Echtzeit-Updates',
                'description' => 'Sofortige Synchronisierung über Google Webhooks.',
            ],
        ],
        'setup_title' => 'Einrichtung',
        'setup_steps' => [
            'google_cloud' => [
                'title' => 'Google Cloud Projekt Erstellen',
                'description' => 'Gehen Sie zur Google Cloud Console und erstellen Sie ein neues Projekt.',
            ],
            'enable_api' => [
                'title' => 'Kalender API Aktivieren',
                'description' => 'Aktivieren Sie die Google Calendar API aus der API-Bibliothek.',
            ],
            'oauth' => [
                'title' => 'OAuth-Zustimmung Konfigurieren',
                'description' => 'Richten Sie den OAuth-Zustimmungsbildschirm mit Kalender-Bereichen ein.',
            ],
            'configure' => [
                'title' => 'Anmeldedaten zu NetSendo Hinzufügen',
                'description' => 'Geben Sie Client-ID und Client-Secret in Einstellungen → Integrationen ein.',
            ],
            'connect' => [
                'title' => 'Kalender Verbinden',
                'description' => 'Autorisieren Sie Ihren Google Kalender in Einstellungen → Kalender.',
            ],
        ],
        'go_to_settings' => 'Zu Kalender-Einstellungen',
        'resources_title' => 'Ressourcen',
        'docs_link' => 'Kalender API Dokumentation',
        'manage_tasks' => 'CRM-Aufgaben Verwalten',
        'requirements_title' => 'Anforderungen',
        'requirements' => [
            'google_account' => 'Google-Konto',
            'cloud_project' => 'Google Cloud Projekt',
            'calendar_api' => 'Kalender API Aktiviert',
            'netsendo_account' => 'NetSendo-Konto',
        ],
        'help_title' => 'Brauchen Sie Hilfe?',
        'help_desc' => 'Überprüfen Sie unsere Dokumentation für detaillierte Einrichtungsanweisungen.',
        'documentation_button' => 'Dokumentation',
    ],

    'perplexity' => [
        'title' => 'Perplexity AI',
        'hero_title' => 'Perplexity AI',
        'hero_subtitle' => 'KI-gestützte Tiefenforschung mit Echtzeit-Zitaten für Ihre Marketing-Intelligenz.',
        'hero_description' => 'Integrieren Sie Perplexity AI mit NetSendo Brain für erweiterte Forschungsmöglichkeiten. Erhalten Sie umfassende Antworten mit Quellenangaben, analysieren Sie Wettbewerber, entdecken Sie Markttrends und generieren Sie Content-Ideen — alles angetrieben durch fortschrittliche KI mit Echtzeit-Internetsuche.',
        'features_title' => 'Funktionen',
        'features' => [
            'deep_research' => [
                'title' => 'Tiefenforschung mit Zitaten',
                'description' => 'Erhalten Sie umfassende, KI-gestützte Antworten mit Quellenangaben aus dem gesamten Web.',
            ],
            'company_intelligence' => [
                'title' => 'Unternehmensintelligenz',
                'description' => 'Recherchieren Sie Unternehmen eingehend — Produkte, Marktposition, Technologie-Stack und Schlüsselkontakte.',
            ],
            'trend_analysis' => [
                'title' => 'Markttrendanalyse',
                'description' => 'Entdecken Sie Branchentrends, aufkommende Chancen und Marktdynamiken mit KI-Analyse.',
            ],
            'content_research' => [
                'title' => 'Content-Forschungsideen',
                'description' => 'Generieren Sie datengetriebene E-Mail- und SMS-Content-Ideen basierend auf Echtzeit-Web-Intelligenz.',
            ],
        ],
        'setup_title' => 'Einrichtung',
        'setup_steps' => [
            'get_key' => [
                'title' => 'API-Schlüssel erhalten',
                'description' => 'Registrieren Sie sich auf perplexity.ai und generieren Sie einen API-Schlüssel über Ihr Konto-Dashboard.',
            ],
            'configure' => [
                'title' => 'In Brain-Einstellungen konfigurieren',
                'description' => 'Gehen Sie zu Brain-Einstellungen und fügen Sie Ihren Perplexity-API-Schlüssel im Forschungsbereich ein.',
            ],
            'research' => [
                'title' => 'Forschung starten',
                'description' => 'Bitten Sie Brain, ein beliebiges Thema zu recherchieren — es nutzt Perplexity für tiefgehende, zitierte Antworten.',
            ],
        ],
        'api_info' => 'Perplexity AI verwendet das Sonar-Modell für schnelle, präzise Forschung mit Web-Zitaten.',
        'use_cases_title' => 'Anwendungsfälle',
        'use_cases' => [
            'competitor' => [
                'title' => 'Wettbewerbsanalyse',
                'description' => 'Recherchieren Sie Strategien, Produkte und Marktpositionierung der Wettbewerber.',
            ],
            'enrichment' => [
                'title' => 'CRM-Datenanreicherung',
                'description' => 'Sammeln Sie automatisch strukturierte Unternehmensdaten für Ihre CRM-Kontakte.',
            ],
            'campaigns' => [
                'title' => 'Kampagnenforschung',
                'description' => 'Erhalten Sie datengetriebene Erkenntnisse zur Verbesserung Ihrer E-Mail- und SMS-Kampagnen.',
            ],
        ],
        'go_to_settings' => 'API-Schlüssel konfigurieren',
        'requirements_title' => 'Anforderungen',
        'requirements' => [
            'account' => 'Perplexity AI Konto',
            'api_key' => 'Perplexity API-Schlüssel',
            'brain' => 'NetSendo Brain aktiviert',
        ],
        'resources_title' => 'Ressourcen',
        'docs_link' => 'Perplexity API Dokumentation',
        'help_title' => 'Brauchen Sie Hilfe?',
        'help_desc' => 'Überprüfen Sie unsere Dokumentation für detaillierte Einrichtungsanweisungen.',
        'documentation_button' => 'Dokumentation',
    ],

    'serpapi' => [
        'title' => 'SerpAPI',
        'hero_title' => 'SerpAPI',
        'hero_subtitle' => 'Google-Suchergebnisse und Wissensgraphen integriert in Ihren Marketing-Workflow.',
        'hero_description' => 'Verbinden Sie SerpAPI mit NetSendo Brain für schnelle, strukturierte Google-Suchergebnisse. Durchsuchen Sie das Web, entdecken Sie Nachrichten, greifen Sie auf Wissensgraphen zu und finden Sie Unternehmensdaten — alles aus Ihren Brain-Chat-Gesprächen.',
        'features_title' => 'Funktionen',
        'features' => [
            'google_search' => [
                'title' => 'Google-Suchergebnisse',
                'description' => 'Greifen Sie auf strukturierte Google-Suchergebnisse mit Titeln, Snippets und Links zu.',
            ],
            'news_search' => [
                'title' => 'Nachrichtensuche',
                'description' => 'Finden Sie aktuelle Nachrichtenartikel zu jedem Thema für zeitgemäße Marketinginhalte.',
            ],
            'knowledge_graph' => [
                'title' => 'Wissensgraph',
                'description' => 'Erhalten Sie umfangreiche Entitätsdaten aus Googles Wissensgraph für tiefere Einblicke.',
            ],
            'company_lookup' => [
                'title' => 'Unternehmenssuche',
                'description' => 'Finden Sie schnell Unternehmensinformationen, Websites und wichtige Geschäftsdaten.',
            ],
        ],
        'setup_title' => 'Einrichtung',
        'setup_steps' => [
            'get_key' => [
                'title' => 'API-Schlüssel erhalten',
                'description' => 'Registrieren Sie sich auf serpapi.com und erhalten Sie Ihren API-Schlüssel vom Dashboard.',
            ],
            'configure' => [
                'title' => 'In Brain-Einstellungen konfigurieren',
                'description' => 'Gehen Sie zu Brain-Einstellungen und fügen Sie Ihren SerpAPI-Schlüssel im Forschungsbereich ein.',
            ],
            'search' => [
                'title' => 'Suche starten',
                'description' => 'Bitten Sie Brain, im Web zu suchen — es nutzt SerpAPI für schnelle Google-Ergebnisse.',
            ],
        ],
        'search_types_title' => 'Unterstützte Suchtypen',
        'search_types' => [
            'general' => 'Allgemeine Websuche',
            'news' => 'Nachrichtensuche',
            'images' => 'Bildersuche',
        ],
        'use_cases_title' => 'Anwendungsfälle',
        'use_cases' => [
            'competitors' => [
                'title' => 'Wettbewerbsbeobachtung',
                'description' => 'Verfolgen Sie Wettbewerberaktivitäten und Online-Präsenz in Echtzeit.',
            ],
            'trends' => [
                'title' => 'Trenderkennung',
                'description' => 'Finden Sie Trendthemen und Nachrichten für zeitgemäße Marketingkampagnen.',
            ],
            'crm' => [
                'title' => 'Lead-Recherche',
                'description' => 'Recherchieren Sie Leads und Unternehmen schnell vor der Kontaktaufnahme.',
            ],
        ],
        'go_to_settings' => 'API-Schlüssel konfigurieren',
        'requirements_title' => 'Anforderungen',
        'requirements' => [
            'account' => 'SerpAPI Konto',
            'api_key' => 'SerpAPI API-Schlüssel',
            'brain' => 'NetSendo Brain aktiviert',
        ],
        'resources_title' => 'Ressourcen',
        'docs_link' => 'SerpAPI Dokumentation',
        'help_title' => 'Brauchen Sie Hilfe?',
        'help_desc' => 'Überprüfen Sie unsere Dokumentation für detaillierte Einrichtungsanweisungen.',
        'documentation_button' => 'Dokumentation',
    ],
];
