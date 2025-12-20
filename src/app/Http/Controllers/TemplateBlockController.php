<?php

namespace App\Http\Controllers;

use App\Models\TemplateBlock;
use Illuminate\Http\Request;

class TemplateBlockController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $blocks = TemplateBlock::availableToUser(auth()->id())
            ->latest()
            ->paginate(20);

        return response()->json([
            'blocks' => $blocks,
            'blockTypes' => TemplateBlock::BLOCK_TYPES,
            'blockCategories' => TemplateBlock::BLOCK_CATEGORIES,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:' . implode(',', array_keys(TemplateBlock::BLOCK_TYPES)),
            'content' => 'required|array',
            'settings' => 'nullable|array',
        ]);

        $block = auth()->user()->templateBlocks()->create([
            'name' => $validated['name'],
            'type' => $validated['type'],
            'content' => $validated['content'],
            'settings' => $validated['settings'] ?? TemplateBlock::getDefaultSettings($validated['type']),
            'is_global' => false,
        ]);

        return response()->json([
            'success' => true,
            'block' => $block,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, TemplateBlock $templateBlock)
    {
        if ($templateBlock->user_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'content' => 'required|array',
            'settings' => 'nullable|array',
        ]);

        $templateBlock->update($validated);

        return response()->json([
            'success' => true,
            'block' => $templateBlock,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TemplateBlock $templateBlock)
    {
        if ($templateBlock->user_id !== auth()->id()) {
            abort(403);
        }

        $templateBlock->delete();

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * Get default content for a block type
     */
    public function defaults(Request $request)
    {
        $request->validate([
            'type' => 'required|string',
        ]);

        $type = $request->type;

        return response()->json([
            'content' => TemplateBlock::getDefaultContent($type),
            'settings' => TemplateBlock::getDefaultSettings($type),
        ]);
    }
}
