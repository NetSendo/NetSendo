<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\TpayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Inertia\Inertia;

class TpaySettingsController extends Controller
{
    public function __construct(
        private TpayService $tpayService
    ) {}

    /**
     * Display Tpay settings page.
     */
    public function index()
    {
        $settings = TpayService::getTpaySettings();

        return Inertia::render('Settings/TpaySettings/Index', [
            'settings' => [
                'client_id_masked' => $this->maskSecretKey($settings['client_id'] ?? ''),
                'client_secret_masked' => $this->maskSecretKey($settings['client_secret'] ?? ''),
                'security_code_masked' => $this->maskSecretKey($settings['security_code'] ?? ''),
                'sandbox_mode' => $settings['sandbox_mode'],
                'is_configured' => !empty($settings['client_id']) && !empty($settings['client_secret']),
                'webhook_url' => route('webhooks.tpay'),
            ],
        ]);
    }

    /**
     * Update Tpay settings.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'client_id' => ['nullable', 'string', 'max:255'],
            'client_secret' => ['nullable', 'string', 'max:255'],
            'security_code' => ['nullable', 'string', 'max:255'],
            'sandbox_mode' => ['boolean'],
        ]);

        // Save Client ID (encrypted)
        if (!empty($validated['client_id']) && !str_contains($validated['client_id'], '••••')) {
            $this->saveSetting('tpay_client_id', Crypt::encryptString($validated['client_id']));
        }

        // Save Client Secret (encrypted)
        if (!empty($validated['client_secret']) && !str_contains($validated['client_secret'], '••••')) {
            $this->saveSetting('tpay_client_secret', Crypt::encryptString($validated['client_secret']));
        }

        // Save Security Code (encrypted)
        if (!empty($validated['security_code']) && !str_contains($validated['security_code'], '••••')) {
            $this->saveSetting('tpay_security_code', Crypt::encryptString($validated['security_code']));
        }

        // Save sandbox mode
        $this->saveSetting('tpay_sandbox_mode', $validated['sandbox_mode'] ?? false ? '1' : '0');

        // Clear cached token when settings change
        \Illuminate\Support\Facades\Cache::forget('tpay_access_token_' . md5($validated['client_id'] ?? ''));

        return back()->with('success', __('tpay.settings_saved'));
    }

    /**
     * Test Tpay connection.
     */
    public function testConnection(Request $request)
    {
        $settings = TpayService::getTpaySettings();

        // If new credentials provided, use them; otherwise use stored
        $clientId = $request->input('client_id');
        $clientSecret = $request->input('client_secret');
        $sandboxMode = $request->input('sandbox_mode', $settings['sandbox_mode']);

        // If masked, use stored values
        if (empty($clientId) || str_contains($clientId, '••••')) {
            $clientId = $settings['client_id'];
        }
        if (empty($clientSecret) || str_contains($clientSecret, '••••')) {
            $clientSecret = $settings['client_secret'];
        }

        if (empty($clientId) || empty($clientSecret)) {
            return response()->json([
                'success' => false,
                'message' => __('tpay.credentials_missing'),
            ], 400);
        }

        $result = $this->tpayService->testConnection($clientId, $clientSecret, $sandboxMode);

        return response()->json($result, $result['success'] ? 200 : 400);
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
