<?php

namespace App\Http\Controllers;

use App\Models\Funnel;
use App\Models\FunnelStep;
use App\Services\Funnels\FunnelService;
use App\Services\Funnels\FunnelExecutionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class FunnelController extends Controller
{
    protected FunnelService $funnelService;
    protected FunnelExecutionService $executionService;

    public function __construct(FunnelService $funnelService, FunnelExecutionService $executionService)
    {
        $this->funnelService = $funnelService;
        $this->executionService = $executionService;
    }

    /**
     * Display a listing of funnels.
     */
    public function index(Request $request)
    {
        $query = Funnel::forUser(Auth::id())
            ->with(['triggerList:id,name', 'triggerForm:id,name'])
            ->withCount('subscribers');

        // Filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('trigger_type')) {
            $query->where('trigger_type', $request->trigger_type);
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        $funnels = $query->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Funnels/Index', [
            'funnels' => $funnels,
            'filters' => $request->only(['status', 'trigger_type', 'search']),
            'statuses' => Funnel::getStatuses(),
            'triggerTypes' => Funnel::getTriggerTypes(),
        ]);
    }

    /**
     * Show the form for creating a new funnel.
     */
    public function create()
    {
        $lists = $this->funnelService->getAvailableLists(Auth::id());
        $forms = $this->funnelService->getAvailableForms(Auth::id());

        return Inertia::render('Funnels/Builder', [
            'funnel' => null,
            'lists' => $lists,
            'forms' => $forms,
            'messages' => $this->funnelService->getAvailableMessages(Auth::id()),
            'stepTypes' => FunnelStep::getTypes(),
            'delayUnits' => FunnelStep::getDelayUnits(),
            'conditionTypes' => FunnelStep::getConditionTypes(),
            'actionTypes' => FunnelStep::getActionTypes(),
            'triggerTypes' => Funnel::getTriggerTypes(),
            'nodes' => [],
            'edges' => [],
        ]);
    }

    /**
     * Store a newly created funnel.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'trigger_type' => 'required|in:list_signup,tag_added,form_submit,manual',
            'trigger_list_id' => 'nullable|exists:contact_lists,id',
            'trigger_form_id' => 'nullable|exists:subscription_forms,id',
            'trigger_tag' => 'nullable|string|max:100',
            'settings' => 'nullable|array',
            'nodes' => 'nullable|array',
            'edges' => 'nullable|array',
        ]);

        $validated['user_id'] = Auth::id();

        $funnel = $this->funnelService->create($validated);

        // Update steps if nodes provided
        if (!empty($validated['nodes'])) {
            $this->funnelService->updateSteps(
                $funnel,
                $validated['nodes'],
                $validated['edges'] ?? []
            );
        }

        return redirect()->route('funnels.edit', $funnel)
            ->with('success', 'Lejek został utworzony.');
    }

    /**
     * Show the form for editing the funnel.
     */
    public function edit(Funnel $funnel)
    {
        $this->authorize('update', $funnel);

        $funnel->load('steps.message');
        $builderData = $this->funnelService->prepareForBuilder($funnel);

        return Inertia::render('Funnels/Builder', [
            'funnel' => $funnel,
            'lists' => $this->funnelService->getAvailableLists(Auth::id()),
            'forms' => $this->funnelService->getAvailableForms(Auth::id()),
            'messages' => $this->funnelService->getAvailableMessages(Auth::id()),
            'stepTypes' => FunnelStep::getTypes(),
            'delayUnits' => FunnelStep::getDelayUnits(),
            'conditionTypes' => FunnelStep::getConditionTypes(),
            'actionTypes' => FunnelStep::getActionTypes(),
            'triggerTypes' => Funnel::getTriggerTypes(),
            'nodes' => $builderData['nodes'],
            'edges' => $builderData['edges'],
        ]);
    }

    /**
     * Update the funnel.
     */
    public function update(Request $request, Funnel $funnel)
    {
        $this->authorize('update', $funnel);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'trigger_type' => 'sometimes|required|in:list_signup,tag_added,form_submit,manual',
            'trigger_list_id' => 'nullable|exists:contact_lists,id',
            'trigger_form_id' => 'nullable|exists:subscription_forms,id',
            'trigger_tag' => 'nullable|string|max:100',
            'settings' => 'nullable|array',
            'nodes' => 'nullable|array',
            'edges' => 'nullable|array',
        ]);

        $this->funnelService->update($funnel, $validated);

        // Update steps if nodes provided
        if (isset($validated['nodes'])) {
            $this->funnelService->updateSteps(
                $funnel,
                $validated['nodes'],
                $validated['edges'] ?? []
            );
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('success', 'Lejek został zapisany.');
    }

    /**
     * Remove the funnel.
     */
    public function destroy(Funnel $funnel)
    {
        $this->authorize('delete', $funnel);

        $funnel->delete();

        return redirect()->route('funnels.index')
            ->with('success', 'Lejek został usunięty.');
    }

    /**
     * Duplicate the funnel.
     */
    public function duplicate(Funnel $funnel)
    {
        $this->authorize('update', $funnel);

        $newFunnel = $funnel->duplicate();

        return redirect()->route('funnels.edit', $newFunnel)
            ->with('success', 'Lejek został zduplikowany.');
    }

    /**
     * Show funnel statistics.
     */
    public function stats(Funnel $funnel)
    {
        $this->authorize('view', $funnel);

        $stats = $this->funnelService->getStats($funnel);

        return Inertia::render('Funnels/Stats', [
            'funnel' => $funnel->load('triggerList'),
            'stats' => $stats,
        ]);
    }

    /**
     * Toggle funnel status (activate/pause).
     */
    public function toggleStatus(Request $request, Funnel $funnel)
    {
        $this->authorize('update', $funnel);

        if ($funnel->isActive()) {
            $funnel->pause();
            $message = 'Lejek został wstrzymany.';
        } else {
            // Validate before activation
            $errors = $this->funnelService->validate($funnel);
            if (!empty($errors)) {
                return redirect()->back()->withErrors(['validation' => $errors]);
            }

            $funnel->activate();
            $message = 'Lejek został aktywowany.';
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'status' => $funnel->status,
            ]);
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * Validate funnel configuration.
     */
    public function validate(Funnel $funnel)
    {
        $this->authorize('view', $funnel);

        $errors = $this->funnelService->validate($funnel);

        return response()->json([
            'valid' => empty($errors),
            'errors' => $errors,
        ]);
    }
}
