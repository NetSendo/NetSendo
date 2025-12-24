<?php

namespace App\Http\Controllers;

use App\Models\SmsProvider;
use App\Services\Sms\SmsProviderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Inertia\Inertia;

class SmsProviderController extends Controller
{
    public function __construct(
        private SmsProviderService $smsProviderService
    ) {}

    /**
     * Display a listing of SMS providers.
     */
    public function index()
    {
        $providers = auth()->user()->smsProviders()
            ->latest()
            ->get()
            ->map(fn($provider) => [
                'id' => $provider->id,
                'name' => $provider->name,
                'provider' => $provider->provider,
                'provider_label' => SmsProvider::getProviderTypes()[$provider->provider] ?? $provider->provider,
                'from_number' => $provider->from_number,
                'from_name' => $provider->from_name,
                'is_active' => $provider->is_active,
                'is_default' => $provider->is_default,
                'daily_limit' => $provider->daily_limit,
                'sent_today' => $provider->sent_today,
                'last_tested_at' => $provider->last_tested_at?->format('Y-m-d H:i'),
                'last_test_status' => $provider->last_test_status,
            ]);

        return Inertia::render('Settings/SmsProviders/Index', [
            'providers' => $providers,
            'availableProviders' => SmsProviderService::getAvailableProviders(),
        ]);
    }

    /**
     * Get credential fields for a provider type.
     */
    public function fields(string $provider)
    {
        return response()->json([
            'fields' => SmsProviderService::getProviderFields($provider),
        ]);
    }

    /**
     * Store a newly created SMS provider.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'provider' => 'required|string|in:twilio,smsapi,smsapi_com,vonage,messagebird,plivo',
            'credentials' => 'required|array',
            'from_number' => 'nullable|string|max:20',
            'from_name' => 'nullable|string|max:11',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'daily_limit' => 'nullable|integer|min:1',
        ]);

        $smsProvider = new SmsProvider([
            'name' => $validated['name'],
            'provider' => $validated['provider'],
            'from_number' => $validated['from_number'] ?? null,
            'from_name' => $validated['from_name'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'is_default' => $validated['is_default'] ?? false,
            'daily_limit' => $validated['daily_limit'] ?? null,
        ]);

        $smsProvider->user_id = auth()->id();
        $smsProvider->setCredentials($validated['credentials']);
        $smsProvider->save();

        // If set as default, update other providers
        if ($smsProvider->is_default) {
            $smsProvider->setAsDefault();
        }

        return redirect()->route('settings.sms-providers.index')
            ->with('success', 'Dostawca SMS został dodany.');
    }

    /**
     * Update the specified SMS provider.
     */
    public function update(Request $request, SmsProvider $smsProvider)
    {
        if ($smsProvider->user_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'credentials' => 'nullable|array',
            'from_number' => 'nullable|string|max:20',
            'from_name' => 'nullable|string|max:11',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'daily_limit' => 'nullable|integer|min:1',
        ]);

        $smsProvider->fill([
            'name' => $validated['name'],
            'from_number' => $validated['from_number'] ?? $smsProvider->from_number,
            'from_name' => $validated['from_name'] ?? $smsProvider->from_name,
            'is_active' => $validated['is_active'] ?? $smsProvider->is_active,
            'is_default' => $validated['is_default'] ?? $smsProvider->is_default,
            'daily_limit' => $validated['daily_limit'] ?? $smsProvider->daily_limit,
        ]);

        // Only update credentials if provided
        if (!empty($validated['credentials'])) {
            $smsProvider->setCredentials($validated['credentials']);
        }

        $smsProvider->save();

        // If set as default, update other providers
        if ($smsProvider->is_default) {
            $smsProvider->setAsDefault();
        }

        return redirect()->route('settings.sms-providers.index')
            ->with('success', 'Dostawca SMS został zaktualizowany.');
    }

    /**
     * Remove the specified SMS provider.
     */
    public function destroy(SmsProvider $smsProvider)
    {
        if ($smsProvider->user_id !== auth()->id()) {
            abort(403);
        }

        $smsProvider->delete();

        return redirect()->route('settings.sms-providers.index')
            ->with('success', 'Dostawca SMS został usunięty.');
    }

    /**
     * Test connection to the SMS provider.
     */
    public function test(SmsProvider $smsProvider)
    {
        if ($smsProvider->user_id !== auth()->id()) {
            abort(403);
        }

        try {
            $provider = $this->smsProviderService->getProvider($smsProvider);
            $result = $provider->testConnection();

            $smsProvider->updateTestStatus($result['success']);

            return response()->json($result);
        } catch (\Exception $e) {
            $smsProvider->updateTestStatus(false);

            return response()->json([
                'success' => false,
                'message' => 'Błąd: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Set as default provider.
     */
    public function setDefault(SmsProvider $smsProvider)
    {
        if ($smsProvider->user_id !== auth()->id()) {
            abort(403);
        }

        $smsProvider->setAsDefault();

        return response()->json([
            'success' => true,
            'message' => 'Ustawiono jako domyślny dostawca SMS.',
        ]);
    }
}
