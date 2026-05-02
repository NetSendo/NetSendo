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
];
