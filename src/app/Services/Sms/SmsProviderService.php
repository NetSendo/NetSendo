<?php

namespace App\Services\Sms;

use App\Models\SmsProvider;
use App\Services\Sms\Providers\TwilioProvider;
use App\Services\Sms\Providers\SmsApiProvider;
use App\Services\Sms\Providers\VonageProvider;
use App\Services\Sms\Providers\MessageBirdProvider;
use App\Services\Sms\Providers\PlivoProvider;
use InvalidArgumentException;

/**
 * Main service for managing SMS providers.
 *
 * Analogous to MailProviderService - factory pattern for SMS providers.
 */
class SmsProviderService
{
    /**
     * Get the appropriate provider instance for an SmsProvider model.
     */
    public function getProvider(SmsProvider $smsProvider): SmsProviderInterface
    {
        $credentials = $smsProvider->getDecryptedCredentials();

        return match ($smsProvider->provider) {
            SmsProvider::PROVIDER_TWILIO => new TwilioProvider(
                accountSid: $credentials['account_sid'] ?? '',
                authToken: $credentials['auth_token'] ?? '',
                fromNumber: $smsProvider->from_number ?? $credentials['from_number'] ?? ''
            ),

            SmsProvider::PROVIDER_SMSAPI => new SmsApiProvider(
                apiToken: $credentials['api_token'] ?? '',
                fromName: $smsProvider->from_name ?? $credentials['from_name'] ?? null,
                usePolishEndpoint: ($credentials['endpoint'] ?? 'pl') === 'pl',
                testMode: (bool) ($credentials['test_mode'] ?? false)
            ),

            SmsProvider::PROVIDER_SMSAPI_COM => new SmsApiProvider(
                apiToken: $credentials['api_token'] ?? '',
                fromName: $smsProvider->from_name ?? $credentials['from_name'] ?? null,
                usePolishEndpoint: false,
                testMode: (bool) ($credentials['test_mode'] ?? false)
            ),

            SmsProvider::PROVIDER_VONAGE => new VonageProvider(
                apiKey: $credentials['api_key'] ?? '',
                apiSecret: $credentials['api_secret'] ?? '',
                defaultFrom: $smsProvider->from_name ?? $credentials['from_name'] ?? null
            ),

            SmsProvider::PROVIDER_MESSAGEBIRD => new MessageBirdProvider(
                accessKey: $credentials['access_key'] ?? '',
                defaultFrom: $smsProvider->from_name ?? $credentials['from_name'] ?? null
            ),

            SmsProvider::PROVIDER_PLIVO => new PlivoProvider(
                authId: $credentials['auth_id'] ?? '',
                authToken: $credentials['auth_token'] ?? '',
                defaultFrom: $smsProvider->from_number ?? $credentials['from_number'] ?? null
            ),

            default => throw new InvalidArgumentException("Unknown SMS provider: {$smsProvider->provider}"),
        };
    }

    /**
     * Validate that an SMS provider is ready to send.
     */
    public function validateProvider(SmsProvider $smsProvider): bool
    {
        if (!$smsProvider->is_active) {
            return false;
        }

        if ($smsProvider->hasReachedDailyLimit()) {
            return false;
        }

        return true;
    }

    /**
     * Get the best available SMS provider for a user.
     */
    public function getBestProvider(int $userId): ?SmsProvider
    {
        // First try the default provider
        $defaultProvider = SmsProvider::getDefaultFor($userId);

        if ($defaultProvider && $this->validateProvider($defaultProvider)) {
            return $defaultProvider;
        }

        // Try any active provider that hasn't reached the daily limit
        return SmsProvider::forUser($userId)
            ->active()
            ->whereRaw('(daily_limit IS NULL OR sent_today < daily_limit)')
            ->first();
    }

    /**
     * Get credential fields required for each provider type.
     */
    public static function getProviderFields(string $provider): array
    {
        return match ($provider) {
            SmsProvider::PROVIDER_TWILIO => [
                ['name' => 'account_sid', 'label' => 'Account SID', 'type' => 'text', 'required' => true, 'placeholder' => 'ACXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX'],
                ['name' => 'auth_token', 'label' => 'Auth Token', 'type' => 'password', 'required' => true],
                ['name' => 'from_number', 'label' => __('sms_providers.fields.from_number'), 'type' => 'text', 'required' => true, 'placeholder' => '+48123456789'],
            ],

            SmsProvider::PROVIDER_SMSAPI, SmsProvider::PROVIDER_SMSAPI_COM => [
                ['name' => 'api_token', 'label' => __('sms_providers.fields.api_token'), 'type' => 'password', 'required' => true],
                ['name' => 'from_name', 'label' => __('sms_providers.fields.from_name'), 'type' => 'text', 'required' => false, 'placeholder' => 'NetSendo', 'hint' => __('sms_providers.fields.from_name_hint_smsapi')],
                ['name' => 'test_mode', 'label' => __('sms_providers.fields.test_mode'), 'type' => 'checkbox', 'required' => false, 'hint' => __('sms_providers.fields.test_mode_hint')],
            ],

            SmsProvider::PROVIDER_VONAGE => [
                ['name' => 'api_key', 'label' => 'API Key', 'type' => 'text', 'required' => true, 'placeholder' => 'xxxxxxxx'],
                ['name' => 'api_secret', 'label' => 'API Secret', 'type' => 'password', 'required' => true],
                ['name' => 'from_name', 'label' => __('sms_providers.fields.from_name'), 'type' => 'text', 'required' => false, 'placeholder' => 'NetSendo', 'hint' => __('sms_providers.fields.from_name_hint_vonage')],
            ],

            SmsProvider::PROVIDER_MESSAGEBIRD => [
                ['name' => 'access_key', 'label' => 'Access Key', 'type' => 'password', 'required' => true, 'placeholder' => 'live_xxxxxxxx'],
                ['name' => 'from_name', 'label' => __('sms_providers.fields.from_name'), 'type' => 'text', 'required' => false, 'placeholder' => 'NetSendo', 'hint' => __('sms_providers.fields.from_name_hint_messagebird')],
            ],

            SmsProvider::PROVIDER_PLIVO => [
                ['name' => 'auth_id', 'label' => 'Auth ID', 'type' => 'text', 'required' => true, 'placeholder' => 'MAXXXXXXXXXXXXXXXX'],
                ['name' => 'auth_token', 'label' => 'Auth Token', 'type' => 'password', 'required' => true],
                ['name' => 'from_number', 'label' => __('sms_providers.fields.from_number'), 'type' => 'text', 'required' => true, 'placeholder' => '+48123456789'],
            ],

            default => [],
        };
    }

    /**
     * Get available provider types for display.
     */
    public static function getAvailableProviders(): array
    {
        return [
            [
                'id' => SmsProvider::PROVIDER_TWILIO,
                'name' => 'Twilio',
                'description' => __('sms_providers.providers.twilio'),
                'icon' => 'twilio',
            ],
            [
                'id' => SmsProvider::PROVIDER_SMSAPI,
                'name' => __('sms_providers.providers.smsapi_name'),
                'description' => __('sms_providers.providers.smsapi'),
                'icon' => 'smsapi',
            ],
            [
                'id' => SmsProvider::PROVIDER_SMSAPI_COM,
                'name' => __('sms_providers.providers.smsapi_com_name'),
                'description' => __('sms_providers.providers.smsapi_com'),
                'icon' => 'smsapi',
            ],
            [
                'id' => SmsProvider::PROVIDER_VONAGE,
                'name' => 'Vonage (Nexmo)',
                'description' => __('sms_providers.providers.vonage'),
                'icon' => 'vonage',
            ],
            [
                'id' => SmsProvider::PROVIDER_MESSAGEBIRD,
                'name' => 'MessageBird',
                'description' => __('sms_providers.providers.messagebird'),
                'icon' => 'messagebird',
            ],
            [
                'id' => SmsProvider::PROVIDER_PLIVO,
                'name' => 'Plivo',
                'description' => __('sms_providers.providers.plivo'),
                'icon' => 'plivo',
            ],
        ];
    }
}
