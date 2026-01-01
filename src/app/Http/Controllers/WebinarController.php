<?php

namespace App\Http\Controllers;

use App\Models\Webinar;
use App\Models\ContactList;
use App\Services\Webinar\WebinarService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WebinarController extends Controller
{
    public function __construct(protected WebinarService $webinarService) {}

    public function index(Request $request): Response
    {
        $webinars = Webinar::forUser(auth()->id())
            ->with(['targetList', 'sessions'])
            ->when($request->search, fn($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->when($request->type, fn($q, $t) => $q->where('type', $t))
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return Inertia::render('Webinars/Index', [
            'webinars' => $webinars,
            'filters' => $request->only(['search', 'type', 'status']),
            'types' => Webinar::getTypes(),
            'statuses' => Webinar::getStatuses(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Webinars/Create', [
            'types' => Webinar::getTypes(),
            'lists' => ContactList::forUser(auth()->id())->get(['id', 'name']),
            'defaultSettings' => Webinar::DEFAULT_SETTINGS,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:live,auto,hybrid',
            'scheduled_at' => 'nullable|date|after:now',
            'target_list_id' => 'nullable|exists:contact_lists,id',
            'registration_tag' => 'nullable|string|max:50',
            'attended_tag' => 'nullable|string|max:50',
            'missed_tag' => 'nullable|string|max:50',
            'settings' => 'nullable|array',
            'video_url' => 'nullable|url',
            'youtube_live_id' => 'nullable|string',
        ]);

        $webinar = $this->webinarService->create($validated, auth()->id());

        return redirect()->route('webinars.edit', $webinar)
            ->with('success', __('webinars.created'));
    }

    public function show(Webinar $webinar): Response
    {
        $this->authorize('view', $webinar);

        return Inertia::render('Webinars/Show', [
            'webinar' => $webinar->load(['sessions', 'products', 'ctas', 'targetList']),
            'stats' => $this->webinarService->getStats($webinar),
        ]);
    }

    public function edit(Webinar $webinar): Response
    {
        $this->authorize('update', $webinar);

        return Inertia::render('Webinars/Edit', [
            'webinar' => $webinar->load(['products', 'ctas', 'schedule', 'targetList']),
            'types' => Webinar::getTypes(),
            'statuses' => Webinar::getStatuses(),
            'lists' => ContactList::forUser(auth()->id())->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Webinar $webinar)
    {
        $this->authorize('update', $webinar);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'scheduled_at' => 'nullable|date',
            'target_list_id' => 'nullable|exists:contact_lists,id',
            'registration_tag' => 'nullable|string|max:50',
            'attended_tag' => 'nullable|string|max:50',
            'missed_tag' => 'nullable|string|max:50',
            'settings' => 'nullable|array',
            'video_url' => 'nullable|url',
            'youtube_live_id' => 'nullable|string',
            'thumbnail_url' => 'nullable|url',
        ]);

        $this->webinarService->update($webinar, $validated);

        return back()->with('success', __('webinars.updated'));
    }

    public function destroy(Webinar $webinar)
    {
        $this->authorize('delete', $webinar);

        $this->webinarService->delete($webinar);

        return redirect()->route('webinars.index')
            ->with('success', __('webinars.deleted'));
    }

    public function duplicate(Webinar $webinar)
    {
        $this->authorize('view', $webinar);

        $newWebinar = $this->webinarService->duplicate($webinar);

        return redirect()->route('webinars.edit', $newWebinar)
            ->with('success', __('webinars.duplicated'));
    }

    public function studio(Webinar $webinar): Response
    {
        $this->authorize('update', $webinar);

        $session = $webinar->sessions()->where('status', 'live')->first()
            ?? $webinar->sessions()->where('status', 'scheduled')->orderBy('scheduled_at')->first();

        return Inertia::render('Webinars/Studio', [
            'webinar' => $webinar->load(['products', 'ctas']),
            'session' => $session,
            'registrations' => $webinar->registrations()->latest()->limit(50)->get(),
        ]);
    }

    public function start(Webinar $webinar)
    {
        $this->authorize('update', $webinar);

        $session = $this->webinarService->startLive($webinar);

        if (!$session) {
            return back()->with('error', __('webinars.error_start'));
        }

        return back()->with('success', __('webinars.started'));
    }

    public function end(Webinar $webinar)
    {
        $this->authorize('update', $webinar);

        $session = $webinar->sessions()->where('status', 'live')->first();

        if (!$session || !$this->webinarService->endLive($webinar, $session)) {
            return back()->with('error', __('webinars.error_end'));
        }

        return back()->with('success', __('webinars.ended'));
    }

    public function analytics(Webinar $webinar): Response
    {
        $this->authorize('view', $webinar);

        return Inertia::render('Webinars/Analytics', [
            'webinar' => $webinar,
            'stats' => $this->webinarService->getStats($webinar),
            'funnel' => \App\Models\WebinarAnalytic::getConversionFunnel($webinar),
            'timeline' => \App\Models\WebinarAnalytic::getEngagementTimeline($webinar),
            'devices' => \App\Models\WebinarAnalytic::getDeviceBreakdown($webinar),
        ]);
    }
}
