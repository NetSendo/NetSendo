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

    // NetSendo MTA (NMI) mailboxes
    'nmi' => [
        'disabled' => 'Serwer wysyłkowy jest wyłączony. Administrator włącza go w Infrastrukturze pocztowej (przewodnik krok po kroku).',
        'new_mailboxes_disabled' => 'Nowe skrzynki NetSendo MTA są wyłączone w tej instalacji (NMI_ALLOW_NEW w .env).',
        'domain_required' => 'Wybierz jedną ze swoich domen dla NetSendo MTA.',
        'ip_required' => 'Wybierz jeden ze swoich dedykowanych adresów IP dla NetSendo MTA.',
        'domain_not_verified' => 'Domena :domain nie jest jeszcze zweryfikowana. Najpierw dodaj jej rekord CNAME w Deliverability Shield.',
        'domain_not_enabled' => 'NetSendo MTA nie jest włączone dla domeny :domain. Włącz je na stronie domeny w Deliverability Shield.',
        'ip_not_assigned' => 'Adres IP :ip nie jest przypisany do domeny :domain. Przypisz go na stronie adresu IP w Infrastrukturze Pocztowej.',
        'ip_no_dkim' => 'Adres IP :ip nie ma jeszcze kluczy DKIM. Wygeneruj je na stronie adresu IP w Infrastrukturze Pocztowej.',
        'ip_inactive' => 'Adres IP :ip jest wyłączony.',
        'from_email_domain' => 'Adres nadawcy musi być w domenie :domain lub jednej z jej subdomen, aby pasował do podpisu DKIM.',
        'no_domain' => 'Skrzynka ":mailbox" nie ma domeny NetSendo MTA. Edytuj ją i wybierz domenę oraz adres IP.',
        'test' => [
            'not_verified' => 'Domena :domain nie jest zweryfikowana. Najpierw dodaj jej rekord CNAME w Deliverability Shield.',
            'not_enabled' => 'NetSendo MTA jest wyłączone dla domeny :domain.',
            'no_credentials' => 'Domena :domain nie ma loginu do NetSendo MTA. Wyłącz i ponownie włącz NetSendo MTA na stronie domeny.',
            'warming_limit' => 'Osiągnięto dzienny limit ogrzewania tego adresu IP. Spróbuj jutro.',
            'success' => 'Połączenie działa: wiadomość testowa wyszła przez NetSendo MTA.',
            'failed' => 'Połączenie nie powiodło się: :error',
        ],
    ],
];
