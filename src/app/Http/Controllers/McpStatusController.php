<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use App\Models\McpStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class McpStatusController extends Controller
{
    /**
     * Get current MCP status
     */
    public function status()
    {
        $latest = McpStatus::getLatestStatus();

        // Check for MCP key in database first, then fall back to ENV
        $mcpKey = ApiKey::getMcpKey();
        $envApiKey = config('services.mcp.api_key') ?: env('MCP_API_KEY');
        $isConfigured = $mcpKey !== null || !empty($envApiKey);

        if (!$latest) {
            return response()->json([
                'is_configured' => $isConfigured,
                'has_been_tested' => false,
                'status' => $isConfigured ? 'pending' : 'not_configured',
                'message' => $isConfigured ? null : 'MCP API key not configured',
                'tested_at' => null,
                'tested_at_human' => null,
            ]);
        }

        return response()->json([
            'is_configured' => $isConfigured,
            'has_been_tested' => true,
            'status' => $latest->status,
            'message' => $latest->message,
            'version' => $latest->version,
            'api_url' => $latest->api_url,
            'tested_at' => $latest->tested_at?->toIso8601String(),
            'tested_at_human' => $latest->tested_at?->diffForHumans(),
            'is_recent' => McpStatus::isRecentlyTested(),
        ]);
    }

    /**
     * Manually trigger MCP connection test
     */
    public function test(Request $request)
    {
        try {
            // Run the test command
            $exitCode = Artisan::call('mcp:test-connection', ['--silent' => true]);

            // Get the latest status after the test
            $latest = McpStatus::getLatestStatus();

            if ($exitCode === 0 && $latest && $latest->status === 'success') {
                return response()->json([
                    'success' => true,
                    'message' => $latest->message,
                    'version' => $latest->version,
                    'tested_at' => $latest->tested_at?->toIso8601String(),
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $latest?->message ?? 'Unknown error during connection test',
                'tested_at' => $latest?->tested_at?->toIso8601String(),
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error running test: ' . $e->getMessage(),
            ], 500);
        }
    }
}

