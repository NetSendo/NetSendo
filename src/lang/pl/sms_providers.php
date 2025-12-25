<?php

return [
    'title' => 'Dostawcy SMS',
    'subtitle' => 'Skonfiguruj bramki SMS do wysyłania wiadomości tekstowych.',
    'add_new' => 'Dodaj nowego',
    'add_first' => 'Dodaj pierwszego dostawcę',
    'default' => 'Domyślny',
    'sent_today' => 'Wysłano dzisiaj',
    'test_connection' => 'Testuj połączenie',
    'set_as_default' => 'Ustaw jako domyślny',

    'info' => [
        'title' => 'Informacje o konfiguracji',
        'twilio' => 'Wymaga Account SID, Auth Token oraz numeru nadawcy.',
        'smsapi' => 'Wymaga Tokenu API (OAuth).',
    ],

    'status' => [
        'active' => 'Aktywny',
        'inactive' => 'Nieaktywny',
        'error' => 'Błąd',
        'not_tested' => 'Nie testowano',
    ],

    'empty' => [
        'title' => 'Brak dostawców SMS',
        'description' => 'Dodaj swojego pierwszego dostawcę SMS, aby rozpocząć wysyłanie wiadomości.',
    ],

    'modal' => [
        'add_title' => 'Dodaj dostawcę SMS',
        'edit_title' => 'Edytuj dostawcę SMS',
        'provider' => 'Dostawca',
    ],

    'notifications' => [
        'created' => 'Dostawca SMS został dodany pomyślnie.',
        'updated' => 'Dostawca SMS został zaktualizowany.',
        'deleted' => 'Dostawca SMS został usunięty.',
        'set_default' => 'Dostawca SMS został ustawiony jako domyślny.',
    ],
];
