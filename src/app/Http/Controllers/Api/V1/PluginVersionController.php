<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PluginConnection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PluginVersionController extends Controller
{
    /**
     * Check for available plugin updates
     *
     * GET /api/v1/plugin/check-version
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function check(Request $request): JsonResponse
    {
        $type = $request->input('type', 'woocommerce');
        $currentVersion = $request->input('version');

        $pluginConfig = config("netsendo.plugins.{$type}");

        if (!$pluginConfig) {
            return response()->json([
                'error' => 'Unknown plugin type',
            ], 400);
        }

        $latestVersion = $pluginConfig['version'] ?? null;
        $downloadUrl = $pluginConfig['download_url'] ?? null;

        $response = [
            'plugin_type' => $type,
            'latest_version' => $latestVersion,
            'download_url' => $downloadUrl ? url($downloadUrl) : null,
            'min_wp_version' => $pluginConfig['min_wp_version'] ?? null,
            'min_php_version' => $pluginConfig['min_php_version'] ?? null,
        ];

        if ($type === 'woocommerce') {
            $response['min_wc_version'] = $pluginConfig['min_wc_version'] ?? null;
        }

        if ($currentVersion) {
            $response['current_version'] = $currentVersion;
            $response['update_available'] = version_compare($currentVersion, $latestVersion, '<');
        }

        return response()->json($response);
    }

    /**
     * Record heartbeat from plugin
     *
     * POST /api/v1/plugin/heartbeat
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plugin_type' => 'required|in:wordpress,woocommerce',
            'site_url' => 'required|url|max:500',
            'site_name' => 'nullable|string|max:255',
            'plugin_version' => 'required|string|max:20',
            'wp_version' => 'nullable|string|max:20',
            'wc_version' => 'nullable|string|max:20',
            'php_version' => 'nullable|string|max:20',
            'site_info' => 'nullable|array',
        ]);

        $userId = $request->user()->id;

        // Normalize site URL (remove trailing slash)
        $siteUrl = rtrim($validated['site_url'], '/');

        try {
            $connection = PluginConnection::updateOrCreate(
                [
                    'user_id' => $userId,
                    'plugin_type' => $validated['plugin_type'],
                    'site_url' => $siteUrl,
                ],
                [
                    'site_name' => $validated['site_name'] ?? null,
                    'plugin_version' => $validated['plugin_version'],
                    'wp_version' => $validated['wp_version'] ?? null,
                    'wc_version' => $validated['wc_version'] ?? null,
                    'php_version' => $validated['php_version'] ?? null,
                    'site_info' => $validated['site_info'] ?? null,
                    'last_heartbeat_at' => now(),
                    'is_active' => true,
                ]
            );

            $latestVersion = config("netsendo.plugins.{$validated['plugin_type']}.version");
            $updateAvailable = version_compare($validated['plugin_version'], $latestVersion, '<');

            Log::info('Plugin heartbeat received', [
                'user_id' => $userId,
                'plugin_type' => $validated['plugin_type'],
                'site_url' => $siteUrl,
                'plugin_version' => $validated['plugin_version'],
                'update_available' => $updateAvailable,
            ]);

            return response()->json([
                'success' => true,
                'connection_id' => $connection->id,
                'update_available' => $updateAvailable,
                'latest_version' => $latestVersion,
                'download_url' => $updateAvailable
                    ? url(config("netsendo.plugins.{$validated['plugin_type']}.download_url"))
                    : null,
            ]);
        } catch (\Exception $e) {
            Log::error('Plugin heartbeat failed', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'plugin_type' => $validated['plugin_type'] ?? null,
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to record heartbeat',
            ], 500);
        }
    }

    /**
     * Get all plugin connections for the authenticated user
     *
     * GET /api/v1/plugin/connections
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function connections(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $connections = PluginConnection::where('user_id', $userId)
            ->where('is_active', true)
            ->orderBy('plugin_type')
            ->orderBy('site_name')
            ->get()
            ->map(function ($connection) {
                return [
                    'id' => $connection->id,
                    'plugin_type' => $connection->plugin_type,
                    'site_url' => $connection->site_url,
                    'site_name' => $connection->site_name,
                    'display_name' => $connection->display_name,
                    'plugin_version' => $connection->plugin_version,
                    'wp_version' => $connection->wp_version,
                    'wc_version' => $connection->wc_version,
                    'php_version' => $connection->php_version,
                    'last_heartbeat_at' => $connection->last_heartbeat_at?->toIso8601String(),
                    'update_available' => $connection->update_available,
                    'latest_version' => $connection->latest_version,
                    'is_stale' => $connection->is_stale,
                ];
            });

        $stats = [
            'total' => $connections->count(),
            'wordpress' => $connections->where('plugin_type', 'wordpress')->count(),
            'woocommerce' => $connections->where('plugin_type', 'woocommerce')->count(),
            'needs_update' => $connections->where('update_available', true)->count(),
            'stale' => $connections->where('is_stale', true)->count(),
        ];

        return response()->json([
            'connections' => $connections->values(),
            'stats' => $stats,
        ]);
    }

    /**
     * Remove a plugin connection
     *
     * DELETE /api/v1/plugin/connections/{id}
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $connection = PluginConnection::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$connection) {
            return response()->json([
                'error' => 'Connection not found',
            ], 404);
        }

        $connection->delete();

        return response()->json([
            'success' => true,
            'message' => 'Connection removed',
        ]);
    }

    /**
     * Mark a connection as inactive (soft disconnect)
     *
     * POST /api/v1/plugin/connections/{id}/disconnect
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function disconnect(Request $request, int $id): JsonResponse
    {
        $connection = PluginConnection::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$connection) {
            return response()->json([
                'error' => 'Connection not found',
            ], 404);
        }

        $connection->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Connection disconnected',
        ]);
    }
}
