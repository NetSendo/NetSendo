<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\MediaFolder;
use App\Models\MediaColor;
use App\Services\ColorExtractionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class MediaController extends Controller
{
    protected ColorExtractionService $colorService;

    public function __construct(ColorExtractionService $colorService)
    {
        $this->colorService = $colorService;
    }

    /**
     * Display the media library.
     */
    public function index(Request $request)
    {
        $query = Media::where('user_id', auth()->id())
            ->with(['brand:id,name', 'folder:id,name', 'colors']);

        // Apply filters
        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }

        if ($request->filled('folder_id')) {
            $query->where('folder_id', $request->folder_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        // Sorting
        $sortField = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        $media = $query->paginate(24)->withQueryString();

        // Get folders for sidebar
        $folders = MediaFolder::where('user_id', auth()->id())
            ->whereNull('parent_id')
            ->with('children')
            ->get();

        // Get brands for filter
        $brands = auth()->user()->brands ?? collect();

        return Inertia::render('Media/Index', [
            'media' => $media,
            'folders' => $folders,
            'brands' => $brands,
            'filters' => $request->only(['brand_id', 'folder_id', 'type', 'search', 'sort', 'direction']),
        ]);
    }

    /**
     * Upload a new media file.
     */
    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:10240|mimetypes:image/jpeg,image/png,image/gif,image/webp,image/svg+xml,application/pdf',
            'brand_id' => 'nullable|exists:brands,id',
            'folder_id' => 'nullable|exists:media_folders,id',
            'type' => 'nullable|in:image,logo,icon,document',
            'alt_text' => 'nullable|string|max:255',
            'tags' => 'nullable|array',
        ]);

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $mimeType = $file->getMimeType();

        // Generate unique filename
        $filename = Str::uuid() . '.' . $extension;
        $path = 'media/' . auth()->id() . '/' . date('Y/m');

        // Store file
        $storedPath = $file->storeAs($path, $filename, 'public');

        // Get image dimensions if applicable
        $width = null;
        $height = null;
        if (str_starts_with($mimeType, 'image/')) {
            $imageInfo = getimagesize($file->getPathname());
            if ($imageInfo) {
                $width = $imageInfo[0];
                $height = $imageInfo[1];
            }
        }

        // Create media record
        $media = Media::create([
            'user_id' => auth()->id(),
            'brand_id' => $request->brand_id,
            'folder_id' => $request->folder_id,
            'original_name' => $originalName,
            'stored_path' => $storedPath,
            'mime_type' => $mimeType,
            'size' => $file->getSize(),
            'width' => $width,
            'height' => $height,
            'type' => $request->type ?? 'image',
            'alt_text' => $request->alt_text,
            'tags' => $request->tags,
        ]);

        // Extract colors from image
        if ($media->isImage()) {
            $this->colorService->extractAndSaveColors($media);
            $media->load('colors');
        }

        return response()->json([
            'success' => true,
            'media' => $media,
            'url' => $media->url,
        ]);
    }

    /**
     * Upload multiple files.
     */
    public function bulkStore(Request $request)
    {
        $request->validate([
            'files' => 'required|array|max:20',
            'files.*' => 'file|max:10240|mimetypes:image/jpeg,image/png,image/gif,image/webp,image/svg+xml,application/pdf',
            'brand_id' => 'nullable|exists:brands,id',
            'folder_id' => 'nullable|exists:media_folders,id',
        ]);

        $uploaded = [];

        foreach ($request->file('files') as $file) {
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $mimeType = $file->getMimeType();

            $filename = Str::uuid() . '.' . $extension;
            $path = 'media/' . auth()->id() . '/' . date('Y/m');
            $storedPath = $file->storeAs($path, $filename, 'public');

            $width = null;
            $height = null;
            if (str_starts_with($mimeType, 'image/')) {
                $imageInfo = getimagesize($file->getPathname());
                if ($imageInfo) {
                    $width = $imageInfo[0];
                    $height = $imageInfo[1];
                }
            }

            $media = Media::create([
                'user_id' => auth()->id(),
                'brand_id' => $request->brand_id,
                'folder_id' => $request->folder_id,
                'original_name' => $originalName,
                'stored_path' => $storedPath,
                'mime_type' => $mimeType,
                'size' => $file->getSize(),
                'width' => $width,
                'height' => $height,
                'type' => 'image',
            ]);

            if ($media->isImage()) {
                $this->colorService->extractAndSaveColors($media);
            }

            $uploaded[] = $media;
        }

        return response()->json([
            'success' => true,
            'count' => count($uploaded),
            'media' => $uploaded,
        ]);
    }

    /**
     * Show media details.
     */
    public function show(Media $media)
    {
        $this->authorize('view', $media);

        $media->load(['brand', 'folder', 'colors']);

        return Inertia::render('Media/Show', [
            'media' => $media,
        ]);
    }

    /**
     * Update media metadata.
     */
    public function update(Request $request, Media $media)
    {
        $this->authorize('update', $media);

        $validated = $request->validate([
            'brand_id' => 'nullable|exists:brands,id',
            'folder_id' => 'nullable|exists:media_folders,id',
            'type' => 'nullable|in:image,logo,icon,document',
            'alt_text' => 'nullable|string|max:255',
            'tags' => 'nullable|array',
        ]);

        $media->update($validated);

        return response()->json([
            'success' => true,
            'media' => $media,
        ]);
    }

    /**
     * Delete a media file.
     */
    public function destroy(Media $media)
    {
        $this->authorize('delete', $media);

        $media->delete();

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * Bulk delete media files.
     */
    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:media,id',
        ]);

        $deleted = Media::where('user_id', auth()->id())
            ->whereIn('id', $request->ids)
            ->get()
            ->each->delete();

        return response()->json([
            'success' => true,
            'count' => $deleted->count(),
        ]);
    }

    /**
     * Move media to a folder.
     */
    public function move(Request $request, Media $media)
    {
        $this->authorize('update', $media);

        $request->validate([
            'folder_id' => 'nullable|exists:media_folders,id',
        ]);

        $media->update(['folder_id' => $request->folder_id]);

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * Get extracted colors for a media item.
     */
    public function colors(Media $media)
    {
        $this->authorize('view', $media);

        return response()->json([
            'colors' => $media->colors()->orderBy('position')->get(),
        ]);
    }

    /**
     * Search media for WYSIWYG integration.
     */
    public function search(Request $request)
    {
        // All media are shared across all users
        $query = Media::where('type', '!=', 'document');

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }

        $media = $query->orderBy('created_at', 'desc')
            ->take(50)
            ->get(['id', 'original_name', 'stored_path', 'width', 'height', 'type']);

        return response()->json([
            'media' => $media->map(fn($m) => [
                'id' => $m->id,
                'name' => $m->original_name,
                'url' => $m->url,
                'width' => $m->width,
                'height' => $m->height,
                'type' => $m->type,
            ]),
        ]);
    }

    /**
     * Get all colors available to the user (from brands and media).
     */
    public function allUserColors()
    {
        $userId = auth()->id();

        // Get colors from brand palettes
        $brandColors = \App\Models\BrandPalette::whereHas('brand', fn($q) => $q->where('user_id', $userId))
            ->get()
            ->pluck('colors')
            ->flatten()
            ->unique()
            ->values();

        // Get dominant colors from user's media
        $mediaColors = MediaColor::whereHas('media', fn($q) => $q->where('user_id', $userId))
            ->where('is_dominant', true)
            ->orderBy('population', 'desc')
            ->take(20)
            ->pluck('hex_color')
            ->unique()
            ->values();

        // Combine and return
        $allColors = $brandColors->merge($mediaColors)->unique()->values();

        return response()->json([
            'colors' => $allColors,
            'brand_colors' => $brandColors,
            'media_colors' => $mediaColors,
        ]);
    }
}
