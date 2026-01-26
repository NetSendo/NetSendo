<?php

namespace App\Http\Controllers;

use App\Models\CrmTask;
use App\Models\CrmActivity;
use App\Models\CrmContact;
use App\Models\CrmDeal;
use App\Models\CrmFollowUpEnrollment;
use App\Models\CrmFollowUpSequence;
use App\Models\UserCalendarConnection;
use App\Services\GoogleCalendarService;
use App\Helpers\DateHelper;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Carbon\Carbon;

class CrmDashboardController extends Controller
{
    /**
     * Display the CRM dashboard.
     */
    public function index(Request $request): Response
    {
        $userId = auth()->user()->admin_user_id ?? auth()->id();

        // Get tasks grouped by status
        $overdueTasks = CrmTask::forUser($userId)
            ->overdue()
            ->with(['contact.subscriber', 'deal', 'owner'])
            ->orderBy('due_date')
            ->limit(10)
            ->get();

        $todayTasks = CrmTask::forUser($userId)
            ->today()
            ->with(['contact.subscriber', 'deal', 'owner'])
            ->orderBy('due_date')
            ->get()
            ->map(function ($task) {
                $task->source = 'crm';
                return $task;
            });

        // Add Google Calendar events for today
        $googleEvents = $this->getGoogleCalendarEventsForToday($userId);

        // Merge CRM tasks with Google events, sort by time
        $todayTasks = $todayTasks->concat($googleEvents)->sortBy('due_date')->values();

        $upcomingTasks = CrmTask::forUser($userId)
            ->upcoming()
            ->with(['contact.subscriber', 'deal', 'owner'])
            ->orderBy('due_date')
            ->limit(10)
            ->get();

        // Get recent activities
        $recentActivities = CrmActivity::forUser($userId)
            ->with(['subject', 'createdBy'])
            ->recent(15)
            ->get();

        // Get hot leads (high score contacts)
        $hotLeads = CrmContact::forUser($userId)
            ->hotLeads(30)
            ->with(['subscriber', 'company', 'owner'])
            ->limit(5)
            ->get();

        // Get summary stats
        $stats = $this->getStats($userId);

        return Inertia::render('Crm/Dashboard', [
            'overdueTasks' => $overdueTasks,
            'todayTasks' => $todayTasks,
            'upcomingTasks' => $upcomingTasks,
            'recentActivities' => $recentActivities,
            'hotLeads' => $hotLeads,
            'stats' => $stats,
            'userTimezone' => DateHelper::getUserTimezone(),
        ]);
    }

    /**
     * Get Google Calendar events for today.
     */
    private function getGoogleCalendarEventsForToday(int $userId): \Illuminate\Support\Collection
    {
        try {
            $calendarConnection = UserCalendarConnection::where('user_id', $userId)
                ->where('is_active', true)
                ->first();

            if (!$calendarConnection) {
                return collect([]);
            }

            $calendarService = app(GoogleCalendarService::class);
            $now = Carbon::now();
            $startOfDay = $now->copy()->startOfDay();
            $endOfDay = $now->copy()->endOfDay();

            $googleEvents = $calendarService->listEvents(
                $calendarConnection,
                $startOfDay,
                $endOfDay
            );

            return collect($googleEvents['items'] ?? [])->map(function ($event) {
                $start = $event['start']['dateTime'] ?? $event['start']['date'] ?? null;
                $end = $event['end']['dateTime'] ?? $event['end']['date'] ?? null;

                // Extract Google Meet link
                $meetLink = $event['hangoutLink'] ?? null;
                if (!$meetLink && isset($event['conferenceData']['entryPoints'])) {
                    $videoEntry = collect($event['conferenceData']['entryPoints'])
                        ->firstWhere('entryPointType', 'video');
                    $meetLink = $videoEntry['uri'] ?? null;
                }

                // Extract Zoom meeting link from location, description, or conferenceData
                $zoomLink = null;
                $zoomPattern = '/https?:\/\/[a-z0-9.-]*zoom\.us\/[jw]\/[\d\w?=&%-]+/i';

                // Check location first (most common place for Zoom links)
                if (!empty($event['location']) && preg_match($zoomPattern, $event['location'], $matches)) {
                    $zoomLink = $matches[0];
                }
                // Check description if not found in location
                if (!$zoomLink && !empty($event['description']) && preg_match($zoomPattern, $event['description'], $matches)) {
                    $zoomLink = $matches[0];
                }
                // Check conferenceData for Zoom (some Zoom integrations use this)
                if (!$zoomLink && isset($event['conferenceData']['entryPoints'])) {
                    $zoomEntry = collect($event['conferenceData']['entryPoints'])
                        ->first(function ($entry) {
                            return isset($entry['uri']) && str_contains($entry['uri'], 'zoom.us');
                        });
                    $zoomLink = $zoomEntry['uri'] ?? null;
                }

                return (object) [
                    'id' => 'google_' . ($event['id'] ?? uniqid()),
                    'google_event_id' => $event['id'] ?? null,
                    'title' => $event['summary'] ?? __('crm.tasks.untitled_event'),
                    'due_date' => $start ? Carbon::parse($start) : null,
                    'end_date' => $end ? Carbon::parse($end) : null,
                    'type' => 'meeting',
                    'priority' => 'medium',
                    'status' => 'pending',
                    'source' => 'google',
                    'google_meet_link' => $meetLink,
                    'zoom_meeting_link' => $zoomLink,
                    'location' => $event['location'] ?? null,
                    'contact' => null,
                    'deal' => null,
                    'owner' => null,
                ];
            });
        } catch (\Exception $e) {
            \Log::warning('Failed to fetch Google Calendar events for dashboard', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return collect([]);
        }
    }

    /**
     * Display the CRM user guide.
     */
    public function guide(): Response
    {
        return Inertia::render('Crm/Guide');
    }

    /**
     * Get CRM statistics for the dashboard.
     */
    public function stats(Request $request)
    {
        $userId = auth()->user()->admin_user_id ?? auth()->id();

        return response()->json($this->getStats($userId));
    }

    /**
     * Get stats for a user.
     */
    private function getStats(int $userId): array
    {
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();

        return [
            'contacts' => [
                'total' => CrmContact::forUser($userId)->count(),
                'leads' => CrmContact::forUser($userId)->withStatus('lead')->count(),
                'clients' => CrmContact::forUser($userId)->withStatus('client')->count(),
            ],
            'deals' => [
                'open' => CrmDeal::forUser($userId)->open()->count(),
                'open_value' => CrmDeal::forUser($userId)->open()->sum('value'),
                'won_this_month' => CrmDeal::forUser($userId)
                    ->won()
                    ->where('closed_at', '>=', $startOfMonth)
                    ->count(),
                'won_value_this_month' => CrmDeal::forUser($userId)
                    ->won()
                    ->where('closed_at', '>=', $startOfMonth)
                    ->sum('value'),
            ],
            'tasks' => [
                'overdue' => CrmTask::forUser($userId)->overdue()->count(),
                'today' => CrmTask::forUser($userId)->today()->count(),
                'upcoming' => CrmTask::forUser($userId)->upcoming()->count(),
            ],
            'follow_ups' => [
                'active_enrollments' => CrmFollowUpEnrollment::whereHas('sequence', fn($q) => $q->where('user_id', $userId))->active()->count(),
                'due_today' => CrmFollowUpEnrollment::whereHas('sequence', fn($q) => $q->where('user_id', $userId))
                    ->active()
                    ->whereDate('next_action_at', $now->toDateString())
                    ->count(),
                'sequences_active' => CrmFollowUpSequence::forUser($userId)->active()->count(),
            ],
        ];
    }
}
