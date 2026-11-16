<?php

return [
    'title' => 'SMS Providers',
    'subtitle' => 'Configure SMS gateways for sending text messages.',
    'add_new' => 'Add New',
    'add_first' => 'Add First Provider',
    'default' => 'Default',
    'sent_today' => 'Sent Today',
    'test_connection' => 'Test Connection',
    'set_as_default' => 'Set as Default',

    'info' => [
        'title' => 'Configuration Info',
        'twilio' => 'Requires Account SID, Auth Token and Sender ID.',
        'smsapi' => 'Requires API Token (OAuth).',
    ],

    'status' => [
        'active' => 'Active',
        'inactive' => 'Inactive',
        'error' => 'Error',
        'not_tested' => 'Not Tested',
    ],

    'empty' => [
        'title' => 'No SMS Providers',
        'description' => 'Add your first SMS provider to start sending messages.',
    ],

    'modal' => [
        'add_title' => 'Add SMS Provider',
        'edit_title' => 'Edit SMS Provider',
        'provider' => 'Provider',
    ],

    'notifications' => [
        'created' => 'SMS Provider added successfully.',
        'updated' => 'SMS Provider updated successfully.',
        'deleted' => 'SMS Provider deleted successfully.',
        'set_default' => 'SMS Provider set as default.',
        'test_failed' => 'Error: :error',
    ],
    'fields' => [
        'from_number' => 'Sender number',
        'api_token' => 'API token',
        'from_name' => 'Sender name',
        'from_name_hint_smsapi' => 'Up to 11 alphanumeric characters',
        'test_mode' => 'Test mode',
        'test_mode_hint' => 'Sends no SMS, only tests the API',
        'from_name_hint_vonage' => 'Or a phone number',
        'from_name_hint_messagebird' => 'Up to 11 characters or a phone number',
    ],
    'providers' => [
        'twilio' => 'Global SMS provider (USA, Europe, Asia)',
        'smsapi_name' => 'SMS API (Poland)',
        'smsapi' => 'Polish SMS provider, the best price for Poland',
        'smsapi_com_name' => 'SMS API (International)',
        'smsapi_com' => 'SMS API for international sending',
        'vonage' => 'The world\'s second-largest SMS provider',
        'messagebird' => 'Dutch provider with great prices in Europe',
        'plivo' => 'Low-cost alternative to Twilio',
    ],
];
