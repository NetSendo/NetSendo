<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TagResource;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TagController extends Controller
{
    /**
     * Get all tags for the user
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $query = Tag::where('user_id', $user->id);

        // Search by name
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = min($request->get('per_page', 50), 100);

        return TagResource::collection($query->paginate($perPage));
    }

    /**
     * Get a single tag
     */
    public function show(Request $request, int $id): TagResource|JsonResponse
    {
        $user = $request->user();

        $tag = Tag::where('user_id', $user->id)->find($id);

        if (!$tag) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'Tag not found',
            ], 404);
        }

        return new TagResource($tag);
    }
}
