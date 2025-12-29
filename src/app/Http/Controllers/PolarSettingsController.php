<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\PolarService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Inertia\Inertia;

class PolarSettingsController extends Controller
{
    /**
     * Display Polar settings page.
     */
    public function index()
    {
        $settings = self::getPolarSettings();

        return Inertia::render('Settings/PolarSettings/Index', [
            'settings' => [
                'access_token_masked' => $this->maskSecretKey($settings['access_token'] ?? ''),
                'webhook_secret_masked' => $this->maskSecretKey($settings['webhook_secret'] ?? ''),
                'environment' => $settings['environment'] ?? 'sandbox',
                'is_configured' => !empty($settings['access_token']),
            ],
        ]);
    }

    /**
     * Update Polar settings.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'access_token' => ['nullable', 'string', 'max:500'],
            'webhook_secret' => ['nullable', 'string', 'max:500'],
            'environment' => ['nullable', 'string', 'in:sandbox,production'],
        ]);

        // Save access token (encrypted)
        if (!empty($validated['access_token']) && !str_contains($validated['access_token'], '••••')) {
            $this->saveSetting('polar_access_token', Crypt::encryptString($validated['access_token']));
        }

        // Save webhook secret (encrypted)
        if (!empty($validated['webhook_secret']) && !str_contains($validated['webhook_secret'], '••••')) {
            $this->saveSetting('polar_webhook_secret', Crypt::encryptString($validated['webhook_secret']));
        }

        // Save environment
        if (isset($validated['environment'])) {
            $this->saveSetting('polar_environment', $validated['environment']);
        }

        return back()->with('success', __('polar.settings_saved'));
    }

    /**
     * Test Polar connection.
     */
    public function testConnection()
    {
        $settings = self::getPolarSettings();

        if (empty($settings['access_token'])) {
            return response()->json([
                'success' => false,
                'message' => __('polar.access_token_missing'),
            ], 400);
        }

        try {
            $polarService = new PolarService();
            $success = $polarService->testConnection();

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => __('polar.connection_success'),
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => __('polar.connection_failed'),
                ], 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all Polar settings from database.
     */
    public static function getPolarSettings(): array
    {
        $accessTokenEncrypted = Setting::where('key', 'polar_access_token')->first()?->value;
        $webhookSecretEncrypted = Setting::where('key', 'polar_webhook_secret')->first()?->value;
        $environment = Setting::where('key', 'polar_environment')->first()?->value;

        $accessToken = null;
        $webhookSecret = null;

        if ($accessTokenEncrypted) {
            try {
                $accessToken = Crypt::decryptString($accessTokenEncrypted);
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
            'access_token' => $accessToken,
            'webhook_secret' => $webhookSecret,
            'environment' => $environment ?? 'sandbox',
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
