<?php

namespace App\Http\Controllers;

use App\Models\CrmTask;
use App\Models\CrmContact;
use App\Models\CrmDeal;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use Carbon\Carbon;

class CrmTaskController extends Controller
{
    /**
     * Display a listing of tasks.
     */
    public function index(Request $request): Response
    {
        $userId = auth()->user()->admin_user_id ?? auth()->id();

        // Determine view (today, upcoming, overdue, all)
        $view = $request->get('view', 'today');

        $query = CrmTask::forUser($userId)
            ->with(['contact.subscriber', 'deal', 'owner']);

        // Filter by view
        switch ($view) {
            case 'overdue':
                $query->overdue();
                break;
            case 'today':
                $query->today();
                break;
            case 'upcoming':
                $query->upcoming();
                break;
            case 'completed':
                $query->completed();
                break;
            default:
                $query->pending();
        }

        // Additional filters
        if ($request->filled('owner_id')) {
            $query->ownedBy($request->owner_id);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Sorting
        $query->orderBy('due_date', 'asc')->orderBy('priority', 'desc');

        $tasks = $query->paginate(50)->withQueryString();

        // Get counts for tabs
        $counts = [
            'overdue' => CrmTask::forUser($userId)->overdue()->count(),
            'today' => CrmTask::forUser($userId)->today()->count(),
            'upcoming' => CrmTask::forUser($userId)->upcoming()->count(),
        ];

        // Get filter options
        $owners = User::where(function ($q) use ($userId) {
            $q->where('id', $userId)->orWhere('admin_user_id', $userId);
        })->get(['id', 'name']);

        $contacts = CrmContact::forUser($userId)
            ->with('subscriber:id,email,first_name,last_name')
            ->limit(100)
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'name' => $c->full_name,
            ]);

        return Inertia::render('Crm/Tasks/Index', [
            'tasks' => $tasks,
            'counts' => $counts,
            'view' => $view,
            'owners' => $owners,
            'contacts' => $contacts,
            'filters' => $request->only(['view', 'owner_id', 'priority', 'type']),
        ]);
    }

    /**
     * Store a newly created task.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $userId = auth()->user()->admin_user_id ?? auth()->id();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'type' => 'required|in:call,email,meeting,task,follow_up',
            'priority' => 'required|in:low,medium,high',
            'due_date' => 'nullable|date',
            'crm_contact_id' => 'nullable|exists:crm_contacts,id',
            'crm_deal_id' => 'nullable|exists:crm_deals,id',
            'owner_id' => 'nullable|exists:users,id',
        ]);

        $task = CrmTask::create([
            ...$validated,
            'user_id' => $userId,
            'owner_id' => $validated['owner_id'] ?? auth()->id(),
            'status' => 'pending',
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'task' => $task->load(['contact.subscriber', 'deal'])]);
        }

        return redirect()->back()->with('success', 'Zadanie zostało utworzone.');
    }

    /**
     * Update the specified task.
     */
    public function update(Request $request, CrmTask $task): RedirectResponse|JsonResponse
    {
        $userId = auth()->user()->admin_user_id ?? auth()->id();

        if ($task->user_id !== $userId) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'type' => 'sometimes|required|in:call,email,meeting,task,follow_up',
            'priority' => 'sometimes|required|in:low,medium,high',
            'status' => 'sometimes|required|in:pending,in_progress,completed,cancelled',
            'due_date' => 'nullable|date',
            'owner_id' => 'nullable|exists:users,id',
        ]);

        $task->update($validated);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'task' => $task->fresh(['contact.subscriber', 'deal'])]);
        }

        return redirect()->back()->with('success', 'Zadanie zostało zaktualizowane.');
    }

    /**
     * Mark task as completed.
     */
    public function complete(Request $request, CrmTask $task): RedirectResponse|JsonResponse
    {
        $userId = auth()->user()->admin_user_id ?? auth()->id();

        if ($task->user_id !== $userId) {
            abort(403);
        }

        $task->complete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('success', 'Zadanie zostało ukończone.');
    }

    /**
     * Reschedule a task.
     */
    public function reschedule(Request $request, CrmTask $task): RedirectResponse|JsonResponse
    {
        $userId = auth()->user()->admin_user_id ?? auth()->id();

        if ($task->user_id !== $userId) {
            abort(403);
        }

        $validated = $request->validate([
            'due_date' => 'required|date|after_or_equal:today',
        ]);

        $task->reschedule(Carbon::parse($validated['due_date']));

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'task' => $task->fresh()]);
        }

        return redirect()->back()->with('success', 'Zadanie zostało przełożone.');
    }

    /**
     * Remove the specified task.
     */
    public function destroy(CrmTask $task): RedirectResponse
    {
        $userId = auth()->user()->admin_user_id ?? auth()->id();

        if ($task->user_id !== $userId) {
            abort(403);
        }

        $task->delete();

        return redirect()->back()->with('success', 'Zadanie zostało usunięte.');
    }
}
