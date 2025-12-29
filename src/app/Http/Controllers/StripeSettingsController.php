<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Inertia\Inertia;

class StripeSettingsController extends Controller
{
    /**
     * Display Stripe settings page.
     */
    public function index()
    {
        $settings = $this->getStripeSettings();
        $oauthSettings = $this->getOAuthSettings();

        return Inertia::render('Settings/StripeSettings/Index', [
            'settings' => [
                'publishable_key' => $settings['publishable_key'] ?? '',
                'secret_key_masked' => $this->maskSecretKey($settings['secret_key'] ?? ''),
                'webhook_secret_masked' => $this->maskSecretKey($settings['webhook_secret'] ?? ''),
                'is_configured' => !empty($settings['publishable_key']) && !empty($settings['secret_key']),
                'connection_mode' => $oauthSettings['connection_mode'] ?? 'api_key',
                'oauth' => [
                    'is_connected' => !empty($oauthSettings['stripe_user_id']),
                    'stripe_user_id' => $oauthSettings['stripe_user_id'] ?? null,
                    'publishable_key' => $oauthSettings['publishable_key'] ?? null,
                ],
                'client_id' => Setting::where('key', 'stripe_client_id')->first()?->value ?? '',
                'client_id_configured' => !empty(config('services.stripe.client_id'))
                    || !empty(Setting::where('key', 'stripe_client_id')->first()?->value),
                'redirect_uri' => route('settings.stripe.oauth.callback'),
            ],
        ]);
    }

    /**
     * Get OAuth-related settings.
     */
    protected function getOAuthSettings(): array
    {
        return [
            'connection_mode' => Setting::where('key', 'stripe_connection_mode')->first()?->value,
            'stripe_user_id' => Setting::where('key', 'stripe_oauth_stripe_user_id')->first()?->value,
            'publishable_key' => Setting::where('key', 'stripe_oauth_publishable_key')->first()?->value,
        ];
    }

    /**
     * Update Stripe settings.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'publishable_key' => ['nullable', 'string', 'max:255'],
            'secret_key' => ['nullable', 'string', 'max:255'],
            'webhook_secret' => ['nullable', 'string', 'max:255'],
            'client_id' => ['nullable', 'string', 'max:255'],
        ]);

        // Save publishable key (not encrypted - it's public)
        if (isset($validated['publishable_key'])) {
            $this->saveSetting('stripe_publishable_key', $validated['publishable_key']);
        }

        // Save secret key (encrypted)
        if (!empty($validated['secret_key']) && !str_contains($validated['secret_key'], '••••')) {
            $this->saveSetting('stripe_secret_key', Crypt::encryptString($validated['secret_key']));
        }

        // Save webhook secret (encrypted)
        if (!empty($validated['webhook_secret']) && !str_contains($validated['webhook_secret'], '••••')) {
            $this->saveSetting('stripe_webhook_secret', Crypt::encryptString($validated['webhook_secret']));
        }

        // Save Client ID (for OAuth Connect)
        if (isset($validated['client_id'])) {
            $this->saveSetting('stripe_client_id', $validated['client_id']);
        }

        return back()->with('success', __('stripe.settings_saved'));
    }

    /**
     * Test Stripe connection.
     */
    public function testConnection()
    {
        $settings = $this->getStripeSettings();

        if (empty($settings['secret_key'])) {
            return response()->json([
                'success' => false,
                'message' => __('stripe.api_key_missing'),
            ], 400);
        }

        try {
            \Stripe\Stripe::setApiKey($settings['secret_key']);
            \Stripe\Balance::retrieve();

            return response()->json([
                'success' => true,
                'message' => __('stripe.connection_success'),
            ]);
        } catch (\Stripe\Exception\AuthenticationException $e) {
            return response()->json([
                'success' => false,
                'message' => __('stripe.invalid_api_key'),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all Stripe settings from database.
     */
    public static function getStripeSettings(): array
    {
        $publishableKey = Setting::where('key', 'stripe_publishable_key')->first()?->value;
        $secretKeyEncrypted = Setting::where('key', 'stripe_secret_key')->first()?->value;
        $webhookSecretEncrypted = Setting::where('key', 'stripe_webhook_secret')->first()?->value;

        $secretKey = null;
        $webhookSecret = null;

        if ($secretKeyEncrypted) {
            try {
                $secretKey = Crypt::decryptString($secretKeyEncrypted);
            } catch (\Exception $e) {
                // Decryption failed - key might be corrupted
            }
        }

        if ($webhookSecretEncrypted) {
            try {
                $webhookSecret = Crypt::decryptString($webhookSecretEncrypted);
            } catch (\Exception $e) {
                // Decryption failed
            }
        }

        return [
            'publishable_key' => $publishableKey,
            'secret_key' => $secretKey,
            'webhook_secret' => $webhookSecret,
        ];
    }

    /**
     * Save a setting to database.
     */
    private function saveSetting(string $key, ?string $value): void
    {
        if (empty($value)) {
            Setting::where('key', $key)->delete();
            return;
        }

        Setting::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }

    /**
     * Mask secret key for display.
     */
    private function maskSecretKey(?string $key): string
    {
        if (empty($key)) {
            return '';
        }

        if (strlen($key) <= 8) {
            return str_repeat('•', strlen($key));
        }

        return substr($key, 0, 4) . str_repeat('•', 12) . substr($key, -4);
    }
}
