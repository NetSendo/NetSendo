<?php

namespace App\Services\Brain;

use App\Models\AiActionPlan;
use App\Models\AiBrainSettings;
use App\Models\AiGoal;
use App\Models\User;
use App\Services\AI\AiService;
use App\Services\Brain\Agents\BaseAgent;
use Illuminate\Support\Facades\Log;

/**
 * GoalPlanner — Adds goal-aware planning layer on top of agent orchestration.
 *
 * Responsibilities:
 *  - Create persistent goals from user descriptions
 *  - Decompose complex goals into ordered sub-plans
 *  - Track progress across sessions
 *  - Handle plan failures with retry/adjustment logic
 *  - Suggest proactive goals
 */
class GoalPlanner
{
    public function __construct(
        protected AiService $aiService,
        protected KnowledgeBaseService $knowledgeBase,
    ) {}

    /**
     * Determine if a user message describes a high-level goal (vs. a simple action).
     * Uses AI to distinguish between "create a campaign" (action) and
     * "re-engage 500 inactive users over the next 2 weeks" (goal).
     */
    public function isGoalRequest(string $message, User $user): ?array
    {
        $integration = $this->aiService->getDefaultIntegration();
        if (!$integration) {
            return null;
        }

        $settings = AiBrainSettings::getForUser($user->id);
        $langCode = $settings->resolveLanguage($user);
        $langName = AiBrainSettings::getLanguageName($langCode);

        $prompt = <<<PROMPT
Analyze this user message and determine if it's a HIGH-LEVEL GOAL or a SIMPLE ACTION.

A GOAL is a multi-step objective that requires planning, multiple actions over time,
and has clear success criteria. Examples:
- "Re-engage inactive subscribers from the last 6 months"
- "Build an automated welcome series for new subscribers"
- "Increase open rates by 20% in the next month"

A SIMPLE ACTION is a single, immediate task. Examples:
- "Create a new subscriber list"
- "Send me subscriber statistics"
- "Write an email about a product launch"

USER MESSAGE: {$message}

Respond in VALID JSON ONLY:
{
  "is_goal": true/false,
  "title": "short goal title (max 8 words)",
  "description": "1-2 sentence description of the goal",
  "priority": "low|medium|high|urgent",
  "success_criteria": ["criterion 1", "criterion 2"],
  "estimated_plans": 2-8,
  "confidence": 0.0-1.0
}

IMPORTANT: Respond in {$langName}.
PROMPT;

        try {
            $response = $this->aiService->generateContent($prompt, $integration, [
                'max_tokens' => 500,
                'temperature' => 0.2,
            ]);

            $data = $this->parseJson($response);

            if ($data && ($data['is_goal'] ?? false) && ($data['confidence'] ?? 0) >= 0.6) {
                return $data;
            }
        } catch (\Exception $e) {
            Log::warning('GoalPlanner: goal detection failed', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Create a persistent goal from user description.
     */
    public function createGoal(
        User $user,
        string $title,
        ?string $description = null,
        string $priority = 'medium',
        ?array $successCriteria = null,
        ?int $conversationId = null,
    ): AiGoal {
        return AiGoal::create([
            'user_id' => $user->id,
            'ai_conversation_id' => $conversationId,
            'title' => $title,
            'description' => $description,
            'priority' => $priority,
            'success_criteria' => $successCriteria,
            'status' => 'active',
        ]);
    }

    /**
     * Decompose a goal into ordered sub-plans that agents can execute.
     * Returns structured plan descriptions for the orchestrator to feed to agents.
     */
    public function decomposeGoal(AiGoal $goal, User $user): array
    {
        $integration = $this->aiService->getDefaultIntegration();
        if (!$integration) {
            return [];
        }

        $settings = AiBrainSettings::getForUser($user->id);
        $langCode = $settings->resolveLanguage($user);
        $langName = AiBrainSettings::getLanguageName($langCode);

        $knowledgeContext = $this->knowledgeBase->getContext($user, 'general');

        // Include completed plan context
        $completedContext = '';
        $completedPlans = $goal->plans()->where('status', 'completed')->get();
        if ($completedPlans->isNotEmpty()) {
            $completedContext = "\n\nALREADY COMPLETED PLANS:\n";
            foreach ($completedPlans as $plan) {
                $summary = is_array($plan->execution_summary)
                    ? json_encode($plan->execution_summary)
                    : ($plan->execution_summary ?? 'completed');
                $completedContext .= "- {$plan->title}: {$summary}\n";
            }
        }

        $criteriaStr = '';
        if (!empty($goal->success_criteria)) {
            $criteriaStr = "\nSUCCESS CRITERIA:\n" . implode("\n", array_map(
                fn($c) => "- {$c}",
                $goal->success_criteria
            ));
        }

        $prompt = <<<PROMPT
You are an expert marketing strategist. Decompose this goal into sequential plans.

GOAL: {$goal->title}
DESCRIPTION: {$goal->description}
{$criteriaStr}
{$completedContext}

{$knowledgeContext}

Create 2-6 sequential plans. Each plan should be executable by one of these agents:
- campaign: email/SMS campaigns, drip series, automation
- list: subscriber lists, cleanup, import
- message: content creation, A/B testing
- crm: contacts, deals, pipelines, tasks
- analytics: reports, trends, comparisons
- segmentation: tags, segments, scoring
- research: web research, competitor analysis

Respond in VALID JSON ONLY:
{
  "plans": [
    {
      "order": 1,
      "agent": "agent_name",
      "intent": "what this plan should achieve",
      "title": "short plan title",
      "depends_on": null,
      "description": "detailed description of what to do"
    }
  ]
}

IMPORTANT: Only include plans NOT yet completed. Respond in {$langName}.
PROMPT;

        try {
            $response = $this->aiService->generateContent($prompt, $integration, [
                'max_tokens' => 2000,
                'temperature' => 0.3,
            ]);

            $data = $this->parseJson($response);

            if ($data && !empty($data['plans'])) {
                // Store decomposition in goal context
                $goal->addContext('decomposition', $data['plans']);
                return $data['plans'];
            }
        } catch (\Exception $e) {
            Log::error('GoalPlanner: decomposition failed', ['error' => $e->getMessage()]);
        }

        return [];
    }

    /**
     * Get the next plan to execute for a goal.
     * Considers dependencies and what's already completed.
     */
    public function getNextAction(AiGoal $goal): ?array
    {
        $decomposition = $goal->context['decomposition'] ?? [];

        if (empty($decomposition)) {
            return null;
        }

        $completedIntents = $goal->plans()
            ->where('status', 'completed')
            ->pluck('intent')
            ->toArray();

        // Find first plan that hasn't been completed and whose dependencies are met
        foreach ($decomposition as $plan) {
            $alreadyDone = false;
            foreach ($completedIntents as $ci) {
                if (str_contains(strtolower($ci), strtolower($plan['intent'] ?? ''))
                    || str_contains(strtolower($plan['intent'] ?? ''), strtolower($ci))) {
                    $alreadyDone = true;
                    break;
                }
            }

            if ($alreadyDone) {
                continue;
            }

            // Check dependency
            if (!empty($plan['depends_on'])) {
                $depCompleted = false;
                foreach ($completedIntents as $ci) {
                    if (str_contains(strtolower($ci), strtolower($plan['depends_on']))) {
                        $depCompleted = true;
                        break;
                    }
                }
                if (!$depCompleted) {
                    continue;
                }
            }

            return $plan;
        }

        return null; // All plans completed
    }

    /**
     * Handle a plan failure — decide whether to retry, adjust, or abort.
     */
    public function handlePlanFailure(AiActionPlan $plan, string $reason, User $user): array
    {
        $goal = $plan->goal;

        if (!$goal) {
            return ['action' => 'abort', 'message' => $reason];
        }

        // Count failures for this goal
        $failedCount = $goal->plans()->where('status', 'failed')->count();

        if ($failedCount >= 3) {
            // Too many failures — pause the goal and alert user
            $goal->pause();
            return [
                'action' => 'pause_goal',
                'message' => __('brain.goals.too_many_failures', ['title' => $goal->title]),
            ];
        }

        // Log the failure context
        $goal->addContext("failure_{$plan->id}", [
            'plan_title' => $plan->title,
            'reason' => $reason,
            'failed_at' => now()->toIso8601String(),
        ]);

        // Suggest retry with adjusted approach
        return [
            'action' => 'retry',
            'message' => __('brain.goals.plan_failed_retrying', [
                'plan' => $plan->title,
                'goal' => $goal->title,
            ]),
        ];
    }

    /**
     * Get active goals for a user (for context injection).
     */
    public function getActiveGoalsContext(User $user): string
    {
        try {
            $goals = AiGoal::forUser($user->id)->active()->get();
        } catch (\Exception $e) {
            // Table may not exist yet if migration hasn't been run
            return '';
        }

        if ($goals->isEmpty()) {
            return '';
        }

        $context = "\n\nUSER'S ACTIVE GOALS:\n";
        foreach ($goals as $goal) {
            $context .= "- [{$goal->priority}] {$goal->title} ({$goal->progress_percent}% complete)\n";
            if ($goal->description) {
                $context .= "  Description: {$goal->description}\n";
            }
        }

        return $context;
    }

    /**
     * Format a goal status message for display in chat.
     */
    public function formatGoalStatus(AiGoal $goal): string
    {
        $statusEmoji = match ($goal->status) {
            'active' => '🎯',
            'paused' => '⏸️',
            'completed' => '✅',
            'failed' => '❌',
            'cancelled' => '🚫',
            default => '📋',
        };

        $text = "{$statusEmoji} **{$goal->title}**\n";
        $text .= __('brain.goals.progress_bar', [
            'percent' => $goal->progress_percent,
            'completed' => $goal->completed_plans,
            'total' => $goal->total_plans,
        ]) . "\n";

        if ($goal->description) {
            $text .= "{$goal->description}\n";
        }

        // Show linked plans
        $plans = $goal->plans()->orderBy('created_at')->get();
        if ($plans->isNotEmpty()) {
            $text .= "\n" . __('brain.goals.linked_plans') . "\n";
            foreach ($plans as $plan) {
                $planEmoji = match ($plan->status) {
                    'completed' => '✅',
                    'executing' => '⏳',
                    'failed' => '❌',
                    default => '⬜',
                };
                $text .= "  {$planEmoji} {$plan->title}\n";
            }
        }

        return $text;
    }

    /**
     * Suggest proactive goals based on user's data (CRM, campaigns, lists).
     */
    public function suggestGoals(User $user): array
    {
        $suggestions = [];

        // Delegate to MarketingSalesSkill for domain-specific suggestions
        $skillTasks = Skills\MarketingSalesSkill::getSuggestedTasks($user);

        foreach ($skillTasks as $task) {
            $suggestions[] = [
                'title' => $task['title'] ?? $task['description'] ?? 'Suggested goal',
                'description' => $task['description'] ?? '',
                'priority' => $task['priority'] ?? 'medium',
                'category' => $task['category'] ?? 'general',
            ];
        }

        return array_slice($suggestions, 0, 5); // Limit to 5 suggestions
    }

    /**
     * Parse JSON from AI response (handles markdown code blocks).
     */
    protected function parseJson(string $response): ?array
    {
        // Strip markdown code blocks
        $response = preg_replace('/```(?:json)?\s*/', '', $response);
        $response = preg_replace('/```\s*$/', '', $response);
        $response = trim($response);

        $data = json_decode($response, true);
        return $data ?: null;
    }
}
