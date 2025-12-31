<?php

namespace App\Services;

use App\Models\CampaignAudit;
use App\Models\CampaignAuditIssue;
use App\Models\CampaignRecommendation;
use App\Models\User;
use App\Models\Message;
use App\Models\EmailOpen;
use App\Models\EmailClick;
use App\Services\AI\AiService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class CampaignAdvisorService
{
    public function __construct(
        protected AiService $aiService
    ) {}

    /**
     * Generate recommendations for an audit based on user's target improvement
     */
    public function generateRecommendations(CampaignAudit $audit, float $targetImprovementPercent = 2.0): Collection
    {
        $user = $audit->user;

        // Get user's advisor settings
        $settings = $user->settings['campaign_advisor'] ?? [];
        $maxRecommendations = $settings['recommendation_count'] ?? 5;
        $focusAreas = $settings['focus_areas'] ?? [];
        $language = $settings['analysis_language'] ?? 'en';

        $recommendations = collect();

        try {
            // 1. Generate Quick Wins from current issues
            $quickWins = $this->generateQuickWins($audit, $focusAreas, $language);
            $recommendations = $recommendations->merge($quickWins);

            // 2. Generate Strategic recommendations from historical data
            $strategic = $this->generateStrategicRecommendations($user, $audit, $focusAreas, $language);
            $recommendations = $recommendations->merge($strategic);

            // 3. Generate Growth recommendations using AI
            $growth = $this->generateGrowthRecommendations($user, $audit, $language);
            $recommendations = $recommendations->merge($growth);

            // Prioritize and limit recommendations
            $recommendations = $this->prioritizeRecommendations($recommendations, $targetImprovementPercent);
            $recommendations = $recommendations->take($maxRecommendations);

            // Save recommendations to database
            foreach ($recommendations as $recData) {
                $audit->recommendations()->create($recData);
            }

        } catch (\Exception $e) {
            Log::error('Failed to generate recommendations', [
                'audit_id' => $audit->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $audit->recommendations()->get();
    }

    /**
     * Generate Quick Win recommendations from current audit issues
     */
    protected function generateQuickWins(CampaignAudit $audit, array $focusAreas = [], string $language = 'en'): Collection
    {
        $quickWins = collect();
        $issues = $audit->issues()->orderBy('impact_score', 'desc')->get();

        // Issue keys that have recommendations
        $issueKeysWithRecommendations = [
            CampaignAuditIssue::ISSUE_MISSING_PREHEADER => [
                'expected_impact' => 3.0,
                'effort_level' => CampaignRecommendation::EFFORT_LOW,
            ],
            CampaignAuditIssue::ISSUE_LONG_SUBJECT => [
                'expected_impact' => 2.0,
                'effort_level' => CampaignRecommendation::EFFORT_LOW,
            ],
            CampaignAuditIssue::ISSUE_NO_PERSONALIZATION => [
                'expected_impact' => 4.0,
                'effort_level' => CampaignRecommendation::EFFORT_LOW,
            ],
            CampaignAuditIssue::ISSUE_SPAM_CONTENT => [
                'expected_impact' => 5.0,
                'effort_level' => CampaignRecommendation::EFFORT_MEDIUM,
            ],
            CampaignAuditIssue::ISSUE_STALE_LIST => [
                'expected_impact' => 6.0,
                'effort_level' => CampaignRecommendation::EFFORT_MEDIUM,
            ],
            CampaignAuditIssue::ISSUE_POOR_TIMING => [
                'expected_impact' => 3.5,
                'effort_level' => CampaignRecommendation::EFFORT_LOW,
            ],
            CampaignAuditIssue::ISSUE_OVER_MAILING => [
                'expected_impact' => 4.0,
                'effort_level' => CampaignRecommendation::EFFORT_MEDIUM,
            ],
            CampaignAuditIssue::ISSUE_NO_AUTOMATION => [
                'expected_impact' => 8.0,
                'effort_level' => CampaignRecommendation::EFFORT_HIGH,
            ],
            CampaignAuditIssue::ISSUE_SMS_MISSING => [
                'expected_impact' => 7.0,
                'effort_level' => CampaignRecommendation::EFFORT_HIGH,
            ],
        ];

        foreach ($issues as $issue) {
            if (isset($issueKeysWithRecommendations[$issue->issue_key])) {
                $recMeta = $issueKeysWithRecommendations[$issue->issue_key];

                // Skip if focus areas set and this category isn't included
                if (!empty($focusAreas) && !in_array($issue->category, $focusAreas)) {
                    continue;
                }

                // Get translated content using Laravel's localization
                $translationKey = "recommendations.{$issue->issue_key}";
                $title = __("{$translationKey}.title", [], $language);
                $description = __("{$translationKey}.description", [], $language);
                $actionSteps = __("{$translationKey}.action_steps", [], $language);

                // Fallback to English if translation not found
                if ($title === "{$translationKey}.title") {
                    $title = __("{$translationKey}.title", [], 'en');
                    $description = __("{$translationKey}.description", [], 'en');
                    $actionSteps = __("{$translationKey}.action_steps", [], 'en');
                }

                // Ensure action_steps is an array
                if (is_string($actionSteps)) {
                    $actionSteps = [$actionSteps];
                }

                $quickWins->push([
                    'type' => CampaignRecommendation::TYPE_QUICK_WIN,
                    'priority' => $this->calculatePriorityFromImpactEffort(
                        $recMeta['expected_impact'],
                        $recMeta['effort_level']
                    ),
                    'title' => $title,
                    'description' => $description,
                    'expected_impact' => $recMeta['expected_impact'],
                    'effort_level' => $recMeta['effort_level'],
                    'category' => $issue->category,
                    'action_steps' => $actionSteps,
                    'context' => [
                        'source_issue_id' => $issue->id,
                        'source_issue_key' => $issue->issue_key,
                    ],
                ]);
            }
        }

        return $quickWins->take(3); // Max 3 quick wins
    }

    /**
     * Generate Strategic recommendations from historical performance data
     */
    protected function generateStrategicRecommendations(User $user, CampaignAudit $audit, array $focusAreas = [], string $language = 'en'): Collection
    {
        $strategic = collect();

        // Analyze historical data
        $trends = $this->analyzePerformanceTrends($user);

        // Check open rate trend
        if (isset($trends['open_rate']) && $trends['open_rate']['trend'] === 'declining') {
            $change = $trends['open_rate']['change'];
            $strategic->push([
                'type' => CampaignRecommendation::TYPE_STRATEGIC,
                'priority' => 8,
                'title' => __('recommendations.declining_open_rate.title', [], $language),
                'description' => __('recommendations.declining_open_rate.description', ['change' => $change], $language),
                'expected_impact' => 5.0,
                'effort_level' => CampaignRecommendation::EFFORT_MEDIUM,
                'category' => CampaignAuditIssue::CATEGORY_DELIVERABILITY,
                'action_steps' => __('recommendations.declining_open_rate.action_steps', [], $language),
                'context' => [
                    'open_rate_change' => $trends['open_rate']['change'],
                    'current_rate' => $trends['open_rate']['current'],
                ],
            ]);
        }

        // Check click rate patterns
        if (isset($trends['click_rate']) && $trends['click_rate']['current'] < 2.0) {
            $strategic->push([
                'type' => CampaignRecommendation::TYPE_STRATEGIC,
                'priority' => 7,
                'title' => __('recommendations.low_click_rate.title', [], $language),
                'description' => __('recommendations.low_click_rate.description', [], $language),
                'expected_impact' => 4.0,
                'effort_level' => CampaignRecommendation::EFFORT_MEDIUM,
                'category' => CampaignAuditIssue::CATEGORY_CONTENT,
                'action_steps' => __('recommendations.low_click_rate.action_steps', [], $language),
                'context' => [
                    'current_click_rate' => $trends['click_rate']['current'],
                ],
            ]);
        }

        // Check for segmentation opportunities
        $subscriberCount = \App\Models\Subscriber::whereHas('contactLists', function ($q) use ($user) {
            $q->where('contact_lists.user_id', $user->id);
        })->distinct()->count();
        $taggedPercent = $this->calculateTaggedSubscriberPercent($user);

        if ($subscriberCount > 500 && $taggedPercent < 30) {
            $strategic->push([
                'type' => CampaignRecommendation::TYPE_STRATEGIC,
                'priority' => 6,
                'title' => __('recommendations.low_segmentation.title', [], $language),
                'description' => __('recommendations.low_segmentation.description', ['percent' => $taggedPercent], $language),
                'expected_impact' => 5.0,
                'effort_level' => CampaignRecommendation::EFFORT_HIGH,
                'category' => CampaignAuditIssue::CATEGORY_SEGMENTATION,
                'action_steps' => __('recommendations.low_segmentation.action_steps', [], $language),
                'context' => [
                    'subscriber_count' => $subscriberCount,
                    'tagged_percent' => $taggedPercent,
                ],
            ]);
        }

        return $strategic->take(2); // Max 2 strategic recommendations
    }

    /**
     * Generate AI-powered Growth recommendations
     */
    protected function generateGrowthRecommendations(User $user, CampaignAudit $audit, string $language = 'en'): Collection
    {
        $growth = collect();

        try {
            $integration = $this->aiService->getDefaultIntegration();
            if (!$integration) {
                return $growth;
            }

            // Build context for AI
            $context = $this->buildAiContext($user, $audit);

            $prompt = $this->buildGrowthPrompt($context, $language);
            $response = $this->aiService->generateContent($prompt, $integration, [
                'max_tokens' => 800,
                'temperature' => 0.4,
            ]);

            $aiRecommendations = $this->parseAiRecommendations($response);

            foreach ($aiRecommendations as $rec) {
                $growth->push([
                    'type' => CampaignRecommendation::TYPE_GROWTH,
                    'priority' => $rec['priority'] ?? 5,
                    'title' => $rec['title'],
                    'description' => $rec['description'],
                    'expected_impact' => $rec['expected_impact'] ?? 5.0,
                    'effort_level' => $rec['effort_level'] ?? CampaignRecommendation::EFFORT_MEDIUM,
                    'category' => $rec['category'] ?? null,
                    'action_steps' => $rec['action_steps'] ?? [],
                    'context' => [
                        'source' => 'ai_generated',
                    ],
                ]);
            }

        } catch (\Exception $e) {
            Log::warning('AI growth recommendations failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $growth->take(2); // Max 2 AI recommendations
    }

    /**
     * Analyze historical performance trends
     */
    protected function analyzePerformanceTrends(User $user): array
    {
        $trends = [];

        // Get messages from last 30 days
        $recentMessages = Message::where('user_id', $user->id)
            ->where('status', 'sent')
            ->where('channel', 'email')
            ->where('created_at', '>=', now()->subDays(30))
            ->withCount('queueEntries as total_sent')
            ->get();

        if ($recentMessages->count() < 3) {
            return $trends;
        }

        // Calculate open rate trend
        $totalSent = $recentMessages->sum('total_sent');
        $totalOpens = 0;
        $totalClicks = 0;

        foreach ($recentMessages as $message) {
            $totalOpens += EmailOpen::where('message_id', $message->id)->count();
            $totalClicks += EmailClick::where('message_id', $message->id)->count();
        }

        $currentOpenRate = $totalSent > 0 ? ($totalOpens / $totalSent) * 100 : 0;
        $currentClickRate = $totalSent > 0 ? ($totalClicks / $totalSent) * 100 : 0;

        // Compare with previous period
        $olderMessages = Message::where('user_id', $user->id)
            ->where('status', 'sent')
            ->where('channel', 'email')
            ->whereBetween('created_at', [now()->subDays(60), now()->subDays(30)])
            ->withCount('queueEntries as total_sent')
            ->get();

        if ($olderMessages->count() >= 3) {
            $olderSent = $olderMessages->sum('total_sent');
            $olderOpens = 0;

            foreach ($olderMessages as $message) {
                $olderOpens += EmailOpen::where('message_id', $message->id)->count();
            }

            $previousOpenRate = $olderSent > 0 ? ($olderOpens / $olderSent) * 100 : 0;
            $openRateChange = $currentOpenRate - $previousOpenRate;

            $trends['open_rate'] = [
                'current' => round($currentOpenRate, 1),
                'previous' => round($previousOpenRate, 1),
                'change' => round($openRateChange, 1),
                'trend' => $openRateChange < -2 ? 'declining' : ($openRateChange > 2 ? 'improving' : 'stable'),
            ];
        }

        $trends['click_rate'] = [
            'current' => round($currentClickRate, 1),
        ];

        return $trends;
    }

    /**
     * Calculate percentage of subscribers with tags
     */
    protected function calculateTaggedSubscriberPercent(User $user): float
    {
        $total = \App\Models\Subscriber::whereHas('contactLists', function ($q) use ($user) {
            $q->where('contact_lists.user_id', $user->id);
        })->distinct()->count();

        if ($total === 0) {
            return 0;
        }

        $tagged = \App\Models\Subscriber::whereHas('contactLists', function ($q) use ($user) {
            $q->where('contact_lists.user_id', $user->id);
        })->whereHas('tags')->distinct()->count();

        return round(($tagged / $total) * 100, 1);
    }

    /**
     * Calculate priority from impact and effort
     */
    protected function calculatePriorityFromImpactEffort(float $impact, string $effort): int
    {
        $effortMultipliers = [
            CampaignRecommendation::EFFORT_LOW => 1.5,
            CampaignRecommendation::EFFORT_MEDIUM => 1.0,
            CampaignRecommendation::EFFORT_HIGH => 0.7,
        ];

        $multiplier = $effortMultipliers[$effort] ?? 1.0;
        $priority = (int) round($impact * $multiplier);

        return max(1, min(10, $priority));
    }

    /**
     * Prioritize recommendations based on target improvement
     */
    protected function prioritizeRecommendations(Collection $recommendations, float $targetPercent): Collection
    {
        return $recommendations->sortByDesc(function ($rec) use ($targetPercent) {
            // Calculate a score based on impact vs effort ratio
            // and how much it contributes to the target
            $impactScore = $rec['expected_impact'] ?? 0;

            $effortPenalty = match ($rec['effort_level'] ?? 'medium') {
                CampaignRecommendation::EFFORT_LOW => 0,
                CampaignRecommendation::EFFORT_MEDIUM => 1,
                CampaignRecommendation::EFFORT_HIGH => 3,
                default => 1,
            };

            // Boost quick wins
            $typeBonus = match ($rec['type'] ?? '') {
                CampaignRecommendation::TYPE_QUICK_WIN => 2,
                CampaignRecommendation::TYPE_STRATEGIC => 1,
                CampaignRecommendation::TYPE_GROWTH => 0,
                default => 0,
            };

            return ($impactScore - $effortPenalty + $typeBonus) * ($rec['priority'] ?? 5);
        })->values();
    }

    /**
     * Build context for AI recommendation generation
     */
    protected function buildAiContext(User $user, CampaignAudit $audit): array
    {
        $subscriberCount = \App\Models\Subscriber::whereHas('contactLists', function ($q) use ($user) {
            $q->where('contact_lists.user_id', $user->id);
        })->distinct()->count();

        return [
            'score' => $audit->overall_score,
            'critical_issues' => $audit->critical_count,
            'warning_issues' => $audit->warning_count,
            'subscriber_count' => $subscriberCount,
            'list_count' => $user->contactLists()->count(),
            'automation_count' => $user->automationRules()->count(),
            'has_sms' => Message::where('user_id', $user->id)->where('channel', 'sms')->exists(),
            'categories_with_issues' => $audit->issues()->distinct()->pluck('category')->toArray(),
        ];
    }

    /**
     * Build prompt for AI growth recommendations
     */
    protected function buildGrowthPrompt(array $context, string $language = 'en'): string
    {
        $languageNames = [
            'en' => 'English',
            'pl' => 'Polish',
            'de' => 'German',
            'es' => 'Spanish',
            'fr' => 'French',
            'it' => 'Italian',
            'pt' => 'Portuguese',
            'nl' => 'Dutch',
        ];

        $languageName = $languageNames[$language] ?? 'English';
        $categoriesWithIssues = is_array($context['categories_with_issues'])
            ? implode(', ', $context['categories_with_issues'])
            : $context['categories_with_issues'];

        return <<<PROMPT
You are an email marketing expert advisor. Based on this user's campaign data, provide 1-2 growth-focused recommendations.

IMPORTANT: Respond in {$languageName} language. All text (title, description, action_steps) MUST be written in {$languageName}.

USER DATA:
- Current health score: {$context['score']}/100
- Critical issues: {$context['critical_issues']}
- Warning issues: {$context['warning_issues']}
- Total subscribers: {$context['subscriber_count']}
- Contact lists: {$context['list_count']}
- Automations: {$context['automation_count']}
- Uses SMS: {$context['has_sms']}

CATEGORIES WITH ISSUES: {$categoriesWithIssues}

Provide strategic growth recommendations that go beyond fixing current issues. Focus on scaling their email marketing success.

RESPOND IN JSON FORMAT ONLY (but with {$languageName} text content):
[
  {
    "title": "Short recommendation title in {$languageName}",
    "description": "2-3 sentence description in {$languageName}",
    "expected_impact": 5.0,
    "effort_level": "medium",
    "priority": 6,
    "category": "revenue|content|automation|segmentation",
    "action_steps": ["Step 1 in {$languageName}", "Step 2 in {$languageName}", "Step 3 in {$languageName}"]
  }
]

Return 1-2 actionable recommendations. Focus on high-impact strategies.
PROMPT;
    }

    /**
     * Parse AI response into recommendations array
     */
    protected function parseAiRecommendations(string $response): array
    {
        try {
            if (preg_match('/\[[\s\S]*\]/', $response, $matches)) {
                $recommendations = json_decode($matches[0], true);
                if (is_array($recommendations)) {
                    return array_slice($recommendations, 0, 2);
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to parse AI recommendations', ['response' => $response]);
        }

        return [];
    }

    /**
     * Track impact of applied recommendations
     */
    public function measureRecommendationImpact(CampaignRecommendation $recommendation): ?float
    {
        if (!$recommendation->is_applied || !$recommendation->applied_at) {
            return null;
        }

        $audit = $recommendation->audit;
        $user = $audit->user;

        // Get audits after the recommendation was applied
        $laterAudits = CampaignAudit::where('user_id', $user->id)
            ->where('status', CampaignAudit::STATUS_COMPLETED)
            ->where('created_at', '>', $recommendation->applied_at)
            ->orderBy('created_at')
            ->limit(3)
            ->get();

        if ($laterAudits->isEmpty()) {
            return null;
        }

        // Calculate average improvement in relevant metrics
        $baseScore = $audit->overall_score;
        $laterAvgScore = $laterAudits->avg('overall_score');

        $improvement = $laterAvgScore - $baseScore;

        // Update the recommendation with measured impact
        $recommendation->recordImpact($improvement);

        return $improvement;
    }

    /**
     * Get summary of recommendation effectiveness for a user
     */
    public function getRecommendationEffectiveness(User $user): array
    {
        $applied = CampaignRecommendation::whereHas('audit', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->where('is_applied', true)->get();

        if ($applied->isEmpty()) {
            return [
                'total_applied' => 0,
                'avg_expected_impact' => 0,
                'avg_actual_impact' => null,
                'success_rate' => null,
            ];
        }

        $withResults = $applied->whereNotNull('result_impact');

        return [
            'total_applied' => $applied->count(),
            'avg_expected_impact' => round($applied->avg('expected_impact'), 1),
            'avg_actual_impact' => $withResults->count() > 0
                ? round($withResults->avg('result_impact'), 1)
                : null,
            'success_rate' => $withResults->count() > 0
                ? round($withResults->where('result_impact', '>', 0)->count() / $withResults->count() * 100)
                : null,
        ];
    }
}
