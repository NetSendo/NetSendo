<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class McpController extends Controller
{
    /**
     * Test MCP connection.
     *
     * Public endpoint for AI assistants and external tools
     * to verify their API key and connection to NetSendo.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function test(Request $request): JsonResponse
    {
        $user = $request->user();
        $apiKey = $request->get('api_key');

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized',
                'message' => 'Unable to verify API key',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'message' => 'Connection successful',
            'data' => [
                'account_name' => $user->name,
                'account_email' => $user->email,
                'api_url' => config('app.url'),
                'version' => config('app.version', '1.0.0'),
                'mcp_enabled' => true,
                'api_key_name' => $apiKey?->name ?? 'Unknown',
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }
}
