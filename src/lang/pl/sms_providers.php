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
        'test_failed' => 'Błąd: :error',
    ],
    'fields' => [
        'from_number' => 'Numer nadawcy',
        'api_token' => 'Token API',
        'from_name' => 'Nazwa nadawcy',
        'from_name_hint_smsapi' => 'Max 11 znaków alfanumerycznych',
        'test_mode' => 'Tryb testowy',
        'test_mode_hint' => 'Nie wysyła SMS, tylko testuje API',
        'from_name_hint_vonage' => 'Lub numer telefonu',
        'from_name_hint_messagebird' => 'Max 11 znaków lub numer telefonu',
    ],
    'providers' => [
        'twilio' => 'Globalny dostawca SMS (USA, Europa, Azja)',
        'smsapi_name' => 'SMS API (Polska)',
        'smsapi' => 'Polski dostawca SMS - najlepsza cena dla Polski',
        'smsapi_com_name' => 'SMS API (Międzynarodowy)',
        'smsapi_com' => 'SMS API dla wysyłek międzynarodowych',
        'vonage' => 'Globalny dostawca SMS #2 na świecie',
        'messagebird' => 'Holenderski dostawca - świetne ceny w Europie',
        'plivo' => 'Ekonomiczna alternatywa dla Twilio',
    ],
];
