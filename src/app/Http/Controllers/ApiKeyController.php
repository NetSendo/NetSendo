<?php

namespace App\Http\Controllers;

use App\Helpers\DateHelper;
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
                    'is_mcp' => $key->is_mcp,
                    'last_used_at' => DateHelper::formatFullForUser($key->last_used_at),
                    'expires_at' => DateHelper::formatFullForUser($key->expires_at),
                    'created_at' => DateHelper::formatFullForUser($key->created_at),
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
            'is_mcp' => 'nullable|boolean',
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

        // Handle MCP flag - pass plain key for encryption
        if (!empty($validated['is_mcp'])) {
            $result['model']->markAsMcp($result['key']);
        }

        return response()->json([
            'message' => __('API key created successfully'),
            'key' => $result['key'], // Plain key - shown only once!
            'api_key' => [
                'id' => $result['model']->id,
                'name' => $result['model']->name,
                'key_prefix' => $result['model']->key_prefix,
                'permissions' => $result['model']->permissions,
                'is_mcp' => $result['model']->is_mcp,
                'created_at' => DateHelper::formatFullForUser($result['model']->created_at),
            ],
        ]);
    }

    /**
     * Update an API key (permissions, is_mcp flag)
     */
    public function update(Request $request, ApiKey $apiKey)
    {
        // Ensure user owns the key
        if ($apiKey->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|in:' . implode(',', ApiKey::PERMISSIONS),
            'is_mcp' => 'nullable|boolean',
            'plain_key' => 'nullable|string|starts_with:ns_live_',
        ]);

        // Update name if provided
        if (isset($validated['name'])) {
            $apiKey->update(['name' => $validated['name']]);
        }

        // Update permissions if provided
        if (isset($validated['permissions'])) {
            $apiKey->update(['permissions' => $validated['permissions']]);
        }

        // Handle MCP flag
        if (isset($validated['is_mcp'])) {
            if ($validated['is_mcp']) {
                // Pass plain key for encryption if provided
                $plainKey = $validated['plain_key'] ?? null;
                $apiKey->markAsMcp($plainKey);
            } else {
                $apiKey->unmarkAsMcp();
            }
        }

        return redirect()->route('settings.api-keys.index')
            ->with('success', __('API key updated successfully'));
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

