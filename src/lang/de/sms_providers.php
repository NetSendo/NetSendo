<?php

return [
    'fields' => [
        'from_number' => 'Absendernummer',
        'api_token' => 'API-Token',
        'from_name' => 'Absendername',
        'from_name_hint_smsapi' => 'Bis zu 11 alphanumerische Zeichen',
        'test_mode' => 'Testmodus',
        'test_mode_hint' => 'Sendet keine SMS, testet nur die API',
        'from_name_hint_vonage' => 'Oder eine Telefonnummer',
        'from_name_hint_messagebird' => 'Bis zu 11 Zeichen oder eine Telefonnummer',
    ],
    'providers' => [
        'twilio' => 'Globaler SMS-Anbieter (USA, Europa, Asien)',
        'smsapi_name' => 'SMS API (Polen)',
        'smsapi' => 'Polnischer SMS-Anbieter, der beste Preis für Polen',
        'smsapi_com_name' => 'SMS API (International)',
        'smsapi_com' => 'SMS API für den internationalen Versand',
        'vonage' => 'Der weltweit zweitgrößte SMS-Anbieter',
        'messagebird' => 'Niederländischer Anbieter mit sehr guten Preisen in Europa',
        'plivo' => 'Günstige Alternative zu Twilio',
    ],
    'notifications' => [
        'created' => 'SMS-Anbieter wurde hinzugefügt.',
        'updated' => 'SMS-Anbieter wurde aktualisiert.',
        'deleted' => 'SMS-Anbieter wurde gelöscht.',
        'set_default' => 'SMS-Anbieter wurde als Standard festgelegt.',
        'test_failed' => 'Fehler: :error',
    ],
];
