<?php

return [
    // Page titles
    'title' => 'Deliverability Shield',
    'subtitle' => 'Stellen Sie sicher, dass Ihre E-Mails im Posteingang landen, nicht im Spam',

    // Navigation
    'add_domain' => 'Domain hinzufügen',
    'verified' => 'Verifiziert',
    'pending_verification' => 'Verifizierung ausstehend',
    'never_checked' => 'Nie überprüft',
    'last_check' => 'Letzte Prüfung',
    'refresh' => 'Aktualisieren',

    // Stats
    'stats' => [
        'domains' => 'Domains',
        'verified' => 'Verifiziert',
        'critical' => 'Kritische Probleme',
        'avg_score' => 'Durchschn. Score',
    ],

    // Domains
    'domains' => [
        'title' => 'Ihre Domains',
        'empty' => [
            'title' => 'Noch keine Domains hinzugefügt',
            'description' => 'Fügen Sie Ihre erste Domain hinzu, um die Zustellbarkeit zu überwachen',
        ],
    ],

    // DMARC Wiz
    'dmarc_wiz' => [
        'title' => 'DMARC Wiz',
        'subtitle' => 'Fügen Sie Ihre Domain in wenigen Sekunden hinzu',
        'step_domain' => 'Domain',
        'step_verify' => 'Verifizieren',
        'enter_domain_title' => 'Domain eingeben',
        'enter_domain_description' => 'Dies ist die Domain, von der Sie E-Mails senden',
        'add_record_title' => 'DNS-Eintrag hinzufügen',
        'add_record_description' => 'Fügen Sie diesen CNAME-Eintrag zu den DNS-Einstellungen Ihrer Domain hinzu',
        'dns_propagation_info' => 'DNS-Änderungen können bis zu 48 Stunden dauern. Sie können jederzeit verifizieren.',
        'add_and_verify' => 'Hinzufügen & Verifizierung prüfen',
        'add_domain_btn' => 'Domain hinzufügen',
    ],

    // Domain fields
    'domain_name' => 'Domainname',
    'record_type' => 'Eintragstyp',
    'host' => 'Host',
    'target' => 'Zielwert',
    'type' => 'Typ',

    // Status
    'status_overview' => 'Statusübersicht',
    'verification_required' => 'Verifizierung erforderlich',
    'verification_description' => 'Fügen Sie den folgenden CNAME-Eintrag zu Ihren DNS-Einstellungen hinzu, um den Domainbesitz zu verifizieren.',
    'add_this_record' => 'Diesen DNS-Eintrag hinzufügen',
    'verify_now' => 'Jetzt verifizieren',

    // Alerts
    'alerts' => [
        'title' => 'E-Mail-Benachrichtigungen',
        'description' => 'Erhalten Sie Benachrichtigungen bei Problemen mit der Zustellbarkeit Ihrer Domain',
    ],

    // Test email
    'test_email' => 'E-Mail testen',
    'test_email_description' => 'Führen Sie eine Simulation durch, um die Zustellbarkeit vor dem Senden zu prüfen',

    // Simulations
    'simulations' => [
        'recent' => 'Letzte Simulationen',
        'empty' => 'Noch keine Simulationen. Führen Sie Ihren ersten InboxPassport AI Test durch.',
        'history' => 'Simulationsverlauf',
        'no_history' => 'Kein Simulationsverlauf',
        'no_history_desc' => 'Führen Sie Ihre erste InboxPassport AI Simulation durch, um Ergebnisse zu sehen.',
    ],

    // InboxPassport
    'inbox_passport' => [
        'title' => 'InboxPassport AI',
        'subtitle' => 'Sehen Sie vorher, wo Ihre E-Mail landen wird',
        'how_it_works' => 'So funktioniert es',
        'step1_title' => 'Domain analysieren',
        'step1_desc' => 'Wir prüfen Ihre SPF, DKIM und DMARC Konfiguration',
        'step2_title' => 'Inhalt scannen',
        'step2_desc' => 'KI erkennt Spam-Trigger, verdächtige Links und Formatierungsprobleme',
        'step3_title' => 'Zustellung vorhersagen',
        'step3_desc' => 'Erhalten Sie Vorhersagen für Gmail, Outlook und Yahoo',
        'what_we_check' => 'Was wir analysieren',
    ],

    // Simulation form
    'select_domain' => 'Domain auswählen',
    'no_verified_domains' => 'Keine verifizierten Domains. Fügen Sie zuerst eine Domain hinzu.',
    'email_subject' => 'E-Mail-Betreff',
    'subject_placeholder' => 'Geben Sie den E-Mail-Betreff ein...',
    'email_content' => 'E-Mail-Inhalt (HTML)',
    'content_placeholder' => 'Fügen Sie hier Ihren E-Mail HTML-Inhalt ein...',
    'analyzing' => 'Analysiere...',
    'run_simulation' => 'InboxPassport AI starten',

    // Analysis elements
    'spam_words' => 'Spam-Wörter',
    'subject_analysis' => 'Betreffanalyse',
    'link_check' => 'Link-Prüfung',
    'html_structure' => 'HTML-Struktur',
    'formatting' => 'Formatierung',

    // Results
    'simulation_result' => 'Simulationsergebnis',
    'predicted_folder' => 'Vorhergesagter Ordner',
    'provider_predictions' => 'Anbieter-Vorhersagen',
    'confidence' => 'Sicherheit',
    'issues_found' => 'Gefundene Probleme',
    'recommendations' => 'Empfehlungen',
    'run_new_simulation' => 'Neue Simulation starten',
    'view_history' => 'Verlauf anzeigen',
    'new_simulation' => 'Neue Simulation',

    // Scores
    'score' => [
        'excellent' => 'Ausgezeichnet',
        'good' => 'Gut',
        'fair' => 'Befriedigend',
        'poor' => 'Schlecht',
    ],

    // Folders
    'folder' => [
        'inbox' => 'Posteingang',
        'promotions' => 'Werbung',
        'spam' => 'Spam',
    ],

    // Table headers
    'subject' => 'Betreff',
    'domain' => 'Domain',
    'score' => 'Score',
    'folder' => 'Ordner',

    // Actions
    'confirm_delete' => 'Möchten Sie diese Domain wirklich entfernen?',

    // Messages
    'messages' => [
        'domain_added' => 'Domain erfolgreich hinzugefügt. Fügen Sie den CNAME-Eintrag zur Verifizierung hinzu.',
        'cname_verified' => 'Domain erfolgreich verifiziert!',
        'cname_not_found' => 'CNAME-Eintrag nicht gefunden. Bitte überprüfen Sie Ihre DNS-Einstellungen.',
        'status_refreshed' => 'Status erfolgreich aktualisiert.',
        'domain_removed' => 'Domain erfolgreich entfernt.',
        'alerts_updated' => 'Benachrichtigungseinstellungen aktualisiert.',
        'simulation_complete' => 'Simulation abgeschlossen!',
    ],

    // Validation
    'validation' => [
        'domain_format' => 'Bitte geben Sie einen gültigen Domainnamen ein',
        'domain_exists' => 'Diese Domain wurde bereits hinzugefügt',
    ],

    // Localhost/Development Environment Warning
    'localhost_warning' => [
        'title' => 'Entwicklungsumgebung erkannt',
        'description' => 'Sie betreiben NetSendo auf localhost. DNS-Verifizierung erfordert eine öffentliche Domain. CNAME-Einträge, die auf localhost verweisen, können nicht verifiziert werden.',
    ],

    // Upsell for non-GOLD users
    'upsell' => [
        'title' => 'Deliverability Shield freischalten',
        'description' => 'Maximieren Sie Ihre E-Mail-Zustellbarkeit mit erweiterten Tools. Stellen Sie sicher, dass jede E-Mail im Posteingang landet, nicht im Spam.',
        'feature1' => 'DMARC Wiz - Einfache Domain-Einrichtung',
        'feature2' => 'InboxPassport AI - Vor dem Senden testen',
        'feature3' => 'DNS-Überwachung 24/7',
        'feature4' => 'Automatische Benachrichtigungen & Empfehlungen',
        'cta' => 'Auf GOLD upgraden',
    ],
];
