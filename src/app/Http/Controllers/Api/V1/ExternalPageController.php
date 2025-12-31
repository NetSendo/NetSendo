<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ExternalPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExternalPageController extends Controller
{
    /**
     * Get list of external pages for the authenticated user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->attributes->get('api_user');

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $pages = ExternalPage::where('user_id', $user->id)
            ->select(['id', 'name', 'url'])
            ->orderBy('name')
            ->get()
            ->map(function ($page) {
                return [
                    'id' => $page->id,
                    'name' => $page->name,
                    'url' => $page->url,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $pages,
        ]);
    }
}
