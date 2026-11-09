<?php

namespace App\Models;

use App\Licensing\Feature;
use App\Services\LicenseService;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiBrainSettings extends Model
{
    /** Whether the licence includes Brain autonomy, read once per settings instance. */
    private ?bool $autonomyLicence = null;

    /**
     * Brain working without approval (semi-automatic and autonomous modes, cron, goals,
     * digest, Telegram, research, voice) is GOLD; the chat in manual mode is SILVER.
     */
    public function autonomyLicensed(): bool
    {
        return $this->autonomyLicence ??= app(LicenseService::class)->allows(Feature::BrainAutonomy);
    }

    /**
     * The work mode in effect: without GOLD Brain works in manual mode whatever is stored;
     * the stored choice applies again once GOLD is active.
     */
    protected function workMode(): Attribute
    {
        return Attribute::get(fn ($value) => $this->autonomyLicensed() ? $value : 'manual');
    }

    protected $fillable = [
        'user_id',
        'work_mode',
        'telegram_chat_id',
        'telegram_username',
        'telegram_link_code',
        'telegram_linked_at',
        'telegram_bot_token',
        'perplexity_api_key',
        'serpapi_api_key',
        'preferred_language',
        'preferred_model',
        'preferred_integration_id',
        'embedding_integration_id',
        'embedding_model',
        'model_routing',
        'preferences',
        'agent_permissions',
        'strategy_settings',
        'agent_modes',
        'daily_token_limit',
        'tokens_used_today',
        'token_reset_date',
        'is_active',
        'cron_enabled',
        'cron_interval_minutes',
        'cron_max_tasks',
        'last_cron_run_at',
        'last_activity_at',
    ];

    protected $casts = [
        'preferences' => 'array',
        'agent_permissions' => 'array',
        'strategy_settings' => 'array',
        'agent_modes' => 'array',
        'model_routing' => 'array',
        'telegram_linked_at' => 'datetime',
        'telegram_bot_token' => 'encrypted',
        'perplexity_api_key' => 'encrypted',
        'serpapi_api_key' => 'encrypted',
        'token_reset_date' => 'date',
        'is_active' => 'boolean',
        'daily_token_limit' => 'integer',
        'embedding_integration_id' => 'integer',
        'tokens_used_today' => 'integer',
        'cron_enabled' => 'boolean',
        'cron_interval_minutes' => 'integer',
        'cron_max_tasks' => 'integer',
        'last_cron_run_at' => 'datetime',
        'last_activity_at' => 'datetime',
    ];

    /**
     * Default strategy settings per agent type.
     */
    public const DEFAULT_STRATEGY = [
        'campaign' => [
            'tone' => 'professional',
            'max_sends_per_week' => 5,
            'preferred_topics' => [],
            'excluded_days' => [],
            'preferred_send_hours' => ['start' => 9, 'end' => 17],
            'max_audience_per_campaign' => 0,
            'require_ab_test' => false,
            'goal_focus' => 'engagement',
        ],
        'crm' => [
            'auto_follow_up' => true,
            'follow_up_delay_hours' => 24,
            'min_score_for_action' => 30,
        ],
        'analytics' => [
            'report_frequency' => 'weekly',
            'include_recommendations' => true,
        ],
        'segmentation' => [
            'auto_tag' => true,
            'min_segment_size' => 10,
        ],
        'message' => [
            'tone' => 'friendly',
            'max_length_chars' => 0,
            'always_include_cta' => true,
        ],
    ];

    /**
     * Available agent types for per-agent configuration.
     */
    public const AGENT_TYPES = [
        'campaign' => '📧 Campaign',
        'message' => '✉️ Message / Content',
        'crm' => '👥 CRM',
        'analytics' => '📊 Analytics',
        'segmentation' => '🎯 Segmentation',
        'list' => '📋 List Management',
        'research' => '🔍 Research',
        'revenue' => '💰 Revenue',
        'funnel' => '🌀 Funnels',
        'deliverability' => '📮 Deliverability',
    ];

    /**
     * Available task types for model routing.
     */
    public const MODEL_ROUTING_TASKS = [
        'orchestration' => '🧠 Orkiestracja (klasyfikacja intencji)',
        'content_generation' => '✉️ Generowanie treści email/SMS',
        'analytics' => '📊 Analiza i raporty',
        'campaign' => '📧 Planowanie kampanii',
        'crm' => '👥 CRM',
        'segmentation' => '🎯 Segmentacja',
        'research' => '🔍 Internet Research',
        'conversation' => '💬 Konwersacja ogólna',
    ];

    /**
     * Get model config for a specific task type.
     * Returns ['integration_id' => int|null, 'model' => string|null] or null.
     */
    public function getModelForTask(string $task): ?array
    {
        $routing = $this->model_routing ?? [];
        $config = $routing[$task] ?? null;
        if (!$config || (empty($config['integration_id']) && empty($config['model']))) {
            return null;
        }
        return [
            'integration_id' => $config['integration_id'] ?? null,
            'model' => $config['model'] ?? null,
        ];
    }

    /**
     * The integration and model Brain uses for a kind of work: the model assigned to the task in
     * the advanced settings, else the Brain model (settings → AI model), else the first usable
     * integration with its default model. Every Brain service asks here, so the choice in the
     * settings decides — not whichever integration happens to come first in the database.
     *
     * @param  string  $task  a key of MODEL_ROUTING_TASKS
     * @return array{0: ?AiIntegration, 1: ?string}  the integration and the model (null = its default)
     */
    public function aiFor(string $task): array
    {
        $routed = $this->getModelForTask($task);

        if (!empty($routed['integration_id'])) {
            $integration = AiIntegration::find($routed['integration_id']);
            if ($integration?->isUsable()) {
                return [$integration, $routed['model'] ?: null];
            }
        }

        $integration = null;
        $model = null;
        if ($this->preferred_integration_id) {
            $preferred = AiIntegration::find($this->preferred_integration_id);
            if ($preferred?->isUsable()) {
                $integration = $preferred;
                $model = $this->preferred_model ?: null;
            }
        }

        $integration ??= app(\App\Services\AI\AiService::class)->getDefaultIntegration();

        // A task with a model but no integration uses that model on the Brain integration
        if ($integration && !empty($routed['model'])) {
            $model = $routed['model'];
        }

        return [$integration, $model];
    }

    /**
     * The model to retry with after the model assigned to a task failed (quota, outage, revoked
     * key): the Brain model, else the first usable integration. Null when that is the model
     * that just failed.
     *
     * @return array{0: AiIntegration, 1: ?string}|null
     */
    public function fallbackAi(?AiIntegration $failed, ?string $failedModel): ?array
    {
        $integration = null;
        $model = null;

        if ($this->preferred_integration_id) {
            $preferred = AiIntegration::find($this->preferred_integration_id);
            if ($preferred?->isUsable()) {
                $integration = $preferred;
                $model = $this->preferred_model ?: null;
            }
        }

        $integration ??= app(\App\Services\AI\AiService::class)->getDefaultIntegration();

        if (!$integration) {
            return null;
        }

        $sameIntegration = $failed && $failed->id === $integration->id;
        $sameModel = ($failedModel ?: $failed?->default_model) === ($model ?: $integration->default_model);
        if ($sameIntegration && $sameModel) {
            return null;
        }

        return [$integration, $model];
    }

    /**
     * Get the Telegram bot token (from DB or fallback to config).
     */
    public function getBotToken(): ?string
    {
        return $this->telegram_bot_token ?: config('services.telegram.bot_token');
    }

    /**
     * Check if Telegram bot is configured.
     */
    public function isTelegramBotConfigured(): bool
    {
        return !empty($this->getBotToken());
    }

    /**
     * Get the user that owns these settings.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if Telegram is connected.
     */
    public function isTelegramConnected(): bool
    {
        return !empty($this->telegram_chat_id) && !empty($this->telegram_linked_at);
    }

    /**
     * Check if Perplexity API is configured.
     */
    public function isPerplexityConfigured(): bool
    {
        return !empty($this->perplexity_api_key);
    }

    /**
     * Check if SerpAPI is configured.
     */
    public function isSerpApiConfigured(): bool
    {
        return !empty($this->serpapi_api_key);
    }

    /**
     * Check if any research API is configured.
     */
    public function isResearchEnabled(): bool
    {
        // Web research is part of Brain autonomy (GOLD)
        return $this->autonomyLicensed() && ($this->isPerplexityConfigured() || $this->isSerpApiConfigured());
    }

    /**
     * Check if the daily token limit is reached.
     */
    public function isTokenLimitReached(): bool
    {
        return $this->tokensUsedToday() >= $this->daily_token_limit;
    }

    /**
     * Tokens charged today. The stored counter is reset only when tokens are charged, so a
     * count left from an earlier day is no longer today's — without this a day that ended
     * over the limit blocked Brain for good (blocked calls charge nothing, so nothing reset it).
     */
    public function tokensUsedToday(): int
    {
        return $this->countsForToday() ? (int) $this->tokens_used_today : 0;
    }

    /**
     * Add tokens used and check if reset is needed.
     */
    public function addTokensUsed(int $tokens): void
    {
        if (!$this->countsForToday()) {
            $this->update([
                'tokens_used_today' => $tokens,
                'token_reset_date' => today(),
            ]);
        } else {
            $this->increment('tokens_used_today', $tokens);
        }
    }

    /** Whether the stored token counter was started today. */
    private function countsForToday(): bool
    {
        return $this->token_reset_date !== null && !$this->token_reset_date->lt(today());
    }

    /**
     * Get a numeric Brain threshold: preferences override → config default.
     * Keys mirror config/brain.php (e.g. 'hot_lead_score').
     */
    public function getThreshold(string $key, float $default = 0): float
    {
        $prefValue = ($this->preferences ?? [])[$key] ?? null;
        if (is_numeric($prefValue)) {
            return (float) $prefValue;
        }

        return (float) config("brain.{$key}", $default);
    }

    /**
     * Hot-lead score threshold for this user.
     */
    public static function hotLeadScore(int $userId): int
    {
        try {
            return (int) static::getForUser($userId)->getThreshold('hot_lead_score', 50);
        } catch (\Exception $e) {
            return (int) config('brain.hot_lead_score', 50);
        }
    }

    /**
     * Check if an agent is allowed to auto-execute.
     */
    public function isAgentAllowed(string $agentType): bool
    {
        $mode = $this->getAgentMode($agentType);
        if ($mode === 'manual') {
            return false;
        }

        $permissions = $this->agent_permissions ?? [];
        return $permissions[$agentType] ?? true; // default: allowed
    }

    /**
     * Get strategy settings for a specific agent type.
     * Returns merged defaults + user overrides.
     */
    public function getStrategyForAgent(string $agentType): array
    {
        $defaults = self::DEFAULT_STRATEGY[$agentType] ?? [];
        $userSettings = ($this->strategy_settings ?? [])[$agentType] ?? [];

        return array_merge($defaults, $userSettings);
    }

    /**
     * Get the effective work mode for a specific agent.
     * Checks agent_modes first, falls back to global work_mode.
     */
    public function getAgentMode(string $agentType): string
    {
        // Without GOLD every agent works in manual mode, like the global one
        if (!$this->autonomyLicensed()) {
            return 'manual';
        }

        $agentModes = $this->agent_modes ?? [];
        return $agentModes[$agentType] ?? $this->work_mode;
    }

    /**
     * Set the work mode for a specific agent. A mode equal to the global one is no override:
     * the agent follows the global mode again, so a later change of the global mode reaches it.
     */
    public function setAgentMode(string $agentType, string $mode): void
    {
        $validModes = ['autonomous', 'semi_auto', 'manual'];
        if (!in_array($mode, $validModes)) {
            throw new \InvalidArgumentException("Invalid agent mode: {$mode}");
        }

        $agentModes = $this->agent_modes ?? [];
        if ($mode === $this->work_mode) {
            unset($agentModes[$agentType]);
        } else {
            $agentModes[$agentType] = $mode;
        }
        $this->update(['agent_modes' => $agentModes ?: null]);
    }

    /**
     * Change the global work mode. Every agent follows it again: per-agent modes are cleared,
     * so choosing "Manual" really makes the whole Brain advisory.
     */
    public function setWorkMode(string $mode): void
    {
        $this->update(['work_mode' => $mode, 'agent_modes' => null]);
    }

    /**
     * The mode the agents actually work in: the one they all share, or "mixed" when per-agent
     * modes differ. The chat header shows this rather than the global mode alone.
     *
     * @param  string[]  $agentTypes
     */
    public function effectiveMode(?array $agentTypes = null): string
    {
        $agentTypes ??= array_keys(self::AGENT_TYPES);
        $modes = array_values(array_unique(array_map(fn (string $agent) => $this->getAgentMode($agent), $agentTypes)));

        return count($modes) === 1 ? $modes[0] : 'mixed';
    }

    /**
     * Check if per-agent modes are configured (not just using global).
     */
    public function hasPerAgentModes(): bool
    {
        return !empty($this->agent_modes);
    }

    /**
     * Get or create settings for a user.
     */
    public static function getForUser(int $userId): self
    {
        return static::firstOrCreate(
            ['user_id' => $userId],
            [
                'work_mode' => 'semi_auto',
                'preferred_language' => 'auto',
                'daily_token_limit' => 100000,
            ]
        );
    }

    /**
     * Resolve the effective language code for Brain responses.
     * 'auto' uses the user's UI locale; any other value is used directly.
     */
    public function resolveLanguage(?User $user = null): string
    {
        $lang = $this->preferred_language ?? 'auto';

        if ($lang === 'auto') {
            if ($user) {
                return $user->locale ?? app()->getLocale();
            }
            return app()->getLocale();
        }

        return $lang;
    }

    /**
     * Get human-readable language name from a code.
     * Supports standard ISO codes and custom values (returned as-is).
     */
    public static function getLanguageName(string $code): string
    {
        $names = [
            'pl' => 'Polish (Polski)',
            'en' => 'English',
            'de' => 'German (Deutsch)',
            'es' => 'Spanish (Español)',
            'fr' => 'French (Français)',
            'it' => 'Italian (Italiano)',
            'pt' => 'Portuguese (Português)',
            'nl' => 'Dutch (Nederlands)',
            'zh' => 'Chinese (中文)',
            'ja' => 'Japanese (日本語)',
            'ko' => 'Korean (한국어)',
            'ru' => 'Russian (Русский)',
            'ar' => 'Arabic (العربية)',
            'uk' => 'Ukrainian (Українська)',
            'cs' => 'Czech (Čeština)',
            'sv' => 'Swedish (Svenska)',
            'tr' => 'Turkish (Türkçe)',
        ];

        return $names[$code] ?? $code;
    }
}
