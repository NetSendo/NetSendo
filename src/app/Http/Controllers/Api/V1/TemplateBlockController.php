<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ManagesContactLists;
use App\Http\Controllers\Controller;
use App\Models\TemplateBlock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * API Controller for the template builder's saved blocks ("Saved blocks" in
 * the builder sidebar) and the block-type reference needed to build a
 * template's json_structure.
 *
 * Endpoints:
 * - GET    /api/v1/template-blocks/types - Block types with default content (messages:read)
 * - GET    /api/v1/template-blocks       - Own + global saved blocks (messages:read)
 * - POST   /api/v1/template-blocks       - Save a block (messages:write)
 * - PUT    /api/v1/template-blocks/{id}  - Update an own block (messages:write)
 * - DELETE /api/v1/template-blocks/{id}  - Delete an own block (messages:write)
 */
class TemplateBlockController extends Controller
{
    use ManagesContactLists;

    public function types(Request $request): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'messages:read')) {
            return $denied;
        }

        $types = collect(TemplateBlock::BLOCK_TYPES)->map(fn (array $info, string $type) => [
            'type' => $type,
            'label' => $info['label'],
            'category' => $info['category'],
            'default_content' => TemplateBlock::getDefaultContent($type),
            'default_settings' => TemplateBlock::getDefaultSettings($type),
        ])->values();

        return response()->json([
            'data' => $types,
            'categories' => TemplateBlock::BLOCK_CATEGORIES,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'messages:read')) {
            return $denied;
        }

        $validated = $request->validate([
            'type' => ['nullable', Rule::in(array_keys(TemplateBlock::BLOCK_TYPES))],
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        $query = $this->available($request->user()->id);

        if (!empty($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        if (!empty($validated['search'])) {
            $query->where('name', 'like', '%' . $validated['search'] . '%');
        }

        $blocks = $query->latest()->orderByDesc('id')->paginate($validated['per_page'] ?? 25);

        return response()->json([
            'data' => collect($blocks->items())->map(fn (TemplateBlock $b) => $this->present($b, $request->user()->id))->all(),
            'meta' => [
                'current_page' => $blocks->currentPage(),
                'last_page' => $blocks->lastPage(),
                'per_page' => $blocks->perPage(),
                'total' => $blocks->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'messages:write')) {
            return $denied;
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => ['required', Rule::in(array_keys(TemplateBlock::BLOCK_TYPES))],
            'content' => 'required|array',
            'settings' => 'nullable|array',
        ]);

        // Same defaults as the panel's "Save block".
        $block = $request->user()->templateBlocks()->create([
            'name' => $validated['name'],
            'type' => $validated['type'],
            'content' => $request->input('content'),
            'settings' => $request->input('settings') ?? TemplateBlock::getDefaultSettings($validated['type']),
            'is_global' => false,
        ]);

        return response()->json([
            'data' => $this->present($block->fresh(), $request->user()->id),
            'message' => 'Block saved successfully',
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'messages:write')) {
            return $denied;
        }

        $block = $this->findOwn($request, $id);
        if ($block instanceof JsonResponse) {
            return $block;
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'content' => 'sometimes|required|array',
            'settings' => 'nullable|array',
        ]);

        foreach (['content', 'settings'] as $key) {
            if (array_key_exists($key, $validated)) {
                $validated[$key] = $request->input($key);
            }
        }

        $block->update($validated);

        return response()->json([
            'data' => $this->present($block->fresh(), $request->user()->id),
            'message' => 'Block updated successfully',
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'messages:write')) {
            return $denied;
        }

        $block = $this->findOwn($request, $id);
        if ($block instanceof JsonResponse) {
            return $block;
        }

        $block->delete();

        return response()->json([
            'data' => ['id' => $id, 'deleted' => true],
            'message' => 'Block deleted successfully',
        ]);
    }

    protected function available(int $userId): Builder
    {
        return TemplateBlock::query()->where(
            fn (Builder $q) => $q->where('user_id', $userId)->orWhere('is_global', true)
        );
    }

    protected function findOwn(Request $request, int $id): TemplateBlock|JsonResponse
    {
        $userId = $request->user()->id;
        $block = $this->available($userId)->find($id);

        if (!$block) {
            return response()->json(['error' => 'Not Found', 'message' => 'Block not found'], 404);
        }

        if ($block->user_id !== $userId) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'Global blocks are read-only. Save a copy with POST /template-blocks.',
            ], 403);
        }

        return $block;
    }

    protected function present(TemplateBlock $block, int $userId): array
    {
        return [
            'id' => $block->id,
            'name' => $block->name,
            'type' => $block->type,
            'content' => $block->content,
            'settings' => $block->settings,
            'is_global' => (bool) $block->is_global,
            'editable' => $block->user_id === $userId,
            'usage_count' => (int) $block->usage_count,
            'created_at' => $block->created_at?->toIso8601String(),
            'updated_at' => $block->updated_at?->toIso8601String(),
        ];
    }
}
