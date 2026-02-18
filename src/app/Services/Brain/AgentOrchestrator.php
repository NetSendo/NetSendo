<?php

namespace App\Services\Brain;

use App\Models\AiActionPlan;
use App\Models\AiBrainSettings;
use App\Models\AiConversation;
use App\Models\AiExecutionLog;
use App\Models\AiIntegration;
use App\Models\User;
use App\Services\AI\AiService;
use App\Services\Brain\Agents\BaseAgent;
use App\Services\Brain\Agents\AnalyticsAgent;
use App\Services\Brain\Agents\CampaignAgent;
use App\Services\Brain\Agents\CrmAgent;
use App\Services\Brain\Agents\ListAgent;
use App\Services\Brain\Agents\MessageAgent;
use App\Services\Brain\Agents\SegmentationAgent;
use App\Services\Brain\Skills\MarketingSalesSkill;
use Illuminate\Support\Facades\Log;

class AgentOrchestrator
{
    protected array $agents = [];

    public function __construct(
        protected AiService $aiService,
        protected ConversationManager $conversationManager,
        protected ModeController $modeController,
        protected KnowledgeBaseService $knowledgeBase,
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
        ];
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
                // Step 1: Classify intent
                $intent = $this->classifyIntent($message, $conversation, $user);

                // Step 2: Get knowledge context for this intent
                $knowledgeContext = $this->knowledgeBase->getContext($user, $intent['task_type'] ?? 'general');

                // Step 3: Route to appropriate agent or handle as conversation
                if ($intent['requires_agent']) {
                    $result = $this->handleAgentRequest($intent, $user, $conversation, $channel, $knowledgeContext);
                } else {
                    $result = $this->handleConversation($message, $user, $conversation, $knowledgeContext, $integration, $settings->preferred_model);
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
        $skillContext = MarketingSalesSkill::getSystemPrompt($settings->preferred_language ?? 'pl');

        $prompt = <<<PROMPT
{$skillContext}

---

Sklasyfikuj intencję użytkownika. Odpowiedz TYLKO prawidłowym JSON.

DOSTĘPNI AGENCI:
{$agentDescriptions}

OSTATNI KONTEKST ROZMOWY:
{$recentContext}

NOWA WIADOMOŚĆ UŻYTKOWNIKA:
{$message}

Odpowiedz w JSON:
{
  "requires_agent": true/false,
  "agent": "campaign|list|message|crm|analytics|segmentation|null",
  "intent": "krótki opis intencji",
  "task_type": "campaign|message|list|crm|analytics|segmentation|general",
  "confidence": 0.0-1.0,
  "parameters": {}
}

Ustaw requires_agent=false dla pytań ogólnych, rozmów, pozdrowień.
Ustaw requires_agent=true gdy użytkownik chce WYKONAĆ konkretną akcję (np. stworzyć kampanię, dodać do listy, etc.)
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

        // Create an action plan
        $plan = $agent->plan($intent, $user, $knowledgeContext);

        if (!$plan) {
            return [
                'type' => 'message',
                'message' => __('brain.plan_failed'),
            ];
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

        try {
            $result = $agent->execute($plan, $user);

            $plan->markCompleted([
                'result' => $result['message'] ?? 'Completed',
                'completed_steps' => $plan->completed_steps,
                'failed_steps' => $plan->failed_steps,
            ]);

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
        $response = $provider->generateText(
            json_encode($messages),
            $modelToUse,
            ['max_tokens' => 2000, 'temperature' => 0.7]
        );

        $actualModel = $modelToUse ?: ($integration->default_model ?: 'unknown');

        return [
            'type' => 'message',
            'message' => $response,
            'model' => $actualModel,
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
                    ['max_tokens' => 2000, 'temperature' => 0.7]
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
                        0, 0, $actualModel
                    );

                    // Track tokens (estimate)
                    $estimatedTokens = (int) (strlen($fullText) / 4);
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
                    0, 0, $actualModel, $durationMs
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
            $prompt = <<<PROMPT
Wygeneruj KRÓTKI tytuł (max 5 słów) podsumowujący temat tej rozmowy. Odpowiedz TYLKO tytułem, bez cudzysłowów, bez znaków interpunkcyjnych na końcu.

Wiadomość użytkownika: {$userMessage}
Odpowiedź: {$aiResponse}

Tytuł:
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

        $patterns = [
            'campaign' => ['kampani', 'newsletter', 'wyślij mail', 'wysyłk', 'email blast', 'mailing'],
            'list' => ['list', 'subskryb', 'kontakt', 'grupa'],
            'message' => ['napisz', 'treść', 'temat', 'subject', 'szablon', 'template', 'wiadomoś'],
            'crm' => ['crm', 'deal', 'lead', 'pipeline', 'scoring', 'zadani', 'firma', 'prospekt', 'klient'],
            'analytics' => ['statystyk', 'analiz', 'raport', 'wynik', 'open rate', 'click', 'trend'],
            'segmentation' => ['segment', 'tag', 'automat', 'reguł', 'scoring', 'filtr'],
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

    /**
     * Get all registered agents.
     */
    public function getAgents(): array
    {
        return $this->agents;
    }
}
