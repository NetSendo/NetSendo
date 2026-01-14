<?php

namespace App\Http\Controllers;

use App\Models\MediaFolder;
use Illuminate\Http\Request;

class MediaFolderController extends Controller
{
    /**
     * Get all folders for the current user.
     */
    public function index()
    {
        $folders = MediaFolder::where('user_id', auth()->id())
            ->whereNull('parent_id')
            ->with('children')
            ->withCount('media')
            ->orderBy('name')
            ->get();

        return response()->json([
            'folders' => $folders,
        ]);
    }

    /**
     * Create a new folder.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:media_folders,id',
        ]);

        // Verify parent belongs to user
        if ($validated['parent_id']) {
            $parent = MediaFolder::where('user_id', auth()->id())
                ->find($validated['parent_id']);
            if (!$parent) {
                return response()->json(['error' => 'Invalid parent folder'], 403);
            }
        }

        $folder = MediaFolder::create([
            'user_id' => auth()->id(),
            'name' => $validated['name'],
            'parent_id' => $validated['parent_id'],
        ]);

        return response()->json([
            'success' => true,
            'folder' => $folder,
        ]);
    }

    /**
     * Update a folder.
     */
    public function update(Request $request, MediaFolder $mediaFolder)
    {
        if ($mediaFolder->user_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:media_folders,id',
        ]);

        // Prevent circular reference
        if ($validated['parent_id'] == $mediaFolder->id) {
            return response()->json(['error' => 'Cannot set folder as its own parent'], 422);
        }

        // Verify parent belongs to user
        if ($validated['parent_id']) {
            $parent = MediaFolder::where('user_id', auth()->id())
                ->find($validated['parent_id']);
            if (!$parent) {
                return response()->json(['error' => 'Invalid parent folder'], 403);
            }
        }

        $mediaFolder->update($validated);

        return response()->json([
            'success' => true,
            'folder' => $mediaFolder,
        ]);
    }

    /**
     * Delete a folder.
     */
    public function destroy(MediaFolder $mediaFolder)
    {
        if ($mediaFolder->user_id !== auth()->id()) {
            abort(403);
        }

        // Move media to root before deleting
        $mediaFolder->media()->update(['folder_id' => null]);

        // Move child folders to parent
        $mediaFolder->children()->update(['parent_id' => $mediaFolder->parent_id]);

        $mediaFolder->delete();

        return response()->json([
            'success' => true,
        ]);
    }
}
