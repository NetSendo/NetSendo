<?php

namespace App\Services\Brain\Telegram;

use App\Models\AiBrainSettings;
use App\Models\AiPendingApproval;
use App\Models\User;
use App\Services\Brain\AgentOrchestrator;
use App\Services\Brain\VoiceTranscriptionService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramBotService
{
    protected string $apiBase = 'https://api.telegram.org/bot';

    public function __construct(
        protected TelegramAuthService $authService,
    ) {}

    /**
     * Resolve the bot token for a given user (from DB) or fallback to config.
     */
    protected function resolveBotToken(?User $user = null): string
    {
        if ($user) {
            $settings = AiBrainSettings::getForUser($user->id);
            $token = $settings->getBotToken();
            if ($token) {
                return $token;
            }
        }

        return config('services.telegram.bot_token', '');
    }

    /**
     * Resolve any available bot token from DB (for self-hosted with single instance).
     * Used as fallback when chat_id is not yet linked to any user.
     */
    protected function resolveAnyBotToken(): string
    {
        $settings = AiBrainSettings::whereNotNull('telegram_bot_token')
            ->where('telegram_bot_token', '!=', '')
            ->first();

        if ($settings) {
            return $settings->getBotToken() ?: '';
        }

        return config('services.telegram.bot_token', '');
    }

    /**
     * Resolve the bot token from a Telegram chat_id (find linked user first).
     */
    protected function resolveBotTokenByChatId(string $chatId): string
    {
        $user = $this->authService->findUserByChatId($chatId);
        return $this->resolveBotToken($user);
    }

    /**
     * Process an incoming webhook update from Telegram.
     */
    public function processUpdate(array $update): void
    {
        if (isset($update['message'])) {
            $this->handleMessage($update['message']);
        } elseif (isset($update['callback_query'])) {
            $this->handleCallbackQuery($update['callback_query']);
        }
    }

    /**
     * Handle an incoming text message.
     */
    protected function handleMessage(array $message): void
    {
        $chatId = (string) $message['chat']['id'];
        $text = $message['text'] ?? '';
        $username = $message['from']['username'] ?? null;

        // Handle voice messages
        if (isset($message['voice']) || isset($message['audio'])) {
            $this->handleVoiceMessage($message);
            return;
        }

        if (empty($text)) {
            return;
        }

        // Handle commands
        if (str_starts_with($text, '/')) {
            $this->handleCommand($chatId, $text, $username);
            return;
        }

        // Find linked user
        $user = $this->authService->findUserByChatId($chatId);

        if (!$user) {
            $this->sendMessage($chatId, "⚠️ Your Telegram account is not linked to NetSendo.\n\nUse `/connect YOUR_CODE` to link your account.\nYou can find the code in the NetSendo panel → Settings → AI Brain → Telegram.");
            return;
        }

        // Set locale so __() translations resolve the user's language
        $this->setUserLocale($user);

        // Check if user requested a new conversation
        $forceNew = $this->consumeForceNewFlag($chatId);

        // Process through the Brain
        try {
            $orchestrator = app(AgentOrchestrator::class);
            $result = $orchestrator->processMessage($text, $user, 'telegram', forceNew: $forceNew);

            if ($forceNew) {
                $this->sendMessage($chatId, '🆕 ' . __('brain.telegram_new_conversation_started'));
            }

            if ($result['type'] === 'approval_request') {
                $this->sendApprovalRequest($chatId, $result);
            } else {
                $this->sendMessage($chatId, $result['message'] ?? 'Processed.', $this->getNewConversationKeyboard());
            }
        } catch (\Exception $e) {
            Log::error('Telegram message processing failed', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
            $this->sendMessage($chatId, '❌ An error occurred. Please try again.');
        }
    }

    /**
     * Handle an incoming voice message (voice note or audio file).
     */
    protected function handleVoiceMessage(array $message): void
    {
        $chatId = (string) $message['chat']['id'];

        // Find linked user
        $user = $this->authService->findUserByChatId($chatId);

        if (!$user) {
            $this->sendMessage($chatId, "⚠️ Your Telegram account is not linked to NetSendo.\n\nUse `/connect YOUR_CODE` to link your account.");
            return;
        }

        // Set locale so __() translations resolve the user's language
        $this->setUserLocale($user);

        // Get voice or audio file info
        $voice = $message['voice'] ?? $message['audio'] ?? null;
        if (!$voice || empty($voice['file_id'])) {
            $this->sendMessage($chatId, '❌ Could not process the voice message.');
            return;
        }

        $this->sendMessage($chatId, '🎤 Transcribing your voice message...');

        try {
            // Get file URL from Telegram
            $fileUrl = $this->getFileUrl($voice['file_id'], $chatId);

            if (!$fileUrl) {
                $this->sendMessage($chatId, '❌ Failed to download the voice file from Telegram.');
                return;
            }

            // Transcribe using VoiceTranscriptionService
            $transcription = app(VoiceTranscriptionService::class);
            $settings = AiBrainSettings::getForUser($user->id);
            $language = $settings->preferred_language ?? null;

            $text = $transcription->transcribeFromUrl($fileUrl, 'ogg', $language);

            // Show what was transcribed
            $this->sendMessage($chatId, "🎤 _" . $text . "_");

            // Check if user requested a new conversation
            $forceNew = $this->consumeForceNewFlag($chatId);

            // Process through the Brain
            $orchestrator = app(AgentOrchestrator::class);
            $result = $orchestrator->processMessage($text, $user, 'telegram', forceNew: $forceNew);

            if ($forceNew) {
                $this->sendMessage($chatId, '🆕 ' . __('brain.telegram_new_conversation_started'));
            }

            if ($result['type'] === 'approval_request') {
                $this->sendApprovalRequest($chatId, $result);
            } else {
                $this->sendMessage($chatId, $result['message'] ?? 'Processed.', $this->getNewConversationKeyboard());
            }
        } catch (\Exception $e) {
            Log::error('Telegram voice message processing failed', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
            $this->sendMessage($chatId, '❌ Could not transcribe the voice message. Make sure OpenAI integration is configured.');
        }
    }

    /**
     * Get the download URL for a Telegram file.
     */
    protected function getFileUrl(string $fileId, string $chatId): ?string
    {
        $botToken = $this->resolveBotTokenByChatId($chatId);
        if (empty($botToken)) {
            $botToken = $this->resolveAnyBotToken();
        }

        if (empty($botToken)) {
            return null;
        }

        try {
            $response = Http::get("{$this->apiBase}{$botToken}/getFile", [
                'file_id' => $fileId,
            ]);

            if ($response->successful() && $response->json('ok')) {
                $filePath = $response->json('result.file_path');
                return "https://api.telegram.org/file/bot{$botToken}/{$filePath}";
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Telegram getFile failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Handle bot commands (/start, /connect, /mode, /help, etc.)
     */
    protected function handleCommand(string $chatId, string $text, ?string $username): void
    {
        $parts = explode(' ', trim($text));
        $command = strtolower($parts[0]);

        match ($command) {
            '/start' => $this->sendMessage($chatId, $this->getWelcomeMessage()),
            '/connect' => $this->handleConnect($chatId, $parts[1] ?? null, $username),
            '/disconnect' => $this->handleDisconnect($chatId),
            '/mode' => $this->handleMode($chatId, $parts[1] ?? null),
            '/status' => $this->handleStatus($chatId),
            '/new' => $this->handleNewConversation($chatId),
            '/help' => $this->sendMessage($chatId, $this->getHelpMessage()),
            '/knowledge' => $this->handleKnowledge($chatId, array_slice($parts, 1)),
            '/goals' => $this->handleGoals($chatId),
            '/report' => $this->handleReport($chatId),
            '/kpi' => $this->handleKpi($chatId),
            '/calendar' => $this->handleCalendar($chatId),
            default => $this->sendMessage($chatId, "Unknown command. Use /help to see available commands."),
        };
    }

    /**
     * Handle /connect command.
     */
    protected function handleConnect(string $chatId, ?string $code, ?string $username): void
    {
        if (!$code) {
            $this->sendMessage($chatId, "Use: `/connect YOUR_CODE`\n\nYou can find the code in the NetSendo panel → Settings → AI Brain.");
            return;
        }

        $user = $this->authService->linkAccount($code, $chatId, $username);

        if ($user) {
            $this->sendMessage($chatId, "✅ **Connected to NetSendo!**\n\nWelcome, {$user->name}! 🎉\n\nYou can now manage your email marketing directly from Telegram.\n\nType /help to see the available commands.");
        } else {
            $this->sendMessage($chatId, "❌ Invalid code. Check the code in the NetSendo panel and try again.");
        }
    }

    /**
     * Handle /disconnect command.
     */
    protected function handleDisconnect(string $chatId): void
    {
        $user = $this->authService->findUserByChatId($chatId);

        if ($user) {
            $this->authService->unlinkAccount($user);
            $this->sendMessage($chatId, "✅ Disconnected from NetSendo. Use /connect to reconnect.");
        } else {
            $this->sendMessage($chatId, "You are not connected to any NetSendo account.");
        }
    }

    /**
     * Handle /mode command.
     */
    protected function handleMode(string $chatId, ?string $newMode): void
    {
        $user = $this->authService->findUserByChatId($chatId);
        if (!$user) {
            $this->sendMessage($chatId, "⚠️ First connect your account: /connect YOUR_CODE");
            return;
        }

        $modeController = app(\App\Services\Brain\ModeController::class);

        if (!$newMode) {
            $currentMode = $modeController->getMode($user);
            $label = $modeController->getModeLabel($currentMode);
            $desc = $modeController->getModeDescription($currentMode);

            $this->sendMessage($chatId, "**Current mode:** {$label}\n{$desc}\n\nChange mode:\n`/mode autonomous` - full autonomy\n`/mode semi_auto` - semi-automatic\n`/mode manual` - manual");
            return;
        }

        try {
            $modeController->setMode($user, $newMode);
            $label = $modeController->getModeLabel($newMode);
            $this->sendMessage($chatId, "✅ Mode changed to: {$label}");
        } catch (\InvalidArgumentException $e) {
            $this->sendMessage($chatId, "❌ Unknown mode. Available: `autonomous`, `semi_auto`, `manual`");
        }
    }

    /**
     * Handle /status command.
     */
    protected function handleStatus(string $chatId): void
    {
        $user = $this->authService->findUserByChatId($chatId);
        if (!$user) {
            $this->sendMessage($chatId, "⚠️ Not connected.");
            return;
        }

        $settings = \App\Models\AiBrainSettings::getForUser($user->id);
        $modeController = app(\App\Services\Brain\ModeController::class);

        $status = "📊 **NetSendo Brain Status**\n\n";
        $status .= "👤 Account: {$user->name}\n";
        $status .= "🔧 Mode: {$modeController->getModeLabel($settings->work_mode)}\n";
        $status .= "🔢 Tokens today: {$settings->tokens_used_today}/{$settings->daily_token_limit}\n";

        // Pending approvals
        $pendingCount = AiPendingApproval::forUser($user->id)->pending()->count();
        if ($pendingCount > 0) {
            $status .= "\n⏳ Plans awaiting approval: {$pendingCount}";
        }

        $this->sendMessage($chatId, $status);
    }

    /**
     * Handle /knowledge command.
     */
    protected function handleKnowledge(string $chatId, array $args): void
    {
        $user = $this->authService->findUserByChatId($chatId);
        if (!$user) {
            $this->sendMessage($chatId, "⚠️ Not connected.");
            return;
        }

        $text = implode(' ', $args);
        if (empty($text)) {
            $this->sendMessage($chatId, "Use: `/knowledge Information to remember`\n\nExample: `/knowledge Our main product is an online course for 297 EUR`");
            return;
        }

        $kb = app(\App\Services\Brain\KnowledgeBaseService::class);
        $entry = $kb->addEntry($user, 'company', mb_substr($text, 0, 100), $text, 'telegram');

        $this->sendMessage($chatId, "✅ Saved to knowledge base (category: {$entry->category}).");
    }

    /**
     * Handle /goals command — list active goals with progress.
     */
    protected function handleGoals(string $chatId): void
    {
        $user = $this->authService->findUserByChatId($chatId);
        if (!$user) {
            $this->sendMessage($chatId, "⚠️ Not connected.");
            return;
        }

        $this->setUserLocale($user);

        try {
            $goals = \App\Models\AiGoal::forUser($user->id)
                ->active()
                ->orderByDesc('created_at')
                ->limit(5)
                ->get();

            if ($goals->isEmpty()) {
                $this->sendMessage($chatId, "🎯 No active goals.\n\nCreate one in the Brain Monitor or via chat.");
                return;
            }

            $lines = ["🎯 *" . __('brain.goals.active_goals') . "*\n"];
            foreach ($goals as $goal) {
                $progress = $goal->progress ?? 0;
                $bar = str_repeat('▓', (int) ($progress / 10)) . str_repeat('░', 10 - (int) ($progress / 10));
                $emoji = match ($goal->priority) {
                    'high' => '🔴',
                    'medium' => '🟡',
                    'low' => '🟢',
                    default => '⚪',
                };
                $lines[] = "{$emoji} *{$goal->title}*";
                $lines[] = "   {$bar} {$progress}%";
                if ($goal->description) {
                    $lines[] = "   ↳ " . mb_substr($goal->description, 0, 80);
                }
                $lines[] = "";
            }

            $this->sendMessage($chatId, implode("\n", $lines));
        } catch (\Exception $e) {
            $this->sendMessage($chatId, "❌ Could not load goals.");
        }
    }

    /**
     * Handle /report command — send latest weekly digest summary.
     */
    protected function handleReport(string $chatId): void
    {
        $user = $this->authService->findUserByChatId($chatId);
        if (!$user) {
            $this->sendMessage($chatId, "⚠️ Not connected.");
            return;
        }

        $this->setUserLocale($user);
        $this->sendMessage($chatId, "📊 Generating report...");

        try {
            $digestService = app(\App\Services\Brain\WeeklyDigestService::class);
            $digest = $digestService->generateDigest($user, 'week');

            if (!$digest || empty($digest['summary'])) {
                $this->sendMessage($chatId, "No report data available yet.");
                return;
            }

            $lines = ["📊 *" . __('brain.digest.title') . "*\n"];
            $lines[] = $digest['summary'];

            if (!empty($digest['recommendations'])) {
                $lines[] = "\n💡 *" . __('brain.digest.recommendations') . ":*";
                foreach (array_slice($digest['recommendations'], 0, 3) as $rec) {
                    $lines[] = "• {$rec}";
                }
            }

            $this->sendMessage($chatId, implode("\n", $lines));
        } catch (\Exception $e) {
            $this->sendMessage($chatId, "❌ Report generation failed.");
        }
    }

    /**
     * Handle /kpi command — quick KPI snapshot.
     */
    protected function handleKpi(string $chatId): void
    {
        $user = $this->authService->findUserByChatId($chatId);
        if (!$user) {
            $this->sendMessage($chatId, "⚠️ Not connected.");
            return;
        }

        $this->setUserLocale($user);

        try {
            $now = now();
            $weekAgo = $now->copy()->subWeek();
            $monthAgo = $now->copy()->subMonth();

            // Subscribers
            $total = \App\Models\Subscriber::where('user_id', $user->id)->count();
            $newThisWeek = \App\Models\Subscriber::where('user_id', $user->id)
                ->where('created_at', '>=', $weekAgo)->count();

            // Campaign metrics
            $snapshots = \App\Models\AiPerformanceSnapshot::forUser($user->id)
                ->where('captured_at', '>=', $monthAgo)->get();
            $avgOR = $snapshots->count() > 0 ? round($snapshots->avg('open_rate'), 1) : null;
            $avgCTR = $snapshots->count() > 0 ? round($snapshots->avg('click_rate'), 1) : null;

            // Brain efficiency
            $completed = \App\Models\AiActionPlan::forUser($user->id)
                ->where('status', 'completed')
                ->where('completed_at', '>=', $monthAgo)->count();
            $totalPlans = \App\Models\AiActionPlan::forUser($user->id)
                ->whereIn('status', ['completed', 'failed'])
                ->where('created_at', '>=', $monthAgo)->count();
            $efficiency = $totalPlans > 0 ? round(($completed / $totalPlans) * 100) : null;

            $lines = ["📈 *KPI Snapshot*\n"];
            $lines[] = "👥 Subscribers: *{$total}* (+{$newThisWeek} this week)";
            $lines[] = "📧 Open Rate: *" . ($avgOR !== null ? "{$avgOR}%" : '—') . "* (30d avg)";
            $lines[] = "🖱️ Click Rate: *" . ($avgCTR !== null ? "{$avgCTR}%" : '—') . "* (30d avg)";
            $lines[] = "🧠 Brain Efficiency: *" . ($efficiency !== null ? "{$efficiency}%" : '—') . "* ({$completed}/{$totalPlans} plans)";

            $this->sendMessage($chatId, implode("\n", $lines));
        } catch (\Exception $e) {
            $this->sendMessage($chatId, "❌ Could not load KPIs.");
        }
    }

    /**
     * Handle /calendar command — upcoming planned campaigns.
     */
    protected function handleCalendar(string $chatId): void
    {
        $user = $this->authService->findUserByChatId($chatId);
        if (!$user) {
            $this->sendMessage($chatId, "⚠️ Not connected.");
            return;
        }

        $this->setUserLocale($user);

        try {
            $calendarService = app(\App\Services\Brain\CampaignCalendarService::class);
            $upcoming = $calendarService->getUpcomingCampaigns($user, 7);

            if ($upcoming->isEmpty()) {
                $this->sendMessage($chatId, "📅 No planned campaigns. The Brain will generate a calendar during the next CRON cycle.");
                return;
            }

            $typeEmoji = [
                'newsletter' => '📰',
                'promotion' => '🏷️',
                'nurturing' => '🌱',
                'win_back' => '🔄',
                'announcement' => '📢',
            ];

            $lines = ["📅 *" . __('brain.calendar.upcoming') . "*\n"];
            foreach ($upcoming as $entry) {
                $day = $entry->planned_date->format('D m/d');
                $emoji = $typeEmoji[$entry->campaign_type] ?? '📧';
                $status = strtoupper($entry->status);
                $lines[] = "{$emoji} *{$day}* — {$entry->topic}";
                $lines[] = "   ↳ {$entry->campaign_type} | {$entry->target_audience} | [{$status}]";
            }

            $this->sendMessage($chatId, implode("\n", $lines));
        } catch (\Exception $e) {
            $this->sendMessage($chatId, "❌ Could not load calendar.");
        }
    }

    /**
     * Handle callback query (inline keyboard buttons).
     */
    protected function handleCallbackQuery(array $callbackQuery): void
    {
        $chatId = (string) $callbackQuery['message']['chat']['id'];
        $data = $callbackQuery['data'] ?? '';
        $callbackId = $callbackQuery['id'];

        // Answer the callback query first
        $this->answerCallbackQuery($callbackId, $chatId);

        // Handle "new conversation" button
        if ($data === 'new_conversation') {
            $this->handleNewConversation($chatId);
            return;
        }

        // Parse callback data: e.g., "approve:123", "reject:123", "approve_goal:123", "reject_goal:123"
        $parts = explode(':', $data);
        $action = $parts[0] ?? '';
        $approvalId = (int) ($parts[1] ?? 0);

        if (!$approvalId) {
            return;
        }

        $user = $this->authService->findUserByChatId($chatId);
        if (!$user) {
            return;
        }

        $this->setUserLocale($user);

        // Handle goal proposals
        if ($action === 'approve_goal' || $action === 'reject_goal') {
            $this->handleGoalApproval($chatId, $user, $approvalId, $action === 'approve_goal');
            return;
        }

        try {
            $modeController = app(\App\Services\Brain\ModeController::class);

            if ($action === 'approve') {
                $approval = $modeController->processApproval($approvalId, true);
                $this->sendMessage($chatId, "✅ " . __('brain.plan_queued'));

                // Queue the execution (Brain 2.0 Phase 1) — the job sends
                // the result back to this chat when it finishes
                if ($approval->plan) {
                    \App\Jobs\ExecuteBrainPlanJob::dispatch($approval->plan->id, $user->id, 'telegram');
                }

            } elseif ($action === 'reject') {
                $modeController->processApproval($approvalId, false, 'Rejected via Telegram');
                $this->sendMessage($chatId, "❌ Plan rejected.");
            }
        } catch (\Exception $e) {
            $this->sendMessage($chatId, "❌ Error: {$e->getMessage()}");
        }
    }

    /**
     * Handle goal proposal approval/rejection from Telegram.
     */
    protected function handleGoalApproval(string $chatId, User $user, int $approvalId, bool $approved): void
    {
        try {
            $approval = AiPendingApproval::where('user_id', $user->id)
                ->where('id', $approvalId)
                ->where('status', 'pending')
                ->first();

            if (!$approval) {
                $this->sendMessage($chatId, "⚠️ " . __('brain.goal_expired'));
                return;
            }

            $goalData = json_decode($approval->summary, true);

            if (!$goalData || ($goalData['type'] ?? '') !== 'goal_proposal') {
                $this->sendMessage($chatId, "⚠️ " . __('brain.goal_invalid'));
                return;
            }

            if ($approved) {
                // Create the goal
                $goalPlanner = app(\App\Services\Brain\GoalPlanner::class);
                $goal = $goalPlanner->createGoal(
                    $user,
                    $goalData['title'],
                    $goalData['description'] ?? null,
                    $goalData['priority'] ?? 'medium',
                );

                $approval->update(['status' => 'approved']);

                $this->sendMessage($chatId, "✅ " . __('brain.goal_approved', [
                    'title' => $goal->title,
                    'id' => $goal->id,
                ]));
            } else {
                $approval->update(['status' => 'rejected']);
                $this->sendMessage($chatId, "❌ " . __('brain.goal_rejected', [
                    'title' => $goalData['title'],
                ]));
            }
        } catch (\Exception $e) {
            Log::error('Goal approval failed', ['error' => $e->getMessage()]);
            $this->sendMessage($chatId, "❌ Error: {$e->getMessage()}");
        }
    }

    /**
     * Send an approval request with inline keyboard.
     */
    protected function sendApprovalRequest(string $chatId, array $result): void
    {
        $approvalId = $result['approval_id'] ?? 0;

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '✅ Approve', 'callback_data' => "approve:{$approvalId}"],
                    ['text' => '❌ Reject', 'callback_data' => "reject:{$approvalId}"],
                ],
            ],
        ];

        $this->sendMessage($chatId, $result['message'] ?? 'Plan awaiting approval:', $keyboard);
    }

    /**
     * Send a text message to a Telegram chat.
     */
    public function sendMessage(string $chatId, string $text, ?array $replyMarkup = null): ?array
    {
        $botToken = $this->resolveBotTokenByChatId($chatId);

        // Fallback: if no token found by chat_id (user not linked yet),
        // try to find any configured token (self-hosted single-instance)
        if (empty($botToken)) {
            $botToken = $this->resolveAnyBotToken();
        }

        if (empty($botToken)) {
            Log::warning('Telegram bot token not configured');
            return null;
        }

        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'disable_web_page_preview' => true,
        ];

        if ($replyMarkup) {
            $payload['reply_markup'] = json_encode($replyMarkup);
        }

        try {
            $response = Http::post("{$this->apiBase}{$botToken}/sendMessage", $payload);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Telegram sendMessage failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return null;
        } catch (\Exception $e) {
            Log::error('Telegram API error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Answer a callback query.
     */
    protected function answerCallbackQuery(string $callbackId, string $chatId): void
    {
        $botToken = $this->resolveBotTokenByChatId($chatId);
        if (empty($botToken)) return;

        Http::post("{$this->apiBase}{$botToken}/answerCallbackQuery", [
            'callback_query_id' => $callbackId,
        ]);
    }

    /**
     * Set the webhook URL for the Telegram bot.
     */
    public function setWebhook(string $url, ?User $user = null): array
    {
        $botToken = $this->resolveBotToken($user);

        if (empty($botToken)) {
            return ['ok' => false, 'description' => 'Bot token not configured'];
        }

        $response = Http::post("{$this->apiBase}{$botToken}/setWebhook", [
            'url' => $url,
            'allowed_updates' => ['message', 'callback_query'],
        ]);

        return $response->json();
    }

    // === Conversation Management ===

    /**
     * Handle /new command or "New conversation" button.
     */
    protected function handleNewConversation(string $chatId): void
    {
        $user = $this->authService->findUserByChatId($chatId);
        if (!$user) {
            $this->sendMessage($chatId, "⚠️ First connect your account: /connect YOUR_CODE");
            return;
        }

        $this->setUserLocale($user);
        Cache::put("telegram_force_new:{$chatId}", true, now()->addHour());
        $this->sendMessage($chatId, '✅ ' . __('brain.telegram_new_conversation_confirm'));
    }

    /**
     * Check and consume the force-new-conversation flag.
     */
    protected function consumeForceNewFlag(string $chatId): bool
    {
        $key = "telegram_force_new:{$chatId}";
        if (Cache::pull($key)) {
            return true;
        }
        return false;
    }

    /**
     * Build the inline keyboard with "New conversation" button.
     */
    protected function getNewConversationKeyboard(): array
    {
        return [
            'inline_keyboard' => [
                [
                    ['text' => '🆕 ' . __('brain.telegram_new_conversation_btn'), 'callback_data' => 'new_conversation'],
                ],
            ],
        ];
    }

    // === Locale Helper ===

    /**
     * Set the application locale based on the user's Brain language preference.
     * Telegram webhooks don't go through SetLocale middleware, so we need to set it manually.
     */
    protected function setUserLocale(User $user): void
    {
        $settings = AiBrainSettings::getForUser($user->id);
        $langCode = $settings->resolveLanguage($user);
        if ($langCode) {
            App::setLocale($langCode);
        }
    }

    // === Message Templates ===

    protected function getWelcomeMessage(): string
    {
        return <<<MSG
🧠 **Welcome to NetSendo Brain!**

I am your AI assistant for email marketing.

I can help you:
📧 Create and manage email/SMS campaigns
📋 Manage contact lists
✉️ Generate message content
📊 Analyze results

**To get started**, link your NetSendo account:
`/connect YOUR_CODE`

You can find the code in the NetSendo panel → Settings → AI Brain → Telegram.
MSG;
    }

    protected function getHelpMessage(): string
    {
        return <<<MSG
📖 **NetSendo Brain Commands:**

🔗 `/connect CODE` — Link NetSendo account
🔌 `/disconnect` — Unlink account
🔧 `/mode [mode]` — Change work mode
📊 `/status` — Account status and tokens
🆕 `/new` — Start a new conversation
📝 `/knowledge [text]` — Add to knowledge base
🎯 `/goals` — View active goals with progress
📈 `/kpi` — Quick KPI snapshot
📊 `/report` — Weekly performance report
📅 `/calendar` — Upcoming planned campaigns
❓ `/help` — This help

**Work modes:**
🤖 `autonomous` — AI does everything automatically
🤝 `semi_auto` — AI proposes, you approve
👤 `manual` — AI advises, you execute

**Example commands:**
• "Create a welcome campaign"
• "Show my lists"
• "Write a newsletter about a new product"
• "Clean bounced from the main list"
MSG;
    }
}
