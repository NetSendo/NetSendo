<?php

namespace App\Services\Brain;

use App\Models\AiActionPlan;
use App\Models\AiActionPlanStep;
use App\Models\AiBrainActivityLog;
use App\Models\AiBrainSettings;
use App\Models\AiExecutionLog;
use App\Models\AiGoal;
use App\Models\User;
use App\Services\AI\AiService;
use App\Services\Brain\PerformanceTracker;
use App\Services\Brain\Skills\MarketingSalesSkill;
use Illuminate\Support\Facades\Log;

/**
 * SituationAnalyzer — AI-powered strategic analysis of the user's current state.
 *
 * Called during the CRON cycle BEFORE rule-based task detection.
 * Reviews the full context (goals, CRM, campaigns, activity) and uses AI
 * to decide what the highest-impact actions are.
 */
class SituationAnalyzer
{
    public function __construct(
        protected AiService $aiService,
        protected GoalPlanner $goalPlanner,
        protected KnowledgeBaseService $knowledgeBase,
    ) {}

    /**
     * Perform a full situation analysis for the user.
     *
     * @return array{summary: string, priorities: array}|null
     */
    public function analyze(User $user): ?array
    {
        $integration = $this->aiService->getDefaultIntegration();
        if (!$integration) {
            return null;
        }

        $settings = AiBrainSettings::getForUser($user->id);

        // Use user-preferred integration if set
        if ($settings->preferred_integration_id) {
            $preferredIntegration = \App\Models\AiIntegration::find($settings->preferred_integration_id);
            if ($preferredIntegration?->is_active) {
                $integration = $preferredIntegration;
            }
        }

        try {
            $context = $this->gatherContext($user, $settings);
            $prompt = $this->buildAnalysisPrompt($context, $user, $settings);

            // Call AI with date context
            $response = $this->aiService->generateContent(
                AiService::prependDateContext($prompt, $user->timezone),
                $integration,
                [
                    'max_tokens' => 8000,
                    'temperature' => 0.3,
                ]
            );

            $report = $this->parseReport($response);

            if ($report) {
                // Log the analysis
                AiBrainActivityLog::logEvent($user->id, 'situation_analysis', 'completed', null, [
                    'summary' => mb_substr($report['summary'] ?? '', 0, 500),
                    'priorities_count' => count($report['priorities'] ?? []),
                    'priorities' => array_map(fn($p) => [
                        'title' => $p['title'] ?? '',
                        'agent' => $p['agent'] ?? null,
                        'priority' => $p['priority'] ?? 'medium',
                    ], $report['priorities'] ?? []),
                ]);

                return $report;
            }
        } catch (\Exception $e) {
            Log::warning('SituationAnalyzer: analysis failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            AiBrainActivityLog::logEvent($user->id, 'situation_analysis', 'error', null, [
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Gather all relevant context data for the analysis.
     */
    protected function gatherContext(User $user, AiBrainSettings $settings): array
    {
        $now = now();
        $context = [];

        // 1. Goals summary
        $context['goals'] = $this->goalPlanner->getGoalsSummary($user);

        // 2. Recent execution activity (last 7 days)
        try {
            $recentLogs = AiExecutionLog::forUser($user->id)
                ->where('created_at', '>=', $now->copy()->subDays(7))
                ->selectRaw('agent_type, status, COUNT(*) as count')
                ->groupBy('agent_type', 'status')
                ->get();

            $context['recent_activity'] = [
                'total_executions' => $recentLogs->sum('count'),
                'success_count' => $recentLogs->where('status', 'success')->sum('count'),
                'error_count' => $recentLogs->where('status', 'error')->sum('count'),
                'agents_used' => $recentLogs->pluck('agent_type')->unique()->values()->toArray(),
            ];
        } catch (\Exception $e) {
            $context['recent_activity'] = ['total_executions' => 0];
        }

        // 3. Pending and failed action plans
        try {
            $pendingPlans = AiActionPlan::forUser($user->id)
                ->whereIn('status', ['pending_approval', 'draft'])
                ->count();
            $failedPlans = AiActionPlan::forUser($user->id)
                ->where('status', 'failed')
                ->where('created_at', '>=', $now->copy()->subDays(7))
                ->count();
            $completedPlans = AiActionPlan::forUser($user->id)
                ->where('status', 'completed')
                ->where('created_at', '>=', $now->copy()->subDays(7))
                ->count();

            $context['plans'] = [
                'pending' => $pendingPlans,
                'failed_recent' => $failedPlans,
                'completed_recent' => $completedPlans,
            ];
        } catch (\Exception $e) {
            $context['plans'] = ['pending' => 0, 'failed_recent' => 0, 'completed_recent' => 0];
        }

        // 4. CRM summary
        try {
            $context['crm'] = [
                'total_contacts' => \App\Models\CrmContact::where('user_id', $user->id)->count(),
                'hot_leads' => \App\Models\CrmContact::where('user_id', $user->id)->where('score', '>=', 50)->count(),
                'open_deals' => \App\Models\CrmDeal::where('user_id', $user->id)->whereNull('closed_at')->count(),
            ];
        } catch (\Exception $e) {
            $context['crm'] = ['total_contacts' => 0, 'hot_leads' => 0, 'open_deals' => 0];
        }

        // 5. Subscriber/campaign metrics
        try {
            $lists = \App\Models\ContactList::where('user_id', $user->id)->withCount('subscribers')->get();
            $totalSubscribers = $lists->sum('subscribers_count');
            $listCount = $lists->count();

            $recentCampaigns = AiActionPlan::forUser($user->id)
                ->where('agent_type', 'campaign')
                ->where('created_at', '>=', $now->copy()->subDays(7))
                ->count();

            $context['marketing'] = [
                'total_subscribers' => $totalSubscribers,
                'list_count' => $listCount,
                'recent_campaigns' => $recentCampaigns,
            ];
        } catch (\Exception $e) {
            $context['marketing'] = ['total_subscribers' => 0, 'list_count' => 0, 'recent_campaigns' => 0];
        }

        // 6. Knowledge base size
        try {
            $context['knowledge_entries'] = \App\Models\KnowledgeEntry::where('user_id', $user->id)->count();
        } catch (\Exception $e) {
            $context['knowledge_entries'] = 0;
        }

        // 7. Last situation analysis (to avoid repeating)
        try {
            $lastAnalysis = AiBrainActivityLog::where('user_id', $user->id)
                ->where('event_type', 'situation_analysis')
                ->where('status', 'completed')
                ->latest()
                ->first();

            if ($lastAnalysis) {
                $context['last_analysis'] = [
                    'summary' => $lastAnalysis->metadata['summary'] ?? null,
                    'priorities' => $lastAnalysis->metadata['priorities'] ?? [],
                    'at' => $lastAnalysis->created_at->toIso8601String(),
                ];
            }
        } catch (\Exception $e) {
            // ignore
        }

        // 8. Work mode
        $context['work_mode'] = $settings->work_mode;

        // 9. Campaign performance history (from PerformanceTracker)
        try {
            $performanceTracker = app(PerformanceTracker::class);
            $context['campaign_performance'] = $performanceTracker->getPerformanceContext($user);
        } catch (\Exception $e) {
            $context['campaign_performance'] = ['has_data' => false];
        }

        // 10. Upcoming campaign calendar
        try {
            $calendarService = app(CampaignCalendarService::class);
            $calendarContext = $calendarService->getUpcomingContext($user);
            if (!empty($calendarContext)) {
                $context['campaign_calendar'] = $calendarContext;
            }
        } catch (\Exception $e) {
            // Ignore — table may not exist
        }

        return $context;
    }

    /**
     * Analyze the situation AND create action plans from priorities.
     * This is the main entry point for interactive chat requests.
     *
     * @return array{summary: string, priorities: array, created_tasks: array}|null
     */
    public function analyzeAndCreateTasks(User $user): ?array
    {
        $report = $this->analyze($user);

        if (!$report || empty($report['priorities'])) {
            return $report;
        }

        $createdTasks = [];

        foreach ($report['priorities'] as $priority) {
            if (empty($priority['action']) || empty($priority['agent'])) {
                continue;
            }

            try {
                // Create an action plan for this priority
                $plan = AiActionPlan::create([
                    'user_id' => $user->id,
                    'agent_type' => $priority['agent'],
                    'title' => $priority['title'] ?? 'Situation Analysis Task',
                    'description' => $priority['reasoning'] ?? $priority['action'],
                    'intent' => $priority['action'],
                    'status' => 'draft',
                    'priority' => $priority['priority'] ?? 'medium',
                ]);

                // Create a single step for the plan
                AiActionPlanStep::create([
                    'ai_action_plan_id' => $plan->id,
                    'step_order' => 1,
                    'action_type' => 'execute_action',
                    'title' => $priority['title'] ?? 'Execute',
                    'description' => $priority['action'],
                    'config' => [
                        'source' => 'situation_analysis',
                        'estimated_impact' => $priority['estimated_impact'] ?? 'medium',
                    ],
                    'status' => 'pending',
                ]);

                $createdTasks[] = [
                    'plan_id' => $plan->id,
                    'title' => $plan->title,
                    'agent' => $plan->agent_type,
                    'priority' => $priority['priority'] ?? 'medium',
                    'action' => $priority['action'],
                    'status' => 'draft',
                ];
            } catch (\Exception $e) {
                Log::warning('SituationAnalyzer: failed to create task', [
                    'priority' => $priority['title'] ?? '',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $report['created_tasks'] = $createdTasks;

        return $report;
    }

    /**
     * Build the AI prompt for situation analysis.
     */
    protected function buildAnalysisPrompt(array $context, User $user, AiBrainSettings $settings): string
    {
        $langCode = $settings->resolveLanguage($user);
        $langName = AiBrainSettings::getLanguageName($langCode);

        $contextJson = json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        // Include last analysis to help the AI avoid repeating itself
        $lastAnalysisNote = '';
        if (!empty($context['last_analysis']['summary'])) {
            $lastAnalysisNote = <<<NOTE

PREVIOUS ANALYSIS (avoid repeating the exact same recommendations if the situation hasn't changed):
Summary: {$context['last_analysis']['summary']}
NOTE;
        }

        return <<<PROMPT
You are an expert marketing strategist and CRM advisor for an email marketing & CRM platform.

Analyze the user's current situation and decide what the most impactful next actions should be.
Your goal is to act like a proactive business advisor who identifies opportunities and risks.
You should generate ACTIONABLE tasks that agents can execute autonomously WITHOUT asking the user for more details.

CURRENT CONTEXT:
{$contextJson}
{$lastAnalysisNote}

AVAILABLE AGENTS (you can assign tasks to any of these):
- campaign: email/SMS campaigns, drip series, automation
- list: subscriber lists, cleanup, import, hygiene
- message: content creation, email copy, A/B testing
- crm: contacts, deals, pipelines, tasks, follow-ups
- analytics: reports, trends, comparisons, performance reviews
- segmentation: tags, segments, scoring, audience targeting
- research: web research, competitor analysis, market trends

INSTRUCTIONS:
1. Summarize the current situation in 2-3 concise sentences (what's going well, what needs attention)
2. Identify 1-3 highest-impact priorities based on the data
3. For each priority, specify what agent should handle it and a DETAILED, SELF-CONTAINED action message
4. The action message MUST contain all info the agent needs — topic, audience, tone, goal — so it can execute WITHOUT asking the user
5. Consider: campaign gaps, CRM opportunities, data hygiene, engagement optimization
6. If there are active goals, prioritize actions that advance those goals
7. Don't suggest actions that were recently completed (check recent_activity)
8. If campaign_performance data is available, use lessons from past campaigns to inform new recommendations
   - If a campaign had low open rates → suggest better subject lines or different audience targeting
   - If a campaign performed well → suggest similar approaches or scaling up
   - Reference specific past campaign metrics when relevant

Respond in VALID JSON ONLY:
{
  "summary": "2-3 sentence situational summary",
  "priorities": [
    {
      "title": "short title (max 10 words)",
      "agent": "campaign|list|message|crm|analytics|segmentation|research",
      "action": "detailed, self-contained action message with topic, audience, tone, and goal — the agent must be able to execute this without asking for clarification",
      "priority": "high|medium|low",
      "reasoning": "why this matters now (1 sentence)",
      "estimated_impact": "high|medium|low",
      "target_list_ids": [1, 2],
      "exclude_segments": ["churned", "bounced"]
    }
  ]
}

IMPORTANT: Respond in {$langName}. All text fields (summary, title, action, reasoning) must be in {$langName}.
PROMPT;
    }

    /**
     * Parse the AI response into a structured report.
     */
    protected function parseReport(string $response): ?array
    {
        // Strip markdown code blocks
        $response = preg_replace('/```(?:json)?\s*/', '', $response);
        $response = preg_replace('/```\s*$/', '', $response);
        $response = trim($response);

        $data = json_decode($response, true);

        if (!$data || !isset($data['summary'])) {
            return null;
        }

        // Validate priorities structure
        if (!empty($data['priorities'])) {
            $data['priorities'] = array_filter($data['priorities'], function ($p) {
                return !empty($p['action']) && !empty($p['agent']);
            });
            $data['priorities'] = array_values($data['priorities']);
        }

        return $data;
    }
}
