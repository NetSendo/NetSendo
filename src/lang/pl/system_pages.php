<?php

return [
    // Title and general
    'title' => 'Strony systemowe',
    'subtitle' => 'Zarządzaj stronami HTML wyświetlanymi użytkownikom po akcjach',
    'all_lists' => 'Szablony globalne',
    'select_list' => 'Wybierz listę',
    'search_list' => 'Szukaj listy...',
    'no_lists_found' => 'Nie znaleziono list',
    'global_default' => 'Szablon globalny (domyślny)',
    'edit_for_list' => 'Edytujesz strony dla listy: :name',
    
    // Table headers
    'table' => [
        'name' => 'Nazwa',
        'title' => 'Tytuł strony',
        'slug' => 'Adres (URI)',
        'access' => 'Dostęp',
        'status' => 'Status',
    ],
    
    // Status
    'customized' => 'Dostosowany',
    'default' => 'Domyślny',
    'customize' => 'Dostosuj',
    
    // Access levels
    'access' => [
        'public' => 'Publiczny',
        'private' => 'Prywatny',
    ],
    
    // Edit form
    'edit_title' => 'Edytuj stronę: :name',
    'context' => 'Kontekst:',
    'back_to_list' => '← Powrót do listy',
    'page_title_label' => 'Tytuł strony',
    'slug_label' => 'Adres strony (slug)',
    'slug_readonly_global' => 'Adres można zmienić tylko dla stron dostosowanych do konkretnej listy',
    'access_label' => 'Widoczność',
    'access_help' => 'Publiczne strony są dostępne dla wszystkich. Prywatne wymagają autoryzacji.',
    'content_label' => 'Treść HTML strony',
    'current_url' => 'Aktualny adres',
    
    // Messages
    'updated' => 'Strona została zaktualizowana.',
    'custom_created' => 'Utworzono dostosowaną wersję strony dla tej listy.',
    'reset_to_default' => 'Przywrócono domyślną wersję strony.',
    'cannot_delete_global' => 'Nie można usunąć globalnego szablonu strony.',
    
    // Global defaults (translation key for controller)
    'global_defaults' => 'Szablony globalne',
];
