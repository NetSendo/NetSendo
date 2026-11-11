<?php

return [
    'title' => 'Skrzynki pocztowe',
    'created' => 'Skrzynka pocztowa została utworzona.',
    'updated' => 'Skrzynka pocztowa została zaktualizowana.',
    'deleted' => 'Skrzynka pocztowa została usunięta.',
    'set_default' => 'Skrzynka ustawiona jako domyślna.',

    'types' => [
        'broadcast' => 'Jednorazowe (Broadcast)',
        'autoresponder' => 'Autoresponder (Kolejka)',
        'system' => 'Systemowe',
    ],

    'bounce' => [
        'section_title' => 'Monitoring skrzynki bounce',
        'section_desc' => 'Monitoruj dedykowaną skrzynkę IMAP pod kątem emaili zwrotnych (bounce) i automatycznie oznaczaj subskrybentów jako bounced.',
        'enabled' => 'Włącz monitoring bounce',
        'imap_host' => 'Host IMAP',
        'imap_port' => 'Port IMAP',
        'imap_encryption' => 'Szyfrowanie',
        'imap_username' => 'Użytkownik IMAP',
        'imap_password' => 'Hasło IMAP',
        'imap_folder' => 'Folder IMAP',
        'test_connection' => 'Testuj połączenie IMAP',
        'test_success' => 'Połączenie ze skrzynką bounce nawiązane poprawnie',
        'test_failed' => 'Nie udało się połączyć ze skrzynką bounce',
        'last_scanned' => 'Ostatnie skanowanie',
        'last_scan_count' => 'Bounce znalezione w ostatnim skanie',
        'never_scanned' => 'Nigdy nie skanowano',
        'processed' => 'Przetworzono :count bounce(ów)',
    ],

    'bounce_webhooks' => [
        'token_generated' => 'Wygenerowano token webhooka. Wklej go u dostawcy – poprzedni token, jeśli był, już nie działa.',
        'invalid_sendgrid_key' => 'To nie jest poprawny klucz weryfikacyjny SendGrid. Skopiuj klucz publiczny, który SendGrid pokazuje po włączeniu Signed Event Webhook.',
    ],

    'replies' => [
        'test_success' => 'Połączenie ze skrzynką odpowiedzi nawiązane poprawnie',
        'test_failed' => 'Nie udało się połączyć ze skrzynką odpowiedzi',
        'not_configured' => 'Odczyt odpowiedzi nie jest skonfigurowany. Zapisz ustawienia skrzynki i spróbuj ponownie.',
    ],

    'custom_headers' => [
        'section_title' => 'Niestandardowe nagłówki SMTP',
        'section_desc' => 'Dodaj niestandardowe nagłówki do wszystkich e-maili wysyłanych z tego serwera (np. Feedback-ID dla Google Postmaster Tools).',
        'key' => 'Nazwa nagłówka',
        'value' => 'Wartość nagłówka',
        'add' => 'Dodaj nagłówek',
        'remove' => 'Usuń',
        'key_placeholder' => 'np. Feedback-ID',
        'value_placeholder' => 'np. campaign1:user123:netsendo',
    ],

    // NetSendo MTA (NMI) mailboxes: a verified domain and, optionally, a dedicated IP
    'nmi' => [
        'errors' => [
            'domain_required' => 'Wybierz jedną ze swoich zweryfikowanych domen dla skrzynki NetSendo MTA.',
            'domain_not_verified' => 'Domena :domain nie jest jeszcze zweryfikowana. Najpierw zweryfikuj ją w module Dostarczalność.',
            'domain_missing' => 'Ta skrzynka NetSendo MTA nie ma już domeny wysyłkowej (domena została usunięta). Edytuj skrzynkę i wybierz zweryfikowaną domenę.',
            'from_domain_mismatch' => 'Adres nadawcy musi być w wybranej domenie (@:domain).',
            'ip_invalid' => 'Wybierz aktywny dedykowany adres IP z jednej ze swoich pul albo żaden.',
            'ip_other_domain' => 'Adres IP :ip jest już przypisany do innej domeny.',
            'mta_login_file' => 'NetSendo nie mogło zapisać pliku loginów MTA (:file). Sprawdź, czy katalog istnieje i jest zapisywalny, i zapisz ponownie.',
            'mta_login_refused' => 'MTA nie zna loginu tej domeny: musi czytać plik loginów zapisywany przez NetSendo (wolumen nmi-auth w docker-compose.yml).',
        ],
    ],

    // Field names in validation messages of the mailbox form
    'attributes' => [
        'name' => 'nazwa skrzynki',
        'provider' => 'dostawca',
        'from_email' => 'adres nadawcy',
        'from_name' => 'nazwa nadawcy',
        'reply_to' => 'adres Reply-To',
        'allowed_types' => 'dozwolone typy wiadomości',
        'credentials' => 'dane logowania do serwera',
        'daily_limit' => 'dzienny limit wysyłek',
        'time_restriction' => 'minimalny odstęp',
        'custom_headers.*.key' => 'nazwa nagłówka',
        'custom_headers.*.value' => 'wartość nagłówka',
        'domain_configuration_id' => 'domena wysyłkowa',
        'dedicated_ip_id' => 'dedykowany adres IP',
        'reply_imap_host' => 'host IMAP odpowiedzi',
    ],
];
