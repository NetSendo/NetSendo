<?php

return [
    'title' => 'Rynek',
    'subtitle' => 'Odkryj integracje i rozszerzenia, które wzmocnią Twój email marketing.',
    'active_integrations' => 'Aktywne Integracje',
    'active' => 'Aktywne',
    'coming_soon' => 'Wkrótce',
    'soon' => 'Wkrótce',
    'banner_title' => 'Więcej integracji wkrótce',
    'banner_desc' => 'Stale pracujemy nad dodawaniem nowych integracji, aby pomóc Ci rozwijać Twój biznes.',
    'features' => [
        'one_click' => 'Instalacja 1 kliknięciem',
        'auto_sync' => 'Automatyczna synchronizacja',
        'no_code' => 'Konfiguracja bez kodu',
    ],
    'categories' => [
        'ecommerce' => [
            'title' => 'E-commerce',
            'desc' => 'Połącz swój sklep',
        ],
        'crm' => [
            'title' => 'CRM',
            'desc' => 'Relacje z klientami',
        ],
        'forms' => [
            'title' => 'Formularze i Ankiety',
            'desc' => 'Generowanie leadów',
        ],
        'automation' => [
            'title' => 'Automatyzacja',
            'desc' => 'Połącz przepływy pracy',
        ],
        'payments' => [
            'title' => 'Płatności',
            'desc' => 'Przetwarzaj płatności',
        ],
        'analytics' => [
            'title' => 'Analityka',
            'desc' => 'Śledź wyniki',
        ],
        'ai' => [
            'title' => 'AI & Badania',
            'desc' => 'Narzędzia inteligencji',
        ],
    ],
    'request_title' => "Nie widzisz tego, czego potrzebujesz?",
    'request_desc' => "Daj nam znać, jaką integrację chciałbyś zobaczyć w następnej kolejności. Priorytetyzujemy naszą mapę drogową na podstawie opinii użytkowników.",
    'request_button' => 'Poproś o integrację',
    'request_modal_title' => 'Poproś o Integrację',
    'request_success_title' => 'Prośba otrzymana!',
    'request_success_desc' => "Dziękujemy za opinię. Dam znać, gdy ta integracja będzie dostępna.",
    'request_integration_name' => 'Nazwa Integracji',
    'request_integration_name_placeholder' => 'np. Shopify, Salesforce...',
    'request_description' => 'Opis (Opcjonalnie)',
    'request_description_placeholder' => 'Jak chciałbyś korzystać z tej integracji?',
    'request_priority' => 'Priorytet',
    'priority_low' => 'Niski',
    'priority_normal' => 'Normalny',
    'priority_high' => 'Wysoki',
    'request_submitted_as' => 'Zgłoszono jako',
    'request_submit' => 'Wyślij Prośbę',
    'request_error' => 'Nie udało się wysłać prośby. Spróbuj ponownie.',

    'wordpress' => [
        'title' => 'WordPress',
        'hero_title' => 'WordPress',
        'hero_subtitle' => 'Profesjonalne formularze zapisu i content gating dla blogerów i twórców treści na WordPress.',
        'hero_description' => 'Wtyczka NetSendo dla WordPress to kompletne rozwiązanie dla blogerów i twórców treści. Dodawaj profesjonalne formularze zapisu do newslettera, ograniczaj widoczność artykułów tylko dla subskrybentów i buduj swoją listę mailingową z poziomu WordPress.',
        'features_title' => 'Funkcje',
        'features' => [
            'forms' => [
                'title' => 'Formularze subskrypcji',
                'description' => 'Profesjonalne formularze zapisu z różnymi stylami: inline, minimal, card.',
            ],
            'gating' => [
                'title' => 'Content Gating',
                'description' => 'Ogranicz dostęp do treści tylko dla subskrybentów.',
            ],
            'blocks' => [
                'title' => 'Bloki Gutenberg',
                'description' => 'Dedykowane bloki do łatwej edycji treści.',
            ],
            'widget' => [
                'title' => 'Widget do sidebara',
                'description' => 'Gotowy widget formularza zapisu do umieszczenia w dowolnym sidebarze.',
            ],
            'gdpr' => [
                'title' => 'GDPR Ready',
                'description' => 'Wbudowany checkbox zgody RODO z konfigurowalnym tekstem.',
            ],
            'settings' => [
                'title' => 'Łatwa Konfiguracja',
                'description' => 'Prosty panel ustawień w administratorze WP.',
            ],
        ],
        'setup_title' => 'Konfiguracja',
        'setup_steps' => [
            'download' => [
                'title' => 'Pobierz Wtyczkę',
                'description' => 'Pobierz plik zip wtyczki na swój komputer.',
            ],
            'install' => [
                'title' => 'Zainstaluj',
                'description' => 'Prześlij i aktywuj wtyczkę w panelu WordPress.',
            ],
            'configure' => [
                'title' => 'Skonfiguruj',
                'description' => 'Wprowadź klucz API i URL w ustawieniach wtyczki.',
            ],
            'add_forms' => [
                'title' => 'Dodaj Formularze',
                'description' => 'Użyj shortcodów lub bloków, aby dodać formularze do postów.',
            ],
        ],
        'download_button' => 'Pobierz Wtyczkę',
        'shortcodes_title' => 'Shortcodes',
        'shortcodes' => [
            'form_basic' => 'Formularz podstawowy',
            'form_styled' => 'Formularz stylizowany',
            'gate_percentage' => 'Ukryj treść po X%',
            'gate_subscribers' => 'Ukryj dla subskrybentów',
        ],
        'api_config_title' => 'Konfiguracja API',
        'api_url_label' => 'URL API',
        'api_url_help' => 'Skopiuj ten URL do ustawień wtyczki.',
        'api_key_label' => 'Klucz API',
        'api_key_desc' => 'Potrzebujesz klucza API, aby połączyć wtyczkę.',
        'manage_api_keys' => 'Zarządzaj Kluczami API',
        'requirements_title' => 'Wymagania',
        'requirements' => [
            'wp' => 'WordPress 5.8 lub nowszy',
            'php' => 'PHP 7.4 lub nowszy',
            'account' => 'Aktywne konto NetSendo',
        ],
        'content_gate_types_title' => 'Typy Content Gating',
        'content_gate_types' => [
            'percentage_desc' => 'Ukryj treść po przeczytaniu określonego procentu.',
            'subscribers_only_desc' => 'Treść widoczna tylko dla aktywnych subskrybentów.',
            'logged_in_desc' => 'Treść widoczna tylko dla zalogowanych użytkowników.',
        ],
        'resources_title' => 'Zasoby',
        'docs_link' => 'Dokumentacja WordPress',
        'lists_link' => 'Zarządzaj Listami',
        'help_title' => 'Potrzebujesz pomocy?',
        'help_desc' => 'Sprawdź naszą dokumentację lub skontaktuj się z pomocą techniczną.',
        'documentation_button' => 'Dokumentacja',
    ],

    'woocommerce' => [
        'title' => 'WooCommerce',
        'hero_title' => 'Integracja WooCommerce',
        'hero_subtitle' => 'Połącz swój sklep i zwiększ sprzedaż.',
        'hero_description' => 'Bezproblemowo zintegruj swój sklep WooCommerce z NetSendo. Automatycznie synchronizuj klientów, odzyskuj porzucone koszyki i śledź przychody.',
        'features_title' => 'Funkcje',
        'features' => [
            'auto_subscribe' => [
                'title' => 'Automatyczna Subskrypcja',
                'description' => 'Automatycznie dodawaj klientów do list mailingowych podczas zakupu.',
            ],
            'cart_recovery' => [
                'title' => 'Odzyskiwanie Koszyków',
                'description' => 'Odzyskuj utraconą sprzedaż dzięki automatycznym e-mailom o porzuconych koszykach.',
            ],
            'product_settings' => [
                'title' => 'Synchronizacja Produktów',
                'description' => 'Mapuj produkty WooCommerce do tagów i list NetSendo.',
            ],
            'external_pages' => [
                'title' => 'Strony Zewnętrzne',
                'description' => 'Śledź odwiedziny i zdarzenia na stronach sklepu.',
            ],
        ],
        'setup_title' => 'Konfiguracja',
        'setup_steps' => [
            'download' => [
                'title' => 'Pobierz',
                'description' => 'Pobierz plik wtyczki.',
            ],
            'install' => [
                'title' => 'Zainstaluj',
                'description' => 'Prześlij do WordPress/WooCommerce.',
            ],
            'configure' => [
                'title' => 'Skonfiguruj',
                'description' => 'Połącz API.',
            ],
            'lists' => [
                'title' => 'Mapuj Listy',
                'description' => 'Wybierz listy dla klientów.',
            ],
        ],
        'download_button' => 'Pobierz Wtyczkę',
        'api_config_title' => 'Konfiguracja API',
        'api_url_label' => 'URL API',
        'api_url_help' => 'Endpoint dla webhooków.',
        'api_key_label' => 'Klucz API',
        'api_key_desc' => 'Wygeneruj klucz dla swojego sklepu.',
        'manage_api_keys' => 'Zarządzaj Kluczami API',
        'requirements_title' => 'Wymagania',
        'requirements' => [
            'wp' => 'WordPress 5.8+',
            'wc' => 'WooCommerce 6.0+',
            'php' => 'PHP 7.4+',
            'account' => 'Konto NetSendo',
        ],
        'docs_link' => 'Dokumentacja WooCommerce',
        'lists_link' => 'Zarządzaj Listami',
        'funnels_link' => 'Lejki Sprzedażowe',
        'help_title' => 'Pomoc',
        'help_desc' => 'Przeczytaj poradnik lub skontaktuj się z nami.',
        'documentation_button' => 'Dokumentacja',
    ],

    'shopify' => [
        'title' => 'Shopify',
        'hero_title' => 'Integracja Shopify',
        'hero_subtitle' => 'Połącz swój sklep Shopify i automatycznie synchronizuj klientów.',
        'hero_description' => 'Zintegruj swój sklep Shopify z NetSendo za pomocą webhooków. Automatycznie dodawaj klientów do list mailingowych, gdy składają zamówienia, tworzą konta lub finalizują zakupy.',
        'features_title' => 'Funkcje',
        'features' => [
            'auto_subscribe' => [
                'title' => 'Automatyczna Subskrypcja',
                'description' => 'Automatycznie dodawaj klientów do list przy zakupie.',
            ],
            'customer_sync' => [
                'title' => 'Synchronizacja Klientów',
                'description' => 'Synchronizuj nowe rejestracje bezpośrednio na listy.',
            ],
            'order_tracking' => [
                'title' => 'Śledzenie Zamówień',
                'description' => 'Przechowuj dane zamówień jako pola niestandardowe.',
            ],
            'real_time' => [
                'title' => 'Aktualizacje w Czasie Rzeczywistym',
                'description' => 'Natychmiastowe powiadomienia webhook.',
            ],
        ],
        'setup_title' => 'Konfiguracja',
        'setup_steps' => [
            'api_key' => [
                'title' => 'Wygeneruj Klucz API',
                'description' => 'Utwórz klucz API w ustawieniach NetSendo.',
            ],
            'shopify_admin' => [
                'title' => 'Otwórz Panel Shopify',
                'description' => 'Przejdź do Ustawienia > Powiadomienia > Webhooki.',
            ],
            'create_webhook' => [
                'title' => 'Utwórz Webhook',
                'description' => 'Dodaj URL webhooka i wybierz zdarzenia do śledzenia.',
            ],
            'test' => [
                'title' => 'Przetestuj Połączenie',
                'description' => 'Złóż testowe zamówienie, aby zweryfikować integrację.',
            ],
        ],
        'webhook_config_title' => 'Konfiguracja Webhooka',
        'webhook_url_label' => 'URL Webhooka',
        'webhook_url_help' => 'Dodaj ten URL w ustawieniach webhooka Shopify.',
        'api_key_label' => 'Klucz API',
        'api_key_desc' => 'Dołącz klucz API jako Bearer token w nagłówkach webhooka.',
        'manage_api_keys' => 'Zarządzaj Kluczami API',
        'supported_events' => 'Obsługiwane Zdarzenia',
        'list_id_note_title' => 'Ważne: Wymagane ID Listy',
        'list_id_note_desc' => 'Dodaj netsendo_list_id do payloadu webhooka lub użyj Shopify Flow.',
        'requirements_title' => 'Wymagania',
        'requirements' => [
            'store' => 'Aktywny sklep Shopify',
            'account' => 'Konto NetSendo',
            'api_key' => 'Klucz API do uwierzytelniania',
        ],
        'resources_title' => 'Zasoby',
        'docs_link' => 'Dokumentacja Shopify Webhook',
        'lists_link' => 'Zarządzaj Listami',
        'help_title' => 'Potrzebujesz pomocy?',
        'help_desc' => 'Sprawdź dokumentację lub skontaktuj się z nami.',
        'documentation_button' => 'Dokumentacja',
    ],

    'gmail' => [
        'title' => 'Gmail',
        'hero_title' => 'Integracja Gmail',
        'hero_subtitle' => 'Połącz swoje konta Gmail do zarządzania skrzynkami pocztowymi.',
        'hero_description' => 'Zintegruj Gmail z NetSendo, aby używać kont Google jako skrzynek wysyłkowych. Uwierzytelnianie OAuth 2.0 zapewnia bezpieczne połączenie bez przechowywania haseł.',
        'features_title' => 'Funkcje',
        'features' => [
            'imap' => [
                'title' => 'Dostęp IMAP',
                'description' => 'Odczytuj i synchronizuj wiadomości ze skrzynki Gmail.',
            ],
            'smtp' => [
                'title' => 'Wysyłanie SMTP',
                'description' => 'Wysyłaj e-maile bezpośrednio przez serwery Gmail.',
            ],
            'oauth' => [
                'title' => 'OAuth 2.0',
                'description' => 'Bezpieczne uwierzytelnianie bez przechowywania haseł.',
            ],
            'tracking' => [
                'title' => 'Śledzenie E-maili',
                'description' => 'Śledź otwarcia i kliknięcia wysłanych wiadomości.',
            ],
        ],
        'setup_title' => 'Konfiguracja',
        'setup_steps' => [
            'google_cloud' => [
                'title' => 'Utwórz Projekt Google Cloud',
                'description' => 'Przejdź do Google Cloud Console i utwórz nowy projekt.',
            ],
            'enable_api' => [
                'title' => 'Włącz Gmail API',
                'description' => 'W swoim projekcie włącz Gmail API z biblioteki API.',
            ],
            'oauth' => [
                'title' => 'Skonfiguruj OAuth Consent',
                'description' => 'Skonfiguruj ekran zgody OAuth z wymaganymi uprawnieniami.',
            ],
            'configure' => [
                'title' => 'Dodaj Dane Uwierzytelniające',
                'description' => 'Wprowadź Client ID i Client Secret w Ustawienia → Integracje.',
            ],
            'authorize' => [
                'title' => 'Autoryzuj Konto Gmail',
                'description' => 'Połącz swoje konto Gmail przez proces OAuth.',
            ],
        ],
        'go_to_settings' => 'Przejdź do Kont E-mail',
        'resources_title' => 'Zasoby',
        'docs_link' => 'Dokumentacja Gmail API',
        'manage_accounts' => 'Zarządzaj Kontami E-mail',
        'requirements_title' => 'Wymagania',
        'requirements' => [
            'google_account' => 'Konto Google',
            'cloud_project' => 'Projekt Google Cloud',
            'oauth_credentials' => 'Dane OAuth 2.0',
            'netsendo_account' => 'Konto NetSendo',
        ],
        'help_title' => 'Potrzebujesz pomocy?',
        'help_desc' => 'Sprawdź dokumentację, aby uzyskać szczegółowe instrukcje konfiguracji.',
        'documentation_button' => 'Dokumentacja',
    ],

    'google_calendar' => [
        'title' => 'Google Calendar',
        'hero_title' => 'Integracja Google Calendar',
        'hero_subtitle' => 'Synchronizuj zadania CRM z Kalendarzem Google.',
        'hero_description' => 'Dwukierunkowa synchronizacja między zadaniami CRM NetSendo a Kalendarzem Google. Automatyczne tworzenie, aktualizacja i śledzenie zadań na obu platformach.',
        'features_title' => 'Funkcje',
        'features' => [
            'two_way_sync' => [
                'title' => 'Synchronizacja Dwukierunkowa',
                'description' => 'Zmiany synchronizują się automatycznie w obu kierunkach.',
            ],
            'task_sync' => [
                'title' => 'Synchronizacja Zadań',
                'description' => 'Zadania CRM pojawiają się jako wydarzenia w kalendarzu.',
            ],
            'reminders' => [
                'title' => 'Przypomnienia',
                'description' => 'Otrzymuj powiadomienia o nadchodzących zadaniach.',
            ],
            'webhooks' => [
                'title' => 'Aktualizacje w Czasie Rzeczywistym',
                'description' => 'Natychmiastowa synchronizacja przez webhooki Google.',
            ],
        ],
        'setup_title' => 'Konfiguracja',
        'setup_steps' => [
            'google_cloud' => [
                'title' => 'Utwórz Projekt Google Cloud',
                'description' => 'Przejdź do Google Cloud Console i utwórz nowy projekt.',
            ],
            'enable_api' => [
                'title' => 'Włącz Calendar API',
                'description' => 'Włącz Google Calendar API z biblioteki API.',
            ],
            'oauth' => [
                'title' => 'Skonfiguruj OAuth Consent',
                'description' => 'Skonfiguruj ekran zgody OAuth z uprawnieniami kalendarza.',
            ],
            'configure' => [
                'title' => 'Dodaj Dane Uwierzytelniające',
                'description' => 'Wprowadź Client ID i Client Secret w Ustawienia → Integracje.',
            ],
            'connect' => [
                'title' => 'Połącz Kalendarz',
                'description' => 'Autoryzuj Kalendarz Google w Ustawienia → Kalendarz.',
            ],
        ],
        'go_to_settings' => 'Przejdź do Ustawień Kalendarza',
        'resources_title' => 'Zasoby',
        'docs_link' => 'Dokumentacja Calendar API',
        'manage_tasks' => 'Zarządzaj Zadaniami CRM',
        'requirements_title' => 'Wymagania',
        'requirements' => [
            'google_account' => 'Konto Google',
            'cloud_project' => 'Projekt Google Cloud',
            'calendar_api' => 'Włączone Calendar API',
            'netsendo_account' => 'Konto NetSendo',
        ],
        'help_title' => 'Potrzebujesz pomocy?',
        'help_desc' => 'Sprawdź dokumentację, aby uzyskać szczegółowe instrukcje konfiguracji.',
        'documentation_button' => 'Dokumentacja',
    ],

    'perplexity' => [
        'title' => 'Perplexity AI',
        'hero_title' => 'Perplexity AI',
        'hero_subtitle' => 'Zaawansowane badania AI z cytowaniami w czasie rzeczywistym dla Twojej inteligencji marketingowej.',
        'hero_description' => 'Zintegruj Perplexity AI z NetSendo Brain, aby odblokować zaawansowane możliwości badawcze. Uzyskaj kompleksowe odpowiedzi z cytowaniami, analizuj konkurencję, odkrywaj trendy rynkowe i generuj pomysły na treści — wszystko napędzane zaawansowaną AI przeszukującą internet w czasie rzeczywistym.',
        'features_title' => 'Funkcje',
        'features' => [
            'deep_research' => [
                'title' => 'Głębokie badania z cytowaniami',
                'description' => 'Uzyskaj kompleksowe, napędzane AI odpowiedzi z cytowaniami źródeł z całego internetu.',
            ],
            'company_intelligence' => [
                'title' => 'Wywiad firmowy',
                'description' => 'Badaj firmy dogłębnie — produkty, pozycję rynkową, stos technologiczny i kluczowe kontakty.',
            ],
            'trend_analysis' => [
                'title' => 'Analiza trendów rynkowych',
                'description' => 'Odkrywaj trendy branżowe, pojawiające się szanse i dynamikę rynku dzięki analizie AI.',
            ],
            'content_research' => [
                'title' => 'Pomysły na treści',
                'description' => 'Generuj oparte na danych pomysły na treści e-mail i SMS na podstawie inteligencji internetowej.',
            ],
        ],
        'setup_title' => 'Konfiguracja',
        'setup_steps' => [
            'get_key' => [
                'title' => 'Pobierz klucz API',
                'description' => 'Zarejestruj się na perplexity.ai i wygeneruj klucz API z panelu konta.',
            ],
            'configure' => [
                'title' => 'Skonfiguruj w ustawieniach Brain',
                'description' => 'Przejdź do ustawień Brain i wklej klucz API Perplexity w sekcji Badania.',
            ],
            'research' => [
                'title' => 'Zacznij badać',
                'description' => 'Poproś Brain o zbadanie dowolnego tematu — użyje Perplexity do głębokich, cytowanych odpowiedzi.',
            ],
        ],
        'api_info' => 'Perplexity AI używa modelu Sonar do szybkich, dokładnych badań z cytowaniami internetowymi.',
        'use_cases_title' => 'Przypadki użycia',
        'use_cases' => [
            'competitor' => [
                'title' => 'Analiza konkurencji',
                'description' => 'Badaj strategie, produkty i pozycjonowanie rynkowe konkurentów.',
            ],
            'enrichment' => [
                'title' => 'Wzbogacanie danych CRM',
                'description' => 'Automatycznie zbieraj ustrukturyzowane dane firmowe dla kontaktów CRM.',
            ],
            'campaigns' => [
                'title' => 'Badania kampanii',
                'description' => 'Uzyskaj oparte na danych spostrzeżenia, aby poprawić kampanie e-mail i SMS.',
            ],
        ],
        'go_to_settings' => 'Skonfiguruj klucz API',
        'requirements_title' => 'Wymagania',
        'requirements' => [
            'account' => 'Konto Perplexity AI',
            'api_key' => 'Klucz API Perplexity',
            'brain' => 'NetSendo Brain włączony',
        ],
        'resources_title' => 'Zasoby',
        'docs_link' => 'Dokumentacja API Perplexity',
        'help_title' => 'Potrzebujesz pomocy?',
        'help_desc' => 'Sprawdź naszą dokumentację po szczegółowe instrukcje konfiguracji.',
        'documentation_button' => 'Dokumentacja',
    ],

    'serpapi' => [
        'title' => 'SerpAPI',
        'hero_title' => 'SerpAPI',
        'hero_subtitle' => 'Wyniki wyszukiwania Google i grafy wiedzy zintegrowane z Twoim procesem marketingowym.',
        'hero_description' => 'Połącz SerpAPI z NetSendo Brain, aby uzyskać szybkie, ustrukturyzowane wyniki wyszukiwania Google. Przeszukuj internet, odkrywaj wiadomości, korzystaj z grafów wiedzy i znajdź dane firm — wszystko z poziomu rozmów Brain.',
        'features_title' => 'Funkcje',
        'features' => [
            'google_search' => [
                'title' => 'Wyniki wyszukiwania Google',
                'description' => 'Uzyskaj ustrukturyzowane wyniki wyszukiwania Google z tytułami, fragmentami i linkami.',
            ],
            'news_search' => [
                'title' => 'Wyszukiwanie wiadomości',
                'description' => 'Znajdź najnowsze artykuły na dowolny temat dla aktualnych treści marketingowych.',
            ],
            'knowledge_graph' => [
                'title' => 'Graf wiedzy',
                'description' => 'Uzyskaj bogate dane podmiotów z Grafu Wiedzy Google dla głębszych spostrzeżeń.',
            ],
            'company_lookup' => [
                'title' => 'Wyszukiwanie danych firm',
                'description' => 'Szybko znajdź informacje o firmie, strony internetowe i kluczowe dane biznesowe.',
            ],
        ],
        'setup_title' => 'Konfiguracja',
        'setup_steps' => [
            'get_key' => [
                'title' => 'Pobierz klucz API',
                'description' => 'Zarejestruj się na serpapi.com i pobierz klucz API z panelu.',
            ],
            'configure' => [
                'title' => 'Skonfiguruj w ustawieniach Brain',
                'description' => 'Przejdź do ustawień Brain i wklej klucz SerpAPI w sekcji Badania.',
            ],
            'search' => [
                'title' => 'Zacznij wyszukiwać',
                'description' => 'Poproś Brain o wyszukanie w internecie — użyje SerpAPI do szybkich wyników Google.',
            ],
        ],
        'search_types_title' => 'Obsługiwane typy wyszukiwania',
        'search_types' => [
            'general' => 'Ogólne wyszukiwanie',
            'news' => 'Wyszukiwanie wiadomości',
            'images' => 'Wyszukiwanie obrazów',
        ],
        'use_cases_title' => 'Przypadki użycia',
        'use_cases' => [
            'competitors' => [
                'title' => 'Monitoring konkurencji',
                'description' => 'Śledź aktywność i obecność online konkurentów w czasie rzeczywistym.',
            ],
            'trends' => [
                'title' => 'Odkrywanie trendów',
                'description' => 'Znajdź popularne tematy i wiadomości dla aktualnych kampanii marketingowych.',
            ],
            'crm' => [
                'title' => 'Badanie leadów',
                'description' => 'Szybko zbadaj leady i firmy przed podjęciem kontaktu.',
            ],
        ],
        'go_to_settings' => 'Skonfiguruj klucz API',
        'requirements_title' => 'Wymagania',
        'requirements' => [
            'account' => 'Konto SerpAPI',
            'api_key' => 'Klucz API SerpAPI',
            'brain' => 'NetSendo Brain włączony',
        ],
        'resources_title' => 'Zasoby',
        'docs_link' => 'Dokumentacja SerpAPI',
        'help_title' => 'Potrzebujesz pomocy?',
        'help_desc' => 'Sprawdź naszą dokumentację po szczegółowe instrukcje konfiguracji.',
        'documentation_button' => 'Dokumentacja',
    ],
];
