<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\BrandPalette;
use App\Models\Media;
use App\Services\ColorExtractionService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BrandController extends Controller
{
    protected ColorExtractionService $colorService;

    public function __construct(ColorExtractionService $colorService)
    {
        $this->colorService = $colorService;
    }

    /**
     * Display a listing of brands.
     */
    public function index()
    {
        $brands = Brand::where('user_id', auth()->id())
            ->withCount('media')
            ->with(['logo:id,stored_path', 'palettes'])
            ->orderBy('name')
            ->get();

        return Inertia::render('Brands/Index', [
            'brands' => $brands,
        ]);
    }

    /**
     * Show the form for creating a new brand.
     */
    public function create()
    {
        // Get user's media for logo selection
        $userMedia = Media::where('user_id', auth()->id())
            ->whereIn('type', ['logo', 'image'])
            ->orderBy('created_at', 'desc')
            ->take(50)
            ->get();

        return Inertia::render('Brands/Edit', [
            'brand' => null,
            'userMedia' => $userMedia,
        ]);
    }

    /**
     * Store a newly created brand.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'primary_color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'secondary_color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'logo_media_id' => 'nullable|exists:media,id',
        ]);

        $brand = Brand::create([
            'user_id' => auth()->id(),
            ...$validated,
        ]);

        // Create default palette if colors are provided
        if ($validated['primary_color'] || $validated['secondary_color']) {
            $colors = array_filter([
                $validated['primary_color'] ?? null,
                $validated['secondary_color'] ?? null,
            ]);

            if (!empty($colors)) {
                $brand->palettes()->create([
                    'name' => 'Default',
                    'colors' => array_values($colors),
                    'is_default' => true,
                ]);
            }
        }

        return redirect()->route('brands.index')
            ->with('success', __('brands.created'));
    }

    /**
     * Display the specified brand.
     */
    public function show(Brand $brand)
    {
        $this->authorize('view', $brand);

        $brand->load(['logo', 'palettes', 'media']);

        // Get colors from logo if exists
        $logoColors = [];
        if ($brand->logo) {
            $logoColors = $brand->logo->colors()->orderBy('position')->get();
        }

        return Inertia::render('Brands/Show', [
            'brand' => $brand,
            'logoColors' => $logoColors,
        ]);
    }

    /**
     * Show the form for editing the specified brand.
     */
    public function edit(Brand $brand)
    {
        $this->authorize('update', $brand);

        $brand->load(['logo', 'palettes']);

        // Get user's media for logo selection
        $userMedia = Media::where('user_id', auth()->id())
            ->whereIn('type', ['logo', 'image'])
            ->orderBy('created_at', 'desc')
            ->take(50)
            ->get();

        return Inertia::render('Brands/Edit', [
            'brand' => $brand,
            'userMedia' => $userMedia,
        ]);
    }

    /**
     * Update the specified brand.
     */
    public function update(Request $request, Brand $brand)
    {
        $this->authorize('update', $brand);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'primary_color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'secondary_color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'logo_media_id' => 'nullable|exists:media,id',
        ]);

        $brand->update($validated);

        return redirect()->route('brands.index')
            ->with('success', __('brands.updated'));
    }

    /**
     * Remove the specified brand.
     */
    public function destroy(Brand $brand)
    {
        $this->authorize('delete', $brand);

        // Unlink media from brand before deleting
        Media::where('brand_id', $brand->id)->update(['brand_id' => null]);

        $brand->delete();

        return redirect()->route('brands.index')
            ->with('success', __('brands.deleted'));
    }

    /**
     * Store a new color palette for the brand.
     */
    public function storePalette(Request $request, Brand $brand)
    {
        $this->authorize('update', $brand);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'colors' => 'required|array|min:1|max:20',
            'colors.*' => 'string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'is_default' => 'boolean',
        ]);

        $palette = $brand->palettes()->create($validated);

        if ($validated['is_default'] ?? false) {
            $palette->setAsDefault();
        }

        return response()->json([
            'success' => true,
            'palette' => $palette,
        ]);
    }

    /**
     * Update a color palette.
     */
    public function updatePalette(Request $request, Brand $brand, BrandPalette $palette)
    {
        $this->authorize('update', $brand);

        if ($palette->brand_id !== $brand->id) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'colors' => 'required|array|min:1|max:20',
            'colors.*' => 'string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'is_default' => 'boolean',
        ]);

        $palette->update($validated);

        if ($validated['is_default'] ?? false) {
            $palette->setAsDefault();
        }

        return response()->json([
            'success' => true,
            'palette' => $palette,
        ]);
    }

    /**
     * Delete a color palette.
     */
    public function destroyPalette(Brand $brand, BrandPalette $palette)
    {
        $this->authorize('update', $brand);

        if ($palette->brand_id !== $brand->id) {
            abort(404);
        }

        $palette->delete();

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * Get all colors associated with a brand.
     */
    public function allColors(Brand $brand)
    {
        $this->authorize('view', $brand);

        $colors = $brand->all_colors;

        return response()->json([
            'colors' => $colors,
            'primary' => $brand->primary_color,
            'secondary' => $brand->secondary_color,
        ]);
    }

    /**
     * Extract colors from uploaded logo.
     */
    public function extractLogoColors(Request $request, Brand $brand)
    {
        $this->authorize('update', $brand);

        if (!$brand->logo) {
            return response()->json([
                'success' => false,
                'message' => 'Brand has no logo',
            ], 400);
        }

        $this->colorService->extractAndSaveColors($brand->logo);
        $colors = $brand->logo->colors()->orderBy('position')->get();

        // Auto-set primary and secondary colors if not set
        if (!$brand->primary_color && $colors->count() > 0) {
            $brand->primary_color = $colors->first()->hex_color;
        }
        if (!$brand->secondary_color && $colors->count() > 1) {
            $brand->secondary_color = $colors->skip(1)->first()->hex_color;
        }
        $brand->save();

        return response()->json([
            'success' => true,
            'colors' => $colors,
            'primary' => $brand->primary_color,
            'secondary' => $brand->secondary_color,
        ]);
    }
}
