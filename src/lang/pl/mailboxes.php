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
];
