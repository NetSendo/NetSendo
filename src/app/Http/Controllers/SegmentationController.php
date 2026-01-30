<?php

namespace App\Http\Controllers;

use App\Models\AutomationRule;
use App\Models\AutomationRuleLog;
use App\Models\CrmContact;
use App\Models\Subscriber;
use App\Models\Tag;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SegmentationController extends Controller
{
    /**
     * Display the segmentation dashboard.
     */
    public function index()
    {
        $userId = Auth::id();

        return Inertia::render('Segmentation/Dashboard', [
            'tagSegments' => $this->getTagSegments($userId),
            'scoreSegments' => $this->getScoreSegments($userId),
            'automationStats' => $this->getAutomationStats($userId),
            'recentActivity' => $this->getRecentActivity($userId),
            'engagementTrends' => $this->getEngagementTrends($userId),
        ]);
    }

    /**
     * Get tag distribution with subscriber counts.
     */
    protected function getTagSegments(int $userId): array
    {
        $tags = Tag::withCount(['subscribers' => function ($query) use ($userId) {
            $query->where('subscribers.user_id', $userId);
        }])
            ->orderByDesc('subscribers_count')
            ->take(15)
            ->get()
            ->map(function ($tag) {
                return [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'color' => $tag->color ?? '#6366f1',
                    'count' => $tag->subscribers_count,
                ];
            })
            ->toArray();

        return $tags;
    }

    /**
     * Get score-based segments.
     */
    protected function getScoreSegments(int $userId): array
    {
        $contacts = CrmContact::forUser($userId)->get();

        $segments = [
            'cold' => ['min' => 0, 'max' => 25, 'label' => 'Zimny 🥶', 'color' => '#3b82f6', 'count' => 0],
            'warm' => ['min' => 26, 'max' => 50, 'label' => 'Ciepły 🌡️', 'color' => '#eab308', 'count' => 0],
            'hot' => ['min' => 51, 'max' => 75, 'label' => 'Gorący 🔥', 'color' => '#f97316', 'count' => 0],
            'super_hot' => ['min' => 76, 'max' => PHP_INT_MAX, 'label' => 'Super Hot 🚀', 'color' => '#ef4444', 'count' => 0],
        ];

        foreach ($contacts as $contact) {
            foreach ($segments as $key => &$segment) {
                if ($contact->score >= $segment['min'] && $contact->score <= $segment['max']) {
                    $segment['count']++;
                    break;
                }
            }
        }

        return array_values($segments);
    }

    /**
     * Get automation execution stats.
     */
    protected function getAutomationStats(int $userId): array
    {
        $ruleIds = AutomationRule::forUser($userId)->pluck('id');

        $last24h = Carbon::now()->subDay();
        $last7d = Carbon::now()->subDays(7);

        return [
            'total_rules' => AutomationRule::forUser($userId)->count(),
            'active_rules' => AutomationRule::forUser($userId)->active()->count(),
            'executions_24h' => AutomationRuleLog::whereIn('automation_rule_id', $ruleIds)
                ->where('executed_at', '>=', $last24h)
                ->count(),
            'executions_7d' => AutomationRuleLog::whereIn('automation_rule_id', $ruleIds)
                ->where('executed_at', '>=', $last7d)
                ->count(),
            'success_rate' => $this->calculateSuccessRate($ruleIds, $last7d),
            'top_triggers' => $this->getTopTriggers($userId),
        ];
    }

    /**
     * Calculate success rate for automation executions.
     */
    protected function calculateSuccessRate(object $ruleIds, Carbon $since): float
    {
        $total = AutomationRuleLog::whereIn('automation_rule_id', $ruleIds)
            ->where('executed_at', '>=', $since)
            ->count();

        if ($total === 0) {
            return 100.0;
        }

        $successful = AutomationRuleLog::whereIn('automation_rule_id', $ruleIds)
            ->where('executed_at', '>=', $since)
            ->where('status', 'success')
            ->count();

        return round(($successful / $total) * 100, 1);
    }

    /**
     * Get top triggers from automations.
     */
    protected function getTopTriggers(int $userId): array
    {
        return AutomationRule::forUser($userId)
            ->active()
            ->selectRaw('trigger_event, COUNT(*) as count')
            ->groupBy('trigger_event')
            ->orderByDesc('count')
            ->take(5)
            ->get()
            ->map(function ($row) {
                return [
                    'event' => $row->trigger_event,
                    'label' => AutomationRule::TRIGGER_EVENTS[$row->trigger_event] ?? $row->trigger_event,
                    'count' => $row->count,
                ];
            })
            ->toArray();
    }

    /**
     * Get recent automation activity.
     */
    protected function getRecentActivity(int $userId): array
    {
        $ruleIds = AutomationRule::forUser($userId)->pluck('id');

        return AutomationRuleLog::whereIn('automation_rule_id', $ruleIds)
            ->with(['automationRule:id,name', 'subscriber:id,email,first_name,last_name'])
            ->orderByDesc('executed_at')
            ->take(10)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'rule_name' => $log->automationRule?->name ?? 'Usunięta reguła',
                    'subscriber_email' => $log->subscriber?->email ?? 'Nieznany',
                    'trigger_event' => $log->trigger_event,
                    'status' => $log->status,
                    'executed_at' => $log->executed_at?->diffForHumans(),
                ];
            })
            ->toArray();
    }

    /**
     * Get engagement trends (last 7 days).
     */
    protected function getEngagementTrends(int $userId): array
    {
        $trends = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');

            $ruleIds = AutomationRule::forUser($userId)->pluck('id');

            $executions = AutomationRuleLog::whereIn('automation_rule_id', $ruleIds)
                ->whereDate('executed_at', $date)
                ->count();

            $trends[] = [
                'date' => Carbon::parse($date)->format('d.m'),
                'executions' => $executions,
            ];
        }

        return $trends;
    }
}
