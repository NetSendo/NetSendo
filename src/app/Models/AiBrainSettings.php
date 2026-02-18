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
        'preferred_language',
        'preferred_model',
        'preferred_integration_id',
        'model_routing',
        'preferences',
        'agent_permissions',
        'daily_token_limit',
        'tokens_used_today',
        'token_reset_date',
        'is_active',
    ];

    protected $casts = [
        'preferences' => 'array',
        'agent_permissions' => 'array',
        'model_routing' => 'array',
        'telegram_linked_at' => 'datetime',
        'telegram_bot_token' => 'encrypted',
        'token_reset_date' => 'date',
        'is_active' => 'boolean',
        'daily_token_limit' => 'integer',
        'tokens_used_today' => 'integer',
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
        if ($this->work_mode === 'manual') {
            return false;
        }

        $permissions = $this->agent_permissions ?? [];
        return $permissions[$agentType] ?? true; // default: allowed
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
                'preferred_language' => 'pl',
                'daily_token_limit' => 100000,
            ]
        );
    }
}
