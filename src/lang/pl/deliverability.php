<?php

return [
    // Page titles
    'title' => 'Deliverability Shield',
    'subtitle' => 'Zadbaj, by Twoje maile trafiały do skrzynki, nie do spamu',

    // Navigation
    'add_domain' => 'Dodaj domenę',
    'verified' => 'Zweryfikowana',
    'pending_verification' => 'Oczekuje na weryfikację',
    'never_checked' => 'Nigdy nie sprawdzano',
    'last_check' => 'Ostatnie sprawdzenie',
    'refresh' => 'Odśwież',

    // Stats
    'stats' => [
        'domains' => 'Domeny',
        'verified' => 'Zweryfikowane',
        'critical' => 'Krytyczne problemy',
        'avg_score' => 'Śr. wynik',
    ],

    // Domains
    'domains' => [
        'title' => 'Twoje domeny',
        'empty' => [
            'title' => 'Brak dodanych domen',
            'description' => 'Dodaj swoją pierwszą domenę, aby monitorować dostarczalność',
        ],
    ],

    // DMARC Wiz
    'dmarc_wiz' => [
        'title' => 'DMARC Wiz',
        'subtitle' => 'Dodaj domenę w jednym kroku',
        'step_domain' => 'Domena',
        'step_verify' => 'Weryfikacja',
        'enter_domain_title' => 'Wprowadź domenę',
        'enter_domain_description' => 'To domena, z której wysyłasz e-maile',
        'add_record_title' => 'Dodaj rekord DNS',
        'add_record_description' => 'Dodaj ten rekord CNAME do ustawień DNS Twojej domeny',
        'dns_propagation_info' => 'Propagacja zmian DNS może potrwać do 48 godzin. Możesz weryfikować w dowolnym momencie.',
        'add_and_verify' => 'Dodaj i sprawdź weryfikację',
    ],

    // Domain fields
    'domain_name' => 'Nazwa domeny',
    'record_type' => 'Typ rekordu',
    'host' => 'Host',
    'target' => 'Wartość docelowa',
    'type' => 'Typ',

    // Status
    'status_overview' => 'Przegląd statusu',
    'verification_required' => 'Wymagana weryfikacja',
    'verification_description' => 'Dodaj poniższy rekord CNAME do ustawień DNS, aby zweryfikować własność domeny.',
    'add_this_record' => 'Dodaj ten rekord DNS',
    'verify_now' => 'Zweryfikuj teraz',

    // Alerts
    'alerts' => [
        'title' => 'Powiadomienia e-mail',
        'description' => 'Otrzymuj powiadomienia o problemach z dostarczalnością Twojej domeny',
    ],

    // Test email
    'test_email' => 'Przetestuj swój e-mail',
    'test_email_description' => 'Uruchom symulację, aby sprawdzić dostarczalność przed wysłaniem',

    // Simulations
    'simulations' => [
        'recent' => 'Ostatnie symulacje',
        'empty' => 'Brak symulacji. Uruchom pierwszy test InboxPassport AI.',
        'history' => 'Historia symulacji',
        'no_history' => 'Brak historii symulacji',
        'no_history_desc' => 'Uruchom pierwszą symulację InboxPassport AI, aby zobaczyć wyniki.',
    ],

    // InboxPassport
    'inbox_passport' => [
        'title' => 'InboxPassport AI',
        'subtitle' => 'Sprawdź, gdzie trafi Twój e-mail, zanim go wyślesz',
        'how_it_works' => 'Jak to działa',
        'step1_title' => 'Analiza domeny',
        'step1_desc' => 'Sprawdzamy konfigurację SPF, DKIM i DMARC',
        'step2_title' => 'Skan treści',
        'step2_desc' => 'AI wykrywa słowa spam, podejrzane linki i problemy z formatowaniem',
        'step3_title' => 'Predykcja doręczenia',
        'step3_desc' => 'Otrzymujesz prognozę dla Gmail, Outlook i Yahoo',
        'what_we_check' => 'Co analizujemy',
    ],

    // Simulation form
    'select_domain' => 'Wybierz domenę',
    'no_verified_domains' => 'Brak zweryfikowanych domen. Najpierw dodaj domenę.',
    'email_subject' => 'Temat e-maila',
    'subject_placeholder' => 'Wpisz temat e-maila...',
    'email_content' => 'Treść e-maila (HTML)',
    'content_placeholder' => 'Wklej tutaj kod HTML e-maila...',
    'analyzing' => 'Analizowanie...',
    'run_simulation' => 'Uruchom InboxPassport AI',

    // Analysis elements
    'spam_words' => 'Słowa spam',
    'subject_analysis' => 'Analiza tematu',
    'link_check' => 'Weryfikacja linków',
    'html_structure' => 'Struktura HTML',
    'formatting' => 'Formatowanie',

    // Results
    'simulation_result' => 'Wynik symulacji',
    'predicted_folder' => 'Przewidywany folder',
    'provider_predictions' => 'Predykcje dla dostawców',
    'confidence' => 'pewność',
    'issues_found' => 'Znalezione problemy',
    'recommendations' => 'Rekomendacje',
    'run_new_simulation' => 'Uruchom nową symulację',
    'view_history' => 'Zobacz historię',
    'new_simulation' => 'Nowa symulacja',

    // Scores
    'score' => [
        'excellent' => 'Doskonały',
        'good' => 'Dobry',
        'fair' => 'Dostateczny',
        'poor' => 'Słaby',
    ],

    // Folders
    'folder' => [
        'inbox' => 'Główna skrzynka',
        'promotions' => 'Oferty',
        'spam' => 'Spam',
    ],

    // Table headers
    'subject' => 'Temat',
    'domain' => 'Domena',
    'score' => 'Wynik',
    'folder' => 'Folder',

    // Actions
    'confirm_delete' => 'Czy na pewno chcesz usunąć tę domenę?',

    // Messages
    'messages' => [
        'domain_added' => 'Domena dodana pomyślnie. Dodaj rekord CNAME, aby zweryfikować.',
        'cname_verified' => 'Domena zweryfikowana pomyślnie!',
        'cname_not_found' => 'Rekord CNAME nie został znaleziony. Sprawdź ustawienia DNS.',
        'status_refreshed' => 'Status odświeżony pomyślnie.',
        'domain_removed' => 'Domena usunięta pomyślnie.',
        'alerts_updated' => 'Ustawienia alertów zaktualizowane.',
        'simulation_complete' => 'Symulacja zakończona!',
    ],

    // Validation
    'validation' => [
        'domain_format' => 'Wprowadź poprawną nazwę domeny',
        'domain_exists' => 'Ta domena jest już dodana',
    ],

    // Upsell for non-GOLD users
    'upsell' => [
        'title' => 'Odblokuj Deliverability Shield',
        'description' => 'Maksymalizuj dostarczalność e-maili dzięki zaawansowanym narzędziom. Upewnij się, że każdy e-mail trafia do skrzynki, nie do spamu.',
        'feature1' => 'DMARC Wiz - Łatwa konfiguracja domeny',
        'feature2' => 'InboxPassport AI - Test przed wysyłką',
        'feature3' => 'Monitoring DNS 24/7',
        'feature4' => 'Automatyczne alerty i rekomendacje',
        'cta' => 'Uaktualnij do GOLD',
    ],
];
