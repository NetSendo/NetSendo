<?php

namespace App\Http\Controllers;

use App\Models\Template;
use App\Models\TemplateBlock;
use App\Models\TemplateCategory;
use App\Services\MjmlService;
use App\Services\TemplateAiService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\InsertController;

class TemplateController extends Controller
{
    protected MjmlService $mjmlService;
    protected TemplateAiService $aiService;

    public function __construct(MjmlService $mjmlService, TemplateAiService $aiService)
    {
        $this->mjmlService = $mjmlService;
        $this->aiService = $aiService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = auth()->user()->templates()->latest();

        // Filter by category
        if ($request->filled('category')) {
            $query->where('category_id', $request->category);
        }

        // Filter by search
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Get categories for filter dropdown
        $categories = TemplateCategory::forUser(auth()->id())
            ->orderBy('sort_order')
            ->get();

        // Get starter templates (system templates)
        $starterTemplates = Template::starter()
            ->get()
            ->map(fn ($template) => [
                'id' => $template->id,
                'name' => $template->name,
                'thumbnail' => $template->thumbnail,
                'category' => $template->category,
                'has_blocks' => $template->hasBlocksStructure(),
                'json_structure' => $template->json_structure,
                'settings' => $template->getSettingsWithDefaults(),
            ]);

        return Inertia::render('Template/Index', [
            'templates' => $query
                ->with('templateCategory')
                ->paginate(12)
                ->through(fn ($template) => [
                    'id' => $template->id,
                    'name' => $template->name,
                    'thumbnail' => $template->thumbnail,
                    'category' => $template->category,
                    'category_id' => $template->category_id,
                    'category_data' => $template->templateCategory ? [
                        'id' => $template->templateCategory->id,
                        'name' => $template->templateCategory->name,
                        'slug' => $template->templateCategory->slug,
                        'color' => $template->templateCategory->color,
                    ] : null,
                    'is_public' => $template->is_public,
                    'has_blocks' => $template->hasBlocksStructure(),
                    'json_structure' => $template->json_structure,
                    'settings' => $template->getSettingsWithDefaults(),
                    'created_at' => $template->created_at->diffForHumans(),
                    'updated_at' => $template->updated_at->diffForHumans(),
                ]),
            'starterTemplates' => $starterTemplates,
            'categories' => $categories,
            'filters' => [
                'search' => $request->search,
                'category' => $request->category,
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        // Seed system categories if not exist
        TemplateCategory::seedSystemCategories();

        $categories = TemplateCategory::forUser(auth()->id())
            ->orderBy('sort_order')
            ->get();

        // Get block types for builder
        $blockTypes = TemplateBlock::BLOCK_TYPES;
        $blockCategories = TemplateBlock::BLOCK_CATEGORIES;

        // Get user's saved blocks
        $savedBlocks = TemplateBlock::availableToUser(auth()->id())
            ->latest()
            ->take(20)
            ->get();

        // Check if AI is available
        $aiAvailable = $this->aiService->isAvailable();

        // Starting from a starter template?
        $starterId = $request->query('starter');
        $starterTemplate = null;
        if ($starterId) {
            $starterTemplate = Template::where('id', $starterId)
                ->where(function ($q) {
                    $q->where('user_id', auth()->id())
                        ->orWhere('is_public', true);
                })
                ->first();
        }

        return Inertia::render('Template/Builder', [
            'template' => null,
            'starterTemplate' => $starterTemplate ? [
                'id' => $starterTemplate->id,
                'name' => $starterTemplate->name,
                'json_structure' => $starterTemplate->json_structure,
                'settings' => $starterTemplate->getSettingsWithDefaults(),
            ] : null,
            'categories' => $categories,
            'blockTypes' => $blockTypes,
            'blockCategories' => $blockCategories,
            'savedBlocks' => $savedBlocks,
            'aiAvailable' => $aiAvailable,
            'defaultSettings' => Template::defaultSettings(),
            'inserts' => Template::where('user_id', auth()->id())->inserts()->orderBy('name')->get(),
            'signatures' => Template::where('user_id', auth()->id())->signatures()->orderBy('name')->get(),
            'systemVariables' => (new InsertController)->getSystemVariables(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'preheader' => 'nullable|string|max:500',
            'category' => 'nullable|string|max:50',
            'category_id' => 'nullable|exists:template_categories,id',
            'json_structure' => 'nullable|array',
            'settings' => 'nullable|array',
            'content' => 'nullable|string',
        ]);

        // Generate MJML from JSON structure
        if (!empty($validated['json_structure'])) {
            $settings = $validated['settings'] ?? Template::defaultSettings();
            $mjml = $this->mjmlService->jsonToMjml($validated['json_structure'], $settings);
            $validated['mjml_content'] = $mjml;
        }

        $template = auth()->user()->templates()->create($validated);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'template_id' => $template->id,
                'template' => [
                    'id' => $template->id,
                    'name' => $template->name,
                ],
                'redirect' => route('templates.edit', $template),
            ]);
        }

        return redirect()->route('templates.edit', $template)
            ->with('success', 'Szablon został utworzony.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Template $template)
    {
        if ($template->user_id !== auth()->id()) {
            abort(403);
        }

        $categories = TemplateCategory::forUser(auth()->id())
            ->orderBy('sort_order')
            ->get();

        $blockTypes = TemplateBlock::BLOCK_TYPES;
        $blockCategories = TemplateBlock::BLOCK_CATEGORIES;

        $savedBlocks = TemplateBlock::availableToUser(auth()->id())
            ->latest()
            ->take(20)
            ->get();

        $aiAvailable = $this->aiService->isAvailable();

        return Inertia::render('Template/Builder', [
            'template' => [
                'id' => $template->id,
                'name' => $template->name,
                'preheader' => $template->preheader,
                'category' => $template->category,
                'category_id' => $template->category_id,
                'json_structure' => $template->json_structure,
                'settings' => $template->getSettingsWithDefaults(),
                'content' => $template->content,
                'is_public' => $template->is_public,
            ],
            'starterTemplate' => null,
            'categories' => $categories,
            'blockTypes' => $blockTypes,
            'blockCategories' => $blockCategories,
            'savedBlocks' => $savedBlocks,
            'aiAvailable' => $aiAvailable,
            'defaultSettings' => Template::defaultSettings(),
            'inserts' => Template::where('user_id', auth()->id())->inserts()->orderBy('name')->get(),
            'signatures' => Template::where('user_id', auth()->id())->signatures()->orderBy('name')->get(),
            'systemVariables' => (new InsertController)->getSystemVariables(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Template $template)
    {
        if ($template->user_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'preheader' => 'nullable|string|max:500',
            'category' => 'nullable|string|max:50',
            'category_id' => 'nullable|exists:template_categories,id',
            'json_structure' => 'nullable|array',
            'settings' => 'nullable|array',
            'content' => 'nullable|string',
            'is_public' => 'nullable|boolean',
        ]);

        // Generate MJML from JSON structure
        if (!empty($validated['json_structure'])) {
            $settings = $validated['settings'] ?? $template->getSettingsWithDefaults();
            $mjml = $this->mjmlService->jsonToMjml($validated['json_structure'], $settings);
            $validated['mjml_content'] = $mjml;
        }

        $template->update($validated);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Szablon został zaktualizowany.',
            ]);
        }

        return redirect()->route('templates.index')
            ->with('success', 'Szablon został zaktualizowany.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Template $template)
    {
        if ($template->user_id !== auth()->id()) {
            abort(403);
        }

        $template->delete();

        return redirect()->route('templates.index')
            ->with('success', 'Szablon został usunięty.');
    }

    /**
     * Preview the compiled template
     */
    public function preview(Template $template)
    {
        if ($template->user_id !== auth()->id() && !$template->is_public) {
            abort(403);
        }

        // If template has MJML, return info that rendering will be done on frontend
        // If template has only HTML content, return it directly
        return response()->json([
            'mjml_content' => $template->mjml_content,
            'html_content' => $template->content,
            'settings' => $template->getSettingsWithDefaults(),
        ]);
    }

    /**
     * Duplicate a template
     */
    public function duplicate(Template $template)
    {
        if ($template->user_id !== auth()->id() && !$template->is_public) {
            abort(403);
        }

        $clone = $template->duplicate();
        $clone->user_id = auth()->id();
        $clone->save();

        return redirect()->route('templates.edit', $clone)
            ->with('success', 'Szablon został zduplikowany.');
    }

    /**
     * Export template as ZIP
     */
    public function export(Template $template)
    {
        if ($template->user_id !== auth()->id()) {
            abort(403);
        }

        $data = [
            'name' => $template->name,
            'preheader' => $template->preheader,
            'category' => $template->category,
            'json_structure' => $template->json_structure,
            'settings' => $template->settings,
            'mjml_content' => $template->mjml_content,
            'exported_at' => now()->toIso8601String(),
            'version' => '1.0',
        ];

        return response()->json($data)
            ->header('Content-Disposition', 'attachment; filename="template-' . $template->id . '.json"');
    }

    /**
     * Import template from JSON
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:json|max:5120',
        ]);

        $content = file_get_contents($request->file('file')->getRealPath());
        $data = json_decode($content, true);

        if (!$data || !isset($data['name'])) {
            return back()->with('error', 'Nieprawidłowy format pliku szablonu.');
        }

        $template = auth()->user()->templates()->create([
            'name' => $data['name'] . ' (import)',
            'preheader' => $data['preheader'] ?? null,
            'category' => $data['category'] ?? null,
            'json_structure' => $data['json_structure'] ?? null,
            'settings' => $data['settings'] ?? null,
            'mjml_content' => $data['mjml_content'] ?? null,
        ]);

        return redirect()->route('templates.edit', $template)
            ->with('success', 'Szablon został zaimportowany.');
    }

    /**
 * Get compiled HTML from template (for message editor)
 * Note: For MJML templates, returns raw MJML for frontend compilation via mjml-browser
 */
public function compiled(Template $template)
{
    if ($template->user_id !== auth()->id() && !$template->is_public) {
        abort(403);
    }

    // If template has direct HTML content, return it
    if ($template->content) {
        return response()->json([
            'html' => $template->content,
            'preheader' => $template->preheader,
            'source' => 'content',
        ]);
    }

    // If template has MJML content, return it for frontend compilation
    if ($template->mjml_content) {
        return response()->json([
            'mjml' => $template->mjml_content,
            'preheader' => $template->preheader,
            'source' => 'mjml',
        ]);
    }

    // If has JSON structure, generate MJML and return it for frontend compilation
    if (!empty($template->json_structure)) {
        try {
            $settings = $template->getSettingsWithDefaults();
            $mjml = $this->mjmlService->jsonToMjml($template->json_structure, $settings);
            return response()->json([
                'mjml' => $mjml,
                'preheader' => $template->preheader,
                'source' => 'json_structure',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    return response()->json([
        'html' => '',
        'source' => 'empty',
    ]);
}
}
