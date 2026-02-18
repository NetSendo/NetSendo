<?php

namespace App\Services\Brain\Telegram;

use App\Models\AiBrainSettings;
use App\Models\AiPendingApproval;
use App\Models\User;
use App\Services\Brain\AgentOrchestrator;
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
            $this->sendMessage($chatId, "⚠️ Twoje konto Telegram nie jest połączone z NetSendo.\n\nUżyj `/connect TWÓJ_KOD` aby połączyć konto.\nKod znajdziesz w panelu NetSendo → Ustawienia → AI Brain → Telegram.");
            return;
        }

        // Process through the Brain
        try {
            $orchestrator = app(AgentOrchestrator::class);
            $result = $orchestrator->processMessage($text, $user, 'telegram');

            if ($result['type'] === 'approval_request') {
                $this->sendApprovalRequest($chatId, $result);
            } else {
                $this->sendMessage($chatId, $result['message'] ?? 'Przetworzono.');
            }
        } catch (\Exception $e) {
            Log::error('Telegram message processing failed', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
            $this->sendMessage($chatId, '❌ Wystąpił błąd. Spróbuj ponownie.');
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
            '/help' => $this->sendMessage($chatId, $this->getHelpMessage()),
            '/knowledge' => $this->handleKnowledge($chatId, array_slice($parts, 1)),
            default => $this->sendMessage($chatId, "Nieznana komenda. Użyj /help aby zobaczyć dostępne komendy."),
        };
    }

    /**
     * Handle /connect command.
     */
    protected function handleConnect(string $chatId, ?string $code, ?string $username): void
    {
        if (!$code) {
            $this->sendMessage($chatId, "Użyj: `/connect TWÓJ_KOD`\n\nKod znajdziesz w panelu NetSendo → Ustawienia → AI Brain.");
            return;
        }

        $user = $this->authService->linkAccount($code, $chatId, $username);

        if ($user) {
            $this->sendMessage($chatId, "✅ **Połączono z NetSendo!**\n\nWitaj, {$user->name}! 🎉\n\nTeraz możesz zarządzać swoim email marketingiem bezpośrednio z Telegrama.\n\nWpisz /help aby zobaczyć możliwości.");
        } else {
            $this->sendMessage($chatId, "❌ Nieprawidłowy kod. Sprawdź kod w panelu NetSendo i spróbuj ponownie.");
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
            $this->sendMessage($chatId, "✅ Odłączono od NetSendo. Użyj /connect aby połączyć ponownie.");
        } else {
            $this->sendMessage($chatId, "Nie jesteś połączony z żadnym kontem NetSendo.");
        }
    }

    /**
     * Handle /mode command.
     */
    protected function handleMode(string $chatId, ?string $newMode): void
    {
        $user = $this->authService->findUserByChatId($chatId);
        if (!$user) {
            $this->sendMessage($chatId, "⚠️ Najpierw połącz konto: /connect TWÓJ_KOD");
            return;
        }

        $modeController = app(\App\Services\Brain\ModeController::class);

        if (!$newMode) {
            $currentMode = $modeController->getMode($user);
            $label = $modeController->getModeLabel($currentMode);
            $desc = $modeController->getModeDescription($currentMode);

            $this->sendMessage($chatId, "**Aktualny tryb:** {$label}\n{$desc}\n\nZmień tryb:\n`/mode autonomous` - pełna autonomiczność\n`/mode semi_auto` - półautomat\n`/mode manual` - manualny");
            return;
        }

        try {
            $modeController->setMode($user, $newMode);
            $label = $modeController->getModeLabel($newMode);
            $this->sendMessage($chatId, "✅ Tryb zmieniony na: {$label}");
        } catch (\InvalidArgumentException $e) {
            $this->sendMessage($chatId, "❌ Nieznany tryb. Dostępne: `autonomous`, `semi_auto`, `manual`");
        }
    }

    /**
     * Handle /status command.
     */
    protected function handleStatus(string $chatId): void
    {
        $user = $this->authService->findUserByChatId($chatId);
        if (!$user) {
            $this->sendMessage($chatId, "⚠️ Nie połączono.");
            return;
        }

        $settings = \App\Models\AiBrainSettings::getForUser($user->id);
        $modeController = app(\App\Services\Brain\ModeController::class);

        $status = "📊 **Status NetSendo Brain**\n\n";
        $status .= "👤 Konto: {$user->name}\n";
        $status .= "🔧 Tryb: {$modeController->getModeLabel($settings->work_mode)}\n";
        $status .= "🔢 Tokeny dziś: {$settings->tokens_used_today}/{$settings->daily_token_limit}\n";

        // Pending approvals
        $pendingCount = AiPendingApproval::forUser($user->id)->pending()->count();
        if ($pendingCount > 0) {
            $status .= "\n⏳ Plany czekające na zatwierdzenie: {$pendingCount}";
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
            $this->sendMessage($chatId, "⚠️ Nie połączono.");
            return;
        }

        $text = implode(' ', $args);
        if (empty($text)) {
            $this->sendMessage($chatId, "Użyj: `/knowledge Treść informacji do zapamiętania`\n\nPrzykład: `/knowledge Nasz główny produkt to kurs online za 297 zł`");
            return;
        }

        $kb = app(\App\Services\Brain\KnowledgeBaseService::class);
        $entry = $kb->addEntry($user, 'company', mb_substr($text, 0, 100), $text, 'telegram');

        $this->sendMessage($chatId, "✅ Zapisano w bazie wiedzy (kategoria: {$entry->category}).");
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

        // Parse callback data: e.g., "approve:123" or "reject:123"
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

        try {
            $modeController = app(\App\Services\Brain\ModeController::class);

            if ($action === 'approve') {
                $approval = $modeController->processApproval($approvalId, true);
                $this->sendMessage($chatId, "✅ Plan zaakceptowany! Rozpoczynam wykonanie...");

                // Execute the plan
                $orchestrator = app(AgentOrchestrator::class);
                $result = $orchestrator->executePlan($approval->plan, $user);
                $this->sendMessage($chatId, $result['message'] ?? 'Plan wykonany.');

            } elseif ($action === 'reject') {
                $modeController->processApproval($approvalId, false, 'Odrzucono przez Telegram');
                $this->sendMessage($chatId, "❌ Plan odrzucony.");
            }
        } catch (\Exception $e) {
            $this->sendMessage($chatId, "❌ Błąd: {$e->getMessage()}");
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
                    ['text' => '✅ Zaakceptuj', 'callback_data' => "approve:{$approvalId}"],
                    ['text' => '❌ Odrzuć', 'callback_data' => "reject:{$approvalId}"],
                ],
            ],
        ];

        $this->sendMessage($chatId, $result['message'] ?? 'Plan do zatwierdzenia:', $keyboard);
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

    // === Message Templates ===

    protected function getWelcomeMessage(): string
    {
        return <<<MSG
🧠 **Witaj w NetSendo Brain!**

Jestem Twoim asystentem AI do email marketingu.

Mogę pomóc Ci:
📧 Tworzyć i zarządzać kampaniami email/SMS
📋 Zarządzać listami kontaktów
✉️ Generować treści wiadomości
📊 Analizować wyniki

**Aby rozpocząć**, połącz swoje konto NetSendo:
`/connect TWÓJ_KOD`

Kod znajdziesz w panelu NetSendo → Ustawienia → AI Brain → Telegram.
MSG;
    }

    protected function getHelpMessage(): string
    {
        return <<<MSG
📖 **Komendy NetSendo Brain:**

🔗 `/connect KOD` — Połącz konto NetSendo
🔌 `/disconnect` — Odłącz konto
🔧 `/mode [tryb]` — Zmień tryb pracy
📊 `/status` — Status konta i tokeny
📝 `/knowledge [tekst]` — Dodaj do bazy wiedzy
❓ `/help` — Ta pomoc

**Tryby pracy:**
🤖 `autonomous` — AI robi wszystko sam
🤝 `semi_auto` — AI proponuje, Ty zatwierdzasz
👤 `manual` — AI doradza, Ty robisz

**Przykłady poleceń:**
• "Stwórz kampanię powitalną"
• "Pokaż moje listy"
• "Napisz newsletter o nowym produkcie"
• "Wyczyść bounced z listy głównej"
MSG;
    }
}
