<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SMS Provider Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for SMS providers. Each provider can be configured
    | via environment variables or through the UI (stored in database).
    |
    */

    'default' => env('SMS_DEFAULT_PROVIDER', null),

    /*
    |--------------------------------------------------------------------------
    | Provider Types
    |--------------------------------------------------------------------------
    */
    'providers' => [
        'twilio' => [
            'name' => 'Twilio',
            'class' => App\Services\Sms\Providers\TwilioProvider::class,
        ],
        'smsapi' => [
            'name' => 'SMS API (PL)',
            'class' => App\Services\Sms\Providers\SmsApiProvider::class,
        ],
        'smsapi_com' => [
            'name' => 'SMS API (COM)',
            'class' => App\Services\Sms\Providers\SmsApiProvider::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS Settings
    |--------------------------------------------------------------------------
    */

    // Maximum SMS length before splitting into parts (GSM-7 encoding)
    'max_length_gsm' => 160,

    // Maximum SMS length for Unicode content
    'max_length_unicode' => 70,

    // Default batch size for bulk sending
    'batch_size' => 100,

    // Delay between batches (in seconds) to avoid rate limiting
    'batch_delay' => 1,

    /*
    |--------------------------------------------------------------------------
    | Phone Number Defaults
    |--------------------------------------------------------------------------
    */

    // Default country code to prepend if number doesn't have one
    'default_country_code' => env('SMS_DEFAULT_COUNTRY_CODE', '48'),
];
