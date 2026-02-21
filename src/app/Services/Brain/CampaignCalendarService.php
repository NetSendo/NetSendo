<?php

namespace App\Services\Brain;

use App\Models\AiCampaignCalendar;
use App\Models\AiGoal;
use App\Models\AiPerformanceSnapshot;
use App\Models\User;
use App\Services\AI\AiService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * CampaignCalendarService — Proactive campaign planning.
 *
 * Generates a weekly campaign calendar based on goals, audience,
 * and past performance patterns. Called from CRON to keep the calendar fresh.
 */
class CampaignCalendarService
{
    public function __construct(
        protected AiService $aiService,
    ) {}

    /**
     * Generate a weekly campaign plan for the upcoming week.
     * Only generates if no plan exists for the current/next week yet.
     *
     * @return array{generated: bool, entries: int}
     */
    public function generateWeeklyPlan(User $user): array
    {
        $weekStart = now()->startOfWeek(Carbon::MONDAY)->addWeek();

        // Check if we already have a plan for this week
        $existingCount = AiCampaignCalendar::forUser($user->id)
            ->forWeek($weekStart)
            ->count();

        if ($existingCount > 0) {
            return ['generated' => false, 'entries' => 0];
        }

        $integration = $this->aiService->getDefaultIntegration();
        if (!$integration) {
            return ['generated' => false, 'entries' => 0];
        }

        // Gather context for planning
        $context = $this->gatherPlanningContext($user);

        $langInstruction = $this->getLanguageInstruction($user);

        $prompt = <<<PROMPT
You are a marketing strategist planning the upcoming week's email campaigns.

CURRENT CONTEXT:
- Total subscribers: {$context['total_subscribers']}
- Active goals: {$context['goals']}
- Recent performance: {$context['performance']}
- Today: {$context['today']}
- Planning for week: {$weekStart->format('Y-m-d')} to {$weekStart->copy()->addDays(6)->format('Y-m-d')}

{$langInstruction}

Plan 2-4 campaigns for the upcoming week. For each campaign include:
- planned_day: 1-7 (Monday=1, Sunday=7)
- campaign_type: newsletter, promotion, nurturing, win_back, or announcement
- topic: short topic description
- description: 1-2 sentence description of what this campaign should cover
- target_audience: who should receive it

Respond in JSON array format:
[
  {
    "planned_day": 2,
    "campaign_type": "newsletter",
    "topic": "Weekly industry insights",
    "description": "Share latest trends and company updates",
    "target_audience": "All active subscribers"
  }
]

Be specific and strategic. Align campaigns with active goals when possible.
PROMPT;

        try {
            $response = $this->aiService->generateContent(
                AiService::prependDateContext($prompt, $user->timezone),
                $integration,
                ['max_tokens' => 2000, 'temperature' => 0.5]
            );

            $plans = $this->parseJson($response);

            if (empty($plans)) {
                return ['generated' => false, 'entries' => 0];
            }

            // Get active goal for linking
            $activeGoalId = null;
            try {
                $activeGoalId = AiGoal::forUser($user->id)->active()->latest()->value('id');
            } catch (\Exception $e) {
                // ai_goals table may not exist
            }

            $created = 0;
            foreach ($plans as $plan) {
                $day = (int) ($plan['planned_day'] ?? 2);
                $plannedDate = $weekStart->copy()->addDays(max(0, min(6, $day - 1)));

                AiCampaignCalendar::create([
                    'user_id' => $user->id,
                    'week_start' => $weekStart,
                    'planned_date' => $plannedDate,
                    'campaign_type' => $plan['campaign_type'] ?? 'newsletter',
                    'target_audience' => $plan['target_audience'] ?? null,
                    'topic' => mb_substr($plan['topic'] ?? 'Campaign', 0, 255),
                    'description' => $plan['description'] ?? null,
                    'status' => 'draft',
                    'ai_goal_id' => $activeGoalId,
                ]);
                $created++;
            }

            return ['generated' => true, 'entries' => $created];
        } catch (\Exception $e) {
            Log::warning('CampaignCalendar: plan generation failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return ['generated' => false, 'entries' => 0];
        }
    }

    /**
     * Get upcoming planned campaigns for context injection into SituationAnalyzer.
     */
    public function getUpcomingContext(User $user): string
    {
        try {
            $upcoming = AiCampaignCalendar::forUser($user->id)
                ->upcoming()
                ->orderBy('planned_date')
                ->limit(7)
                ->get();

            if ($upcoming->isEmpty()) {
                return '';
            }

            $lines = ["--- CAMPAIGN CALENDAR ---"];
            foreach ($upcoming as $entry) {
                $dayName = $entry->planned_date->format('l');
                $date = $entry->planned_date->format('m/d');
                $status = strtoupper($entry->status);
                $lines[] = "[{$status}] {$dayName} {$date}: {$entry->topic} ({$entry->campaign_type}) → {$entry->target_audience}";
            }

            return implode("\n", $lines) . "\n";
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Get upcoming campaigns as structured array (for API/Telegram).
     */
    public function getUpcomingCampaigns(User $user, int $limit = 7): Collection
    {
        try {
            return AiCampaignCalendar::forUser($user->id)
                ->upcoming()
                ->orderBy('planned_date')
                ->limit($limit)
                ->get();
        } catch (\Exception $e) {
            return collect();
        }
    }

    /**
     * Gather context data for AI planning.
     */
    protected function gatherPlanningContext(User $user): array
    {
        // Total subscribers
        $totalSubscribers = 0;
        try {
            $totalSubscribers = \App\Models\Subscriber::where('user_id', $user->id)->count();
        } catch (\Exception $e) {
            // Ignore
        }

        // Active goals
        $goals = 'None set';
        try {
            $activeGoals = AiGoal::forUser($user->id)->active()->get(['title', 'priority']);
            if ($activeGoals->isNotEmpty()) {
                $goals = $activeGoals->map(fn($g) => "{$g->title} ({$g->priority})")->join(', ');
            }
        } catch (\Exception $e) {
            // ai_goals table may not exist
        }

        // Recent performance
        $performance = 'No data';
        $snapshots = AiPerformanceSnapshot::forUser($user->id)
            ->orderByDesc('captured_at')
            ->limit(3)
            ->get();
        if ($snapshots->isNotEmpty()) {
            $performance = $snapshots->map(fn($s) =>
                "{$s->campaign_title}: OR {$s->open_rate}%, CTR {$s->click_rate}%"
            )->join('; ');
        }

        return [
            'total_subscribers' => $totalSubscribers,
            'goals' => $goals,
            'performance' => $performance,
            'today' => now()->format('Y-m-d (l)'),
        ];
    }

    /**
     * Get language instruction for AI prompts.
     */
    protected function getLanguageInstruction(User $user): string
    {
        $settings = \App\Models\AiBrainSettings::getForUser($user->id);
        $langCode = $settings->resolveLanguage($user);
        $langName = \App\Models\AiBrainSettings::getLanguageName($langCode);
        return "IMPORTANT: Respond in {$langName}.";
    }

    /**
     * Parse JSON from AI response.
     */
    protected function parseJson(string $response): ?array
    {
        $response = preg_replace('/```(?:json)?\s*/', '', $response);
        $response = preg_replace('/```\s*$/', '', $response);
        $response = trim($response);

        $data = json_decode($response, true);
        return is_array($data) ? $data : null;
    }
}
