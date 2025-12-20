<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiIntegration extends Model
{
    protected $fillable = [
        'provider',
        'name',
        'api_key',
        'base_url',
        'default_model',
        'max_tokens_small',
        'max_tokens_large',
        'is_active',
        'last_tested_at',
        'last_test_status',
        'last_test_message',
    ];

    protected $casts = [
        'api_key' => 'encrypted',
        'is_active' => 'boolean',
        'last_tested_at' => 'datetime',
    ];

    protected $hidden = [
        'api_key',
    ];

    protected $appends = [
        'has_api_key',
    ];

    /**
     * Check if the integration has an API key set.
     */
    public function getHasApiKeyAttribute(): bool
    {
        return !empty($this->api_key);
    }

    /**
     * Get the models associated with this integration.
     */
    public function models(): HasMany
    {
        return $this->hasMany(AiModel::class);
    }

    /**
     * Get all available providers with their configurations.
     */
    public static function getProviders(): array
    {
        return [
            'openai' => [
                'name' => 'OpenAI',
                'description' => 'GPT-4, GPT-3.5 i inne modele OpenAI',
                'requires_api_key' => true,
                'supports_base_url' => true,
                'default_base_url' => 'https://api.openai.com/v1',
                'logo' => 'openai',
                'color' => '#10A37F',
            ],
            'anthropic' => [
                'name' => 'Anthropic',
                'description' => 'Claude 3.5, Claude 3 Opus, Haiku',
                'requires_api_key' => true,
                'supports_base_url' => false,
                'default_base_url' => 'https://api.anthropic.com',
                'logo' => 'anthropic',
                'color' => '#D4A574',
            ],
            'grok' => [
                'name' => 'Grok (xAI)',
                'description' => 'Grok 2, Grok 2 Mini od xAI',
                'requires_api_key' => true,
                'supports_base_url' => false,
                'default_base_url' => 'https://api.x.ai/v1',
                'logo' => 'grok',
                'color' => '#1DA1F2',
            ],
            'openrouter' => [
                'name' => 'OpenRouter',
                'description' => 'Dostęp do wielu modeli przez jeden API',
                'requires_api_key' => true,
                'supports_base_url' => false,
                'default_base_url' => 'https://openrouter.ai/api/v1',
                'logo' => 'openrouter',
                'color' => '#6366F1',
            ],
            'ollama' => [
                'name' => 'Ollama',
                'description' => 'Lokalne modele AI (Llama, Mistral, etc.)',
                'requires_api_key' => false,
                'supports_base_url' => true,
                'default_base_url' => 'http://localhost:11434',
                'logo' => 'ollama',
                'color' => '#FFFFFF',
            ],
            'gemini' => [
                'name' => 'Google Gemini',
                'description' => 'Gemini 2.0, Gemini 1.5 Pro/Flash',
                'requires_api_key' => true,
                'supports_base_url' => false,
                'default_base_url' => 'https://generativelanguage.googleapis.com/v1beta',
                'logo' => 'gemini',
                'color' => '#4285F4',
            ],
        ];
    }

    /**
     * Get default models for a provider (December 2025).
     */
    public static function getDefaultModels(string $provider): array
    {
        $models = [
            'openai' => [
                ['model_id' => 'gpt-5.2-pro', 'display_name' => 'GPT-5.2 Pro (Najnowszy - Grudzień 2025)'],
                ['model_id' => 'gpt-5.2-thinking', 'display_name' => 'GPT-5.2 Thinking (Głębokie analizy)'],
                ['model_id' => 'gpt-5.2-instant', 'display_name' => 'GPT-5.2 Instant (Szybki i tani)'],
                ['model_id' => 'gpt-5', 'display_name' => 'GPT-5'],
                ['model_id' => 'gpt-5-mini', 'display_name' => 'GPT-5 Mini'],
                ['model_id' => 'gpt-4.1', 'display_name' => 'GPT-4.1'],
                ['model_id' => 'gpt-4o', 'display_name' => 'GPT-4o'],
                ['model_id' => 'o1', 'display_name' => 'o1 (Rozumowanie)'],
            ],
            'anthropic' => [
                ['model_id' => 'claude-opus-4.5', 'display_name' => 'Claude Opus 4.5 (Najnowszy - Listopad 2025)'],
                ['model_id' => 'claude-sonnet-4.5', 'display_name' => 'Claude Sonnet 4.5 (Wrzesień 2025)'],
                ['model_id' => 'claude-haiku-4.5', 'display_name' => 'Claude Haiku 4.5 (Październik 2025)'],
                ['model_id' => 'claude-opus-4', 'display_name' => 'Claude Opus 4'],
                ['model_id' => 'claude-sonnet-4', 'display_name' => 'Claude Sonnet 4'],
                ['model_id' => 'claude-3-5-sonnet-latest', 'display_name' => 'Claude 3.5 Sonnet'],
            ],
            'grok' => [
                ['model_id' => 'grok-3', 'display_name' => 'Grok 3 (Najnowszy)'],
                ['model_id' => 'grok-3-vision', 'display_name' => 'Grok 3 Vision'],
                ['model_id' => 'grok-2', 'display_name' => 'Grok 2'],
                ['model_id' => 'grok-2-vision', 'display_name' => 'Grok 2 Vision'],
            ],
            'openrouter' => [
                ['model_id' => 'openai/gpt-5.2-pro', 'display_name' => 'OpenAI GPT-5.2 Pro'],
                ['model_id' => 'openai/gpt-5', 'display_name' => 'OpenAI GPT-5'],
                ['model_id' => 'anthropic/claude-opus-4.5', 'display_name' => 'Claude Opus 4.5'],
                ['model_id' => 'anthropic/claude-sonnet-4.5', 'display_name' => 'Claude Sonnet 4.5'],
                ['model_id' => 'google/gemini-3-flash', 'display_name' => 'Gemini 3 Flash'],
                ['model_id' => 'google/gemini-2.5-pro', 'display_name' => 'Gemini 2.5 Pro'],
                ['model_id' => 'meta-llama/llama-4-405b', 'display_name' => 'Llama 4 405B'],
                ['model_id' => 'x-ai/grok-3', 'display_name' => 'Grok 3'],
                ['model_id' => 'deepseek/deepseek-v3', 'display_name' => 'DeepSeek V3'],
            ],
            'ollama' => [
                ['model_id' => 'llama4', 'display_name' => 'Llama 4 (Najnowszy)'],
                ['model_id' => 'llama3.3', 'display_name' => 'Llama 3.3'],
                ['model_id' => 'llama3.2', 'display_name' => 'Llama 3.2'],
                ['model_id' => 'qwen3', 'display_name' => 'Qwen 3'],
                ['model_id' => 'mistral-large', 'display_name' => 'Mistral Large'],
                ['model_id' => 'gemma3', 'display_name' => 'Gemma 3'],
                ['model_id' => 'phi4', 'display_name' => 'Phi-4'],
                ['model_id' => 'deepseek-v3', 'display_name' => 'DeepSeek V3'],
            ],
            'gemini' => [
                ['model_id' => 'gemini-3-flash', 'display_name' => 'Gemini 3 Flash (Najnowszy - Grudzień 2025)'],
                ['model_id' => 'gemini-3-flash-preview', 'display_name' => 'Gemini 3 Flash Preview'],
                ['model_id' => 'gemini-2.5-pro', 'display_name' => 'Gemini 2.5 Pro'],
                ['model_id' => 'gemini-2.5-pro-latest', 'display_name' => 'Gemini 2.5 Pro Latest'],
                ['model_id' => 'gemini-2.5-flash', 'display_name' => 'Gemini 2.5 Flash'],
                ['model_id' => 'gemini-2.5-flash-latest', 'display_name' => 'Gemini 2.5 Flash Latest'],
                ['model_id' => 'gemini-2.0-flash', 'display_name' => 'Gemini 2.0 Flash'],
                ['model_id' => 'gemini-2.0-flash-exp', 'display_name' => 'Gemini 2.0 Flash Experimental'],
                ['model_id' => 'gemini-1.5-pro', 'display_name' => 'Gemini 1.5 Pro'],
                ['model_id' => 'gemini-1.5-pro-latest', 'display_name' => 'Gemini 1.5 Pro Latest'],
                ['model_id' => 'gemini-1.5-flash', 'display_name' => 'Gemini 1.5 Flash'],
                ['model_id' => 'gemini-1.5-flash-latest', 'display_name' => 'Gemini 1.5 Flash Latest'],
                ['model_id' => 'gemini-1.5-flash-8b', 'display_name' => 'Gemini 1.5 Flash 8B (Szybki)'],
            ],
        ];

        return $models[$provider] ?? [];
    }

    /**
     * Check if the integration has a valid API key.
     */
    public function hasApiKey(): bool
    {
        return !empty($this->api_key);
    }

    /**
     * Get the effective base URL.
     */
    public function getEffectiveBaseUrl(): string
    {
        if (!empty($this->base_url)) {
            return $this->base_url;
        }

        $providers = self::getProviders();
        return $providers[$this->provider]['default_base_url'] ?? '';
    }

    /**
     * Scope to only active integrations.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
