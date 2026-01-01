<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Webinar;
use App\Models\WebinarRegistration;
use App\Models\WebinarAnalytic;
use App\Services\Webinar\WebinarService;
use Carbon\Carbon;
use DateTimeZone;
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

        // Get common timezones for dropdown
        $timezones = $this->getCommonTimezones();

        // Get webinar's timezone or user's timezone as default
        $defaultTimezone = $webinar->timezone ?? $webinar->user->timezone ?? 'Europe/Warsaw';

        return view('webinar.register', [
            'webinar' => $webinar,
            'nextSessions' => $nextSessions,
            'canRegister' => $webinar->canRegister(),
            'timezones' => $timezones,
            'defaultTimezone' => $defaultTimezone,
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
            'timezone' => 'nullable|string|max:64',
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

        // Load session relationship for display
        $registration->load('session');

        return view('webinar.registered', [
            'webinar' => $webinar,
            'registration' => $registration,
        ]);
    }

    public function watch(string $slug, string $token): View
    {
        $registration = WebinarRegistration::where('access_token', $token)
            ->whereHas('webinar', fn($q) => $q->where('slug', $slug))
            ->with(['webinar', 'session'])
            ->firstOrFail();

        $webinar = $registration->webinar;

        // Handle join
        $session = $webinar->sessions()->where('status', 'live')->first();
        $this->webinarService->handleJoin($webinar, $registration, $session);

        // Calculate session start time for countdown
        $sessionStartTime = null;

        if ($registration->session && $registration->session->scheduled_at) {
            // Use the registered session's scheduled time
            $sessionStartTime = $registration->session->scheduled_at;
        } elseif ($webinar->scheduled_at) {
            // Use webinar's scheduled time for live webinars
            $sessionStartTime = $webinar->scheduled_at;
        } elseif ($webinar->isAutoWebinar() && $webinar->schedule) {
            // Get next session time for autowebinars
            $nextSessions = $webinar->schedule->getNextSessionTimes(1);
            if (!empty($nextSessions)) {
                $sessionStartTime = $nextSessions[0];
            }
        }

        // Check if session should already be playing
        $isLive = $session && $session->status === 'live';
        $shouldPlay = $isLive || ($sessionStartTime && now()->gte($sessionStartTime));

        // Check if webinar has video configured
        $hasVideo = !empty($webinar->video_url) || !empty($webinar->youtube_live_id);

        // Check if session has ended (session time passed, but no live session and no video)
        // For autowebinars: if session start time was more than 2 hours ago and no video
        $sessionEnded = false;
        if ($sessionStartTime && now()->gte($sessionStartTime)) {
            $hoursAfterStart = now()->diffInHours($sessionStartTime);
            // Consider ended if more than 2 hours passed from start time and no active live session
            if (!$isLive && !$hasVideo && $hoursAfterStart >= 2) {
                $sessionEnded = true;
            }
            // Also consider ended if the registered session is marked as ended
            if ($registration->session && $registration->session->status === 'ended') {
                $sessionEnded = true;
            }
        }

        // Check if replay is available
        $hasReplay = !empty($webinar->video_url);

        // Get registration's timezone for display
        $registrationTimezone = $registration->timezone ?? $webinar->timezone ?? 'UTC';

        return view('webinar.watch', [
            'webinar' => $webinar,
            'registration' => $registration,
            'session' => $session,
            'products' => $webinar->products()->active()->get(),
            'pinnedProduct' => $webinar->products()->pinned()->first(),
            'sessionStartTime' => $sessionStartTime?->toIso8601String(),
            'shouldPlay' => $shouldPlay,
            'sessionEnded' => $sessionEnded,
            'hasReplay' => $hasReplay,
            'registrationTimezone' => $registrationTimezone,
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
        $registration = WebinarRegistration::where('access_token', $token)
            ->whereHas('webinar', fn($q) => $q->where('slug', $slug))
            ->with('webinar')
            ->first();

        if (!$registration || !$registration->webinar) {
            return response()->json(['error' => 'Registration not found'], 404);
        }

        $videoTime = (int) $request->get('video_time_seconds', 0);
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

    /**
     * Get common timezones for dropdown.
     */
    protected function getCommonTimezones(): array
    {
        return [
            'Europe/Warsaw' => '(UTC+01:00) Warsaw',
            'Europe/London' => '(UTC+00:00) London',
            'Europe/Paris' => '(UTC+01:00) Paris',
            'Europe/Berlin' => '(UTC+01:00) Berlin',
            'Europe/Madrid' => '(UTC+01:00) Madrid',
            'Europe/Rome' => '(UTC+01:00) Rome',
            'Europe/Amsterdam' => '(UTC+01:00) Amsterdam',
            'Europe/Brussels' => '(UTC+01:00) Brussels',
            'Europe/Vienna' => '(UTC+01:00) Vienna',
            'Europe/Prague' => '(UTC+01:00) Prague',
            'Europe/Stockholm' => '(UTC+01:00) Stockholm',
            'Europe/Helsinki' => '(UTC+02:00) Helsinki',
            'Europe/Athens' => '(UTC+02:00) Athens',
            'Europe/Moscow' => '(UTC+03:00) Moscow',
            'America/New_York' => '(UTC-05:00) New York',
            'America/Chicago' => '(UTC-06:00) Chicago',
            'America/Denver' => '(UTC-07:00) Denver',
            'America/Los_Angeles' => '(UTC-08:00) Los Angeles',
            'America/Toronto' => '(UTC-05:00) Toronto',
            'America/Mexico_City' => '(UTC-06:00) Mexico City',
            'America/Sao_Paulo' => '(UTC-03:00) São Paulo',
            'Asia/Tokyo' => '(UTC+09:00) Tokyo',
            'Asia/Shanghai' => '(UTC+08:00) Shanghai',
            'Asia/Hong_Kong' => '(UTC+08:00) Hong Kong',
            'Asia/Singapore' => '(UTC+08:00) Singapore',
            'Asia/Seoul' => '(UTC+09:00) Seoul',
            'Asia/Dubai' => '(UTC+04:00) Dubai',
            'Asia/Kolkata' => '(UTC+05:30) Kolkata',
            'Australia/Sydney' => '(UTC+10:00) Sydney',
            'Australia/Melbourne' => '(UTC+10:00) Melbourne',
            'Pacific/Auckland' => '(UTC+12:00) Auckland',
            'UTC' => '(UTC+00:00) UTC',
        ];
    }

    /**
     * Auto-register subscriber for webinar from email link.
     * Uses signed URL for security.
     */
    public function autoRegister(Request $request, string $slug, string $subscriberToken)
    {
        $webinar = Webinar::where('slug', $slug)
            ->whereIn('status', [Webinar::STATUS_SCHEDULED, Webinar::STATUS_LIVE, Webinar::STATUS_PUBLISHED])
            ->firstOrFail();

        // Find subscriber by signed token
        $subscriber = \App\Models\Subscriber::where('id', $subscriberToken)->first();

        if (!$subscriber) {
            // If subscriber not found, redirect to normal registration
            return redirect()->route('webinar.register', $slug);
        }

        // Check if already registered
        $existingRegistration = $webinar->registrations()
            ->where('email', $subscriber->email)
            ->first();

        if ($existingRegistration) {
            // Already registered, redirect to watch
            return redirect()->route('webinar.watch', [
                'slug' => $slug,
                'token' => $existingRegistration->access_token,
            ]);
        }

        // Auto-register the subscriber
        $registrationData = [
            'email' => $subscriber->email,
            'first_name' => $subscriber->first_name,
            'last_name' => $subscriber->last_name,
            'phone' => $subscriber->phone,
            'timezone' => $webinar->timezone ?? $webinar->user->timezone ?? 'Europe/Warsaw',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referrer_url' => $request->header('referer'),
            'utm_source' => 'email',
            'utm_medium' => 'auto-register',
        ];

        $registration = $this->webinarService->register($webinar, $registrationData);

        if (!$registration) {
            // Registration failed, redirect to normal registration page
            return redirect()->route('webinar.register', $slug)
                ->with('error', __('webinars.public.register.closed'));
        }

        // Redirect to watch page
        return redirect()->route('webinar.watch', [
            'slug' => $slug,
            'token' => $registration->access_token,
        ]);
    }
}
