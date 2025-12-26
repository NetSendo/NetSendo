<?php

namespace App\Http\Controllers;

use App\Models\CampaignAudit;
use App\Models\CampaignAuditIssue;
use App\Models\CampaignRecommendation;
use App\Services\CampaignAdvisorService;
use App\Services\CampaignAuditorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class CampaignAuditorController extends Controller
{
    public function __construct(
        protected CampaignAuditorService $auditorService,
        protected CampaignAdvisorService $advisorService
    ) {}

    /**
     * Display the Campaign Auditor dashboard
     */
    public function index()
    {
        $user = Auth::user();

        $latestAudit = CampaignAudit::where('user_id', $user->id)
            ->with([
                'issues' => function ($q) {
                    $q->orderByRaw("FIELD(severity, 'critical', 'warning', 'info')");
                },
                'recommendations' => function ($q) {
                    $q->orderBy('priority', 'desc');
                }
            ])
            ->latest()
            ->first();

        $hasRecentAudit = $latestAudit &&
            $latestAudit->status === CampaignAudit::STATUS_COMPLETED &&
            $latestAudit->created_at->gt(now()->subHours(24));

        // Get audit history for trend display
        $auditHistory = CampaignAudit::where('user_id', $user->id)
            ->where('status', CampaignAudit::STATUS_COMPLETED)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get(['id', 'overall_score', 'created_at', 'critical_count', 'warning_count']);

        // Get advisor settings
        $advisorSettings = $user->settings['campaign_advisor'] ?? [
            'weekly_improvement_target' => 2.0,
            'recommendation_count' => 5,
            'auto_prioritize' => true,
            'focus_areas' => [],
        ];

        // Get recommendation effectiveness stats
        $effectiveness = $this->advisorService->getRecommendationEffectiveness($user);

        return Inertia::render('CampaignAuditor/Index', [
            'latestAudit' => $latestAudit,
            'hasRecentAudit' => $hasRecentAudit,
            'auditHistory' => $auditHistory,
            'categories' => CampaignAuditIssue::CATEGORY_LABELS,
            'severities' => CampaignAuditIssue::getSeverities(),
            'advisorSettings' => $advisorSettings,
            'recommendationTypes' => CampaignRecommendation::TYPE_LABELS,
            'effortLevels' => CampaignRecommendation::EFFORT_LABELS,
            'effectiveness' => $effectiveness,
        ]);
    }

    /**
     * Run a new audit
     */
    public function runAudit(Request $request)
    {
        $request->validate([
            'type' => 'nullable|in:quick,full',
        ]);

        $user = Auth::user();
        $type = $request->input('type', CampaignAudit::TYPE_FULL);

        // Check if audit is already running
        $running = CampaignAudit::where('user_id', $user->id)
            ->where('status', CampaignAudit::STATUS_RUNNING)
            ->exists();

        if ($running) {
            return response()->json([
                'success' => false,
                'error' => __('campaign_auditor.audit_in_progress'),
            ], 400);
        }

        // Rate limiting: max 5 audits per hour
        $recentAudits = CampaignAudit::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($recentAudits >= 5) {
            return response()->json([
                'success' => false,
                'error' => __('campaign_auditor.rate_limit_exceeded'),
            ], 429);
        }

        $audit = $this->auditorService->runAudit($user, $type);

        return response()->json([
            'success' => $audit->status === CampaignAudit::STATUS_COMPLETED,
            'audit' => $audit->load(['issues', 'recommendations']),
        ]);
    }

    /**
     * Get audit details
     */
    public function show(CampaignAudit $audit)
    {
        $this->authorize('view', $audit);

        return response()->json([
            'audit' => $audit->load(['issues' => function ($q) {
                $q->orderByRaw("FIELD(severity, 'critical', 'warning', 'info')");
            }]),
        ]);
    }

    /**
     * Get issues filtered by category or severity
     */
    public function issues(CampaignAudit $audit, Request $request)
    {
        $this->authorize('view', $audit);

        $query = $audit->issues();

        if ($request->has('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->has('severity')) {
            $query->where('severity', $request->input('severity'));
        }

        if ($request->boolean('fixable_only')) {
            $query->where('is_fixable', true)->where('is_fixed', false);
        }

        $issues = $query
            ->orderByRaw("FIELD(severity, 'critical', 'warning', 'info')")
            ->get();

        return response()->json([
            'issues' => $issues,
            'total' => $issues->count(),
        ]);
    }

    /**
     * Mark an issue as fixed
     */
    public function markFixed(CampaignAuditIssue $issue)
    {
        $this->authorize('update', $issue->audit);

        $issue->markAsFixed();

        return response()->json([
            'success' => true,
            'issue' => $issue->fresh(),
        ]);
    }

    /**
     * Get Dashboard widget data (lightweight endpoint)
     */
    public function dashboardWidget()
    {
        $userId = Auth::id();

        $latestAudit = CampaignAudit::where('user_id', $userId)
            ->where('status', CampaignAudit::STATUS_COMPLETED)
            ->latest()
            ->first();

        if (!$latestAudit) {
            return response()->json([
                'hasAudit' => false,
                'score' => null,
                'scoreLabel' => null,
                'criticalCount' => 0,
                'warningCount' => 0,
                'lastAuditAt' => null,
                'isStale' => true,
            ]);
        }

        return response()->json([
            'hasAudit' => true,
            'score' => $latestAudit->overall_score,
            'scoreLabel' => $latestAudit->score_label,
            'criticalCount' => $latestAudit->critical_count,
            'warningCount' => $latestAudit->warning_count,
            'infoCount' => $latestAudit->info_count,
            'totalIssues' => $latestAudit->total_issues,
            'estimatedRevenueLoss' => $latestAudit->estimated_revenue_loss,
            'lastAuditAt' => $latestAudit->created_at->toISOString(),
            'lastAuditHuman' => $latestAudit->created_at->diffForHumans(),
            'isStale' => !$latestAudit->isValid(24),
        ]);
    }

    /**
     * Get audit statistics for a time period
     */
    public function statistics(Request $request)
    {
        $userId = Auth::id();
        $days = $request->input('days', 30);

        $audits = CampaignAudit::where('user_id', $userId)
            ->where('status', CampaignAudit::STATUS_COMPLETED)
            ->where('created_at', '>=', now()->subDays($days))
            ->orderBy('created_at')
            ->get();

        // Calculate score trend
        $scoreTrend = $audits->map(fn ($a) => [
            'date' => $a->created_at->format('Y-m-d'),
            'score' => $a->overall_score,
        ])->values();

        // Most common issues
        $commonIssues = CampaignAuditIssue::whereIn('campaign_audit_id', $audits->pluck('id'))
            ->selectRaw('issue_key, COUNT(*) as count')
            ->groupBy('issue_key')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        return response()->json([
            'totalAudits' => $audits->count(),
            'averageScore' => round($audits->avg('overall_score') ?? 0),
            'latestScore' => $audits->last()?->overall_score,
            'scoreTrend' => $scoreTrend,
            'commonIssues' => $commonIssues,
            'improvement' => $this->calculateImprovement($audits),
        ]);
    }

    /**
     * Calculate score improvement between first and last audit
     */
    protected function calculateImprovement($audits): ?array
    {
        if ($audits->count() < 2) {
            return null;
        }

        $first = $audits->first();
        $last = $audits->last();
        $change = $last->overall_score - $first->overall_score;

        return [
            'from' => $first->overall_score,
            'to' => $last->overall_score,
            'change' => $change,
            'direction' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'stable'),
            'percentage' => $first->overall_score > 0
                ? round(($change / $first->overall_score) * 100, 1)
                : 0,
        ];
    }

    /**
     * Get recommendations for an audit
     */
    public function recommendations(CampaignAudit $audit)
    {
        $this->authorize('view', $audit);

        $recommendations = $audit->recommendations()
            ->orderBy('priority', 'desc')
            ->get();

        return response()->json([
            'recommendations' => $recommendations,
            'total' => $recommendations->count(),
            'by_type' => [
                'quick_win' => $recommendations->where('type', 'quick_win')->count(),
                'strategic' => $recommendations->where('type', 'strategic')->count(),
                'growth' => $recommendations->where('type', 'growth')->count(),
            ],
        ]);
    }

    /**
     * Mark a recommendation as applied
     */
    public function applyRecommendation(CampaignRecommendation $recommendation)
    {
        $this->authorize('update', $recommendation->audit);

        $recommendation->markAsApplied();

        return response()->json([
            'success' => true,
            'recommendation' => $recommendation->fresh(),
        ]);
    }

    /**
     * Get advisor settings for current user
     */
    public function getAdvisorSettings()
    {
        $user = Auth::user();

        $settings = $user->settings['campaign_advisor'] ?? [
            'weekly_improvement_target' => 2.0,
            'recommendation_count' => 5,
            'auto_prioritize' => true,
            'focus_areas' => [],
        ];

        return response()->json([
            'settings' => $settings,
            'categories' => CampaignAuditIssue::CATEGORY_LABELS,
        ]);
    }

    /**
     * Update advisor settings for current user
     */
    public function updateAdvisorSettings(Request $request)
    {
        $request->validate([
            'weekly_improvement_target' => 'required|numeric|min:1|max:10',
            'recommendation_count' => 'nullable|integer|min:1|max:10',
            'auto_prioritize' => 'nullable|boolean',
            'focus_areas' => 'nullable|array',
            'focus_areas.*' => 'string|in:' . implode(',', array_keys(CampaignAuditIssue::CATEGORY_LABELS)),
            'analysis_language' => 'nullable|string|in:en,pl,de,es,fr,it,pt,nl',
        ]);

        $user = Auth::user();
        $settings = $user->settings ?? [];

        $settings['campaign_advisor'] = [
            'weekly_improvement_target' => (float) $request->input('weekly_improvement_target', 2.0),
            'recommendation_count' => (int) $request->input('recommendation_count', 5),
            'auto_prioritize' => (bool) $request->input('auto_prioritize', true),
            'focus_areas' => $request->input('focus_areas', []),
            'analysis_language' => $request->input('analysis_language', 'en'),
        ];

        $user->update(['settings' => $settings]);

        return response()->json([
            'success' => true,
            'settings' => $settings['campaign_advisor'],
        ]);
    }

    /**
     * Measure impact of applied recommendations
     */
    public function measureImpact(CampaignRecommendation $recommendation)
    {
        $this->authorize('view', $recommendation->audit);

        $impact = $this->advisorService->measureRecommendationImpact($recommendation);

        return response()->json([
            'success' => true,
            'impact' => $impact,
            'recommendation' => $recommendation->fresh(),
        ]);
    }
}
