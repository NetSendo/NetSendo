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
        'subtitle' => 'Dodaj domenę w kilka sekund',
        'step_domain' => 'Domena',
        'step_verify' => 'Weryfikacja',
        'enter_domain_title' => 'Wprowadź domenę',
        'enter_domain_description' => 'To domena, z której wysyłasz e-maile',
        'add_record_title' => 'Dodaj rekord DNS',
        'add_record_description' => 'Dodaj ten rekord CNAME do ustawień DNS Twojej domeny',
        'dns_propagation_info' => 'Propagacja zmian DNS może potrwać do 48 godzin. Możesz weryfikować w dowolnym momencie.',
        'add_and_verify' => 'Dodaj i sprawdź weryfikację',
        'add_domain_btn' => 'Dodaj domenę',
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

    // Domain issues
    'domain' => [
        'spf_warning' => 'Rekord SPF ma status ostrzegawczy - może wpływać na dostarczalność',
        'dmarc_policy_none' => 'Polityka DMARC jest ustawiona na "none" - wiadomości mogą trafić do spamu',
    ],

    // DNS Issues (detailed)
    'issues' => [
        'spf_missing' => 'Brak rekordu SPF dla domeny',
        'spf_no_include' => 'Rekord SPF nie zawiera wymaganego include',
        'spf_no_provider_include' => 'Rekord SPF nie zawiera include dla :provider (:required)',
        'spf_permissive' => 'Rekord SPF jest zbyt permisywny (+all lub ?all)',
        'dkim_missing' => 'Brak rekordu DKIM (sprawdzono selektory: :selectors_checked)',
        'dkim_invalid' => 'Rekord DKIM jest nieprawidłowy (brak klucza publicznego)',
        'dmarc_missing' => 'Brak rekordu DMARC dla domeny',
        'dmarc_none' => 'Polityka DMARC ustawiona na "none"',
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
        'gmail_managed_dns' => 'Gmail automatycznie zarządza SPF/DKIM dla Twojego konta. Nie wymaga dodatkowej konfiguracji DNS.',
        'domain_not_configured' => 'Domena :domain nie jest skonfigurowana w DMARC Wiz. Dodaj ją, aby uzyskać pełną analizę dostarczalności.',
        'no_domain_warning' => 'Brak skonfigurowanej domeny. Analiza oparta tylko na treści wiadomości.',
    ],

    // Validation
    'validation' => [
        'domain_format' => 'Wprowadź poprawną nazwę domeny',
        'domain_exists' => 'Ta domena jest już dodana',
    ],

    // Localhost/Development Environment Warning
    'localhost_warning' => [
        'title' => 'Wykryto środowisko deweloperskie',
        'description' => 'NetSendo działa na localhost. Weryfikacja DNS wymaga publicznej domeny. Rekordy CNAME wskazujące na localhost nie mogą być zweryfikowane.',
    ],

    // HTML Analysis Issues
    'html' => [
        'ratio_low' => 'Niski stosunek tekstu do HTML - Twój e-mail zawiera zbyt dużo kodu HTML',
        'hidden_text' => 'Wykryto ukryty tekst (display:none) - to wskaźnik spamu',
        'tiny_font' => 'Wykryto bardzo małą czcionkę - to wskaźnik spamu',
        'image_heavy' => 'E-mail z dużą ilością obrazów i małą ilością tekstu - dodaj więcej tekstu',
    ],

    // Subject Analysis Issues
    'subject' => [
        'too_long' => 'Temat jest zbyt długi (ponad 60 znaków)',
        'too_short' => 'Temat jest zbyt krótki (poniżej 5 znaków)',
        'all_caps' => 'Temat zawiera zbyt dużo wielkich liter',
        'exclamations' => 'Temat zawiera zbyt dużo wykrzykników',
        'questions' => 'Temat zawiera zbyt dużo znaków zapytania',
        'fake_reply' => 'Temat zaczyna się od RE: lub FW: co wygląda jak fałszywa odpowiedź',
    ],

    // Link Issues
    'links' => [
        'shortener' => 'Wykryto skracacz URL - używaj pełnych adresów URL',
        'suspicious_tld' => 'Wykryto podejrzane rozszerzenie domeny',
        'ip_address' => 'Wykryto adres IP w URL - używaj prawidłowych nazw domen',
        'too_many' => 'Zbyt dużo linków w e-mailu (ponad 20)',
    ],

    // Formatting Issues
    'formatting' => [
        'caps' => 'Treść zawiera zbyt dużo wielkich liter',
        'symbols' => 'Treść zawiera nadmierną ilość symboli specjalnych',
    ],

    // Content Issues
    'content' => [
        'spam_word' => 'Wykryto słowo wyzwalające spam: ":word"',
    ],

    // Spam Words
    'spam' => [
        'word_detected' => 'Wykryto słowo wyzwalające spam',
    ],

    // Recommendations
    'recommendations' => [
        'fix_domain' => 'Napraw problemy z konfiguracją DNS domeny',
        'upgrade_dmarc' => 'Ulepsz politykę DMARC z "none" na "quarantine" lub "reject"',
        'remove_spam_words' => 'Usuń lub zamień słowa wyzwalające spam w treści',
        'improve_subject' => 'Popraw temat - unikaj wielkich liter i nadmiernej interpunkcji',
        'fix_html' => 'Napraw problemy struktury HTML - popraw stosunek tekstu do HTML',
        'fix_links' => 'Napraw problemy z linkami - unikaj skracaczy URL i podejrzanych domen',
        'looks_good' => 'Twój e-mail wygląda dobrze! Nie wykryto poważnych problemów',
        'add_domain' => 'Dodaj i zweryfikuj domenę w DMARC Wiz, aby uzyskać pełną analizę dostarczalności',
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

    // DMARC Generator (One-Click Fix)
    'dmarc_generator' => [
        'title' => 'Generator DMARC',
        'subtitle' => 'Wygeneruj optymalny rekord DMARC jednym kliknięciem',
        'initial_explanation' => 'Zacznij od polityki "quarantine", aby monitorować bez blokowania wiadomości. To bezpieczny start.',
        'recommended_explanation' => 'Pełna ochrona z polityką "reject". Użyj po 7-14 dniach monitorowania bez problemów.',
        'minimal_explanation' => 'Minimalna konfiguracja DMARC z polityką "quarantine" i podstawowym raportowaniem.',
        'upgrade_notice' => 'Po 7-14 dniach bez problemów możesz bezpiecznie przejść na politykę "reject" dla maksymalnej ochrony.',
        'copy_record' => 'Kopiuj rekord',
        'current_policy' => 'Obecna polityka',
        'recommended_policy' => 'Zalecana polityka',
        'report_email' => 'E-mail do raportów',
        'report_email_hint' => 'Na ten adres będziesz otrzymywać raporty DMARC',
    ],

    // SPF Generator (One-Click Fix)
    'spf_generator' => [
        'title' => 'Generator SPF',
        'subtitle' => 'Wygeneruj zoptymalizowany rekord SPF',
        'optimal_explanation' => 'Uproszczony rekord SPF z twardym odrzuceniem (-all). Zawiera tylko niezbędne include dla Twojego dostawcy.',
        'softfail_explanation' => 'Rekord SPF z miękkim odrzuceniem (~all). Mniej restrykcyjny, ale może wpływać na dostarczalność.',
        'lookup_warning' => 'Twój obecny rekord SPF przekracza lub zbliża się do limitu 10 wyszukiwań DNS. Zalecamy uproszczenie.',
        'lookup_count' => 'Liczba wyszukiwań DNS',
        'max_lookups' => 'Maksymalny limit',
        'copy_record' => 'Kopiuj rekord',
        'current_record' => 'Obecny rekord',
        'optimal_record' => 'Zoptymalizowany rekord',
        'provider_detected' => 'Wykryty dostawca',
    ],

    // DNS Generator Common
    'dns_generator' => [
        'instructions_title' => 'Jak dodać rekord DNS',
        'step1' => '1. Zaloguj się do panelu DNS Twojej domeny (np. OVH, Cloudflare, nazwa.pl)',
        'step2' => '2. Dodaj nowy rekord TXT z powyższymi danymi',
        'step3' => '3. Poczekaj na propagację DNS (do 48h) i kliknij "Weryfikuj"',
        'copy_success' => 'Skopiowano do schowka!',
        'copy_failed' => 'Błąd kopiowania. Skopiuj ręcznie.',
        'show_generator' => 'Pokaż generator',
        'hide_generator' => 'Ukryj generator',
        'one_click_fix' => 'Napraw jednym kliknięciem',
    ],
];

