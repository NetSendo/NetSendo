<?php

namespace App\Services\Brain;

use App\Models\AiBrainSettings;
use App\Models\AiConversation;
use App\Models\AiConversationMessage;
use App\Models\User;
use Illuminate\Support\Collection;

class ConversationManager
{
    /**
     * Get or create an active conversation for a user and channel.
     */
    public function getConversation(User $user, string $channel = 'web'): AiConversation
    {
        return AiConversation::getOrCreateActive($user->id, $channel);
    }

    /**
     * Get a specific conversation by ID (for resuming existing conversations).
     */
    public function getConversationById(int $conversationId, int $userId): ?AiConversation
    {
        return AiConversation::forUser($userId)->find($conversationId);
    }

    /**
     * Create a brand-new conversation (for "New Conversation" button).
     */
    public function createNewConversation(User $user, string $channel = 'web'): AiConversation
    {
        return AiConversation::create([
            'user_id' => $user->id,
            'channel' => $channel,
            'status' => 'active',
            'last_activity_at' => now(),
        ]);
    }

    /**
     * Add a user message to the conversation.
     */
    public function addUserMessage(AiConversation $conversation, string $content): AiConversationMessage
    {
        return $conversation->addMessage('user', $content);
    }

    /**
     * Add an assistant (AI) message to the conversation.
     */
    public function addAssistantMessage(
        AiConversation $conversation,
        string $content,
        array $metadata = [],
        int $tokensIn = 0,
        int $tokensOut = 0,
        ?string $model = null,
    ): AiConversationMessage {
        $message = $conversation->addMessage('assistant', $content, $metadata);

        $message->update([
            'tokens_input' => $tokensIn,
            'tokens_output' => $tokensOut,
            'model_used' => $model,
        ]);

        // Update conversation totals
        $conversation->increment('total_tokens', $tokensIn + $tokensOut);

        return $message;
    }

    /**
     * Add a system message to the conversation.
     */
    public function addSystemMessage(AiConversation $conversation, string $content): AiConversationMessage
    {
        return $conversation->addMessage('system', $content);
    }

    /**
     * Build the system prompt with user context.
     */
    public function buildSystemPrompt(User $user, string $knowledgeContext = ''): string
    {
        // Resolve user's timezone
        $userTimezone = $user->timezone ?? config('app.timezone', 'UTC');
        $now = now()->timezone($userTimezone);
        $dateTimeContext = sprintf(
            "CURRENT DATE AND TIME: %s (%s) | Timezone: %s",
            $now->format('l, j F Y, H:i'),
            $now->format('Y-m-d H:i:s'),
            $userTimezone
        );

        // Resolve preferred response language
        $settings = AiBrainSettings::getForUser($user->id);
        $langCode = $settings->resolveLanguage($user);
        $languageName = AiBrainSettings::getLanguageName($langCode);

        $prompt = <<<PROMPT
{$dateTimeContext}

You are NetSendo Brain — a professional AI assistant specializing in email marketing, SMS marketing, CRM, and marketing automation.

YOUR ROLE:
You are the user's strategic marketing partner. Your goal is to help build effective marketing campaigns,
manage customer relationships, and optimize marketing efforts. You act as an experienced marketer and business advisor.

YOUR COMPETENCIES:
• Creating and managing email and SMS campaigns (segmentation, scheduling, content)
• Managing contact lists, subscribers, and their segmentation
• Generating effective marketing content (email subjects, HTML content, SMS, CTAs)
• Analyzing campaign results (open rate, click rate, conversions, trends)
• Lead scoring and sales funnel management (CRM pipeline)
• Marketing automation (rules, triggers, workflows)
• Marketing strategy planning and consulting
• Managing CRM contacts, companies, deals, and tasks

LANGUAGE RULES:
1. ALWAYS respond in {$languageName}. This is the user's preferred language — use it for ALL responses.
2. If the user writes in a different language, switch to that language for the duration of the conversation.
3. Use natural, professional tone appropriate for the language.

WORK PRINCIPLES:
1. Be specific, proactive, and results-oriented — propose solutions, don't just describe problems
2. When the user requests an action, create a plan with concrete, actionable steps
3. Always explain what you intend to do before doing it — build trust
4. Provide numerical data and KPIs when available — rely on data, not guesses
5. Use emoji for better readability (especially on Telegram)
6. Suggest email marketing best practices (optimal send time, A/B testing, personalization)
7. When you have access to the user's knowledge base — ALWAYS use it to personalize responses
8. Warn about potential issues (spam score, low deliverability, excessive frequency)
9. Ensure GDPR compliance in recommendations regarding personal data
PROMPT;

        // Inject operational context (cron, telegram, work mode, agents)
        $prompt .= $this->buildOperationalContext($user);

        if ($knowledgeContext) {
            $prompt .= "\n\nUSER'S KNOWLEDGE BASE:\n{$knowledgeContext}";
        }

        return $prompt;
    }

    /**
     * Build operational context block — cron, telegram, work mode, agents.
     */
    private function buildOperationalContext(User $user): string
    {
        $settings = AiBrainSettings::getForUser($user->id);
        $userTimezone = $user->timezone ?? config('app.timezone', 'UTC');
        $sections = [];

        // --- Work mode ---
        $modeLabels = [
            'autonomous' => 'Autonomous — you execute tasks independently without asking',
            'semi_auto' => 'Semi-automatic — you propose plans, wait for user approval',
            'manual' => 'Manual — you only advise, do not execute any actions',
        ];
        $modeLabel = $modeLabels[$settings->work_mode] ?? $modeLabels['semi_auto'];
        $sections[] = "YOUR WORK MODE: {$settings->work_mode} — {$modeLabel}";

        // --- CRON awareness ---
        if ($settings->cron_enabled && $settings->cron_interval_minutes) {
            $interval = (int) $settings->cron_interval_minutes;

            $lastRun = $settings->last_cron_run_at
                ? $settings->last_cron_run_at->timezone($userTimezone)->format('Y-m-d H:i')
                : 'never';

            $nextRun = $settings->last_cron_run_at
                ? $settings->last_cron_run_at->addMinutes($interval)->timezone($userTimezone)->format('H:i')
                : 'soon';

            $cronBlock = <<<CRON

AUTONOMOUS WORK (CRON ACTIVE):
Automatic scheduling is enabled! You work autonomously every {$interval} minutes.
• Last run: {$lastRun}
• Next run: ~{$nextRun}
• In each cycle you automatically check suggested tasks and execute high-priority ones.

WHAT THIS MEANS FOR YOU:
- You CAN schedule tasks for future cycles — they will be executed automatically
- You CAN spread work over time (e.g. "prepare content now, send campaign in next cycle")
- You CAN assign tasks to sub-agents who report back to you
- DO NOT say "I can't work in the background" — you work autonomously every {$interval} minutes!
- When the user asks about scheduling: inform them about your active schedule and propose a concrete plan
CRON;
            $sections[] = $cronBlock;
        }

        // --- Sub-agents ---
        $sections[] = <<<AGENTS

YOUR SUB-AGENTS (you can delegate tasks to them):
• campaign — planning and creating email/SMS campaigns, scheduling, A/B tests
• list — managing contact lists, import/export, segments
• message — creating message content, HTML templates, copywriting
• crm — managing contacts, companies, deals, CRM tasks
• analytics — analyzing campaign results, reports, KPIs, trends
• segmentation — audience segmentation, scoring, target groups
• research — internet research, competitor analysis, market trends, company intelligence

Each sub-agent reports results back to you. You can create multi-step plans with task division across agents.
AGENTS;

        // --- Research capabilities ---
        if ($settings->isResearchEnabled()) {
            $researchApis = [];
            if ($settings->isPerplexityConfigured()) {
                $researchApis[] = 'Perplexity AI (deep research with citations)';
            }
            if ($settings->isSerpApiConfigured()) {
                $researchApis[] = 'SerpAPI (Google Search, news, company data)';
            }
            $apiList = implode(', ', $researchApis);

            $sections[] = <<<RESEARCH

INTERNET RESEARCH (ACTIVE ✅):
You have access to real-time internet research via: {$apiList}
• When the user asks about competitors, markets, or external topics — use the 'research' agent
• You can research companies, trends, and content ideas with verified sources
• Research results include citations and can be saved to the Knowledge Base
• DO NOT make up external facts — use your research tools for current information
RESEARCH;
        }

        // --- Telegram ---
        if ($settings->isTelegramConnected()) {
            $sections[] = <<<TELEGRAM_BLOCK

TELEGRAM (CONNECTED ✅):
The user has Telegram connected. After executing automated tasks (CRON), results are reported via Telegram.
• When planning tasks — inform the user that results will be sent to Telegram
• When the user asks about notifications — confirm that reports go to Telegram automatically
TELEGRAM_BLOCK;
        }

        return "\n\n" . implode("\n", $sections);
    }

    /**
     * Build messages array for AI API call (with system prompt).
     */
    public function buildAiPayload(
        AiConversation $conversation,
        User $user,
        string $knowledgeContext = '',
        int $historyLimit = 20,
    ): array {
        $messages = [];

        // System prompt
        $messages[] = [
            'role' => 'system',
            'content' => $this->buildSystemPrompt($user, $knowledgeContext),
        ];

        // Conversation history
        $history = $conversation->buildAiMessages($historyLimit);
        $messages = array_merge($messages, $history);

        return $messages;
    }

    /**
     * Get recent conversations for a user.
     */
    public function getRecentConversations(User $user, int $limit = 10): Collection
    {
        return AiConversation::forUser($user->id)
            ->orderByDesc('last_activity_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Archive old conversations.
     */
    public function archiveOldConversations(User $user, int $daysOld = 30): int
    {
        return AiConversation::forUser($user->id)
            ->active()
            ->where('last_activity_at', '<', now()->subDays($daysOld))
            ->update(['status' => 'archived']);
    }

    /**
     * Set title for a conversation (can be AI-generated).
     */
    public function setTitle(AiConversation $conversation, string $title): void
    {
        $conversation->update(['title' => $title]);
    }
}
