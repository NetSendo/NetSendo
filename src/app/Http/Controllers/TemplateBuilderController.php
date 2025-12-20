<?php

namespace App\Http\Controllers;

use App\Models\Template;
use App\Services\MjmlService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TemplateBuilderController extends Controller
{
    protected MjmlService $mjmlService;

    public function __construct(MjmlService $mjmlService)
    {
        $this->mjmlService = $mjmlService;
    }

    /**
     * Save template JSON structure (auto-save)
     */
    public function saveStructure(Request $request, Template $template)
    {
        if ($template->user_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'json_structure' => 'required|array',
            'settings' => 'nullable|array',
        ]);

        // Generate MJML
        $settings = $validated['settings'] ?? $template->getSettingsWithDefaults();
        $mjml = $this->mjmlService->jsonToMjml($validated['json_structure'], $settings);

        $template->update([
            'json_structure' => $validated['json_structure'],
            'settings' => $validated['settings'] ?? $template->settings,
            'mjml_content' => $mjml,
        ]);

        return response()->json([
            'success' => true,
            'saved_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Compile JSON structure to MJML (for preview)
     */
    public function compile(Request $request)
    {
        $validated = $request->validate([
            'json_structure' => 'required|array',
            'settings' => 'nullable|array',
        ]);

        $settings = $validated['settings'] ?? Template::defaultSettings();
        $mjml = $this->mjmlService->jsonToMjml($validated['json_structure'], $settings);

        // Validate MJML
        $validation = $this->mjmlService->validate($mjml);

        return response()->json([
            'mjml' => $mjml,
            'valid' => $validation['valid'],
            'errors' => $validation['errors'] ?? [],
        ]);
    }

    /**
     * Upload image for template
     */
    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,gif,webp|max:5120',
        ]);

        $file = $request->file('image');
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        
        // Store in public disk under templates folder
        $path = $file->storeAs('templates/images', $filename, 'public');

        // Return relative URL that works with any port
        $url = '/storage/' . $path;

        return response()->json([
            'success' => true,
            'url' => $url,
            'path' => $path,
        ]);
    }

    /**
     * Generate thumbnail for template
     */
    public function generateThumbnail(Request $request, Template $template)
    {
        if ($template->user_id !== auth()->id()) {
            abort(403);
        }

        $request->validate([
            'thumbnail' => 'required|string', // Base64 image data
        ]);

        // Decode base64 and save
        $imageData = $request->thumbnail;
        
        // Remove data URL prefix if present
        if (str_contains($imageData, ',')) {
            $imageData = explode(',', $imageData)[1];
        }

        $imageContent = base64_decode($imageData);
        $filename = 'template-' . $template->id . '-' . time() . '.png';
        $path = 'templates/thumbnails/' . $filename;

        Storage::disk('public')->put($path, $imageContent);

        $template->update([
            'thumbnail' => Storage::disk('public')->url($path),
        ]);

        return response()->json([
            'success' => true,
            'thumbnail' => $template->thumbnail,
        ]);
    }

    /**
     * Upload thumbnail file for template
     */
    public function uploadThumbnail(Request $request)
    {
        $request->validate([
            'thumbnail' => 'required|image|mimes:jpeg,png,gif,webp|max:2048',
            'template_id' => 'required|integer',
        ]);

        $template = Template::where('id', $request->template_id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $file = $request->file('thumbnail');
        $filename = 'template-' . $template->id . '-' . time() . '.' . $file->getClientOriginalExtension();
        
        // Store in public disk under templates/thumbnails folder
        $path = $file->storeAs('templates/thumbnails', $filename, 'public');

        // Use relative URL for consistency across different ports/hosts
        $url = '/storage/' . $path;

        $template->update([
            'thumbnail' => $url,
        ]);

        return response()->json([
            'success' => true,
            'thumbnail' => $url,
        ]);
    }

    /**
     * Get block defaults for a specific type
     */
    public function getBlockDefaults(Request $request)
    {
        $request->validate([
            'type' => 'required|string',
        ]);

        $type = $request->type;
        $content = \App\Models\TemplateBlock::getDefaultContent($type);
        $settings = \App\Models\TemplateBlock::getDefaultSettings($type);

        return response()->json([
            'content' => $content,
            'settings' => $settings,
        ]);
    }
}
