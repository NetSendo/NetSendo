<?php

namespace App\Http\Controllers;

use App\Models\CrmFollowUpSequence;
use App\Models\CrmFollowUpStep;
use App\Models\CrmContact;
use App\Services\DefaultFollowUpSequencesService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class CrmFollowUpSequenceController extends Controller
{
    /**
     * Display a listing of sequences.
     */
    public function index(Request $request): Response
    {
        $userId = auth()->user()->admin_user_id ?? auth()->id();

        $sequences = CrmFollowUpSequence::forUser($userId)
            ->withCount(['steps', 'enrollments', 'activeEnrollments'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $defaultService = new DefaultFollowUpSequencesService();

        return Inertia::render('Crm/Sequences/Index', [
            'sequences' => $sequences,
            'hasDefaults' => $defaultService->hasDefaults($userId),
            'defaultsCount' => $defaultService->getDefaultsCount(),
        ]);
    }

    /**
     * Show the form for creating a new sequence.
     */
    public function create(): Response
    {
        return Inertia::render('Crm/Sequences/Builder', [
            'sequence' => null,
            'triggerTypes' => $this->getTriggerTypes(),
            'actionTypes' => $this->getActionTypes(),
            'taskTypes' => $this->getTaskTypes(),
            'priorities' => $this->getPriorities(),
        ]);
    }

    /**
     * Store a newly created sequence.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $userId = auth()->user()->admin_user_id ?? auth()->id();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'trigger_type' => 'required|in:manual,on_deal_created,on_contact_created,on_task_completed,on_deal_stage_changed',
            'is_active' => 'boolean',
            'steps' => 'required|array|min:1',
            'steps.*.position' => 'required|integer|min:0',
            'steps.*.delay_days' => 'required|integer|min:0',
            'steps.*.delay_hours' => 'required|integer|min:0|max:23',
            'steps.*.action_type' => 'required|in:task,email,sms,wait_for_response',
            'steps.*.task_type' => 'nullable|in:call,email,meeting,task,follow_up',
            'steps.*.task_title' => 'nullable|string|max:255',
            'steps.*.task_description' => 'nullable|string|max:5000',
            'steps.*.task_priority' => 'nullable|in:low,medium,high',
            'steps.*.condition_if_no_response' => 'nullable|in:continue,stop,escalate',
            'steps.*.wait_days_for_response' => 'nullable|integer|min:1',
        ]);

        $sequence = CrmFollowUpSequence::create([
            'user_id' => $userId,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'trigger_type' => $validated['trigger_type'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        // Create steps
        foreach ($validated['steps'] as $stepData) {
            $sequence->steps()->create($stepData);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'sequence' => $sequence->load('steps'),
            ]);
        }

        return redirect()
            ->route('crm.sequences.index')
            ->with('success', 'Sekwencja została utworzona.');
    }

    /**
     * Show the form for editing the specified sequence.
     */
    public function edit(CrmFollowUpSequence $sequence): Response
    {
        $userId = auth()->user()->admin_user_id ?? auth()->id();

        if ($sequence->user_id !== $userId) {
            abort(403);
        }

        return Inertia::render('Crm/Sequences/Builder', [
            'sequence' => $sequence->load('steps'),
            'triggerTypes' => $this->getTriggerTypes(),
            'actionTypes' => $this->getActionTypes(),
            'taskTypes' => $this->getTaskTypes(),
            'priorities' => $this->getPriorities(),
        ]);
    }

    /**
     * Update the specified sequence.
     */
    public function update(Request $request, CrmFollowUpSequence $sequence): RedirectResponse|JsonResponse
    {
        $userId = auth()->user()->admin_user_id ?? auth()->id();

        if ($sequence->user_id !== $userId) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'trigger_type' => 'required|in:manual,on_deal_created,on_contact_created,on_task_completed,on_deal_stage_changed',
            'is_active' => 'boolean',
            'steps' => 'required|array|min:1',
            'steps.*.id' => 'nullable|exists:crm_follow_up_steps,id',
            'steps.*.position' => 'required|integer|min:0',
            'steps.*.delay_days' => 'required|integer|min:0',
            'steps.*.delay_hours' => 'required|integer|min:0|max:23',
            'steps.*.action_type' => 'required|in:task,email,sms,wait_for_response',
            'steps.*.task_type' => 'nullable|in:call,email,meeting,task,follow_up',
            'steps.*.task_title' => 'nullable|string|max:255',
            'steps.*.task_description' => 'nullable|string|max:5000',
            'steps.*.task_priority' => 'nullable|in:low,medium,high',
            'steps.*.condition_if_no_response' => 'nullable|in:continue,stop,escalate',
            'steps.*.wait_days_for_response' => 'nullable|integer|min:1',
        ]);

        $sequence->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'trigger_type' => $validated['trigger_type'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        // Get existing step IDs
        $existingStepIds = $sequence->steps->pluck('id')->toArray();
        $submittedStepIds = collect($validated['steps'])
            ->pluck('id')
            ->filter()
            ->toArray();

        // Delete removed steps
        $stepsToDelete = array_diff($existingStepIds, $submittedStepIds);
        CrmFollowUpStep::whereIn('id', $stepsToDelete)->delete();

        // Update or create steps
        foreach ($validated['steps'] as $stepData) {
            if (!empty($stepData['id'])) {
                $step = CrmFollowUpStep::find($stepData['id']);
                if ($step && $step->sequence_id === $sequence->id) {
                    $step->update($stepData);
                }
            } else {
                $sequence->steps()->create($stepData);
            }
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'sequence' => $sequence->fresh(['steps']),
            ]);
        }

        return redirect()
            ->route('crm.sequences.index')
            ->with('success', 'Sekwencja została zaktualizowana.');
    }

    /**
     * Remove the specified sequence.
     */
    public function destroy(CrmFollowUpSequence $sequence): RedirectResponse
    {
        $userId = auth()->user()->admin_user_id ?? auth()->id();

        if ($sequence->user_id !== $userId) {
            abort(403);
        }

        $sequence->delete();

        return redirect()
            ->route('crm.sequences.index')
            ->with('success', 'Sekwencja została usunięta.');
    }

    /**
     * Enroll a contact in a sequence.
     */
    public function enroll(Request $request, CrmContact $contact): RedirectResponse|JsonResponse
    {
        $userId = auth()->user()->admin_user_id ?? auth()->id();

        if ($contact->user_id !== $userId) {
            abort(403);
        }

        $validated = $request->validate([
            'sequence_id' => 'required|exists:crm_follow_up_sequences,id',
        ]);

        $sequence = CrmFollowUpSequence::findOrFail($validated['sequence_id']);

        if ($sequence->user_id !== $userId) {
            abort(403);
        }

        if ($sequence->isContactEnrolled($contact)) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kontakt jest już zapisany do tej sekwencji.',
                ], 422);
            }

            return redirect()->back()->with('error', 'Kontakt jest już zapisany do tej sekwencji.');
        }

        $enrollment = $sequence->enrollContact($contact);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'enrollment' => $enrollment,
            ]);
        }

        return redirect()->back()->with('success', 'Kontakt został zapisany do sekwencji.');
    }

    /**
     * Duplicate a sequence.
     */
    public function duplicate(CrmFollowUpSequence $sequence): RedirectResponse
    {
        $userId = auth()->user()->admin_user_id ?? auth()->id();

        if ($sequence->user_id !== $userId) {
            abort(403);
        }

        $newSequence = $sequence->duplicate();

        return redirect()
            ->route('crm.sequences.edit', $newSequence)
            ->with('success', 'Sekwencja została zduplikowana.');
    }

    /**
     * Toggle sequence active status.
     */
    public function toggleActive(CrmFollowUpSequence $sequence): JsonResponse
    {
        $userId = auth()->user()->admin_user_id ?? auth()->id();

        if ($sequence->user_id !== $userId) {
            abort(403);
        }

        $sequence->update(['is_active' => !$sequence->is_active]);

        return response()->json([
            'success' => true,
            'is_active' => $sequence->is_active,
        ]);
    }

    /**
     * Restore default sequences (replaces all existing).
     */
    public function restoreDefaults(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'confirm' => 'required|accepted',
        ]);

        $userId = auth()->user()->admin_user_id ?? auth()->id();

        $service = new DefaultFollowUpSequencesService();
        $sequences = $service->restoreDefaultsForUser($userId);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'count' => count($sequences),
                'message' => __('crm.defaults.restored_success'),
            ]);
        }

        return redirect()
            ->route('crm.sequences.index')
            ->with('success', __('crm.defaults.restored_success'));
    }

    /**
     * Create default sequences for user (if they don't exist).
     */
    public function createDefaults(Request $request): RedirectResponse|JsonResponse
    {
        $userId = auth()->user()->admin_user_id ?? auth()->id();

        $service = new DefaultFollowUpSequencesService();
        $sequences = $service->createDefaultsForUser($userId);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'count' => count($sequences),
            ]);
        }

        return redirect()
            ->route('crm.sequences.index')
            ->with('success', __('crm.defaults.restored_success'));
    }

    // ==================== REPORTING ====================

    /**
     * Display the report for a sequence.
     */
    public function report(CrmFollowUpSequence $sequence): Response
    {
        $userId = auth()->user()->admin_user_id ?? auth()->id();

        if ($sequence->user_id !== $userId) {
            abort(403);
        }

        // Load sequence with enrollments and steps
        $sequence->load(['steps', 'enrollments.contact.subscriber']);

        // Calculate detailed stats
        $enrollments = $sequence->enrollments;
        $totalEnrollments = $enrollments->count();
        $activeEnrollments = $enrollments->where('status', 'active')->count();
        $completedEnrollments = $enrollments->where('status', 'completed');
        $completedCount = $completedEnrollments->count();

        // Calculate metrics
        $totalTasksCompleted = $enrollments->sum('tasks_completed');
        $totalEmailsSent = $enrollments->sum('emails_sent');
        $totalResponses = $enrollments->sum('responses_received');
        $conversions = $enrollments->where('converted', true)->count();

        // Conversion rates
        $completionRate = $totalEnrollments > 0
            ? round(($completedCount / $totalEnrollments) * 100, 1)
            : 0;
        $conversionRate = $completedCount > 0
            ? round(($conversions / $completedCount) * 100, 1)
            : 0;
        $responseRate = $totalEmailsSent > 0
            ? round(($totalResponses / $totalEmailsSent) * 100, 1)
            : 0;

        // Average completion time (in days)
        $avgCompletionDays = $completedEnrollments
            ->filter(fn($e) => $e->started_at && $e->completed_at)
            ->avg(fn($e) => $e->started_at->diffInDays($e->completed_at));

        // Step by step progress
        $stepsProgress = $sequence->steps->map(function ($step) use ($enrollments) {
            $atOrPastStep = $enrollments->filter(function ($enrollment) use ($step) {
                return $enrollment->steps_completed >= $step->position;
            })->count();

            return [
                'position' => $step->position,
                'action_type' => $step->action_type,
                'task_type' => $step->task_type,
                'task_title' => $step->task_title,
                'reached_count' => $atOrPastStep,
            ];
        });

        // Recent enrollments for list
        $recentEnrollments = $sequence->enrollments()
            ->with(['contact.subscriber'])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(fn($e) => [
                'id' => $e->id,
                'contact_name' => $e->contact?->full_name ?? $e->contact?->subscriber?->email ?? 'Nieznany',
                'status' => $e->status,
                'status_label' => $e->status_label,
                'progress' => $e->progress,
                'steps_completed' => $e->steps_completed,
                'started_at' => $e->started_at,
                'completed_at' => $e->completed_at,
                'converted' => $e->converted,
            ]);

        return Inertia::render('Crm/Sequences/Report', [
            'sequence' => [
                'id' => $sequence->id,
                'name' => $sequence->name,
                'description' => $sequence->description,
                'trigger_type' => $sequence->trigger_type,
                'is_active' => $sequence->is_active,
                'steps_count' => $sequence->steps->count(),
            ],
            'stats' => [
                'total_enrollments' => $totalEnrollments,
                'active' => $activeEnrollments,
                'completed' => $completedCount,
                'conversions' => $conversions,
                'completion_rate' => $completionRate,
                'conversion_rate' => $conversionRate,
                'response_rate' => $responseRate,
                'avg_completion_days' => $avgCompletionDays ? round($avgCompletionDays, 1) : null,
                'total_tasks_completed' => $totalTasksCompleted,
                'total_emails_sent' => $totalEmailsSent,
                'total_responses' => $totalResponses,
            ],
            'steps_progress' => $stepsProgress,
            'recent_enrollments' => $recentEnrollments,
        ]);
    }

    // ==================== HELPERS ====================

    private function getTriggerTypes(): array
    {
        return [
            ['value' => 'manual', 'label' => 'Ręczny'],
            ['value' => 'on_contact_created', 'label' => 'Po utworzeniu kontaktu'],
            ['value' => 'on_deal_created', 'label' => 'Po utworzeniu dealu'],
            ['value' => 'on_task_completed', 'label' => 'Po ukończeniu zadania'],
            ['value' => 'on_deal_stage_changed', 'label' => 'Po zmianie etapu dealu'],
        ];
    }

    private function getActionTypes(): array
    {
        return [
            ['value' => 'task', 'label' => 'Utwórz zadanie', 'icon' => 'clipboard-list'],
            ['value' => 'email', 'label' => 'Wyślij email', 'icon' => 'mail'],
            ['value' => 'sms', 'label' => 'Wyślij SMS', 'icon' => 'message-square'],
            ['value' => 'wait_for_response', 'label' => 'Czekaj na odpowiedź', 'icon' => 'clock'],
        ];
    }

    private function getTaskTypes(): array
    {
        return [
            ['value' => 'call', 'label' => 'Telefon', 'icon' => 'phone'],
            ['value' => 'email', 'label' => 'Email', 'icon' => 'mail'],
            ['value' => 'meeting', 'label' => 'Spotkanie', 'icon' => 'users'],
            ['value' => 'task', 'label' => 'Zadanie', 'icon' => 'check-square'],
            ['value' => 'follow_up', 'label' => 'Follow-up', 'icon' => 'clock'],
        ];
    }

    private function getPriorities(): array
    {
        return [
            ['value' => 'low', 'label' => 'Niski', 'color' => 'bg-green-100 text-green-800'],
            ['value' => 'medium', 'label' => 'Średni', 'color' => 'bg-amber-100 text-amber-800'],
            ['value' => 'high', 'label' => 'Wysoki', 'color' => 'bg-red-100 text-red-800'],
        ];
    }
}
