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
            ->whereRaw('daily_limit IS NULL OR sent_today < daily_limit')
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
                ['name' => 'from_number', 'label' => 'Numer nadawcy', 'type' => 'text', 'required' => true, 'placeholder' => '+48123456789'],
            ],

            SmsProvider::PROVIDER_SMSAPI, SmsProvider::PROVIDER_SMSAPI_COM => [
                ['name' => 'api_token', 'label' => 'Token API', 'type' => 'password', 'required' => true],
                ['name' => 'from_name', 'label' => 'Nazwa nadawcy', 'type' => 'text', 'required' => false, 'placeholder' => 'NetSendo', 'hint' => 'Max 11 znaków alfanumerycznych'],
                ['name' => 'test_mode', 'label' => 'Tryb testowy', 'type' => 'checkbox', 'required' => false, 'hint' => 'Nie wysyła SMS, tylko testuje API'],
            ],

            SmsProvider::PROVIDER_VONAGE => [
                ['name' => 'api_key', 'label' => 'API Key', 'type' => 'text', 'required' => true, 'placeholder' => 'xxxxxxxx'],
                ['name' => 'api_secret', 'label' => 'API Secret', 'type' => 'password', 'required' => true],
                ['name' => 'from_name', 'label' => 'Nazwa nadawcy', 'type' => 'text', 'required' => false, 'placeholder' => 'NetSendo', 'hint' => 'Lub numer telefonu'],
            ],

            SmsProvider::PROVIDER_MESSAGEBIRD => [
                ['name' => 'access_key', 'label' => 'Access Key', 'type' => 'password', 'required' => true, 'placeholder' => 'live_xxxxxxxx'],
                ['name' => 'from_name', 'label' => 'Nazwa nadawcy', 'type' => 'text', 'required' => false, 'placeholder' => 'NetSendo', 'hint' => 'Max 11 znaków lub numer telefonu'],
            ],

            SmsProvider::PROVIDER_PLIVO => [
                ['name' => 'auth_id', 'label' => 'Auth ID', 'type' => 'text', 'required' => true, 'placeholder' => 'MAXXXXXXXXXXXXXXXX'],
                ['name' => 'auth_token', 'label' => 'Auth Token', 'type' => 'password', 'required' => true],
                ['name' => 'from_number', 'label' => 'Numer nadawcy', 'type' => 'text', 'required' => true, 'placeholder' => '+48123456789'],
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
                'description' => 'Globalny dostawca SMS (USA, Europa, Azja)',
                'icon' => 'twilio',
            ],
            [
                'id' => SmsProvider::PROVIDER_SMSAPI,
                'name' => 'SMS API (Polska)',
                'description' => 'Polski dostawca SMS - najlepsza cena dla Polski',
                'icon' => 'smsapi',
            ],
            [
                'id' => SmsProvider::PROVIDER_SMSAPI_COM,
                'name' => 'SMS API (Międzynarodowy)',
                'description' => 'SMS API dla wysyłek międzynarodowych',
                'icon' => 'smsapi',
            ],
            [
                'id' => SmsProvider::PROVIDER_VONAGE,
                'name' => 'Vonage (Nexmo)',
                'description' => 'Globalny dostawca SMS #2 na świecie',
                'icon' => 'vonage',
            ],
            [
                'id' => SmsProvider::PROVIDER_MESSAGEBIRD,
                'name' => 'MessageBird',
                'description' => 'Holenderski dostawca - świetne ceny w Europie',
                'icon' => 'messagebird',
            ],
            [
                'id' => SmsProvider::PROVIDER_PLIVO,
                'name' => 'Plivo',
                'description' => 'Ekonomiczna alternatywa dla Twilio',
                'icon' => 'plivo',
            ],
        ];
    }
}
