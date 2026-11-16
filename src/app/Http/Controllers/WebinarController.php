<?php

namespace App\Http\Controllers;

use App\Models\Webinar;
use App\Models\ContactList;
use App\Services\LicenseService;
use App\Services\Webinar\WebinarService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
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
            'scheduled_at' => 'nullable|date',
            'timezone' => 'nullable|string|timezone',
            'target_list_id' => 'nullable|exists:contact_lists,id',
            'clicked_list_id' => 'nullable|exists:contact_lists,id',
            'attended_list_id' => 'nullable|exists:contact_lists,id',
            'attended_min_minutes' => 'nullable|integer|min:1|max:300',
            'registration_tag' => 'nullable|string|max:50',
            'attended_tag' => 'nullable|string|max:50',
            'missed_tag' => 'nullable|string|max:50',
            'settings' => 'nullable|array',
            'settings.content' => 'nullable|array',
            'settings.content.*' => 'nullable|string|max:5000',
            'settings.benefits' => 'nullable|array',
            'settings.benefits.*' => 'nullable|string|max:500',
            'settings.registration_sections' => 'nullable|array|max:20',
            'settings.registration_sections.*.type' => 'nullable|in:text,video',
            'settings.registration_sections.*.title' => 'nullable|string|max:255',
            'settings.registration_sections.*.body' => 'nullable|string|max:10000',
            'settings.registration_sections.*.video_url' => 'nullable|string|max:500',
            'settings.registration_sections.*.placement' => 'nullable|in:above_form,below_form',
            'settings.thankyou_sections' => 'nullable|array|max:20',
            'settings.thankyou_sections.*.type' => 'nullable|in:text,video',
            'settings.thankyou_sections.*.title' => 'nullable|string|max:255',
            'settings.thankyou_sections.*.body' => 'nullable|string|max:10000',
            'settings.thankyou_sections.*.video_url' => 'nullable|string|max:500',
            'settings.calendly' => 'nullable|array',
            'settings.calendly.enabled' => 'nullable|boolean',
            'settings.calendly.url' => 'nullable|string|max:500|starts_with:https://calendly.com/,https://www.calendly.com/',
            'settings.calendly.title' => 'nullable|string|max:255',
            'settings.calendly.description' => 'nullable|string|max:2000',
            'video_url' => 'nullable|url',
            'youtube_live_id' => 'nullable|string|max:64',
            'vimeo_id' => 'nullable|string|max:64',
            'video_provider' => 'nullable|in:youtube,vimeo,upload',
        ]);

        // Auto and hybrid webinars are GOLD
        if ($feature = Webinar::featureForType($validated['type'])) {
            app(LicenseService::class)->ensureAllows($feature);
        }

        if (array_key_exists('scheduled_at', $validated)) {
            $validated['scheduled_at'] = $this->scheduledAtInUtc(
                $validated['scheduled_at'],
                $validated['timezone'] ?? auth()->user()->timezone ?? 'UTC',
            );

            // Checked after the conversion: at 19:00 UTC (20:00 Warsaw) a
            // 19:30 typed for Warsaw is already past, though 19:30 UTC is not.
            Validator::make(
                ['scheduled_at' => $validated['scheduled_at']?->toDateTimeString()],
                ['scheduled_at' => 'nullable|after:now'],
            )->validate();
        }

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

        $webinarData = $webinar->load(['products', 'ctas', 'schedule', 'targetList'])->toArray();
        $webinarData['registration_url'] = $webinar->registration_url;
        $webinarData['purchase_thank_you_url'] = $webinar->purchase_thank_you_url;
        $webinarData['effective_timezone'] = $webinar->effective_timezone;
        $webinarData['user_timezone'] = auth()->user()->timezone;
        // The datetime-local input shows wall-clock time in the webinar's zone.
        $webinarData['scheduled_at_local'] = $webinar->scheduled_at
            ?->copy()->setTimezone($webinar->effective_timezone)->format('Y-m-d\TH:i');

        return Inertia::render('Webinars/Edit', [
            'webinar' => $webinarData,
            'types' => Webinar::getTypes(),
            'statuses' => Webinar::getStatuses(),
            'lists' => ContactList::forUser(auth()->id())->get(['id', 'name']),
            'timezones' => \DateTimeZone::listIdentifiers(),
        ]);
    }

    public function updateStatus(Request $request, Webinar $webinar)
    {
        $this->authorize('update', $webinar);

        $validated = $request->validate([
            'status' => 'required|in:draft,scheduled,live,ended,published',
        ]);

        $newStatus = $validated['status'];
        $currentStatus = $webinar->status;

        // Validate status transitions
        $allowedTransitions = [
            Webinar::STATUS_DRAFT => [Webinar::STATUS_SCHEDULED, Webinar::STATUS_PUBLISHED],
            Webinar::STATUS_SCHEDULED => [Webinar::STATUS_DRAFT, Webinar::STATUS_LIVE],
            Webinar::STATUS_LIVE => [Webinar::STATUS_ENDED],
            Webinar::STATUS_ENDED => [Webinar::STATUS_PUBLISHED, Webinar::STATUS_DRAFT],
            Webinar::STATUS_PUBLISHED => [Webinar::STATUS_DRAFT],
        ];

        if (!in_array($newStatus, $allowedTransitions[$currentStatus] ?? [])) {
            return back()->with('error', __('webinars.status_change_not_allowed'));
        }

        // Update timestamps based on status change
        $updateData = ['status' => $newStatus];

        if ($newStatus === Webinar::STATUS_LIVE && !$webinar->started_at) {
            $updateData['started_at'] = now();
        }

        if ($newStatus === Webinar::STATUS_ENDED && !$webinar->ended_at) {
            $updateData['ended_at'] = now();
            if ($webinar->started_at) {
                $updateData['duration_minutes'] = $webinar->started_at->diffInMinutes(now());
            }
        }

        $webinar->update($updateData);

        return back()->with('success', __('webinars.status_updated'));
    }

    public function update(Request $request, Webinar $webinar)
    {
        $this->authorize('update', $webinar);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'scheduled_at' => 'nullable|date',
            'timezone' => 'nullable|string|timezone',
            'target_list_id' => 'nullable|exists:contact_lists,id',
            'clicked_list_id' => 'nullable|exists:contact_lists,id',
            'attended_list_id' => 'nullable|exists:contact_lists,id',
            'attended_min_minutes' => 'nullable|integer|min:1|max:300',
            'registration_tag' => 'nullable|string|max:50',
            'attended_tag' => 'nullable|string|max:50',
            'missed_tag' => 'nullable|string|max:50',
            'settings' => 'nullable|array',
            'settings.content' => 'nullable|array',
            'settings.content.*' => 'nullable|string|max:5000',
            'settings.benefits' => 'nullable|array',
            'settings.benefits.*' => 'nullable|string|max:500',
            'settings.registration_sections' => 'nullable|array|max:20',
            'settings.registration_sections.*.type' => 'nullable|in:text,video',
            'settings.registration_sections.*.title' => 'nullable|string|max:255',
            'settings.registration_sections.*.body' => 'nullable|string|max:10000',
            'settings.registration_sections.*.video_url' => 'nullable|string|max:500',
            'settings.registration_sections.*.placement' => 'nullable|in:above_form,below_form',
            'settings.thankyou_sections' => 'nullable|array|max:20',
            'settings.thankyou_sections.*.type' => 'nullable|in:text,video',
            'settings.thankyou_sections.*.title' => 'nullable|string|max:255',
            'settings.thankyou_sections.*.body' => 'nullable|string|max:10000',
            'settings.thankyou_sections.*.video_url' => 'nullable|string|max:500',
            'settings.calendly' => 'nullable|array',
            'settings.calendly.enabled' => 'nullable|boolean',
            'settings.calendly.url' => 'nullable|string|max:500|starts_with:https://calendly.com/,https://www.calendly.com/',
            'settings.calendly.title' => 'nullable|string|max:255',
            'settings.calendly.description' => 'nullable|string|max:2000',
            'video_url' => 'nullable|url',
            'youtube_live_id' => 'nullable|string|max:64',
            'vimeo_id' => 'nullable|string|max:64',
            'video_provider' => 'nullable|in:youtube,vimeo,upload',
            'thumbnail_url' => 'nullable|url',
        ]);

        if (array_key_exists('scheduled_at', $validated)) {
            // The timezone may change in the same request; an empty one
            // inherits the owner's, as Webinar::effective_timezone does.
            $timezone = array_key_exists('timezone', $validated)
                ? ($validated['timezone'] ?? $webinar->user?->timezone ?? 'UTC')
                : $webinar->effective_timezone;

            $validated['scheduled_at'] = $this->scheduledAtInUtc($validated['scheduled_at'], $timezone);
        }

        $this->webinarService->update($webinar, $validated);

        return back()->with('success', __('webinars.updated'));
    }

    /**
     * The panel sends the start time from a datetime-local input: wall-clock
     * digits without an offset ("2026-11-20T19:00"). They mean that time in
     * the webinar's timezone, so they are read there and stored as UTC (the
     * app timezone). A value that carries its own offset keeps it.
     */
    protected function scheduledAtInUtc(?string $value, string $timezone): ?Carbon
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return Carbon::parse($value, $timezone)->setTimezone(config('app.timezone'));
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

        // A copy of an auto or hybrid webinar is a new GOLD webinar
        if ($feature = Webinar::featureForType($webinar->type)) {
            app(LicenseService::class)->ensureAllows($feature);
        }

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
