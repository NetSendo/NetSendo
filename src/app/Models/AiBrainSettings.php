<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiBrainSettings extends Model
{
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
        'tokens_used_today' => 'integer',
        'cron_enabled' => 'boolean',
        'cron_interval_minutes' => 'integer',
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
        return $this->isPerplexityConfigured() || $this->isSerpApiConfigured();
    }

    /**
     * Check if the daily token limit is reached.
     */
    public function isTokenLimitReached(): bool
    {
        return $this->tokens_used_today >= $this->daily_token_limit;
    }

    /**
     * Add tokens used and check if reset is needed.
     */
    public function addTokensUsed(int $tokens): void
    {
        if ($this->token_reset_date === null || $this->token_reset_date->lt(today())) {
            $this->update([
                'tokens_used_today' => $tokens,
                'token_reset_date' => today(),
            ]);
        } else {
            $this->increment('tokens_used_today', $tokens);
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
        $agentModes = $this->agent_modes ?? [];
        return $agentModes[$agentType] ?? $this->work_mode;
    }

    /**
     * Set the work mode for a specific agent.
     */
    public function setAgentMode(string $agentType, string $mode): void
    {
        $validModes = ['autonomous', 'semi_auto', 'manual'];
        if (!in_array($mode, $validModes)) {
            throw new \InvalidArgumentException("Invalid agent mode: {$mode}");
        }

        $agentModes = $this->agent_modes ?? [];
        $agentModes[$agentType] = $mode;
        $this->update(['agent_modes' => $agentModes]);
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
