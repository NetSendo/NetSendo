<?php

namespace App\Http\Controllers;

use App\Models\CrmDeal;
use App\Models\CrmPipeline;
use App\Models\CrmStage;
use App\Models\CrmContact;
use App\Models\CrmCompany;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class CrmDealController extends Controller
{
    /**
     * Display the Kanban board with deals.
     */
    public function index(Request $request): Response
    {
        $userId = auth()->user()->admin_user_id ?? auth()->id();

        // Get or select pipeline
        $pipelineId = $request->get('pipeline_id');
        $pipeline = $pipelineId
            ? CrmPipeline::where('user_id', $userId)->findOrFail($pipelineId)
            : CrmPipeline::getDefaultForUser($userId);

        if (!$pipeline) {
            // Create default pipeline if none exists
            $pipeline = $this->createDefaultPipeline($userId);
        }

        // Get stages with deals
        $stages = $pipeline->stages()
            ->with(['deals' => function ($q) {
                $q->open()
                    ->with(['contact.subscriber', 'company', 'owner'])
                    ->orderBy('created_at', 'desc');
            }])
            ->orderBy('order')
            ->get();

        // Get all pipelines for selector
        $pipelines = CrmPipeline::forUser($userId)->orderBy('name')->get();

        // Get contacts and companies for new deal form
        $contacts = CrmContact::forUser($userId)
            ->with('subscriber:id,email,first_name,last_name')
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'name' => $c->full_name,
                'email' => $c->email,
            ]);

        $companies = CrmCompany::forUser($userId)->orderBy('name')->get(['id', 'name']);

        $owners = User::where(function ($q) use ($userId) {
            $q->where('id', $userId)->orWhere('admin_user_id', $userId);
        })->get(['id', 'name']);

        return Inertia::render('Crm/Deals/Index', [
            'pipeline' => $pipeline,
            'stages' => $stages,
            'pipelines' => $pipelines,
            'contacts' => $contacts,
            'companies' => $companies,
            'owners' => $owners,
        ]);
    }

    /**
     * Store a newly created deal.
     */
    public function store(Request $request): RedirectResponse
    {
        $userId = auth()->user()->admin_user_id ?? auth()->id();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'crm_pipeline_id' => 'required|exists:crm_pipelines,id',
            'crm_stage_id' => 'required|exists:crm_stages,id',
            'crm_contact_id' => 'nullable|exists:crm_contacts,id',
            'crm_company_id' => 'nullable|exists:crm_companies,id',
            'owner_id' => 'nullable|exists:users,id',
            'value' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'expected_close_date' => 'nullable|date',
            'notes' => 'nullable|string|max:5000',
        ]);

        $deal = CrmDeal::create([
            ...$validated,
            'user_id' => $userId,
            'value' => $validated['value'] ?? 0,
            'currency' => $validated['currency'] ?? 'PLN',
            'status' => 'open',
        ]);

        // Log activity
        $deal->activities()->create([
            'user_id' => $userId,
            'created_by_id' => auth()->id(),
            'type' => 'deal_created',
            'content' => "Deal '{$deal->name}' został utworzony",
        ]);

        // Also log on contact if exists
        if ($deal->crm_contact_id) {
            $deal->contact->logActivity(
                'deal_created',
                "Utworzono deal: {$deal->name}",
                ['deal_id' => $deal->id]
            );
        }

        return redirect()->back()->with('success', 'Deal został utworzony.');
    }

    /**
     * Update deal stage (for drag-and-drop).
     */
    public function updateStage(Request $request, CrmDeal $deal): RedirectResponse
    {
        $userId = auth()->user()->admin_user_id ?? auth()->id();

        if ($deal->user_id !== $userId) {
            abort(403);
        }

        $validated = $request->validate([
            'crm_stage_id' => 'required|exists:crm_stages,id',
        ]);

        $newStage = CrmStage::findOrFail($validated['crm_stage_id']);

        // Move deal and log activity
        $deal->moveToStage($newStage);

        return redirect()->back();
    }

    /**
     * Update deal details.
     */
    public function update(Request $request, CrmDeal $deal): RedirectResponse
    {
        $userId = auth()->user()->admin_user_id ?? auth()->id();

        if ($deal->user_id !== $userId) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'crm_contact_id' => 'nullable|exists:crm_contacts,id',
            'crm_company_id' => 'nullable|exists:crm_companies,id',
            'owner_id' => 'nullable|exists:users,id',
            'value' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'expected_close_date' => 'nullable|date',
            'notes' => 'nullable|string|max:5000',
        ]);

        $deal->update($validated);

        return redirect()->back()->with('success', 'Deal został zaktualizowany.');
    }

    /**
     * Remove the specified deal.
     */
    public function destroy(CrmDeal $deal): RedirectResponse
    {
        $userId = auth()->user()->admin_user_id ?? auth()->id();

        if ($deal->user_id !== $userId) {
            abort(403);
        }

        $deal->delete();

        return redirect()->back()->with('success', 'Deal został usunięty.');
    }

    /**
     * Get pipelines for API.
     */
    public function pipelines(): JsonResponse
    {
        $userId = auth()->user()->admin_user_id ?? auth()->id();

        $pipelines = CrmPipeline::forUser($userId)
            ->with('stages')
            ->orderBy('name')
            ->get();

        return response()->json($pipelines);
    }

    /**
     * Create default pipeline for a user.
     */
    private function createDefaultPipeline(int $userId): CrmPipeline
    {
        $pipeline = CrmPipeline::create([
            'user_id' => $userId,
            'name' => 'Sprzedaż B2B',
            'is_default' => true,
        ]);

        $stages = [
            ['name' => 'Nowy', 'color' => '#6b7280', 'order' => 0],
            ['name' => 'Kontakt', 'color' => '#3b82f6', 'order' => 1],
            ['name' => 'Rozmowa', 'color' => '#8b5cf6', 'order' => 2],
            ['name' => 'Oferta', 'color' => '#f59e0b', 'order' => 3],
            ['name' => 'Negocjacje', 'color' => '#ef4444', 'order' => 4],
            ['name' => 'Wygrany', 'color' => '#10b981', 'order' => 5, 'is_won' => true],
            ['name' => 'Przegrany', 'color' => '#6b7280', 'order' => 6, 'is_lost' => true],
        ];

        foreach ($stages as $stage) {
            $pipeline->stages()->create($stage);
        }

        return $pipeline;
    }
}
