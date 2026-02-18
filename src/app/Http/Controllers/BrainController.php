<?php

namespace App\Http\Controllers;

use App\Models\AiActionPlan;
use App\Models\AiBrainSettings;
use App\Models\AiConversation;
use App\Models\KnowledgeEntry;
use App\Services\Brain\AgentOrchestrator;
use App\Services\Brain\KnowledgeBaseService;
use App\Services\Brain\ModeController;
use App\Services\Brain\Telegram\TelegramAuthService;
use App\Services\Brain\Telegram\TelegramBotService;
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
            'preferred_model' => 'nullable|string|max:100',
            'preferred_integration_id' => 'nullable|integer|exists:ai_integrations,id',
            'model_routing' => 'nullable|array',
        ]);

        $settings = AiBrainSettings::getForUser($request->user()->id);
        $settings->update($request->only([
            'work_mode', 'preferred_language', 'daily_token_limit',
            'preferences', 'telegram_bot_token',
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
            'instructions' => "Wyślij do bota Telegram: /connect {$code}",
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
}

