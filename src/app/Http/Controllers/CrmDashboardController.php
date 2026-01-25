<?php

namespace App\Http\Controllers;

use App\Models\CrmTask;
use App\Models\CrmActivity;
use App\Models\CrmContact;
use App\Models\CrmDeal;
use App\Models\CrmFollowUpEnrollment;
use App\Models\CrmFollowUpSequence;
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
            ->get();

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
        ]);
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
