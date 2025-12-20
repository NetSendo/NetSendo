<?php

/**
 * Localization Configuration
 *
 * This file defines the supported locales and settings for the
 * multi-language system with auto-detection capability.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Supported Locales
    |--------------------------------------------------------------------------
    |
    | Define all languages supported by the application. Each locale includes
    | the language name, native name, flag emoji, and RTL direction flag.
    |
    */

    'supported_locales' => [
        'en' => [
            'name' => 'English',
            'native' => 'English',
            'flag' => '🇬🇧',
            'rtl' => false,
        ],
        'de' => [
            'name' => 'German',
            'native' => 'Deutsch',
            'flag' => '🇩🇪',
            'rtl' => false,
        ],
        'es' => [
            'name' => 'Spanish',
            'native' => 'Español',
            'flag' => '🇪🇸',
            'rtl' => false,
        ],
        'pl' => [
            'name' => 'Polish',
            'native' => 'Polski',
            'flag' => '🇵🇱',
            'rtl' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Locale
    |--------------------------------------------------------------------------
    |
    | The default locale to use when no preference is detected.
    |
    */

    'default_locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Fallback Locale
    |--------------------------------------------------------------------------
    |
    | The locale to use when a translation is not available in the
    | current locale.
    |
    */

    'fallback_locale' => 'en',

];
