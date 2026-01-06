<?php

namespace App\Http\Controllers;

use App\Models\WooCommerceSettings;
use App\Services\WooCommerceApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class WooCommerceIntegrationController extends Controller
{
    /**
     * Show WooCommerce integration settings page
     */
    public function index(): Response
    {
        $settings = WooCommerceSettings::forUser(Auth::id());

        return Inertia::render('Settings/WooCommerceIntegration/Index', [
            'settings' => $settings ? [
                'store_url' => $settings->store_url,
                'consumer_key' => $settings->consumer_key ? '••••••••' . substr($settings->consumer_key, -4) : null,
                'is_connected' => $settings->isConnected(),
                'last_synced_at' => $settings->last_synced_at?->format('Y-m-d H:i'),
                'connection_verified_at' => $settings->connection_verified_at?->format('Y-m-d H:i'),
                'store_info' => $settings->store_info,
            ] : null,
        ]);
    }

    /**
     * Save WooCommerce settings
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'store_url' => 'required|url|max:500',
            'consumer_key' => 'required|string|max:255',
            'consumer_secret' => 'required|string|max:500',
        ]);

        $settings = WooCommerceSettings::updateOrCreate(
            ['user_id' => Auth::id()],
            [
                'store_url' => $validated['store_url'],
                'consumer_key' => $validated['consumer_key'],
                'consumer_secret' => $validated['consumer_secret'],
                'is_active' => true,
                'connection_verified_at' => null,
                'store_info' => null,
            ]
        );

        // Test connection
        $service = new WooCommerceApiService($settings);
        $result = $service->testConnection();

        if ($result['success']) {
            $settings->markAsVerified($result['store_info'] ?? []);

            return back()->with('success', __('settings.woocommerce.connection_success'));
        }

        return back()->with('error', __('settings.woocommerce.connection_failed') . ': ' . ($result['error'] ?? 'Unknown error'));
    }

    /**
     * Test WooCommerce connection
     */
    public function testConnection(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store_url' => 'required|url|max:500',
            'consumer_key' => 'required|string|max:255',
            'consumer_secret' => 'required|string|max:500',
        ]);

        // Create temporary settings object for testing
        $settings = new WooCommerceSettings([
            'user_id' => Auth::id(),
            'store_url' => $validated['store_url'],
            'consumer_key' => $validated['consumer_key'],
            'is_active' => true,
        ]);
        // Set consumer_secret directly (encrypted)
        $settings->consumer_secret = $validated['consumer_secret'];

        $service = new WooCommerceApiService($settings);
        $result = $service->testConnection();

        return response()->json($result);
    }

    /**
     * Disconnect WooCommerce
     */
    public function disconnect()
    {
        $settings = WooCommerceSettings::forUser(Auth::id());

        if ($settings) {
            $settings->update([
                'is_active' => false,
                'connection_verified_at' => null,
            ]);
        }

        return back()->with('success', __('settings.woocommerce.disconnected'));
    }
}
