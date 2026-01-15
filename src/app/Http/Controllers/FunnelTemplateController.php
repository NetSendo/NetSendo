<?php

namespace App\Http\Controllers;

use App\Models\Funnel;
use App\Models\FunnelTemplate;
use App\Services\Funnels\FunnelTemplateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class FunnelTemplateController extends Controller
{
    protected FunnelTemplateService $templateService;

    public function __construct(FunnelTemplateService $templateService)
    {
        $this->templateService = $templateService;
    }

    /**
     * Display template gallery.
     */
    public function index(Request $request)
    {
        $category = $request->get('category');
        $search = $request->get('search');

        $query = FunnelTemplate::forUser(Auth::id())
            ->orderByDesc('is_featured')
            ->orderByDesc('uses_count');

        if ($category) {
            $query->byCategory($category);
        }

        if ($search) {
            $query->search($search);
        }

        $templates = $query->paginate(12);

        return Inertia::render('Funnels/Templates/Index', [
            'templates' => $templates,
            'categories' => FunnelTemplate::getCategories(),
            'activeCategory' => $category,
            'search' => $search,
        ]);
    }

    /**
     * Show template preview.
     */
    public function show(FunnelTemplate $template)
    {
        if (!$template->canBeUsedBy(Auth::id())) {
            abort(403);
        }

        return Inertia::render('Funnels/Templates/Show', [
            'template' => $template,
            'categories' => FunnelTemplate::getCategories(),
        ]);
    }

    /**
     * Create funnel from template.
     */
    public function use(Request $request, FunnelTemplate $template)
    {
        if (!$template->canBeUsedBy(Auth::id())) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'trigger_list_id' => 'nullable|exists:contact_lists,id',
        ]);

        $funnel = $this->templateService->createFromTemplate($template, [
            'user_id' => Auth::id(),
            'name' => $validated['name'],
            'trigger_list_id' => $validated['trigger_list_id'] ?? null,
        ]);

        return redirect()->route('funnels.edit', $funnel)
            ->with('success', 'Lejek został utworzony z szablonu.');
    }

    /**
     * Export existing funnel as template.
     */
    public function export(Request $request, Funnel $funnel)
    {
        $this->authorize('update', $funnel);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'category' => 'required|string',
            'is_public' => 'boolean',
        ]);

        $template = $this->templateService->exportToTemplate($funnel, [
            'user_id' => Auth::id(),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'],
            'is_public' => $validated['is_public'] ?? false,
        ]);

        return redirect()->route('funnel-templates.show', $template)
            ->with('success', 'Szablon został zapisany.');
    }

    /**
     * Delete user's template.
     */
    public function destroy(FunnelTemplate $template)
    {
        if ($template->user_id !== Auth::id()) {
            abort(403);
        }

        $template->delete();

        return redirect()->route('funnel-templates.index')
            ->with('success', 'Szablon został usunięty.');
    }

    /**
     * API: Get templates for modal/gallery.
     */
    public function apiList(Request $request)
    {
        $category = $request->get('category');

        $templates = FunnelTemplate::forUser(Auth::id())
            ->when($category, fn($q) => $q->byCategory($category))
            ->orderByDesc('is_featured')
            ->orderByDesc('uses_count')
            ->get()
            ->map(fn($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
                'description' => $t->description,
                'category' => $t->category,
                'category_icon' => FunnelTemplate::getCategoryIcon($t->category),
                'step_count' => $t->getStepCount(),
                'uses_count' => $t->uses_count,
                'is_featured' => $t->is_featured,
                'is_public' => $t->is_public,
            ]);

        return response()->json([
            'templates' => $templates,
            'categories' => FunnelTemplate::getCategories(),
        ]);
    }
}
