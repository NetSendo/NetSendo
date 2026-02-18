<?php

namespace App\Services\Brain\Agents;

use App\Models\AiActionPlan;
use App\Models\AiActionPlanStep;
use App\Models\AiBrainSettings;
use App\Models\AiExecutionLog;
use App\Models\User;
use App\Services\AI\AiService;
use App\Services\Brain\KnowledgeBaseService;
use Illuminate\Support\Facades\Log;

abstract class BaseAgent
{
    public function __construct(
        protected AiService $aiService,
        protected KnowledgeBaseService $knowledgeBase,
    ) {}

    /**
     * Get the agent name identifier.
     */
    abstract public function getName(): string;

    /**
     * Get list of capabilities this agent handles.
     */
    abstract public function getCapabilities(): array;

    /**
     * Get display label.
     */
    abstract public function getLabel(): string;

    /**
     * Create an action plan for the given intent.
     */
    abstract public function plan(array $intent, User $user, string $knowledgeContext = ''): ?AiActionPlan;

    /**
     * Execute an action plan.
     */
    abstract public function execute(AiActionPlan $plan, User $user): array;

    /**
     * Provide advice without executing (for manual mode).
     */
    abstract public function advise(array $intent, User $user, string $knowledgeContext = ''): array;

    /**
     * Check if this agent needs more information before creating a plan.
     * Override in subclasses for agent-specific logic.
     */
    public function needsMoreInfo(array $intent, User $user, string $knowledgeContext = ''): bool
    {
        // Default: agents that modify things should ask for info when no parameters are provided
        $params = $intent['parameters'] ?? [];
        return empty($params);
    }

    /**
     * Generate questions to ask the user for missing information.
     * Override in subclasses for agent-specific questions.
     */
    public function getInfoQuestions(array $intent, User $user, string $knowledgeContext = ''): string
    {
        return "Potrzebuję więcej szczegółów, aby wykonać to zadanie. Podaj dodatkowe informacje.";
    }

    /**
     * Call AI provider with prompt.
     * Supports per-task model routing via AiBrainSettings.
     */
    protected function callAi(string $prompt, array $options = [], ?User $user = null, ?string $task = null): string
    {
        $integration = $this->aiService->getDefaultIntegration();

        if (!$integration) {
            throw new \RuntimeException('No AI integration configured');
        }

        // Per-task model routing: use task-specific model if configured
        if ($user && $task) {
            $settings = AiBrainSettings::getForUser($user->id);
            $taskConfig = $settings->getModelForTask($task);
            if ($taskConfig) {
                if ($taskConfig['integration_id']) {
                    $taskIntegration = \App\Models\AiIntegration::find($taskConfig['integration_id']);
                    if ($taskIntegration?->is_active) {
                        $integration = $taskIntegration;
                    }
                }
                if ($taskConfig['model']) {
                    $options['model'] = $taskConfig['model'];
                }
            }
        }

        return $this->aiService->generateContent(
            AiService::prependDateContext($prompt),
            $integration,
            $options
        );
    }

    /**
     * Create an action plan with steps.
     */
    protected function createPlan(
        User $user,
        string $intent,
        string $title,
        ?string $description,
        array $steps,
        ?int $conversationId = null,
    ): AiActionPlan {
        $settings = AiBrainSettings::getForUser($user->id);

        $plan = AiActionPlan::create([
            'user_id' => $user->id,
            'ai_conversation_id' => $conversationId,
            'agent_type' => $this->getName(),
            'intent' => $intent,
            'title' => $title,
            'description' => $description,
            'plan_data' => ['steps' => $steps],
            'work_mode' => $settings->work_mode,
            'status' => 'draft',
            'total_steps' => count($steps),
        ]);

        foreach ($steps as $i => $step) {
            AiActionPlanStep::create([
                'ai_action_plan_id' => $plan->id,
                'step_order' => $i + 1,
                'action_type' => $step['action_type'],
                'title' => $step['title'],
                'description' => $step['description'] ?? null,
                'config' => $step['config'] ?? [],
            ]);
        }

        return $plan;
    }

    /**
     * Execute a single step.
     */
    protected function executeStep(AiActionPlanStep $step, User $user): array
    {
        $step->markExecuting();
        $startTime = microtime(true);

        try {
            $result = $this->executeStepAction($step, $user);

            $step->markCompleted($result);

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            AiExecutionLog::logSuccess(
                $user->id,
                $this->getName(),
                "execute_step:{$step->action_type}",
                $step->config,
                $result,
                0, 0, null, $durationMs,
                $step->ai_action_plan_id,
                $step->id
            );

            return $result;

        } catch (\Exception $e) {
            $step->markFailed($e->getMessage());

            AiExecutionLog::logError(
                $user->id,
                $this->getName(),
                "execute_step:{$step->action_type}",
                $e->getMessage(),
                $step->config,
                $step->ai_action_plan_id,
                $step->id
            );

            throw $e;
        }
    }

    /**
     * Execute a specific step action. Override in subclasses.
     */
    protected function executeStepAction(AiActionPlanStep $step, User $user): array
    {
        return ['status' => 'completed', 'message' => 'Step executed'];
    }

    /**
     * Parse JSON from AI response.
     */
    protected function parseJson(string $response): ?array
    {
        $response = trim($response);

        if (preg_match('/```(?:json)?\s*\n?(.*?)\n?```/s', $response, $matches)) {
            $response = $matches[1];
        }

        $data = json_decode($response, true);
        return is_array($data) ? $data : null;
    }
}
