<?php

namespace App\Services\Brain;

use App\Models\AiExecutionLog;
use App\Models\AiGoal;
use App\Models\AiPerformanceSnapshot;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * TaskScorer — Intelligent task prioritization for CRON pipeline.
 *
 * Replaces simple high/medium/low filtering with a data-driven 0-100 score
 * based on impact potential, urgency, goal alignment, and freshness.
 */
class TaskScorer
{
    /**
     * Score all tasks and return them sorted by score (highest first).
     *
     * @param array $tasks Array of task arrays from MarketingSalesSkill + SituationAnalyzer
     * @param User $user
     * @param int $limit Maximum tasks to return (0 = no limit)
     * @return Collection Scored tasks with 'score' and 'score_breakdown' keys added
     */
    public function scoreAll(array $tasks, User $user, int $limit = 5): Collection
    {
        if (empty($tasks)) {
            return collect();
        }

        // Gather context once for all scoring
        $context = $this->gatherScoringContext($user);

        $scored = collect($tasks)->map(function ($task) use ($context) {
            $breakdown = $this->scoreSingleTask($task, $context);
            $task['score'] = array_sum($breakdown);
            $task['score_breakdown'] = $breakdown;
            return $task;
        });

        // Sort by score descending
        $scored = $scored->sortByDesc('score')->values();

        // Apply limit
        if ($limit > 0) {
            $scored = $scored->take($limit);
        }

        return $scored;
    }

    /**
     * Score a single task across 4 dimensions.
     *
     * @return array{impact: int, urgency: int, goal_alignment: int, freshness: int}
     */
    protected function scoreSingleTask(array $task, array $context): array
    {
        return [
            'impact' => $this->scoreImpact($task, $context),
            'urgency' => $this->scoreUrgency($task, $context),
            'goal_alignment' => $this->scoreGoalAlignment($task, $context),
            'freshness' => $this->scoreFreshness($task, $context),
        ];
    }

    /**
     * Impact score (0-25): How many subscribers/contacts does this task affect?
     */
    protected function scoreImpact(array $task, array $context): int
    {
        $category = $task['category'] ?? '';
        $priority = $task['priority'] ?? 'medium';

        // Base score from original priority
        $priorityBase = match ($priority) {
            'urgent' => 20,
            'high' => 15,
            'medium' => 10,
            'low' => 5,
            default => 8,
        };

        // Boost for categories that affect many people
        $categoryBoost = match ($category) {
            'send_broadcast', 'audience_growth' => 5,
            'campaign_follow_up', 'lead_nurturing' => 4,
            'win_back', 'list_hygiene' => 3,
            'a_b_testing', 'crm_pipeline' => 2,
            'ai_analysis' => 4, // AI-identified priorities are high impact
            default => 1,
        };

        // If task targets specific lists, boost based on subscriber count
        $listBoost = 0;
        if (!empty($task['parameters']['target_list_ids']) && $context['total_subscribers'] > 0) {
            $listBoost = min(5, (int) ($context['total_subscribers'] / 500));
        }

        return min(25, $priorityBase + $categoryBoost + $listBoost);
    }

    /**
     * Urgency score (0-25): How long since this type of action was last performed?
     */
    protected function scoreUrgency(array $task, array $context): int
    {
        $category = $task['category'] ?? '';
        $agent = $task['agent'] ?? 'campaign';

        // Check when this agent last executed a task
        $lastExecution = $context['last_executions'][$agent] ?? null;

        if (!$lastExecution) {
            // Never executed — very urgent
            return 20;
        }

        $daysSince = now()->diffInDays($lastExecution);

        // Scale: 0 days = 5, 3 days = 12, 7 days = 18, 14+ days = 25
        $timeScore = min(25, 5 + (int) ($daysSince * 1.5));

        // Category-specific urgency multipliers
        $categoryMultiplier = match ($category) {
            'list_hygiene' => 0.8,  // less urgent
            'analytics_report' => 0.7,  // informational
            'win_back', 'lead_nurturing' => 1.2,  // time-sensitive
            default => 1.0,
        };

        return min(25, (int) ($timeScore * $categoryMultiplier));
    }

    /**
     * Goal alignment score (0-25): Does this task support an active goal?
     */
    protected function scoreGoalAlignment(array $task, array $context): int
    {
        if (empty($context['active_goals'])) {
            // No goals set — give neutral score
            return 12;
        }

        $taskTitle = strtolower($task['title'] ?? '');
        $taskDesc = strtolower($task['description'] ?? '');
        $taskAction = strtolower($task['action'] ?? '');
        $taskText = "{$taskTitle} {$taskDesc} {$taskAction}";

        $maxAlignment = 0;

        foreach ($context['active_goals'] as $goal) {
            $goalText = strtolower(($goal['title'] ?? '') . ' ' . ($goal['description'] ?? ''));
            $alignment = $this->calculateTextSimilarity($taskText, $goalText);
            $maxAlignment = max($maxAlignment, $alignment);
        }

        // Scale 0-1 similarity to 0-25
        return min(25, (int) ($maxAlignment * 25));
    }

    /**
     * Freshness score (0-25): Penalize recently executed similar tasks.
     */
    protected function scoreFreshness(array $task, array $context): int
    {
        $taskTitle = strtolower($task['title'] ?? '');
        $category = $task['category'] ?? '';

        // Check recent execution logs for similar tasks
        $recentSimilar = 0;
        foreach ($context['recent_tasks'] as $recent) {
            $recentTitle = strtolower($recent['title'] ?? '');
            $recentCategory = $recent['category'] ?? '';

            // Same category = potential overlap
            if ($recentCategory === $category) {
                $recentSimilar++;
            }

            // Similar title = definite overlap
            if ($this->calculateTextSimilarity($taskTitle, $recentTitle) > 0.6) {
                $recentSimilar += 2;
            }
        }

        // No recent similar tasks = full score; many = penalized
        // 0 similar = 25, 1 = 20, 2 = 15, 3 = 10, 4+ = 5
        return max(5, 25 - ($recentSimilar * 5));
    }

    /**
     * Gather scoring context (called once per scoring batch).
     */
    protected function gatherScoringContext(User $user): array
    {
        // Last execution per agent
        $lastExecutions = [];
        $agentNames = ['campaign', 'message', 'list', 'crm', 'analytics', 'segmentation', 'research'];
        foreach ($agentNames as $agent) {
            $lastLog = AiExecutionLog::where('user_id', $user->id)
                ->where('agent_type', $agent)
                ->where('status', 'success')
                ->orderByDesc('created_at')
                ->value('created_at');
            if ($lastLog) {
                $lastExecutions[$agent] = $lastLog;
            }
        }

        // Active goals
        $activeGoals = [];
        try {
            $activeGoals = AiGoal::forUser($user->id)
                ->active()
                ->get(['title', 'description', 'priority'])
                ->toArray();
        } catch (\Exception $e) {
            // ai_goals table may not exist
        }

        // Recent task executions (last 7 days)
        $recentTasks = AiExecutionLog::where('user_id', $user->id)
            ->where('status', 'success')
            ->where('action', 'cron_task')
            ->where('created_at', '>=', now()->subDays(7))
            ->get(['agent_type', 'created_at'])
            ->map(fn($log) => [
                'title' => $log->context['task_title'] ?? '',
                'category' => $log->context['category'] ?? '',
                'agent' => $log->agent_type,
                'date' => $log->created_at,
            ])
            ->toArray();

        // Total subscribers for impact calculation
        $totalSubscribers = 0;
        try {
            $totalSubscribers = \App\Models\Subscriber::where('user_id', $user->id)->count();
        } catch (\Exception $e) {
            // Ignore
        }

        return [
            'last_executions' => $lastExecutions,
            'active_goals' => $activeGoals,
            'recent_tasks' => $recentTasks,
            'total_subscribers' => $totalSubscribers,
        ];
    }

    /**
     * Simple keyword-overlap text similarity (0.0 - 1.0).
     */
    protected function calculateTextSimilarity(string $text1, string $text2): float
    {
        $words1 = array_unique(preg_split('/\W+/', $text1, -1, PREG_SPLIT_NO_EMPTY));
        $words2 = array_unique(preg_split('/\W+/', $text2, -1, PREG_SPLIT_NO_EMPTY));

        if (empty($words1) || empty($words2)) {
            return 0.0;
        }

        // Remove very common words
        $stopwords = ['the', 'a', 'an', 'and', 'or', 'for', 'to', 'in', 'on', 'of', 'with', 'is', 'it', 'this', 'that', 'do', 'i', 'na', 'w', 'z', 'i', 'o', 'dla'];
        $words1 = array_diff($words1, $stopwords);
        $words2 = array_diff($words2, $stopwords);

        if (empty($words1) || empty($words2)) {
            return 0.0;
        }

        $intersection = array_intersect($words1, $words2);
        $union = array_unique(array_merge($words1, $words2));

        return count($intersection) / count($union);
    }
}
