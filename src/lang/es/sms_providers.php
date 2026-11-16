<?php

return [
    'fields' => [
        'from_number' => 'Número del remitente',
        'api_token' => 'Token de API',
        'from_name' => 'Nombre del remitente',
        'from_name_hint_smsapi' => 'Hasta 11 caracteres alfanuméricos',
        'test_mode' => 'Modo de prueba',
        'test_mode_hint' => 'No envía SMS, solo prueba la API',
        'from_name_hint_vonage' => 'O un número de teléfono',
        'from_name_hint_messagebird' => 'Hasta 11 caracteres o un número de teléfono',
    ],
    'providers' => [
        'twilio' => 'Proveedor de SMS global (EE. UU., Europa, Asia)',
        'smsapi_name' => 'SMS API (Polonia)',
        'smsapi' => 'Proveedor de SMS polaco, el mejor precio para Polonia',
        'smsapi_com_name' => 'SMS API (Internacional)',
        'smsapi_com' => 'SMS API para envíos internacionales',
        'vonage' => 'El segundo proveedor de SMS más grande del mundo',
        'messagebird' => 'Proveedor neerlandés con muy buenos precios en Europa',
        'plivo' => 'Alternativa económica a Twilio',
    ],
    'notifications' => [
        'created' => 'Proveedor de SMS añadido.',
        'updated' => 'Proveedor de SMS actualizado.',
        'deleted' => 'Proveedor de SMS eliminado.',
        'set_default' => 'Proveedor de SMS establecido como predeterminado.',
        'test_failed' => 'Error: :error',
    ],
];
