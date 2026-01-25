<?php

namespace App\Http\Controllers;

use App\Models\CrmTask;
use App\Models\CrmContact;
use App\Models\CrmDeal;
use App\Models\User;
use App\Models\UserCalendarConnection;
use App\Services\GoogleCalendarService;
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

        // Get calendar connection for sync UI
        $calendarConnection = UserCalendarConnection::where('user_id', $userId)
            ->where('is_active', true)
            ->first();

        $calendars = [];
        if ($calendarConnection) {
            try {
                $calendarService = app(GoogleCalendarService::class);
                $calendars = $calendarService->listCalendars($calendarConnection) ?? [];
            } catch (\Exception $e) {
                // Silently fail - user can still use tasks without calendar sync
            }
        }

        return Inertia::render('Crm/Tasks/Index', [
            'tasks' => $tasks,
            'counts' => $counts,
            'view' => $view,
            'owners' => $owners,
            'contacts' => $contacts,
            'filters' => $request->only(['view', 'owner_id', 'priority', 'type']),
            'calendarConnection' => $calendarConnection ? [
                'id' => $calendarConnection->id,
                'is_active' => $calendarConnection->is_active,
                'calendar_id' => $calendarConnection->calendar_id,
                'connected_email' => $calendarConnection->connected_email,
                'auto_sync_tasks' => $calendarConnection->auto_sync_tasks,
            ] : null,
            'calendars' => $calendars,
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
            'sync_to_calendar' => 'nullable|boolean',
            'selected_calendar_id' => 'nullable|string|max:255',
            // Recurrence
            'is_recurring' => 'nullable|boolean',
            'recurrence_type' => 'nullable|in:daily,weekly,monthly,yearly',
            'recurrence_interval' => 'nullable|integer|min:1|max:99',
            'recurrence_days' => 'nullable|array',
            'recurrence_days.*' => 'integer|min:0|max:6',
            'recurrence_end_date' => 'nullable|date|after:due_date',
            'recurrence_count' => 'nullable|integer|min:1|max:999',
        ]);

        $task = CrmTask::create([
            ...$validated,
            'user_id' => $userId,
            'owner_id' => $validated['owner_id'] ?? auth()->id(),
            'status' => 'pending',
        ]);

        // Sync to Google Calendar if enabled
        if ($task->sync_to_calendar) {
            \App\Jobs\SyncTaskToCalendar::dispatch($task);
        }

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
            'sync_to_calendar' => 'nullable|boolean',
            'selected_calendar_id' => 'nullable|string|max:255',
            // Recurrence
            'is_recurring' => 'nullable|boolean',
            'recurrence_type' => 'nullable|in:daily,weekly,monthly,yearly',
            'recurrence_interval' => 'nullable|integer|min:1|max:99',
            'recurrence_days' => 'nullable|array',
            'recurrence_days.*' => 'integer|min:0|max:6',
            'recurrence_end_date' => 'nullable|date|after:due_date',
            'recurrence_count' => 'nullable|integer|min:1|max:999',
        ]);

        $task->update($validated);

        // Sync changes to Google Calendar
        if ($task->sync_to_calendar) {
            \App\Jobs\SyncTaskToCalendar::dispatch($task->fresh());
        } elseif ($task->isSyncedToCalendar() && isset($validated['sync_to_calendar']) && !$validated['sync_to_calendar']) {
            // User disabled sync - delete event from Calendar
            \App\Jobs\SyncTaskToCalendar::dispatch($task->fresh(), 'delete');
        }

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

        // Update Calendar event if synced
        if ($task->isSyncedToCalendar()) {
            \App\Jobs\SyncTaskToCalendar::dispatch($task->fresh());
        }

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

    /**
     * Snooze task reminder.
     */
    public function snooze(Request $request, CrmTask $task): RedirectResponse|JsonResponse
    {
        $userId = auth()->user()->admin_user_id ?? auth()->id();

        if ($task->user_id !== $userId) {
            abort(403);
        }

        $validated = $request->validate([
            'hours' => 'nullable|integer|min:1|max:72',
        ]);

        $task->snoozeReminder($validated['hours'] ?? 1);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'task' => $task->fresh()]);
        }

        return redirect()->back()->with('success', 'Przypomnienie zostało odłożone.');
    }

    /**
     * Create a follow-up task.
     */
    public function createFollowUp(Request $request, CrmTask $task): RedirectResponse|JsonResponse
    {
        $userId = auth()->user()->admin_user_id ?? auth()->id();

        if ($task->user_id !== $userId) {
            abort(403);
        }

        $validated = $request->validate([
            'days' => 'required|integer|min:1|max:365',
            'title' => 'nullable|string|max:255',
            'type' => 'nullable|in:call,email,meeting,task,follow_up',
        ]);

        $followUpTask = $task->createFollowUp(
            $validated['days'],
            $validated['title'] ?? null,
            $validated['type'] ?? null
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'task' => $followUpTask->load(['contact.subscriber', 'deal']),
            ]);
        }

        return redirect()->back()->with('success', 'Follow-up został utworzony.');
    }

    /**
     * Get tasks with conflicts.
     */
    public function conflicts(Request $request): JsonResponse
    {
        $userId = auth()->user()->admin_user_id ?? auth()->id();

        $tasks = CrmTask::forUser($userId)
            ->withConflicts()
            ->with(['contact.subscriber'])
            ->get();

        return response()->json([
            'tasks' => $tasks,
            'count' => $tasks->count(),
        ]);
    }

    /**
     * Resolve conflict by accepting local version.
     */
    public function resolveConflictLocal(CrmTask $task): JsonResponse
    {
        $userId = auth()->user()->admin_user_id ?? auth()->id();

        if ($task->user_id !== $userId) {
            abort(403);
        }

        if (!$task->has_conflict) {
            return response()->json([
                'success' => false,
                'message' => __('crm.conflicts.no_conflict'),
            ], 400);
        }

        $task->resolveConflictWithLocal();

        return response()->json([
            'success' => true,
            'message' => __('crm.conflicts.resolved_local'),
            'task' => $task->fresh(['contact.subscriber', 'deal']),
        ]);
    }

    /**
     * Resolve conflict by accepting remote version.
     */
    public function resolveConflictRemote(CrmTask $task): JsonResponse
    {
        $userId = auth()->user()->admin_user_id ?? auth()->id();

        if ($task->user_id !== $userId) {
            abort(403);
        }

        if (!$task->has_conflict) {
            return response()->json([
                'success' => false,
                'message' => __('crm.conflicts.no_conflict'),
            ], 400);
        }

        $task->resolveConflictWithRemote();

        return response()->json([
            'success' => true,
            'message' => __('crm.conflicts.resolved_remote'),
            'task' => $task->fresh(['contact.subscriber', 'deal']),
        ]);
    }
}

