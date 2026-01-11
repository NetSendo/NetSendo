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
        $stores = WooCommerceSettings::forUser(Auth::id());

        return Inertia::render('Settings/WooCommerceIntegration/Index', [
            'stores' => $stores->map(fn($store) => [
                'id' => $store->id,
                'name' => $store->name,
                'display_name' => $store->display_name,
                'store_url' => $store->store_url,
                'consumer_key' => $store->consumer_key ? '••••••••' . substr($store->consumer_key, -4) : null,
                'is_connected' => $store->isConnected(),
                'is_default' => $store->is_default,
                'last_synced_at' => $store->last_synced_at?->format('Y-m-d H:i'),
                'connection_verified_at' => $store->connection_verified_at?->format('Y-m-d H:i'),
                'store_info' => $store->store_info,
            ]),
        ]);
    }

    /**
     * Save WooCommerce store settings
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'store_url' => 'required|url|max:500',
            'consumer_key' => 'required|string|max:255',
            'consumer_secret' => 'required|string|max:500',
            'is_default' => 'boolean',
        ]);

        // Check if store URL already exists for this user
        $existingStore = WooCommerceSettings::where('user_id', Auth::id())
            ->where('store_url', rtrim(trim($validated['store_url']), '/'))
            ->first();

        if ($existingStore) {
            return back()->with('error', __('settings.woocommerce.store_url_exists'));
        }

        // Check if this is the first store - make it default
        $isFirstStore = WooCommerceSettings::forUser(Auth::id())->isEmpty();
        $isDefault = $isFirstStore || ($validated['is_default'] ?? false);

        $settings = WooCommerceSettings::create([
            'user_id' => Auth::id(),
            'name' => $validated['name'],
            'store_url' => $validated['store_url'],
            'consumer_key' => $validated['consumer_key'],
            'consumer_secret' => $validated['consumer_secret'],
            'is_active' => true,
            'is_default' => $isDefault,
        ]);

        // If this is set as default, unset others
        if ($isDefault) {
            $settings->setAsDefault();
        }

        // Test connection
        $service = new WooCommerceApiService($settings);
        $result = $service->testConnection();

        if ($result['success']) {
            $settings->markAsVerified($result['store_info'] ?? []);

            return back()->with('success', __('settings.woocommerce.store_added'));
        }

        return back()->with('error', __('settings.woocommerce.connection_failed') . ': ' . ($result['error'] ?? 'Unknown error'));
    }

    /**
     * Update WooCommerce store settings
     */
    public function update(Request $request, int $id)
    {
        $store = WooCommerceSettings::getByIdForUser($id, Auth::id());

        if (!$store) {
            return back()->with('error', __('settings.woocommerce.store_not_found'));
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'store_url' => 'required|url|max:500',
            'consumer_key' => 'nullable|string|max:255',
            'consumer_secret' => 'nullable|string|max:500',
            'is_default' => 'boolean',
        ]);

        // Check if store URL is changing and if new URL already exists
        $newUrl = rtrim(trim($validated['store_url']), '/');
        if ($newUrl !== $store->store_url) {
            $existingStore = WooCommerceSettings::where('user_id', Auth::id())
                ->where('store_url', $newUrl)
                ->where('id', '!=', $id)
                ->first();

            if ($existingStore) {
                return back()->with('error', __('settings.woocommerce.store_url_exists'));
            }
        }

        $updateData = [
            'name' => $validated['name'],
            'store_url' => $validated['store_url'],
        ];

        // Only update credentials if provided
        if (!empty($validated['consumer_key'])) {
            $updateData['consumer_key'] = $validated['consumer_key'];
        }
        if (!empty($validated['consumer_secret'])) {
            $updateData['consumer_secret'] = $validated['consumer_secret'];
        }

        $store->update($updateData);

        // Handle default status
        if ($validated['is_default'] ?? false) {
            $store->setAsDefault();
        }

        // Re-test connection if credentials were updated
        if (!empty($validated['consumer_key']) && !empty($validated['consumer_secret'])) {
            $service = new WooCommerceApiService($store);
            $result = $service->testConnection();

            if ($result['success']) {
                $store->markAsVerified($result['store_info'] ?? []);
            } else {
                $store->update([
                    'connection_verified_at' => null,
                    'store_info' => null,
                ]);
            }
        }

        return back()->with('success', __('settings.woocommerce.store_updated'));
    }

    /**
     * Delete WooCommerce store
     */
    public function destroy(int $id)
    {
        $store = WooCommerceSettings::getByIdForUser($id, Auth::id());

        if (!$store) {
            return back()->with('error', __('settings.woocommerce.store_not_found'));
        }

        $wasDefault = $store->is_default;
        $store->delete();

        // If this was the default, set another one as default
        if ($wasDefault) {
            $anotherStore = WooCommerceSettings::where('user_id', Auth::id())->first();
            if ($anotherStore) {
                $anotherStore->setAsDefault();
            }
        }

        return back()->with('success', __('settings.woocommerce.store_deleted'));
    }

    /**
     * Set store as default
     */
    public function setDefault(int $id)
    {
        $store = WooCommerceSettings::getByIdForUser($id, Auth::id());

        if (!$store) {
            return back()->with('error', __('settings.woocommerce.store_not_found'));
        }

        $store->setAsDefault();

        return back()->with('success', __('settings.woocommerce.default_set'));
    }

    /**
     * Test WooCommerce connection
     */
    public function testConnection(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store_id' => 'nullable|integer',
            'store_url' => 'required_without:store_id|url|max:500',
            'consumer_key' => 'required_without:store_id|string|max:255',
            'consumer_secret' => 'required_without:store_id|string|max:500',
        ]);

        // If testing existing store
        if (!empty($validated['store_id'])) {
            $store = WooCommerceSettings::getByIdForUser($validated['store_id'], Auth::id());
            if (!$store) {
                return response()->json(['success' => false, 'error' => 'Store not found']);
            }
            $service = new WooCommerceApiService($store);
            return response()->json($service->testConnection());
        }

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
     * Disconnect WooCommerce store
     */
    public function disconnect(int $id)
    {
        $store = WooCommerceSettings::getByIdForUser($id, Auth::id());

        if (!$store) {
            return back()->with('error', __('settings.woocommerce.store_not_found'));
        }

        $store->update([
            'is_active' => false,
            'connection_verified_at' => null,
        ]);

        return back()->with('success', __('settings.woocommerce.disconnected'));
    }

    /**
     * Reconnect WooCommerce store
     */
    public function reconnect(int $id)
    {
        $store = WooCommerceSettings::getByIdForUser($id, Auth::id());

        if (!$store) {
            return back()->with('error', __('settings.woocommerce.store_not_found'));
        }

        $store->update(['is_active' => true]);

        // Test connection
        $service = new WooCommerceApiService($store);
        $result = $service->testConnection();

        if ($result['success']) {
            $store->markAsVerified($result['store_info'] ?? []);
            return back()->with('success', __('settings.woocommerce.reconnected'));
        }

        return back()->with('error', __('settings.woocommerce.connection_failed') . ': ' . ($result['error'] ?? 'Unknown error'));
    }
}
