<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Models\ApiKey;
use App\Models\ContactList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Shared guards for the list-management endpoints: permission checks and
 * ownership-scoped list lookup. Every controller in this group resolves the
 * list through here so a key can never reach another account's data.
 */
trait ManagesContactLists
{
    /**
     * @return JsonResponse|null a 403 response when the key lacks the scope
     */
    protected function requirePermission(Request $request, string $permission): ?JsonResponse
    {
        // input() rather than get(): ApiKeyAuth merges the key into the input
        // source, which for a JSON request is the JSON bag that Symfony's
        // get() does not consult.
        $apiKey = $request->input('api_key');

        if (!$apiKey instanceof ApiKey || !$apiKey->hasPermission($permission)) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => "API key does not have the '{$permission}' permission",
            ], 403);
        }

        return null;
    }

    protected function findList(Request $request, int $id): ?ContactList
    {
        return ContactList::forUser($request->user()->id)->find($id);
    }

    protected function listNotFound(): JsonResponse
    {
        return response()->json([
            'error' => 'Not Found',
            'message' => 'Contact list not found',
        ], 404);
    }

    protected function badRequest(string $message): JsonResponse
    {
        return response()->json([
            'error' => 'Bad Request',
            'message' => $message,
        ], 422);
    }
}
