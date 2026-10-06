<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ManagesContactLists;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TagResource;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TagController extends Controller
{
    use ManagesContactLists;

    private const SORTABLE = ['id', 'name', 'color', 'created_at', 'updated_at'];

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

        // Sorting (whitelisted: the values used to reach orderBy unchecked)
        $sortBy = in_array($request->get('sort_by'), self::SORTABLE, true) ? $request->get('sort_by') : 'name';
        $sortOrder = strtolower((string) $request->get('sort_order', 'asc')) === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 50);

        return TagResource::collection($query->paginate($perPage));
    }

    /**
     * Get a single tag
     */
    public function show(Request $request, int $id): TagResource|JsonResponse
    {
        $tag = $this->findTag($request, $id);

        if (!$tag) {
            return $this->tagNotFound();
        }

        return new TagResource($tag);
    }

    /**
     * Create a tag. Mirrors the web TagController (name, color, description,
     * default colour #3b82f6) and additionally refuses a duplicate name within
     * the account, so an agent cannot create two tags it cannot tell apart.
     */
    public function store(Request $request): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'lists:write')) {
            return $denied;
        }

        $validated = $request->validate($this->rules($request));

        $tag = Tag::create([
            'name' => trim($validated['name']),
            'color' => $validated['color'] ?? '#3b82f6',
            'description' => $validated['description'] ?? null,
            'user_id' => $request->user()->id,
        ]);

        return response()->json([
            'data' => (new TagResource($tag))->toArray($request),
            'message' => "Tag '{$tag->name}' created",
        ], 201);
    }

    /**
     * Update a tag (partial: only the fields sent are changed).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'lists:write')) {
            return $denied;
        }

        $tag = $this->findTag($request, $id);

        if (!$tag) {
            return $this->tagNotFound();
        }

        $validated = $request->validate($this->rules($request, $tag));

        if (array_key_exists('name', $validated)) {
            $validated['name'] = trim($validated['name']);
        }
        if (array_key_exists('color', $validated) && $validated['color'] === null) {
            $validated['color'] = '#3b82f6';
        }

        $tag->update($validated);

        return response()->json([
            'data' => (new TagResource($tag->fresh()))->toArray($request),
            'message' => "Tag '{$tag->name}' updated",
        ]);
    }

    /**
     * Delete a tag. Like the web controller it detaches the tag from contact
     * lists first; it also removes it from subscribers and messages so no
     * pivot rows are left behind on drivers without FK cascades. A tag still
     * held by subscribers is only deleted with confirm=true.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'lists:write')) {
            return $denied;
        }

        $tag = $this->findTag($request, $id);

        if (!$tag) {
            return $this->tagNotFound();
        }

        $subscriberCount = $tag->subscribers()->count();

        if ($subscriberCount > 0 && !$request->boolean('confirm')) {
            return response()->json([
                'error' => 'Confirmation Required',
                'message' => "Tag '{$tag->name}' is assigned to {$subscriberCount} subscriber(s). Re-send with confirm=true to remove it from them and delete it.",
                'subscribers_count' => $subscriberCount,
            ], 409);
        }

        $name = $tag->name;
        $listCount = $tag->contactLists()->count();

        DB::transaction(function () use ($tag) {
            $tag->contactLists()->detach();
            $tag->messages()->detach();
            $tag->subscribers()->detach();
            $tag->delete();
        });

        return response()->json([
            'message' => "Tag '{$name}' deleted",
            'tag_id' => $id,
            'subscribers_detached' => $subscriberCount,
            'lists_detached' => $listCount,
        ]);
    }

    private function rules(Request $request, ?Tag $tag = null): array
    {
        $unique = Rule::unique('tags', 'name')->where('user_id', $request->user()->id);
        if ($tag) {
            $unique->ignore($tag->id);
        }

        return [
            'name' => [$tag ? 'sometimes' : 'required', 'required', 'string', 'max:255', $unique],
            'color' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    private function findTag(Request $request, int $id): ?Tag
    {
        return Tag::where('user_id', $request->user()->id)->find($id);
    }

    private function tagNotFound(): JsonResponse
    {
        return response()->json([
            'error' => 'Not Found',
            'message' => 'Tag not found',
        ], 404);
    }
}
