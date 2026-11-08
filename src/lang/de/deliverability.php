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

    // Domain issues
    'domain' => [
        'spf_warning' => 'SPF-Eintrag hat einen Warnstatus - kann die Zustellbarkeit beeinflussen',
        'dmarc_policy_none' => 'DMARC-Richtlinie ist auf "none" gesetzt - E-Mails können im Spam landen',
        'not_verified' => 'Die Domain ist noch nicht verifiziert (CNAME-Eintrag fehlt)',
        'spf_critical' => 'Der SPF-Eintrag ist fehlerhaft',
        'dkim_critical' => 'Der DKIM-Eintrag ist ungültig',
        'dkim_warning' => 'Keine DKIM-Signatur gefunden, oder sie muss geprüft werden',
        'dmarc_critical' => 'Kein DMARC-Eintrag gefunden',
        'dmarc_warning' => 'Der DMARC-Eintrag muss geprüft werden',
    ],

    // DNS Issues (detailed)
    'issues' => [
        'spf_missing' => 'Kein SPF-Eintrag für diese Domain gefunden',
        'spf_no_include' => 'SPF-Eintrag enthält nicht das erforderliche Include',
        'spf_no_provider_include' => 'SPF-Eintrag enthält kein :provider Include (:required)',
        'spf_permissive' => 'SPF-Eintrag ist zu permissiv (+all oder ?all)',
        'dkim_missing' => 'Kein DKIM-Eintrag gefunden (geprüfte Selektoren: :selectors_checked)',
        'dkim_invalid' => 'DKIM-Eintrag ist ungültig (fehlender öffentlicher Schlüssel)',
        'dmarc_missing' => 'Kein DMARC-Eintrag für diese Domain gefunden',
        'dmarc_none' => 'DMARC-Richtlinie ist auf "none" gesetzt',
        'dkim_selector_needed' => ':provider signiert mit eigenen Selektoren, die sich nicht erraten lassen. Tragen Sie einen davon unter DKIM-Selektor ein (der Teil vor ._domainkey in den CNAME-Einträgen Ihres Anbieters).',
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
        'risky' => 'Riskant',
    ],

    // Folders
    'folder' => [
        'inbox' => 'Posteingang',
        'promotions' => 'Werbung',
        'spam' => 'Spam',
        'unknown' => 'Unbekannt',
    ],

    // Table headers (a group of their own: plain strings named 'domain', 'score' and 'folder'
    // here replaced the groups of the same name above, so their keys were never found)
    'table' => [
        'subject' => 'Betreff',
        'domain' => 'Domain',
        'score' => 'Score',
        'folder' => 'Ordner',
    ],

    'status' => [
        'pending' => 'Ausstehend',
        'safe' => 'Geschützt',
        'warning' => 'Handlungsbedarf',
        'critical' => 'Kritisch',
    ],

    'cname' => [
        'verified' => 'Domain verifiziert',
        'pending' => 'Warten auf den CNAME-Eintrag',
    ],

    'spf' => [
        'pending' => 'Noch nicht geprüft',
        'valid' => 'SPF-Eintrag ist gültig',
        'warning' => 'SPF-Eintrag prüfen',
        'critical' => 'SPF-Eintrag ist fehlerhaft',
    ],

    'dkim' => [
        'pending' => 'Noch keine DKIM-Signatur gefunden',
        'valid' => 'DKIM-Eintrag gefunden',
        'warning' => 'DKIM-Eintrag prüfen',
        'critical' => 'DKIM-Eintrag ist ungültig',
    ],

    'dmarc' => [
        'pending' => 'Noch nicht geprüft',
        'valid' => 'DMARC-Richtlinie ist veröffentlicht',
        'warning' => 'DMARC prüfen',
        'critical' => 'Kein DMARC-Eintrag',
    ],

    'dmarc_policy' => [
        'none' => 'Nur Überwachung (p=none)',
        'quarantine' => 'Quarantäne (p=quarantine)',
        'reject' => 'Abweisen (p=reject)',
    ],

    'warnings' => [
        'spf_softfail' => 'Der SPF-Eintrag endet mit ~all (Soft Fail).',
        'dmarc_quarantine' => 'Die DMARC-Richtlinie ist quarantine; vollen Schutz bietet reject.',
        'dmarc_no_reporting' => 'Der DMARC-Eintrag hat keine rua-Adresse, daher erhalten Sie keine Berichte.',
    ],

    'actions' => [
        'go_to_domain_settings' => 'Domain-Einstellungen öffnen',
    ],

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
        'gmail_managed_dns' => 'Gmail verwaltet SPF/DKIM automatisch für Ihr Konto. Keine zusätzliche DNS-Konfiguration erforderlich.',
        'domain_not_configured' => 'Domain :domain ist nicht im DMARC Wiz konfiguriert. Fügen Sie sie für eine vollständige Zustellbarkeitsanalyse hinzu.',
        'no_domain_warning' => 'Keine Domain konfiguriert. Analyse basiert nur auf dem Nachrichteninhalt.',
        'dkim_selector_saved' => 'DKIM-Selektor gespeichert. Die DNS-Einträge wurden erneut geprüft.',
        'copied_to_clipboard' => 'In die Zwischenablage kopiert!',
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

    // HTML Analysis Issues
    'html' => [
        'ratio_low' => 'Niedriges Text-zu-HTML-Verhältnis - Ihre E-Mail enthält zu viel HTML-Code',
        'hidden_text' => 'Versteckter Text erkannt (display:none) - dies ist ein Spam-Indikator',
        'tiny_font' => 'Sehr kleine Schriftgröße erkannt - dies ist ein Spam-Indikator',
        'image_heavy' => 'Bildlastige E-Mail mit wenig Text - fügen Sie mehr Textinhalt hinzu',
    ],

    // Subject Analysis Issues
    'subject' => [
        'too_long' => 'Betreffzeile ist zu lang (über 60 Zeichen)',
        'too_short' => 'Betreffzeile ist zu kurz (unter 5 Zeichen)',
        'all_caps' => 'Betreff enthält zu viele Großbuchstaben',
        'exclamations' => 'Betreff enthält übermäßig viele Ausrufezeichen',
        'questions' => 'Betreff enthält zu viele Fragezeichen',
        'fake_reply' => 'Betreff beginnt mit RE: oder FW: was wie eine gefälschte Antwort aussieht',
    ],

    // Link Issues
    'links' => [
        'shortener' => 'URL-Verkürzung erkannt - verwenden Sie vollständige URLs',
        'suspicious_tld' => 'Verdächtige Domain-Endung erkannt',
        'ip_address' => 'IP-Adresse in URL erkannt - verwenden Sie richtige Domainnamen',
        'too_many' => 'Zu viele Links in der E-Mail (über 20)',
    ],

    // Formatting Issues
    'formatting' => [
        'caps' => 'Inhalt enthält zu viele Großbuchstaben',
        'symbols' => 'Inhalt enthält übermäßig viele Sonderzeichen',
    ],

    // Content Issues
    'content' => [
        'spam_word' => 'Spam-Auslösewort erkannt: ":word"',
    ],

    // Spam Words
    'spam' => [
        'word_detected' => 'Spam-Auslösewort erkannt',
    ],

    // Recommendations
    'recommendations' => [
        'fix_domain' => 'Beheben Sie die DNS-Konfigurationsprobleme Ihrer Domain',
        'upgrade_dmarc' => 'Aktualisieren Sie Ihre DMARC-Richtlinie von "none" auf "quarantine" oder "reject"',
        'remove_spam_words' => 'Entfernen oder ersetzen Sie Spam-Auslösewörter in Ihrem Inhalt',
        'improve_subject' => 'Verbessern Sie Ihre Betreffzeile - vermeiden Sie Großbuchstaben und übermäßige Interpunktion',
        'fix_html' => 'Beheben Sie HTML-Strukturprobleme - verbessern Sie das Text-zu-HTML-Verhältnis',
        'fix_links' => 'Beheben Sie Link-Probleme - vermeiden Sie URL-Verkürzungen und verdächtige Domains',
        'looks_good' => 'Ihre E-Mail sieht gut aus! Keine größeren Probleme erkannt',
        'add_domain' => 'Fügen Sie eine Domain im DMARC Wiz hinzu und verifizieren Sie sie für eine vollständige Zustellbarkeitsanalyse',
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

    // DMARC Generator (One-Click Fix)
    'dmarc_generator' => [
        'title' => 'DMARC-Generator',
        'subtitle' => 'Generieren Sie den optimalen DMARC-Eintrag mit einem Klick',
        'initial_explanation' => 'Beginnen Sie hier: p=none sammelt nur Berichte und blockiert nichts. Lesen Sie die Berichte ein bis zwei Wochen lang, um sicherzugehen, dass jeder Dienst, der für Ihre Domain sendet, SPF oder DKIM besteht.',
        'recommended_explanation' => 'Voller Schutz: E-Mails, die weder SPF noch DKIM bestehen, werden abgewiesen. Verwenden Sie dies nach einigen problemlosen Wochen mit quarantine.',
        'minimal_explanation' => 'Der kürzeste gültige DMARC-Eintrag: p=none ohne Berichte.',
        'upgrade_notice' => 'Ändern Sie nach ein bis zwei Wochen Berichten ohne unbekannte Absender p=none in p=quarantine und später in p=reject.',
        'copy_record' => 'Eintrag kopieren',
        'current_policy' => 'Aktuelle Richtlinie',
        'recommended_policy' => 'Empfohlene Richtlinie',
        'report_email' => 'Bericht-E-Mail',
        'report_email_hint' => 'Sie erhalten DMARC-Berichte an diese Adresse',
        'quarantine_explanation' => 'E-Mails, die weder SPF noch DKIM bestehen, landen im Spam. Wechseln Sie hierher, wenn die Berichte nur Ihre eigenen Dienste zeigen.',
    ],

    // SPF Generator (One-Click Fix)
    'spf_generator' => [
        'title' => 'SPF-Generator',
        'subtitle' => 'Generieren Sie einen optimierten SPF-Eintrag',
        'optimal_explanation' => 'Ihr aktueller Eintrag, ergänzt um die includes, die Ihre Versanddienste brauchen. Alles, was schon darin steht, bleibt erhalten, damit andere Dienste, die für die Domain senden, weiter funktionieren. Endet mit -all (Hard Fail).',
        'softfail_explanation' => 'SPF-Eintrag mit weichem Fail (~all). Weniger restriktiv, kann aber die Zustellbarkeit beeinflussen.',
        'lookup_warning' => 'Ihr aktueller SPF-Eintrag überschreitet oder nähert sich dem Limit von 10 DNS-Lookups. Wir empfehlen eine Vereinfachung.',
        'lookup_count' => 'DNS-Lookup-Anzahl',
        'max_lookups' => 'Maximales Limit',
        'copy_record' => 'Eintrag kopieren',
        'current_record' => 'Aktueller Eintrag',
        'optimal_record' => 'Optimierter Eintrag',
        'provider_detected' => 'Erkannter Anbieter',
        'needs_provider_include' => 'NetSendo weiß nicht, welches SPF-include Ihr SMTP-Anbieter braucht. Entnehmen Sie es der Anleitung des Anbieters (z. B. include:spf.example.net) und ergänzen Sie es im SPF-Eintrag.',
    ],

    // DNS Generator Common
    'dns_generator' => [
        'instructions_title' => 'So fügen Sie einen DNS-Eintrag hinzu',
        'step1' => '1. Melden Sie sich bei Ihrem Domain-DNS-Panel an (z.B. IONOS, Strato, Hetzner)',
        'step2' => '2. Fügen Sie einen neuen TXT-Eintrag mit den obigen Daten hinzu',
        'step3' => '3. Warten Sie auf die DNS-Propagierung (bis zu 48h) und klicken Sie auf "Verifizieren"',
        'copy_success' => 'In Zwischenablage kopiert!',
        'copy_failed' => 'Kopieren fehlgeschlagen. Bitte manuell kopieren.',
        'show_generator' => 'Generator anzeigen',
        'hide_generator' => 'Generator ausblenden',
        'one_click_fix' => 'Ein-Klick-Reparatur',
    ],
];

