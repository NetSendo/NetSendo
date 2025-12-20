<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApiKeyController extends Controller
{
    /**
     * Display API keys management page
     */
    public function index(Request $request): Response
    {
        $apiKeys = ApiKey::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($key) {
                return [
                    'id' => $key->id,
                    'name' => $key->name,
                    'key_prefix' => $key->key_prefix,
                    'permissions' => $key->permissions,
                    'last_used_at' => $key->last_used_at?->format('Y-m-d H:i:s'),
                    'expires_at' => $key->expires_at?->format('Y-m-d H:i:s'),
                    'created_at' => $key->created_at->format('Y-m-d H:i:s'),
                    'is_expired' => $key->isExpired(),
                ];
            });

        return Inertia::render('Settings/ApiKeys/Index', [
            'apiKeys' => $apiKeys,
            'availablePermissions' => ApiKey::PERMISSIONS,
        ]);
    }

    /**
     * Create a new API key
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|in:' . implode(',', ApiKey::PERMISSIONS),
            'expires_at' => 'nullable|date|after:now',
        ]);

        $result = ApiKey::generate(
            userId: $request->user()->id,
            name: $validated['name'],
            permissions: $validated['permissions'] ?? ApiKey::PERMISSIONS,
        );

        // Update expiration if provided
        if (!empty($validated['expires_at'])) {
            $result['model']->update(['expires_at' => $validated['expires_at']]);
        }

        return response()->json([
            'message' => __('API key created successfully'),
            'key' => $result['key'], // Plain key - shown only once!
            'api_key' => [
                'id' => $result['model']->id,
                'name' => $result['model']->name,
                'key_prefix' => $result['model']->key_prefix,
                'permissions' => $result['model']->permissions,
                'created_at' => $result['model']->created_at->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    /**
     * Delete an API key
     */
    public function destroy(Request $request, ApiKey $apiKey)
    {
        // Ensure user owns the key
        if ($apiKey->user_id !== $request->user()->id) {
            abort(403);
        }

        $apiKey->delete();

        return redirect()->route('settings.api-keys.index')
            ->with('success', __('API key deleted successfully'));
    }
}
