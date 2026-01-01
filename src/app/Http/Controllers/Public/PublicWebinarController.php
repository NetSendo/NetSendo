<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Webinar;
use App\Models\WebinarRegistration;
use App\Models\WebinarAnalytic;
use App\Services\Webinar\WebinarService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicWebinarController extends Controller
{
    public function __construct(protected WebinarService $webinarService) {}

    public function register(string $slug): View
    {
        $webinar = Webinar::where('slug', $slug)
            ->whereIn('status', [Webinar::STATUS_SCHEDULED, Webinar::STATUS_LIVE, Webinar::STATUS_PUBLISHED])
            ->firstOrFail();

        // Track page view
        WebinarAnalytic::track($webinar, WebinarAnalytic::EVENT_PAGE_VIEW, null, null, null, null,
            request()->ip(), request()->userAgent());

        $nextSessions = [];
        if ($webinar->isAutoWebinar() && $webinar->schedule) {
            $nextSessions = $webinar->schedule->getNextSessionTimes(5);
        }

        return view('webinar.register', [
            'webinar' => $webinar,
            'nextSessions' => $nextSessions,
            'canRegister' => $webinar->canRegister(),
        ]);
    }

    public function submitRegistration(Request $request, string $slug)
    {
        $webinar = Webinar::where('slug', $slug)->firstOrFail();

        $validated = $request->validate([
            'email' => 'required|email',
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:20',
            'session_time' => 'nullable|date',
        ]);

        // Add UTM and technical data
        $validated['utm_source'] = $request->get('utm_source');
        $validated['utm_medium'] = $request->get('utm_medium');
        $validated['utm_campaign'] = $request->get('utm_campaign');
        $validated['ip_address'] = $request->ip();
        $validated['user_agent'] = $request->userAgent();
        $validated['referrer_url'] = $request->header('referer');

        $registration = $this->webinarService->register($webinar, $validated);

        if (!$registration) {
            return back()->with('error', __('Registration is closed.'));
        }

        return view('webinar.registered', [
            'webinar' => $webinar,
            'registration' => $registration,
        ]);
    }

    public function watch(string $slug, string $token): View
    {
        $registration = WebinarRegistration::where('access_token', $token)
            ->whereHas('webinar', fn($q) => $q->where('slug', $slug))
            ->with('webinar')
            ->firstOrFail();

        $webinar = $registration->webinar;

        // Handle join
        $session = $webinar->sessions()->where('status', 'live')->first();
        $this->webinarService->handleJoin($webinar, $registration, $session);

        return view('webinar.watch', [
            'webinar' => $webinar,
            'registration' => $registration,
            'session' => $session,
            'products' => $webinar->products()->active()->get(),
            'pinnedProduct' => $webinar->products()->pinned()->first(),
        ]);
    }

    public function replay(string $slug, string $token): View
    {
        $registration = WebinarRegistration::where('access_token', $token)
            ->whereHas('webinar', fn($q) => $q->where('slug', $slug))
            ->with('webinar')
            ->firstOrFail();

        $webinar = $registration->webinar;

        if (!$webinar->settings_with_defaults['allow_replay']) {
            abort(404);
        }

        return view('webinar.replay', [
            'webinar' => $webinar,
            'registration' => $registration,
            'products' => $webinar->products()->active()->get(),
        ]);
    }

    public function leave(Request $request, string $slug, string $token)
    {
        $registration = WebinarRegistration::where('access_token', $token)
            ->whereHas('webinar', fn($q) => $q->where('slug', $slug))
            ->firstOrFail();

        $this->webinarService->handleLeave(
            $registration->webinar,
            $registration,
            null,
            $request->get('video_time_seconds')
        );

        return response()->json(['success' => true]);
    }

    public function trackProgress(Request $request, string $slug, string $token)
    {
        $registration = WebinarRegistration::where('access_token', $token)->firstOrFail();

        $videoTime = $request->get('video_time_seconds', 0);
        $registration->updateVideoPosition($videoTime);

        WebinarAnalytic::track(
            $registration->webinar,
            WebinarAnalytic::EVENT_VIDEO_PROGRESS,
            null,
            $registration,
            $videoTime,
            ['percent' => $request->get('percent')]
        );

        return response()->json(['success' => true]);
    }
}
