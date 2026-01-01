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
];
