<?php

return [
    // Title and general
    'title' => 'Wiadomości systemowe',
    'subtitle' => 'Zarządzaj szablonami emaili wysyłanymi automatycznie',
    'all_lists' => 'Szablony globalne',
    'select_list' => 'Wybierz listę',
    'search_list' => 'Szukaj listy...',
    'no_lists_found' => 'Nie znaleziono list',
    'global_default' => 'Szablon globalny (domyślny)',
    'edit_for_list' => 'Edytujesz emaile dla listy: :name',
    
    // Table headers
    'table' => [
        'name' => 'Nazwa',
        'subject' => 'Temat',
        'slug' => 'Identyfikator',
        'active' => 'Aktywny',
        'status' => 'Status',
    ],
    
    // Status
    'customized' => 'Dostosowany',
    'default' => 'Domyślny',
    'customize' => 'Dostosuj',
    
    // Edit form
    'edit_title' => 'Edytuj email: :name',
    'context' => 'Kontekst:',
    'back_to_list' => '← Powrót do listy',
    'subject_label' => 'Temat wiadomości',
    'content_label' => 'Treść HTML emaila',
    'is_active' => 'Wysyłanie włączone',
    'is_active_desc' => 'Gdy wyłączone, ta wiadomość nie będzie wysyłana',
    'available_placeholders' => 'Dostępne zmienne',
    
    // Messages
    'updated' => 'Email został zaktualizowany.',
    'custom_created' => 'Utworzono dostosowaną wersję emaila dla tej listy.',
    'reset_to_default' => 'Przywrócono domyślną wersję emaila.',
    'toggled' => 'Status emaila został zmieniony.',
    'cannot_toggle_global' => 'Nie można zmieniać statusu globalnych emaili.',
    'click_to_enable' => 'Kliknij, aby włączyć',
    'click_to_disable' => 'Kliknij, aby wyłączyć',
    'cannot_delete_global' => 'Nie można usunąć globalnego szablonu emaila.',
    
    // Global defaults (translation key for controller)
    'global_defaults' => 'Szablony globalne',
];
