<?php

namespace App\Services\Brain;

use App\Models\AiActionPlan;
use App\Models\AiBrainActivityLog;
use App\Models\AiBrainSettings;
use App\Models\AiConversation;
use App\Models\AiExecutionLog;
use App\Models\AiGoal;
use App\Models\AiIntegration;
use App\Models\User;
use App\Services\AI\AiService;
use App\Services\Brain\Agents\BaseAgent;
use App\Services\Brain\Agents\AnalyticsAgent;
use App\Services\Brain\Agents\CampaignAgent;
use App\Services\Brain\Agents\CrmAgent;
use App\Services\Brain\Agents\ListAgent;
use App\Services\Brain\Agents\MessageAgent;
use App\Services\Brain\Agents\ResearchAgent;
use App\Services\Brain\Agents\SegmentationAgent;
use App\Services\Brain\Skills\MarketingSalesSkill;
use App\Services\Brain\Skills\ResearchSkill;
use Illuminate\Support\Facades\Log;
use App\Services\Brain\SituationAnalyzer;

class AgentOrchestrator
{
    protected array $agents = [];

    public function __construct(
        protected AiService $aiService,
        protected ConversationManager $conversationManager,
        protected ModeController $modeController,
        protected KnowledgeBaseService $knowledgeBase,
        protected GoalPlanner $goalPlanner,
        protected SituationAnalyzer $situationAnalyzer,
    ) {
        $this->registerAgents();
    }

    /**
     * Register all available specialist agents.
     */
    protected function registerAgents(): void
    {
        $this->agents = [
            'campaign' => app(CampaignAgent::class),
            'list' => app(ListAgent::class),
            'message' => app(MessageAgent::class),
            'crm' => app(CrmAgent::class),
            'analytics' => app(AnalyticsAgent::class),
            'segmentation' => app(SegmentationAgent::class),
            'research' => app(ResearchAgent::class),
        ];
    }

    /**
     * Get all registered agents.
     */
    public function getAgents(): array
    {
        return $this->agents;
    }

    /**
     * Process an incoming message from any channel.
     * This is the main entry point for the Brain.
     */
    public function processMessage(
        string $message,
        User $user,
        string $channel = 'web',
        ?int $conversationId = null,
        bool $forceNew = false,
    ): array {
        $startTime = microtime(true);
        $settings = AiBrainSettings::getForUser($user->id);

        // Log brain activity start event for the activity bar
        AiBrainActivityLog::logEvent($user->id, 'brain_start', 'started', null, [
            'channel' => $channel,
        ]);

        $integration = $this->aiService->getDefaultIntegration();

        if (!$integration) {
            return [
                'type' => 'error',
                'message' => __('brain.no_ai_integration'),
            ];
        }

        // Check token limits
        if ($settings->isTokenLimitReached()) {
            return [
                'type' => 'error',
                'message' => __('brain.token_limit_reached'),
            ];
        }

        // Use user-preferred integration if set
        if ($settings->preferred_integration_id) {
            $preferredIntegration = AiIntegration::find($settings->preferred_integration_id);
            if ($preferredIntegration && $preferredIntegration->is_active) {
                $integration = $preferredIntegration;
            }
        }

        // Resolve conversation: specific ID, force new, or auto-find
        if ($conversationId) {
            $conversation = $this->conversationManager->getConversationById($conversationId, $user->id);
            if (!$conversation) {
                $conversation = $this->conversationManager->createNewConversation($user, $channel);
            }
        } elseif ($forceNew) {
            $conversation = $this->conversationManager->createNewConversation($user, $channel);
        } else {
            $conversation = $this->conversationManager->getConversation($user, $channel);
        }

        // Save user message
        $this->conversationManager->addUserMessage($conversation, $message);

        try {
            // Step 0: Check if there's a pending agent awaiting user details
            $context = $conversation->context ?? [];
            $pendingAgent = $context['pending_agent'] ?? null;

            if ($pendingAgent && isset($this->agents[$pendingAgent])) {
                // User is replying to an info request — route directly to the agent
                $pendingIntent = $context['pending_intent'] ?? [];
                $pendingIntent['parameters'] = array_merge(
                    $pendingIntent['parameters'] ?? [],
                    ['user_details' => $message]
                );

                // Clear pending state
                $conversation->update(['context' => array_diff_key($context, ['pending_agent' => '', 'pending_intent' => ''])]);

                $knowledgeContext = $this->knowledgeBase->getContext($user, $pendingIntent['task_type'] ?? 'general');
                $intent = $pendingIntent;
                $intent['requires_agent'] = true;
                $intent['has_user_details'] = true;
                $result = $this->handleAgentRequest($intent, $user, $conversation, $channel, $knowledgeContext);
            } else {
                // Step 1: Check if this is a high-level goal
                // Wrapped in try-catch: ai_goals table may not exist if migration hasn't been run
                $goalData = null;
                try {
                    $goalData = $this->goalPlanner->isGoalRequest($message, $user);
                } catch (\Exception $e) {
                    Log::debug('Goal detection skipped (table may not exist)', ['error' => $e->getMessage()]);
                }

                if ($goalData) {
                    try {
                        // Create persistent goal and decompose
                        $result = $this->handleGoalRequest($goalData, $user, $conversation);
                    } catch (\Exception $e) {
                        Log::warning('Goal handling failed, falling back to intent classification', ['error' => $e->getMessage()]);
                        $goalData = null; // Fall through to normal flow
                    }
                }

                if (!$goalData) {
                    // Step 2: Classify intent
                    $intent = $this->classifyIntent($message, $conversation, $user);

                    // Step 2.5: Handle situation_analysis intent via SituationAnalyzer
                    if (($intent['task_type'] ?? '') === 'situation_analysis') {
                        $result = $this->handleSituationAnalysis($user, $conversation, $settings);
                    } else {
                        // Step 3: Get knowledge context for this intent
                        $knowledgeContext = $this->knowledgeBase->getContext($user, $intent['task_type'] ?? 'general');

                        // Inject active goal context
                        $goalsContext = $this->goalPlanner->getActiveGoalsContext($user);
                        if ($goalsContext) {
                            $knowledgeContext .= $goalsContext;
                        }

                        // Step 4: Route to appropriate agent or handle as conversation
                        if ($intent['requires_agent']) {
                            $result = $this->handleAgentRequest($intent, $user, $conversation, $channel, $knowledgeContext);
                        } else {
                            $result = $this->handleConversation($message, $user, $conversation, $knowledgeContext, $integration, $settings->preferred_model);
                        }
                    }
                }
            }

            // Step 4: Ensure model label is present for display
            $modelUsed = $result['model'] ?? null;
            if (!$modelUsed && ($result['type'] ?? '') !== 'info_request') {
                // Agent responses use the preferred model or integration default
                $modelUsed = $settings->preferred_model ?: ($integration->default_model ?? null);
            }
            if (!$modelUsed) {
                $modelUsed = ($result['type'] ?? '') === 'info_request' ? 'Brain' : 'unknown';
            }

            $this->conversationManager->addAssistantMessage(
                $conversation,
                $result['message'],
                [
                    'intent' => $intent['intent'] ?? 'conversation',
                    'agent' => $intent['agent'] ?? null,
                    'work_mode' => $settings->work_mode,
                ],
                $result['tokens_input'] ?? 0,
                $result['tokens_output'] ?? 0,
                $modelUsed,
            );

            // Step 5: Track token usage
            $totalTokens = ($result['tokens_input'] ?? 0) + ($result['tokens_output'] ?? 0);
            $settings->addTokensUsed($totalTokens);

            // Step 6: Auto-generate title for new conversations
            if (!$conversation->title && $conversation->message_count <= 3) {
                $this->generateConversationTitle($conversation, $message, $result['message'], $integration);
            }

            // Step 7: Auto-enrich knowledge base (async-friendly, but done inline for now)
            if ($conversation->message_count % 5 === 0) {
                $this->tryAutoEnrich($user, $conversation);
            }

            // Log execution
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            AiExecutionLog::logSuccess(
                $user->id,
                $intent['agent'] ?? 'orchestrator',
                'process_message',
                ['message' => mb_substr($message, 0, 200)],
                ['response_length' => strlen($result['message'])],
                $result['tokens_input'] ?? 0,
                $result['tokens_output'] ?? 0,
                $modelUsed,
                $durationMs
            );

            // Log brain activity stop event
            AiBrainActivityLog::logEvent($user->id, 'brain_stop', 'completed', $intent['agent'] ?? null, [
                'duration_ms' => $durationMs,
            ], $durationMs);

            // Add conversation metadata to result
            $result['conversation_id'] = $conversation->id;
            $result['model'] = $modelUsed;
            $result['title'] = $conversation->fresh()->title;

            return $result;

        } catch (\Exception $e) {
            Log::error('AgentOrchestrator error', [
                'user_id' => $user->id,
                'message' => $message,
                'error' => $e->getMessage(),
            ]);

            AiExecutionLog::logError(
                $user->id,
                'orchestrator',
                'process_message',
                $e->getMessage(),
                ['message' => mb_substr($message, 0, 200)]
            );

            $errorMsg = __('brain.processing_error');
            $this->conversationManager->addAssistantMessage($conversation, $errorMsg);

            return [
                'type' => 'error',
                'message' => $errorMsg,
                'conversation_id' => $conversation->id,
            ];
        }
    }

    /**
     * Classify the user's intent using AI.
     */
    public function classifyIntent(string $message, AiConversation $conversation, User $user): array
    {
        $integration = $this->aiService->getDefaultIntegration();

        if (!$integration) {
            return [
                'requires_agent' => false,
                'intent' => 'conversation',
                'task_type' => 'general',
            ];
        }

        // Build intent classification prompt
        $recentContext = $conversation->getRecentMessages(5)
            ->map(fn($m) => "{$m->role}: {$m->content}")
            ->join("\n");

        $availableAgents = collect($this->agents)->map(fn(BaseAgent $agent) => [
            'name' => $agent->getName(),
            'capabilities' => $agent->getCapabilities(),
        ])->toArray();

        $agentDescriptions = collect($availableAgents)->map(function ($agent) {
            $caps = implode(', ', $agent['capabilities']);
            return "- {$agent['name']}: {$caps}";
        })->join("\n");

        // Get marketing/sales skill context for richer intent classification
        $settings = AiBrainSettings::getForUser($user->id);
        $langCode = $settings->resolveLanguage($user);
        $skillContext = MarketingSalesSkill::getSystemPrompt($langCode);

        // Add research skill context if research APIs are configured
        $researchContext = '';
        if ($settings->isResearchEnabled()) {
            $researchContext = "\n\n" . ResearchSkill::getSystemPrompt($langCode);
        }

        $prompt = <<<PROMPT
{$skillContext}
{$researchContext}

---

Classify the user's intent. Respond with VALID JSON ONLY.

AVAILABLE AGENTS:
{$agentDescriptions}

RECENT CONVERSATION CONTEXT:
{$recentContext}

NEW USER MESSAGE:
{$message}

Respond in JSON:
{
  "requires_agent": true/false,
  "agent": "campaign|list|message|crm|analytics|segmentation|research|null",
  "intent": "short description of intent",
  "task_type": "campaign|message|list|crm|analytics|segmentation|research|situation_analysis|general",
  "confidence": 0.0-1.0,
  "parameters": {}
}

Set requires_agent=false for general questions, conversations, greetings.
Set requires_agent=true when the user wants to PERFORM a specific action (e.g. create a campaign, research a topic, analyze competitors, etc.)
Set task_type="situation_analysis" (and requires_agent=false) when user asks for a holistic situation review, current state analysis, or overall assessment of their marketing/CRM situation (e.g. "przeanalizuj obecną sytuację", "analyze current situation", "co jest nie tak", "what should I do", "podsumuj stan", "give me an overview").
PROMPT;

        try {
            $response = $this->aiService->generateContent($prompt, $integration, [
                'max_tokens' => 500,
                'temperature' => 0.1,
            ]);

            $parsed = $this->parseJson($response);

            if ($parsed) {
                return array_merge([
                    'requires_agent' => false,
                    'agent' => null,
                    'intent' => 'conversation',
                    'task_type' => 'general',
                    'confidence' => 0.5,
                    'parameters' => [],
                ], $parsed);
            }
        } catch (\Exception $e) {
            Log::warning('Intent classification failed', ['error' => $e->getMessage()]);
        }

        // Fallback: simple keyword matching
        return $this->fallbackIntentClassification($message);
    }

    /**
     * Handle a request that requires a specialist agent.
     */
    protected function handleAgentRequest(
        array $intent,
        User $user,
        AiConversation $conversation,
        string $channel,
        string $knowledgeContext,
    ): array {
        $agentName = $intent['agent'] ?? null;
        $agent = $this->agents[$agentName] ?? null;

        if (!$agent) {
            return $this->handleConversation(
                __('brain.user_wants', ['intent' => $intent['intent']]),
                $user,
                $conversation,
                $knowledgeContext
            );
        }

        $settings = AiBrainSettings::getForUser($user->id);

        // In manual mode, just provide advice without creating an action plan
        if ($settings->work_mode === ModeController::MODE_MANUAL) {
            return $agent->advise($intent, $user, $knowledgeContext);
        }

        // Info-gathering phase: ask for details before creating a plan
        // Skip if user already provided details via a prior info request
        if (empty($intent['has_user_details']) && $agent->needsMoreInfo($intent, $user, $knowledgeContext)) {
            // Save pending state in conversation context
            $context = $conversation->context ?? [];
            $context['pending_agent'] = $agentName;
            $context['pending_intent'] = $intent;
            $conversation->update(['context' => $context]);

            $questions = $agent->getInfoQuestions($intent, $user, $knowledgeContext);
            return [
                'type' => 'info_request',
                'message' => $questions,
            ];
        }

        // Create an action plan (with retry on failure)
        $plan = $agent->plan($intent, $user, $knowledgeContext);

        if (!$plan) {
            // Retry once with a simplified prompt approach
            Log::info('First plan attempt failed, retrying with simplified prompt', [
                'agent' => $agentName,
                'intent' => $intent['intent'] ?? 'unknown',
            ]);

            // Add more context from the intent description
            $enrichedIntent = $intent;
            $enrichedIntent['parameters']['retry'] = true;
            $enrichedIntent['parameters']['original_message'] = $intent['intent'] ?? '';

            $plan = $agent->plan($enrichedIntent, $user, $knowledgeContext);
        }

        if (!$plan) {
            // Provide a more helpful error message instead of generic failure
            $intentDesc = $intent['intent'] ?? __('brain.user_wants', ['intent' => 'unknown']);
            return [
                'type' => 'message',
                'message' => __('brain.plan_failed_detail', [
                    'agent' => $agent->getLabel(),
                    'intent' => mb_substr($intentDesc, 0, 100),
                ]),
            ];
        }

        // Link plan to active goal if one exists
        try {
            $activeGoal = AiGoal::forUser($user->id)->active()->latest()->first();
            if ($activeGoal && !$plan->ai_goal_id) {
                $plan->update(['ai_goal_id' => $activeGoal->id]);
                $activeGoal->updateProgress();
            }
        } catch (\Exception $e) {
            // ai_goals table may not exist yet if migration hasn't been run
        }

        // Check if approval is needed
        if ($this->modeController->requiresApproval($plan->agent_type, $user)) {
            $approval = $this->modeController->requestApproval($plan, $user, $channel);

            return [
                'type' => 'approval_request',
                'message' => $this->formatPlanForApproval($plan),
                'plan_id' => $plan->id,
                'approval_id' => $approval->id,
            ];
        }

        // Autonomous mode: execute immediately
        return $this->executePlan($plan, $user);
    }

    /**
     * Execute an approved action plan.
     */
    public function executePlan(AiActionPlan $plan, User $user): array
    {
        $agentName = $plan->agent_type;
        $agent = $this->agents[$agentName] ?? null;

        if (!$agent) {
            $plan->markFailed(['error' => "Agent '{$agentName}' not found"]);
            return [
                'type' => 'error',
                'message' => __('brain.agent_not_found', ['agent' => $agentName]),
            ];
        }

        $plan->markStarted();

        // Log agent dispatch event
        AiBrainActivityLog::logEvent($user->id, 'agent_dispatch', 'started', $agentName, [
            'plan_id' => $plan->id,
        ]);

        try {
            $result = $agent->execute($plan, $user);

            $plan->markCompleted([
                'result' => $result['message'] ?? 'Completed',
                'completed_steps' => $plan->completed_steps,
                'failed_steps' => $plan->failed_steps,
            ]);

            // Log agent completion event
            AiBrainActivityLog::logEvent($user->id, 'agent_complete', 'completed', $agentName, [
                'plan_id' => $plan->id,
            ]);

            // Extract style preferences from completed campaign/message plans
            try {
                $this->knowledgeBase->extractStylePreferences($user, $plan);
            } catch (\Exception $e) {
                Log::debug('Style extraction skipped', ['error' => $e->getMessage()]);
            }

            return [
                'type' => 'execution_result',
                'message' => $result['message'] ?? __('brain.plan_executed'),
                'plan_id' => $plan->id,
                'tokens_input' => $result['tokens_input'] ?? 0,
                'tokens_output' => $result['tokens_output'] ?? 0,
            ];

        } catch (\Exception $e) {
            $plan->markFailed(['error' => $e->getMessage()]);

            return [
                'type' => 'error',
                'message' => __('brain.plan_execution_error', ['error' => $e->getMessage()]),
                'plan_id' => $plan->id,
            ];
        }
    }

    /**
     * Handle a high-level goal request.
     * Creates persistent goal, decomposes it, and starts first plan.
     */
    protected function handleGoalRequest(
        array $goalData,
        User $user,
        AiConversation $conversation,
    ): array {
        $settings = AiBrainSettings::getForUser($user->id);

        // Create persistent goal
        $goal = $this->goalPlanner->createGoal(
            $user,
            $goalData['title'] ?? 'New Goal',
            $goalData['description'] ?? null,
            $goalData['priority'] ?? 'medium',
            $goalData['success_criteria'] ?? null,
            $conversation->id,
        );

        // Log goal creation
        AiBrainActivityLog::logEvent($user->id, 'goal_created', 'started', null, [
            'goal_id' => $goal->id,
            'title' => $goal->title,
        ]);

        // Decompose into sub-plans
        $subPlans = $this->goalPlanner->decomposeGoal($goal, $user);

        if (empty($subPlans)) {
            // Fallback: create a single plan via the appropriate agent
            $intent = [
                'requires_agent' => true,
                'agent' => $goalData['agent'] ?? 'campaign',
                'intent' => $goalData['description'] ?? $goalData['title'],
                'task_type' => $goalData['agent'] ?? 'campaign',
                'confidence' => 0.8,
                'parameters' => [],
            ];

            $knowledgeContext = $this->knowledgeBase->getContext($user, $intent['task_type']);
            return $this->handleAgentRequest($intent, $user, $conversation, 'web', $knowledgeContext);
        }

        // Format the goal overview for the user
        $message = "🎯 **" . __('brain.goals.created', ['title' => $goal->title]) . "**\n\n";

        if ($goal->description) {
            $message .= "{$goal->description}\n\n";
        }

        $message .= "📋 **" . __('brain.goals.plan_overview') . "** ({$goal->priority})\n";
        foreach ($subPlans as $plan) {
            $order = $plan['order'] ?? '•';
            $agentEmoji = match ($plan['agent'] ?? '') {
                'campaign' => '📧',
                'list' => '📋',
                'message' => '✉️',
                'crm' => '👥',
                'analytics' => '📊',
                'segmentation' => '🎯',
                'research' => '🔍',
                default => '📌',
            };
            $message .= "  {$order}. {$agentEmoji} {$plan['title']}\n";
            if (!empty($plan['description'])) {
                $message .= "     ↳ {$plan['description']}\n";
            }
        }

        // In autonomous mode, start executing the first plan immediately
        if ($settings->work_mode === ModeController::MODE_AUTONOMOUS) {
            $firstPlan = $subPlans[0] ?? null;
            if ($firstPlan) {
                $message .= "\n⚡ " . __('brain.goals.starting_first_plan', [
                    'plan' => $firstPlan['title'] ?? '',
                ]) . "\n";

                // Execute first plan by routing to agent
                $intent = [
                    'requires_agent' => true,
                    'agent' => $firstPlan['agent'] ?? 'campaign',
                    'intent' => $firstPlan['intent'] ?? $firstPlan['description'] ?? $firstPlan['title'],
                    'task_type' => $firstPlan['agent'] ?? 'campaign',
                    'confidence' => 0.9,
                    'parameters' => [],
                ];

                $knowledgeContext = $this->knowledgeBase->getContext($user, $intent['task_type']);
                $planResult = $this->handleAgentRequest($intent, $user, $conversation, 'web', $knowledgeContext);
                $message .= "\n" . ($planResult['message'] ?? '');
            }
        } else {
            $message .= "\n" . __('brain.goals.awaiting_approval');
        }

        return [
            'type' => 'goal_created',
            'message' => $message,
            'goal_id' => $goal->id,
        ];
    }

    /**
     * Handle as regular conversation (no agent needed).
     */
    protected function handleConversation(
        string $message,
        User $user,
        AiConversation $conversation,
        string $knowledgeContext,
        ?AiIntegration $integration = null,
        ?string $preferredModel = null,
    ): array {
        if (!$integration) {
            $integration = $this->aiService->getDefaultIntegration();
        }

        if (!$integration) {
            return [
                'type' => 'message',
                'message' => __('brain.no_ai_integration'),
            ];
        }

        $messages = $this->conversationManager->buildAiPayload(
            $conversation,
            $user,
            $knowledgeContext,
        );

        $provider = $this->aiService->getProvider($integration);
        $modelToUse = $preferredModel ?: null;
        $result = $provider->generateTextWithUsage(
            json_encode($messages),
            $modelToUse,
            ['max_tokens' => 8000, 'temperature' => 0.7]
        );

        $actualModel = $modelToUse ?: ($integration->default_model ?: 'unknown');

        return [
            'type' => 'message',
            'message' => $result['text'],
            'model' => $actualModel,
            'tokens_input' => $result['tokens_input'] ?? 0,
            'tokens_output' => $result['tokens_output'] ?? 0,
        ];
    }

    /**
     * Stream a conversation response, yielding text chunks.
     *
     * Handles the same pre-flight logic as processMessage() but streams the AI response.
     * Non-streamable requests (agent actions) return null — caller should fallback to processMessage().
     *
     * @param callable $onComplete Called with (fullText, metadata) when streaming finishes
     * @return \Generator<string>|null Yields text chunks, or null if not streamable
     */
    public function streamConversation(
        string $message,
        User $user,
        string $channel = 'web',
        ?int $conversationId = null,
        bool $forceNew = false,
        ?callable $onComplete = null,
    ): ?\Generator {
        $startTime = microtime(true);
        $settings = AiBrainSettings::getForUser($user->id);
        $integration = $this->aiService->getDefaultIntegration();

        if (!$integration) {
            return null; // Fallback to synchronous
        }

        if ($settings->isTokenLimitReached()) {
            return null;
        }

        // Use user-preferred integration if set
        if ($settings->preferred_integration_id) {
            $preferredIntegration = AiIntegration::find($settings->preferred_integration_id);
            if ($preferredIntegration && $preferredIntegration->is_active) {
                $integration = $preferredIntegration;
            }
        }

        // Resolve conversation
        if ($conversationId) {
            $conversation = $this->conversationManager->getConversationById($conversationId, $user->id);
            if (!$conversation) {
                $conversation = $this->conversationManager->createNewConversation($user, $channel);
            }
        } elseif ($forceNew) {
            $conversation = $this->conversationManager->createNewConversation($user, $channel);
        } else {
            $conversation = $this->conversationManager->getConversation($user, $channel);
        }

        // Save user message
        $this->conversationManager->addUserMessage($conversation, $message);

        // Check for pending agent or classify intent
        $context = $conversation->context ?? [];
        $pendingAgent = $context['pending_agent'] ?? null;

        if ($pendingAgent && isset($this->agents[$pendingAgent])) {
            return null; // Agent flow — not streamable
        }

        $intent = $this->classifyIntent($message, $conversation, $user);

        if ($intent['requires_agent']) {
            return null; // Agent flow — not streamable
        }

        // Conversation mode — stream it!
        $knowledgeContext = $this->knowledgeBase->getContext($user, $intent['task_type'] ?? 'general');
        $messages = $this->conversationManager->buildAiPayload($conversation, $user, $knowledgeContext);
        $provider = $this->aiService->getProvider($integration);
        $modelToUse = $settings->preferred_model ?: null;
        $actualModel = $modelToUse ?: ($integration->default_model ?: 'unknown');

        // Return a generator that yields chunks and persists on completion
        return (function () use (
            $provider, $messages, $modelToUse, $actualModel,
            $conversation, $user, $message, $settings, $integration,
            $intent, $startTime, $onComplete
        ) {
            $fullText = '';
            $streamCompleted = false;

            try {
                foreach ($provider->generateTextStream(
                    json_encode($messages),
                    $modelToUse,
                    ['max_tokens' => 8000, 'temperature' => 0.7]
                ) as $chunk) {
                    $fullText .= $chunk;
                    yield $chunk;
                }
                $streamCompleted = true;
            } catch (\Exception $e) {
                Log::warning('Streaming interrupted or errored', [
                    'error' => $e->getMessage(),
                    'text_length' => strlen($fullText),
                ]);
                if (empty($fullText)) {
                    $fullText = __('brain.processing_error');
                }
            } finally {
                // Estimate tokens for streaming (APIs don't return usage during stream)
                $estimatedInputTokens = (int) (strlen(json_encode($messages)) / 4);
                $estimatedOutputTokens = (int) (strlen($fullText) / 4);

                // Always persist — even on disconnect, save what we have
                if (!empty($fullText)) {
                    $this->conversationManager->addAssistantMessage(
                        $conversation,
                        $fullText,
                        [
                            'intent' => $intent['intent'] ?? 'conversation',
                            'agent' => null,
                            'work_mode' => $settings->work_mode,
                            'streamed' => true,
                            'completed' => $streamCompleted,
                        ],
                        $estimatedInputTokens, $estimatedOutputTokens, $actualModel
                    );

                    // Track tokens
                    $estimatedTokens = $estimatedInputTokens + $estimatedOutputTokens;
                    $settings->addTokensUsed($estimatedTokens);

                    // Auto-generate title (only on full completion)
                    if ($streamCompleted && !$conversation->title && $conversation->message_count <= 3) {
                        $this->generateConversationTitle($conversation, $message, $fullText, $integration);
                    }

                    // Auto-enrich knowledge
                    if ($streamCompleted && $conversation->message_count % 5 === 0) {
                        $this->tryAutoEnrich($user, $conversation);
                    }
                }

                // Log execution
                $durationMs = (int) ((microtime(true) - $startTime) * 1000);
                AiExecutionLog::logSuccess(
                    $user->id,
                    'orchestrator',
                    $streamCompleted ? 'stream_message' : 'stream_message_partial',
                    ['message' => mb_substr($message, 0, 200)],
                    ['response_length' => strlen($fullText), 'completed' => $streamCompleted],
                    $estimatedInputTokens, $estimatedOutputTokens, $actualModel, $durationMs
                );

                // Notify caller with metadata (only if stream completed normally)
                if ($streamCompleted && $onComplete) {
                    $onComplete([
                        'conversation_id' => $conversation->id,
                        'model' => $actualModel,
                        'title' => $conversation->fresh()->title,
                    ]);
                }
            }
        })();
    }

    /**
     * Auto-generate a short conversation title using AI.
     */
    protected function generateConversationTitle(
        AiConversation $conversation,
        string $userMessage,
        string $aiResponse,
        AiIntegration $integration,
    ): void {
        try {
            // Resolve language for the title
            $userId = $conversation->user_id;
            $settings = AiBrainSettings::getForUser($userId);
            $user = User::find($userId);
            $langCode = $settings->resolveLanguage($user);
            $languageName = AiBrainSettings::getLanguageName($langCode);

            $prompt = <<<PROMPT
Generate a SHORT title (max 5 words) summarizing this conversation topic. Write the title in {$languageName}. Respond with ONLY the title, no quotes, no punctuation at the end.

User message: {$userMessage}
Response: {$aiResponse}

Title:
PROMPT;

            $title = $this->aiService->generateContent($prompt, $integration, [
                'max_tokens' => 30,
                'temperature' => 0.3,
            ]);

            $title = trim($title, " \n\r\t\"'.");
            if (mb_strlen($title) > 80) {
                $title = mb_substr($title, 0, 77) . '...';
            }

            if (!empty($title)) {
                $conversation->update(['title' => $title]);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to generate conversation title', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Format plan for approval display.
     */
    protected function formatPlanForApproval(AiActionPlan $plan): string
    {
        $steps = $plan->steps()->orderBy('step_order')->get();

        $text = __('brain.plan_header', ['title' => $plan->title]) . "\n\n";

        if ($plan->description) {
            $text .= "{$plan->description}\n\n";
        }

        $text .= __('brain.steps_to_execute') . "\n";
        foreach ($steps as $step) {
            $text .= "  {$step->step_order}. {$step->title}\n";
            if ($step->description) {
                $text .= "     ↳ {$step->description}\n";
            }
        }

        $text .= "\n" . __('brain.mode_label', ['mode' => $this->modeController->getModeLabel($plan->work_mode)]);
        $text .= "\n\n" . __('brain.approve_reject');

        return $text;
    }

    /**
     * Try to auto-enrich knowledge base from conversation.
     */
    protected function tryAutoEnrich(User $user, AiConversation $conversation): void
    {
        try {
            $recentMessages = $conversation->getRecentMessages(10)
                ->map(fn($m) => "{$m->role}: {$m->content}")
                ->join("\n");

            $this->knowledgeBase->autoEnrich($user, $recentMessages, "conversation:{$conversation->id}");
        } catch (\Exception $e) {
            Log::debug('Auto-enrichment skipped', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Fallback intent classification using keyword matching.
     */
    protected function fallbackIntentClassification(string $message): array
    {
        $lower = mb_strtolower($message);

        // Situation analysis keywords — check first (higher priority)
        $situationKeywords = [
            'przeanalizuj sytuacj', 'obecn', 'stan', 'podsumuj', 'co jest nie tak',
            'analyze situation', 'current state', 'overview', 'audit', 'przeanalizuj obecn',
        ];
        foreach ($situationKeywords as $keyword) {
            if (mb_strpos($lower, $keyword) !== false) {
                return [
                    'requires_agent' => false,
                    'agent' => null,
                    'intent' => 'situation_analysis',
                    'task_type' => 'situation_analysis',
                    'confidence' => 0.7,
                    'parameters' => [],
                ];
            }
        }

        $patterns = [
            'campaign' => ['kampani', 'newsletter', 'wyślij mail', 'wysyłk', 'email blast', 'mailing'],
            'list' => ['list', 'subskryb', 'kontakt', 'grupa'],
            'message' => ['napisz', 'treść', 'temat', 'subject', 'szablon', 'template', 'wiadomoś'],
            'crm' => ['crm', 'deal', 'lead', 'pipeline', 'scoring', 'zadani', 'firma', 'prospekt', 'klient'],
            'analytics' => ['statystyk', 'analiz', 'raport', 'wynik', 'open rate', 'click', 'trend'],
            'segmentation' => ['segment', 'tag', 'automat', 'reguł', 'scoring', 'filtr'],
            'research' => ['research', 'szukaj', 'wyszukaj', 'find out', 'look up', 'investigate', 'competitor', 'konkuren', 'zbadaj', 'sprawdź w internecie', 'google', 'search online'],
        ];

        foreach ($patterns as $agent => $keywords) {
            foreach ($keywords as $keyword) {
                if (mb_strpos($lower, $keyword) !== false) {
                    return [
                        'requires_agent' => true,
                        'agent' => $agent,
                        'intent' => 'keyword_match',
                        'task_type' => $agent,
                        'confidence' => 0.4,
                        'parameters' => [],
                    ];
                }
            }
        }

        return [
            'requires_agent' => false,
            'intent' => 'conversation',
            'task_type' => 'general',
            'confidence' => 0.5,
            'parameters' => [],
        ];
    }

    /**
     * Handle a situation analysis request from chat.
     * Uses SituationAnalyzer to gather real data + AI analysis,
     * then creates action plans from identified priorities.
     */
    protected function handleSituationAnalysis(
        User $user,
        AiConversation $conversation,
        AiBrainSettings $settings,
    ): array {
        AiBrainActivityLog::logEvent($user->id, 'situation_analysis', 'started');

        try {
            $result = $this->situationAnalyzer->analyzeAndCreateTasks($user);
        } catch (\Exception $e) {
            Log::error('Situation analysis failed', ['error' => $e->getMessage()]);
            return [
                'type' => 'message',
                'message' => __('brain.situation_analysis_error'),
            ];
        }

        if (!$result) {
            return [
                'type' => 'message',
                'message' => __('brain.situation_analysis_no_data'),
            ];
        }

        // Build compact report
        $langCode = $settings->resolveLanguage($user);
        $langName = AiBrainSettings::getLanguageName($langCode);

        $message = "🧠 **" . __('brain.situation_analysis_title') . "**\n\n";
        $message .= $result['summary'] . "\n";

        // Show priorities with created tasks
        if (!empty($result['priorities'])) {
            $message .= "\n📋 **" . __('brain.situation_analysis_priorities') . "**\n";
            foreach ($result['priorities'] as $i => $priority) {
                $num = $i + 1;
                $priorityEmoji = match ($priority['priority'] ?? 'medium') {
                    'high' => '🔴',
                    'medium' => '🟡',
                    'low' => '🟢',
                    default => '⚪',
                };
                $agentEmoji = match ($priority['agent'] ?? '') {
                    'campaign' => '📧',
                    'list' => '📋',
                    'message' => '✉️',
                    'crm' => '👥',
                    'analytics' => '📊',
                    'segmentation' => '🎯',
                    'research' => '🔍',
                    default => '📌',
                };
                $message .= "{$priorityEmoji} {$num}. {$agentEmoji} **{$priority['title']}**\n";
                if (!empty($priority['reasoning'])) {
                    $message .= "   ↳ {$priority['reasoning']}\n";
                }
            }
        }

        // Show created tasks
        $createdTasks = $result['created_tasks'] ?? [];
        if (!empty($createdTasks)) {
            $message .= "\n⚡ **" . __('brain.situation_analysis_tasks_created', ['count' => count($createdTasks)]) . "**\n";
            foreach ($createdTasks as $task) {
                $statusIcon = ($task['status'] ?? '') === 'completed' ? '✅' : '📝';
                $message .= "  {$statusIcon} {$task['title']}\n";
            }
        }

        // In autonomous mode, execute high-priority tasks immediately via direct dispatch
        if ($settings->work_mode === ModeController::MODE_AUTONOMOUS && !empty($createdTasks)) {
            $executed = 0;
            foreach ($createdTasks as $task) {
                if (($task['priority'] ?? 'medium') === 'high' && !empty($task['action']) && !empty($task['agent'])) {
                    try {
                        $taskResult = $this->executeCronTask($task, $user);
                        $executed++;
                    } catch (\Exception $e) {
                        Log::warning('Auto-executing situation task failed', [
                            'task' => $task['title'] ?? '',
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
            if ($executed > 0) {
                $message .= "\n🚀 " . __('brain.situation_analysis_auto_executed', ['count' => $executed]);
            }
        }

        return [
            'type' => 'situation_analysis',
            'message' => $message,
        ];
    }

    /**
     * Execute a task directly from CRON — bypasses intent classification.
     * The task already has agent, action, and priority defined.
     * Auto-fills context from CRM data so agents don't need to ask for info.
     */
    public function executeCronTask(array $task, User $user): array
    {
        $startTime = microtime(true);
        $agentName = $task['agent'] ?? null;
        $agent = $this->agents[$agentName] ?? null;

        if (!$agent) {
            Log::warning('executeCronTask: agent not found', ['agent' => $agentName]);
            return [
                'type' => 'error',
                'message' => "Agent '{$agentName}' not found for cron task.",
            ];
        }

        $settings = AiBrainSettings::getForUser($user->id);

        // Log cron task dispatch
        AiBrainActivityLog::logEvent($user->id, 'cron_task_dispatch', 'started', $agentName, [
            'task_title' => $task['title'] ?? '',
            'task_action' => $task['action'] ?? '',
        ]);

        // Auto-fill context from CRM data — replace the info-gathering step
        $autoContext = $this->gatherAutoContext($user, $agentName);

        // Build enriched intent — no classification needed, task already specifies everything
        $intent = [
            'requires_agent' => true,
            'agent' => $agentName,
            'intent' => $task['action'] ?? $task['title'] ?? '',
            'task_type' => $agentName,
            'confidence' => 1.0,
            'channel' => 'cron',
            'has_user_details' => true, // Skip needsMoreInfo()
            'parameters' => array_merge(
                $task['parameters'] ?? [],
                ['auto_context' => $autoContext],
                ['cron_task' => true],
            ),
        ];

        // Get knowledge context
        $knowledgeContext = $this->knowledgeBase->getContext($user, $agentName);

        // Enrich knowledge context with auto-gathered data
        if (!empty($autoContext)) {
            $autoContextStr = "\n\n--- AUTO-CONTEXT (from CRM/lists) ---\n";
            if (!empty($autoContext['lists'])) {
                $autoContextStr .= "Available lists:\n";
                foreach ($autoContext['lists'] as $list) {
                    $autoContextStr .= "  - {$list['name']} (ID: {$list['id']}, {$list['subscribers_count']} subscribers)\n";
                }
            }
            if (!empty($autoContext['recent_topics'])) {
                $autoContextStr .= "Recent campaign topics (avoid repeating): " . implode(', ', $autoContext['recent_topics']) . "\n";
            }
            if (!empty($autoContext['business_context'])) {
                $autoContextStr .= "Business context: {$autoContext['business_context']}\n";
            }
            $autoContextStr .= "---\n";
            $knowledgeContext .= $autoContextStr;
        }

        try {
            // Create plan directly — agent already has all needed context
            $plan = $agent->plan($intent, $user, $knowledgeContext);

            if (!$plan) {
                Log::warning('executeCronTask: plan creation failed', [
                    'agent' => $agentName,
                    'task' => $task['title'] ?? '',
                ]);
                return [
                    'type' => 'error',
                    'message' => "Failed to create plan for cron task: {$task['title']}",
                ];
            }

            // Execute immediately — cron is always autonomous
            $result = $this->executePlan($plan, $user);

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            AiBrainActivityLog::logEvent($user->id, 'cron_task_complete', 'completed', $agentName, [
                'task_title' => $task['title'] ?? '',
                'plan_id' => $plan->id,
                'duration_ms' => $durationMs,
            ], $durationMs);

            return $result;

        } catch (\Exception $e) {
            Log::error('executeCronTask: execution failed', [
                'agent' => $agentName,
                'task' => $task['title'] ?? '',
                'error' => $e->getMessage(),
            ]);

            AiBrainActivityLog::logEvent($user->id, 'cron_task_error', 'error', $agentName, [
                'task_title' => $task['title'] ?? '',
                'error' => $e->getMessage(),
            ]);

            return [
                'type' => 'error',
                'message' => "Cron task failed: {$e->getMessage()}",
            ];
        }
    }

    /**
     * Gather auto-context from CRM/lists for autonomous task execution.
     * Provides agents with the data they'd normally ask the user for.
     */
    public function gatherAutoContext(User $user, string $agentName): array
    {
        $context = [];

        try {
            // Contact lists with subscriber counts
            $lists = \App\Models\ContactList::where('user_id', $user->id)
                ->withCount('subscribers')
                ->orderByDesc('subscribers_count')
                ->get();

            $context['lists'] = $lists->map(fn($l) => [
                'id' => $l->id,
                'name' => $l->name,
                'subscribers_count' => $l->subscribers_count,
            ])->toArray();

            $context['total_subscribers'] = $lists->sum('subscribers_count');
        } catch (\Exception $e) {
            $context['lists'] = [];
            $context['total_subscribers'] = 0;
        }

        // Recent campaign topics to avoid repetition
        try {
            $recentPlans = AiActionPlan::forUser($user->id)
                ->where('agent_type', 'campaign')
                ->where('created_at', '>=', now()->subDays(14))
                ->pluck('title')
                ->toArray();

            $context['recent_topics'] = $recentPlans;
        } catch (\Exception $e) {
            $context['recent_topics'] = [];
        }

        // CRM data for CRM/segmentation agents
        if (in_array($agentName, ['crm', 'segmentation', 'analytics'])) {
            try {
                $context['hot_leads'] = \App\Models\CrmContact::where('user_id', $user->id)
                    ->where('score', '>=', 50)
                    ->count();
                $context['open_deals'] = \App\Models\CrmDeal::where('user_id', $user->id)
                    ->whereNull('closed_at')
                    ->count();
                $context['total_contacts'] = \App\Models\CrmContact::where('user_id', $user->id)
                    ->count();
            } catch (\Exception $e) {
                // CRM tables may not exist
            }
        }

        // Business context from knowledge base
        try {
            $kbContext = $this->knowledgeBase->getContext($user, 'business');
            if ($kbContext) {
                $context['business_context'] = mb_substr($kbContext, 0, 500);
            }
        } catch (\Exception $e) {
            // ignore
        }

        // Automation rules context
        if (in_array($agentName, ['segmentation', 'campaign', 'analytics'])) {
            try {
                $totalRules = \App\Models\AutomationRule::forUser($user->id)->count();
                $activeRules = \App\Models\AutomationRule::forUser($user->id)->active()->count();
                $context['automations'] = [
                    'total' => $totalRules,
                    'active' => $activeRules,
                ];

                // Top 5 automations for context
                $topRules = \App\Models\AutomationRule::forUser($user->id)
                    ->select('id', 'name', 'trigger_event', 'is_active')
                    ->orderByDesc('is_active')
                    ->orderByDesc('last_executed_at')
                    ->limit(5)
                    ->get();

                $context['automations']['rules'] = $topRules->map(fn($r) => [
                    'id' => $r->id,
                    'name' => $r->name,
                    'trigger' => $r->trigger_event,
                    'active' => $r->is_active,
                ])->toArray();
            } catch (\Exception $e) {
                // AutomationRule table may not exist
            }
        }

        // A/B test context
        if (in_array($agentName, ['campaign', 'message', 'analytics'])) {
            try {
                $runningTests = \App\Models\AbTest::forUser($user->id)->running()->count();
                $completedTests = \App\Models\AbTest::forUser($user->id)->completed()->count();
                $context['ab_tests'] = [
                    'running' => $runningTests,
                    'completed' => $completedTests,
                ];
            } catch (\Exception $e) {
                // AbTest table may not exist
            }
        }

        return $context;
    }

    /**
     * Parse JSON from AI response (handles markdown code blocks).
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
