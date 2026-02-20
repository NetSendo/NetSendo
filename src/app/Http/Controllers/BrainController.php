<?php

namespace App\Http\Controllers;

use App\Models\AiActionPlan;
use App\Models\AiBrainActivityLog;
use App\Models\AiBrainSettings;
use App\Models\AiConversation;
use App\Models\AiExecutionLog;
use App\Models\KnowledgeEntry;
use App\Services\Brain\AgentOrchestrator;
use App\Services\Brain\KnowledgeBaseService;
use App\Services\Brain\ModeController;
use App\Services\Brain\VoiceTranscriptionService;
use App\Services\Brain\WebResearchService;
use App\Services\Brain\Telegram\TelegramAuthService;
use App\Services\Brain\Telegram\TelegramBotService;
use App\Services\Brain\Skills\MarketingSalesSkill;
use App\Services\Brain\Skills\ResearchSkill;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class BrainController extends Controller
{
    public function __construct(
        protected AgentOrchestrator $orchestrator,
        protected KnowledgeBaseService $knowledgeBase,
        protected ModeController $modeController,
        protected TelegramAuthService $telegramAuth,
        protected TelegramBotService $telegramBot,
        protected VoiceTranscriptionService $voiceTranscription,
    ) {}

    /**
     * Send a chat message to the Brain.
     * POST /api/brain/chat
     */
    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:5000',
            'conversation_id' => 'nullable|integer',
            'force_new' => 'nullable|boolean',
        ]);

        $user = $request->user();
        $result = $this->orchestrator->processMessage(
            $request->input('message'),
            $user,
            'web',
            $request->input('conversation_id'),
            $request->boolean('force_new', false),
        );

        return response()->json($result);
    }

    /**
     * Send a voice message to the Brain (audio file → transcription → AI response).
     * POST /brain/api/chat/voice
     */
    public function chatVoice(Request $request): JsonResponse
    {
        $request->validate([
            'audio' => 'required|file|max:25600|mimes:webm,ogg,mp3,mp4,m4a,wav,mpeg',
            'conversation_id' => 'nullable|integer',
            'force_new' => 'nullable|boolean',
        ]);

        $user = $request->user();

        try {
            // Determine language hint from user's Brain preferences
            $settings = AiBrainSettings::getForUser($user->id);
            $language = $settings->preferred_language ?? null;

            // Transcribe the audio file
            $audioFile = $request->file('audio');
            $audioPath = $audioFile->getRealPath();
            $originalFilename = $audioFile->getClientOriginalName();
            $transcribedText = $this->voiceTranscription->transcribe($audioPath, $language, $originalFilename);

            // Process through the Brain (same as text chat)
            $result = $this->orchestrator->processMessage(
                $transcribedText,
                $user,
                'web',
                $request->input('conversation_id'),
                $request->boolean('force_new', false),
            );

            // Append transcribed text for frontend display
            $result['transcribed_text'] = $transcribedText;
            $result['input_type'] = 'voice';

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Voice chat failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => $e->getMessage(),
                'type' => 'voice_error',
            ], 422);
        }
    }

    /**
     * Stream a chat response via Server-Sent Events.
     * POST /api/brain/chat/stream
     */
    public function chatStream(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:5000',
            'conversation_id' => 'nullable|integer',
            'force_new' => 'nullable|boolean',
        ]);

        $user = $request->user();
        $message = $request->input('message');
        $conversationId = $request->input('conversation_id');
        $forceNew = $request->boolean('force_new', false);

        return response()->stream(function () use ($user, $message, $conversationId, $forceNew) {
            // Disable output buffering for real-time streaming
            if (ob_get_level()) ob_end_clean();

            $metadata = [];

            $stream = $this->orchestrator->streamConversation(
                $message,
                $user,
                'web',
                $conversationId,
                $forceNew,
                function (array $meta) use (&$metadata) {
                    $metadata = $meta;
                }
            );

            if ($stream === null) {
                // Not streamable (agent request) — fall back to synchronous
                $result = $this->orchestrator->processMessage(
                    $message,
                    $user,
                    'web',
                    $conversationId,
                    $forceNew,
                );

                echo "data: " . json_encode([
                    'delta' => $result['message'] ?? $result['response'] ?? '',
                ]) . "\n\n";
                flush();

                echo "data: " . json_encode([
                    'done' => true,
                    'conversation_id' => $result['conversation_id'] ?? null,
                    'model' => $result['model'] ?? null,
                    'title' => $result['title'] ?? null,
                    'type' => $result['type'] ?? 'message',
                    'plan' => $result['plan'] ?? null,
                ]) . "\n\n";
                flush();

                return;
            }

            // Stream token by token
            foreach ($stream as $chunk) {
                echo "data: " . json_encode(['delta' => $chunk]) . "\n\n";
                flush();
            }

            // Send completion event with metadata
            echo "data: " . json_encode(array_merge(
                ['done' => true],
                $metadata
            )) . "\n\n";
            flush();

        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no', // Disable Nginx buffering
        ]);
    }

    /**
     * Get conversation history.
     * GET /api/brain/conversations
     */
    public function conversations(Request $request): JsonResponse
    {
        $user = $request->user();

        $conversations = AiConversation::forUser($user->id)
            ->orderByDesc('last_activity_at')
            ->with(['messages' => function ($q) {
                $q->orderByDesc('created_at')->limit(1);
            }])
            ->paginate(20);

        return response()->json($conversations);
    }

    /**
     * Get a specific conversation with messages.
     * GET /api/brain/conversations/{id}
     */
    public function conversation(Request $request, int $id): JsonResponse
    {
        $conversation = AiConversation::forUser($request->user()->id)
            ->with('messages')
            ->findOrFail($id);

        return response()->json($conversation);
    }

    /**
     * Update a conversation (e.g. rename title).
     * PUT /api/brain/conversations/{id}
     */
    public function updateConversation(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:120',
        ]);

        $conversation = AiConversation::forUser($request->user()->id)->findOrFail($id);
        $conversation->update(['title' => $request->input('title')]);

        return response()->json($conversation);
    }

    /**
     * Get knowledge base entries.
     * GET /api/brain/knowledge
     */
    public function knowledge(Request $request): JsonResponse
    {
        $user = $request->user();
        $category = $request->query('category');
        $search = $request->query('search');

        if ($search) {
            $entries = $this->knowledgeBase->search($user, $search, $category);
            return response()->json($entries);
        }

        $grouped = $this->knowledgeBase->getAllGrouped($user);
        return response()->json([
            'entries' => $grouped,
            'summary' => $this->knowledgeBase->getSummary($user),
            'categories' => KnowledgeEntry::CATEGORIES,
        ]);
    }

    /**
     * Store a knowledge entry.
     * POST /api/brain/knowledge
     */
    public function storeKnowledge(Request $request): JsonResponse
    {
        $request->validate([
            'category' => 'required|string|in:' . implode(',', array_keys(KnowledgeEntry::CATEGORIES)),
            'title' => 'required|string|max:255',
            'content' => 'required|string|max:10000',
            'tags' => 'nullable|array',
        ]);

        $entry = $this->knowledgeBase->addEntry(
            $request->user(),
            $request->input('category'),
            $request->input('title'),
            $request->input('content'),
            'user',
            null,
            $request->input('tags', [])
        );

        return response()->json($entry, 201);
    }

    /**
     * Update a knowledge entry.
     * PUT /api/brain/knowledge/{id}
     */
    public function updateKnowledge(Request $request, int $id): JsonResponse
    {
        $entry = KnowledgeEntry::where('user_id', $request->user()->id)->findOrFail($id);

        $request->validate([
            'category' => 'nullable|string|in:' . implode(',', array_keys(KnowledgeEntry::CATEGORIES)),
            'title' => 'nullable|string|max:255',
            'content' => 'nullable|string|max:10000',
            'is_active' => 'nullable|boolean',
            'is_verified' => 'nullable|boolean',
        ]);

        $entry->update($request->only(['category', 'title', 'content', 'is_active', 'is_verified']));

        return response()->json($entry);
    }

    /**
     * Delete a knowledge entry.
     * DELETE /api/brain/knowledge/{id}
     */
    public function deleteKnowledge(Request $request, int $id): JsonResponse
    {
        $entry = KnowledgeEntry::where('user_id', $request->user()->id)->findOrFail($id);
        $entry->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Get action plans.
     * GET /api/brain/plans
     */
    public function plans(Request $request): JsonResponse
    {
        $user = $request->user();
        $status = $request->query('status');

        $query = AiActionPlan::forUser($user->id)
            ->with(['steps', 'pendingApproval'])
            ->orderByDesc('created_at');

        if ($status) {
            $query->withStatus($status);
        }

        return response()->json($query->paginate(20));
    }

    /**
     * Get a specific action plan.
     * GET /api/brain/plans/{id}
     */
    public function plan(Request $request, int $id): JsonResponse
    {
        $plan = AiActionPlan::forUser($request->user()->id)
            ->with(['steps', 'pendingApproval', 'executionLogs'])
            ->findOrFail($id);

        return response()->json($plan);
    }

    /**
     * Approve an action plan.
     * POST /api/brain/plans/{id}/approve
     */
    public function approvePlan(Request $request, int $id): JsonResponse
    {
        $plan = AiActionPlan::forUser($request->user()->id)->findOrFail($id);
        $approval = $plan->pendingApproval;

        if (!$approval) {
            return response()->json(['error' => 'No pending approval found'], 404);
        }

        $approved = $request->boolean('approved', true);
        $reason = $request->input('reason');

        $this->modeController->processApproval($approval->id, $approved, $reason);

        if ($approved) {
            // Execute the plan
            $result = $this->orchestrator->executePlan($plan->fresh(), $request->user());
            return response()->json($result);
        }

        return response()->json(['status' => 'rejected']);
    }

    /**
     * Get Brain settings.
     * GET /api/brain/settings
     */
    public function settings(Request $request): JsonResponse
    {
        $user = $request->user();
        $settings = AiBrainSettings::getForUser($user->id);

        return response()->json([
            'settings' => $settings,
            'modes' => [
                [
                    'value' => 'autonomous',
                    'label' => $this->modeController->getModeLabel('autonomous'),
                    'description' => $this->modeController->getModeDescription('autonomous'),
                ],
                [
                    'value' => 'semi_auto',
                    'label' => $this->modeController->getModeLabel('semi_auto'),
                    'description' => $this->modeController->getModeDescription('semi_auto'),
                ],
                [
                    'value' => 'manual',
                    'label' => $this->modeController->getModeLabel('manual'),
                    'description' => $this->modeController->getModeDescription('manual'),
                ],
            ],
            'telegram_connected' => $settings->isTelegramConnected(),
        ]);
    }

    /**
     * Update Brain settings.
     * PUT /api/brain/settings
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $request->validate([
            'work_mode' => 'nullable|string|in:autonomous,semi_auto,manual',
            'preferred_language' => 'nullable|string|max:5',
            'daily_token_limit' => 'nullable|integer|min:1000',
            'preferences' => 'nullable|array',
            'telegram_bot_token' => 'nullable|string|max:255',
            'perplexity_api_key' => 'nullable|string|max:255',
            'serpapi_api_key' => 'nullable|string|max:255',
            'preferred_model' => 'nullable|string|max:100',
            'preferred_integration_id' => 'nullable|integer|exists:ai_integrations,id',
            'model_routing' => 'nullable|array',
        ]);

        $settings = AiBrainSettings::getForUser($request->user()->id);
        $settings->update($request->only([
            'work_mode', 'preferred_language', 'daily_token_limit',
            'preferences', 'telegram_bot_token',
            'perplexity_api_key', 'serpapi_api_key',
            'preferred_model', 'preferred_integration_id',
            'model_routing',
        ]));

        // Auto-register webhook when bot token is saved
        if ($request->has('telegram_bot_token') && !empty($request->input('telegram_bot_token'))) {
            try {
                $webhookUrl = rtrim(config('app.url'), '/') . '/api/telegram/webhook';
                $result = $this->telegramBot->setWebhook($webhookUrl, $request->user());

                Log::info('Telegram webhook auto-registration', [
                    'url' => $webhookUrl,
                    'result' => $result,
                ]);

                $settings->webhook_setup_result = $result['ok'] ?? false
                    ? 'success'
                    : ($result['description'] ?? 'failed');
            } catch (\Exception $e) {
                Log::warning('Telegram webhook auto-registration failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json($settings);
    }

    /**
     * Generate a Telegram link code.
     * POST /api/brain/telegram/link-code
     */
    public function generateTelegramLinkCode(Request $request): JsonResponse
    {
        $code = $this->telegramAuth->generateLinkCode($request->user());

        return response()->json([
            'code' => $code,
            'instructions' => __('brain.telegram_link_instructions', ['code' => $code]),
        ]);
    }

    /**
     * Disconnect Telegram.
     * POST /api/brain/telegram/disconnect
     */
    public function disconnectTelegram(Request $request): JsonResponse
    {
        $this->telegramAuth->unlinkAccount($request->user());

        return response()->json(['success' => true]);
    }

    /**
     * Test Telegram bot connection.
     * POST /api/brain/telegram/test
     */
    public function testTelegramBot(Request $request): JsonResponse
    {
        $settings = AiBrainSettings::getForUser($request->user()->id);
        $token = $settings->getBotToken();

        if (empty($token)) {
            return response()->json([
                'success' => false,
                'error' => 'Telegram bot token is not configured. Add it in Brain settings.',
            ], 422);
        }

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(10)
                ->get("https://api.telegram.org/bot{$token}/getMe");

            if ($response->successful() && ($response->json('ok') === true)) {
                $bot = $response->json('result');

                return response()->json([
                    'success' => true,
                    'bot' => [
                        'id' => $bot['id'] ?? null,
                        'name' => $bot['first_name'] ?? null,
                        'username' => $bot['username'] ?? null,
                        'can_join_groups' => $bot['can_join_groups'] ?? false,
                        'can_read_all_group_messages' => $bot['can_read_all_group_messages'] ?? false,
                    ],
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => $response->json('description') ?? 'Invalid bot token',
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Connection to Telegram API failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test research API connection (Perplexity or SerpAPI).
     * POST /brain/api/research/test
     */
    public function testResearchApi(Request $request): JsonResponse
    {
        $request->validate([
            'provider' => 'required|string|in:perplexity,serpapi',
            'api_key' => 'required|string|max:255',
        ]);

        $provider = $request->input('provider');
        $apiKey = $request->input('api_key');
        $webResearch = app(WebResearchService::class);

        $result = match ($provider) {
            'perplexity' => $webResearch->testPerplexity($apiKey),
            'serpapi' => $webResearch->testSerpApi($apiKey),
        };

        return response()->json($result);
    }

    /**
     * Get Brain status for the Dashboard widget.
     * GET /brain/api/status
     */
    public function dashboardStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        $settings = AiBrainSettings::getForUser($user->id);

        $knowledgeCount = KnowledgeEntry::where('user_id', $user->id)
            ->where('is_active', true)
            ->count();

        return response()->json([
            'work_mode' => $settings->work_mode ?? 'semi_auto',
            'mode_label' => $this->modeController->getModeLabel($settings->work_mode ?? 'semi_auto'),
            'knowledge_count' => $knowledgeCount,
            'telegram_connected' => $settings->isTelegramConnected(),
        ]);
    }

    /**
     * Get Orchestration Monitor data — live status of Brain and agents.
     * GET /brain/api/monitor
     */
    public function orchestrationMonitor(Request $request): JsonResponse
    {
        $user = $request->user();
        $settings = AiBrainSettings::getForUser($user->id);

        // Agent registry info
        $agents = collect($this->orchestrator->getAgents())->map(function ($agent, $name) use ($user) {
            $lastLog = AiExecutionLog::forUser($user->id)
                ->forAgent($name)
                ->orderByDesc('created_at')
                ->first();

            $todayLogs = AiExecutionLog::forUser($user->id)
                ->forAgent($name)
                ->whereDate('created_at', today())
                ->get();

            $totalToday = $todayLogs->count();
            $successToday = $todayLogs->where('status', 'success')->count();

            return [
                'name' => $name,
                'label' => $agent->getLabel(),
                'capabilities' => $agent->getCapabilities(),
                'last_activity_at' => $lastLog?->created_at,
                'last_action' => $lastLog?->action,
                'last_status' => $lastLog?->status,
                'tasks_today' => $totalToday,
                'success_rate' => $totalToday > 0 ? round(($successToday / $totalToday) * 100, 1) : null,
            ];
        })->values();

        // Plan stats
        $planStats = [
            'total' => AiActionPlan::forUser($user->id)->count(),
            'today' => AiActionPlan::forUser($user->id)->whereDate('created_at', today())->count(),
            'active' => AiActionPlan::forUser($user->id)->active()->count(),
            'completed' => AiActionPlan::forUser($user->id)->withStatus('completed')->count(),
            'failed' => AiActionPlan::forUser($user->id)->withStatus('failed')->count(),
            'pending' => AiActionPlan::forUser($user->id)->pending()->count(),
        ];

        // Today's token usage — real values with cost estimation
        $tokensToday = AiExecutionLog::forUser($user->id)
            ->whereDate('created_at', today())
            ->selectRaw('COALESCE(SUM(tokens_input), 0) as input, COALESCE(SUM(tokens_output), 0) as output')
            ->first();

        // Per-model token breakdown with cost estimation
        $tokensByModel = AiExecutionLog::forUser($user->id)
            ->whereDate('created_at', today())
            ->whereNotNull('model_used')
            ->selectRaw('model_used, COALESCE(SUM(tokens_input), 0) as input, COALESCE(SUM(tokens_output), 0) as output')
            ->groupBy('model_used')
            ->get()
            ->map(function ($row) {
                $input = (int) $row->input;
                $output = (int) $row->output;
                $costUsd = self::estimateTokenCost($row->model_used, $input, $output);
                return [
                    'model' => $row->model_used,
                    'input' => $input,
                    'output' => $output,
                    'total' => $input + $output,
                    'cost_usd' => $costUsd,
                ];
            });

        $totalInput = (int) ($tokensToday->input ?? 0);
        $totalOutput = (int) ($tokensToday->output ?? 0);
        $totalCostUsd = $tokensByModel->sum('cost_usd');

        // Recent activity logs (last 20)
        $recentActivity = AiBrainActivityLog::forUser($user->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        // Last Brain activity
        $lastActivity = AiExecutionLog::forUser($user->id)
            ->orderByDesc('created_at')
            ->first();

        // Determine if Brain is "active" (had activity in the last 5 minutes)
        $isActive = $lastActivity && $lastActivity->created_at->diffInMinutes(now()) < 5;

        // Currently executing plan?
        $executingPlan = AiActionPlan::forUser($user->id)
            ->withStatus('executing')
            ->with('steps')
            ->first();

        $isRunning = $executingPlan !== null;
        $currentTask = null;
        if ($executingPlan) {
            $completedSteps = $executingPlan->steps->where('status', 'completed')->count();
            $totalSteps = $executingPlan->steps->count();
            $currentTask = [
                'plan_id'     => $executingPlan->id,
                'description' => $executingPlan->description ?? $executingPlan->intent,
                'agent'       => $executingPlan->agent_type,
                'started_at'  => $executingPlan->updated_at,
                'progress'    => $totalSteps > 0 ? round(($completedSteps / $totalSteps) * 100) : 0,
                'steps_done'  => $completedSteps,
                'steps_total' => $totalSteps,
            ];
        }

        // Recent execution logs for dashboard feed (last 5)
        $recentLogs = AiExecutionLog::forUser($user->id)
            ->with('plan')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn($log) => [
                'id'         => $log->id,
                'agent'      => $log->agent_type,
                'action'     => $log->action,
                'status'     => $log->status,
                'created_at' => $log->created_at,
                'plan_desc'  => $log->plan?->description ?? $log->plan?->intent,
            ]);

        return response()->json([
            'brain' => [
                'is_active' => $isActive,
                'is_running' => $isRunning,
                'work_mode' => $settings->work_mode ?? 'semi_auto',
                'mode_label' => $this->modeController->getModeLabel($settings->work_mode ?? 'semi_auto'),
                'last_activity_at' => $settings->last_activity_at ?? $lastActivity?->created_at,
                'is_active_flag' => $settings->is_active,
            ],
            'current_task' => $currentTask,
            'agents' => $agents,
            'plan_stats' => $planStats,
            'tokens_today' => [
                'input' => $totalInput,
                'output' => $totalOutput,
                'total' => $totalInput + $totalOutput,
                'cost_usd' => round($totalCostUsd, 4),
                'by_model' => $tokensByModel->values(),
            ],
            'cron' => [
                'enabled' => (bool) $settings->cron_enabled,
                'interval_minutes' => (int) ($settings->cron_interval_minutes ?? 60),
                'last_run_at' => $settings->last_cron_run_at,
            ],
            'recent_activity' => $recentActivity,
            'recent_logs' => $recentLogs,
            'suggested_tasks' => array_merge(
                MarketingSalesSkill::getSuggestedTasks($user),
                ResearchSkill::getSuggestedTasks($user),
            ),
            'task_categories' => MarketingSalesSkill::getTaskCategories(),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Get paginated execution logs for the Orchestration Monitor.
     * GET /brain/api/monitor/logs
     */
    public function orchestrationLogs(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = AiExecutionLog::forUser($user->id)
            ->with(['plan:id,title,agent_type,status', 'step:id,title,action_type,status'])
            ->orderByDesc('created_at');

        // Filters
        if ($agent = $request->query('agent')) {
            $query->forAgent($agent);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($from = $request->query('from')) {
            $query->where('created_at', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->where('created_at', '<=', $to);
        }

        return response()->json($query->paginate(30));
    }

    /**
     * Update CRON settings for the Brain.
     * PUT /brain/api/monitor/cron
     */
    public function updateCronSettings(Request $request): JsonResponse
    {
        $request->validate([
            'cron_enabled' => 'required|boolean',
            'cron_interval_minutes' => 'required|integer|in:5,15,30,60,120,240,360,720,1440',
        ]);

        $settings = AiBrainSettings::getForUser($request->user()->id);
        $settings->update([
            'cron_enabled' => $request->boolean('cron_enabled'),
            'cron_interval_minutes' => $request->input('cron_interval_minutes'),
        ]);

        return response()->json([
            'success' => true,
            'cron_enabled' => (bool) $settings->cron_enabled,
            'cron_interval_minutes' => (int) $settings->cron_interval_minutes,
        ]);
    }

    /**
     * Estimate token cost in USD based on model name.
     * Pricing per 1M tokens (input / output) — approximate, as of 2025.
     */
    private static function estimateTokenCost(string $model, int $inputTokens, int $outputTokens): float
    {
        // Pricing per 1M tokens: [input_price, output_price]
        $pricing = [
            'gpt-4o'            => [2.50, 10.00],
            'gpt-4o-mini'       => [0.15, 0.60],
            'gpt-4-turbo'       => [10.00, 30.00],
            'gpt-4'             => [30.00, 60.00],
            'gpt-3.5-turbo'     => [0.50, 1.50],
            'o1'                => [15.00, 60.00],
            'o1-mini'           => [3.00, 12.00],
            'o3-mini'           => [1.10, 4.40],
            'claude-3-5-sonnet' => [3.00, 15.00],
            'claude-3-5-haiku'  => [0.80, 4.00],
            'claude-3-opus'     => [15.00, 75.00],
            'claude-3-sonnet'   => [3.00, 15.00],
            'claude-3-haiku'    => [0.25, 1.25],
            'gemini-2.0-flash'  => [0.10, 0.40],
            'gemini-1.5-pro'    => [1.25, 5.00],
            'gemini-1.5-flash'  => [0.075, 0.30],
        ];

        // Find matching pricing (partial match for versioned model names)
        $rates = null;
        $modelLower = strtolower($model);
        foreach ($pricing as $key => $price) {
            if (str_contains($modelLower, $key)) {
                $rates = $price;
                break;
            }
        }

        // Fallback: conservative estimate (GPT-4o-mini level)
        if (!$rates) {
            $rates = [0.15, 0.60];
        }

        return round(
            ($inputTokens / 1_000_000) * $rates[0] + ($outputTokens / 1_000_000) * $rates[1],
            6
        );
    }
}

