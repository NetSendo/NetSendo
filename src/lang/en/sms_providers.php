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
    ],
];
