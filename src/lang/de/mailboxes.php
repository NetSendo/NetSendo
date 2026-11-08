<?php

return [
    'title' => 'Postfächer',
    'created' => 'Das Postfach wurde erstellt.',
    'updated' => 'Das Postfach wurde aktualisiert.',
    'deleted' => 'Das Postfach wurde gelöscht.',
    'set_default' => 'Postfach als Standard festgelegt.',

    'types' => [
        'broadcast' => 'Einmalig (Broadcast)',
        'autoresponder' => 'Autoresponder (Warteschlange)',
        'system' => 'System',
    ],

    'bounce' => [
        'section_title' => 'Bounce-Postfach Überwachung',
        'section_desc' => 'Überwachen Sie ein dediziertes IMAP-Postfach für Bounce-E-Mails und markieren Sie Abonnenten automatisch als Bounced.',
        'enabled' => 'Bounce-Überwachung aktivieren',
        'imap_host' => 'IMAP Host',
        'imap_port' => 'IMAP Port',
        'imap_encryption' => 'Verschlüsselung',
        'imap_username' => 'IMAP Benutzername',
        'imap_password' => 'IMAP Passwort',
        'imap_folder' => 'IMAP Ordner',
        'test_connection' => 'IMAP-Verbindung testen',
        'test_success' => 'Verbindung zum Bounce-Postfach erfolgreich',
        'test_failed' => 'Verbindung zum Bounce-Postfach fehlgeschlagen',
        'last_scanned' => 'Zuletzt gescannt',
        'last_scan_count' => 'Bounces im letzten Scan gefunden',
        'never_scanned' => 'Noch nie gescannt',
        'processed' => ':count Bounce(s) verarbeitet',
    ],

    'bounce_webhooks' => [
        'token_generated' => 'Webhook-Token erstellt. Hinterlegen Sie es bei Ihrem Anbieter – ein bisheriges Token ist nicht mehr gültig.',
        'invalid_sendgrid_key' => 'Dies ist kein gültiger SendGrid-Verifizierungsschlüssel. Kopieren Sie den öffentlichen Schlüssel, den SendGrid nach dem Aktivieren von Signed Event Webhook anzeigt.',
    ],

    'replies' => [
        'test_success' => 'Verbindung zum Antwort-Postfach erfolgreich',
        'test_failed' => 'Verbindung zum Antwort-Postfach fehlgeschlagen',
        'not_configured' => 'Die Antworterkennung ist nicht eingerichtet. Speichern Sie die Postfach-Einstellungen und versuchen Sie es erneut.',
    ],

    'custom_headers' => [
        'section_title' => 'Benutzerdefinierte SMTP-Header',
        'section_desc' => 'Fügen Sie benutzerdefinierte Header zu allen E-Mails hinzu, die von diesem Sendeserver gesendet werden (z.B. Feedback-ID für Google Postmaster Tools).',
        'key' => 'Header-Name',
        'value' => 'Header-Wert',
        'add' => 'Header hinzufügen',
        'remove' => 'Entfernen',
        'key_placeholder' => 'z.B. Feedback-ID',
        'value_placeholder' => 'z.B. campaign1:user123:netsendo',
    ],

    // NetSendo MTA (NMI) mailboxes
    'nmi' => [
        'disabled' => 'Der Versandserver ist ausgeschaltet. Ein Admin schaltet ihn in der Mail-Infrastruktur ein (Schritt-für-Schritt-Anleitung).',
        'new_mailboxes_disabled' => 'Neue NetSendo-MTA-Postfächer sind in dieser Installation ausgeschaltet (NMI_ALLOW_NEW in .env).',
        'domain_required' => 'Wählen Sie eine Ihrer Domains für NetSendo MTA.',
        'ip_required' => 'Wählen Sie eine Ihrer dedizierten IPs für NetSendo MTA.',
        'domain_not_verified' => 'Die Domain :domain ist noch nicht verifiziert. Fügen Sie zuerst ihren CNAME-Eintrag im Deliverability Shield hinzu.',
        'domain_not_enabled' => 'NetSendo MTA ist für :domain nicht eingeschaltet. Schalten Sie es auf der Domain-Seite im Deliverability Shield ein.',
        'ip_not_assigned' => 'Die IP :ip ist :domain nicht zugewiesen. Weisen Sie sie auf der IP-Seite in der Mail-Infrastruktur zu.',
        'ip_no_dkim' => 'Die IP :ip hat noch keine DKIM-Schlüssel. Generieren Sie sie auf der IP-Seite in der Mail-Infrastruktur.',
        'ip_inactive' => 'Die IP :ip ist ausgeschaltet.',
        'from_email_domain' => 'Die Absenderadresse muss zu :domain oder einer ihrer Subdomains gehören, damit sie zur DKIM-Signatur passt.',
        'no_domain' => 'Das Postfach ":mailbox" hat keine NetSendo-MTA-Domain. Bearbeiten Sie es und wählen Sie eine Domain und eine IP.',
        'test' => [
            'not_verified' => 'Die Domain :domain ist nicht verifiziert. Fügen Sie zuerst ihren CNAME-Eintrag im Deliverability Shield hinzu.',
            'not_enabled' => 'NetSendo MTA ist für :domain ausgeschaltet.',
            'no_credentials' => 'Die Domain :domain hat keinen NetSendo-MTA-Login. Schalten Sie NetSendo MTA auf der Domain-Seite aus und wieder ein.',
            'warming_limit' => 'Das tägliche Warming-Limit dieser IP ist erreicht. Versuchen Sie es morgen erneut.',
            'success' => 'Die Verbindung funktioniert: Eine Test-E-Mail wurde über NetSendo MTA gesendet.',
            'failed' => 'Verbindung fehlgeschlagen: :error',
        ],
    ],
];
