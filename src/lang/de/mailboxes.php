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

    // NetSendo MTA (NMI) mailboxes: a verified domain and, optionally, a dedicated IP
    'nmi' => [
        'errors' => [
            'domain_required' => 'Wählen Sie eine Ihrer verifizierten Domains für das NetSendo-MTA-Postfach.',
            'domain_not_verified' => 'Die Domain :domain ist noch nicht verifiziert. Verifizieren Sie sie zuerst unter Zustellbarkeit.',
            'domain_missing' => 'Dieses NetSendo-MTA-Postfach hat keine Versanddomain mehr (die Domain wurde entfernt). Bearbeiten Sie das Postfach und wählen Sie eine verifizierte Domain.',
            'from_domain_mismatch' => 'Die Absenderadresse muss in der gewählten Domain liegen (@:domain).',
            'ip_invalid' => 'Wählen Sie eine aktive dedizierte IP aus einem Ihrer IP-Pools oder keine.',
            'ip_other_domain' => 'Die IP-Adresse :ip ist bereits einer anderen Domain zugewiesen.',
            'mta_login_file' => 'NetSendo konnte die MTA-Login-Datei (:file) nicht schreiben. Prüfen Sie, ob das Verzeichnis existiert und beschreibbar ist, und speichern Sie erneut.',
            'mta_login_refused' => 'Der MTA kennt den Login dieser Domain nicht: Er muss die von NetSendo geschriebene Login-Datei lesen (Volume nmi-auth in docker-compose.yml).',
        ],
        // Added to the result of "Test connection" of an NMI mailbox
        'test' => [
            'ip_not_on_mta' => 'Hinweis: Der MTA besitzt :ip nicht, daher wurde diese Nachricht von der eigenen Adresse des MTA gesendet.',
            'unsigned' => 'Hinweis: Die Nachricht wurde nicht DKIM-signiert. Veröffentlichen Sie den DKIM-Eintrag der Domain und verifizieren Sie ihn im Postfach-Formular.',
        ],
    ],

    // Field names in validation messages of the mailbox form
    'attributes' => [
        'name' => 'Name des Postfachs',
        'provider' => 'Anbieter',
        'from_email' => 'Absenderadresse',
        'from_name' => 'Absendername',
        'reply_to' => 'Reply-To-Adresse',
        'allowed_types' => 'erlaubte Nachrichtentypen',
        'credentials' => 'Server-Zugangsdaten',
        'daily_limit' => 'tägliches Versandlimit',
        'time_restriction' => 'Mindestabstand',
        'custom_headers.*.key' => 'Header-Name',
        'custom_headers.*.value' => 'Header-Wert',
        'domain_configuration_id' => 'Versanddomain',
        'dedicated_ip_id' => 'dedizierte IP',
        'reply_imap_host' => 'IMAP-Host für Antworten',
    ],
];
