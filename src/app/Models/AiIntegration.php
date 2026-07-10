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
                'description' => 'GPT-5.2, GPT-5, o3 i inne modele OpenAI',
                'requires_api_key' => true,
                'supports_base_url' => true,
                'default_base_url' => 'https://api.openai.com/v1',
                'logo' => 'openai',
                'color' => '#10A37F',
            ],
            'anthropic' => [
                'name' => 'Anthropic',
                'description' => 'Claude Opus 4.8, Sonnet 5, Haiku 4.5 i starsze modele',
                'requires_api_key' => true,
                'supports_base_url' => false,
                'default_base_url' => 'https://api.anthropic.com',
                'logo' => 'anthropic',
                'color' => '#D4A574',
            ],
            'grok' => [
                'name' => 'Grok (xAI)',
                'description' => 'Grok 4, Grok 3 i starsze modele od xAI',
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
                'supports_optional_api_key' => true,
                'optional_api_key_hint' => 'Opcjonalnie: Bearer token lub klucz dla serwerów za proxy',
                'supports_base_url' => true,
                'default_base_url' => 'http://localhost:11434',
                'logo' => 'ollama',
                'color' => '#FFFFFF',
            ],
            'gemini' => [
                'name' => 'Google Gemini',
                'description' => 'Gemini 2.5 Pro/Flash i nowsze modele Google',
                'requires_api_key' => true,
                'supports_base_url' => false,
                'default_base_url' => 'https://generativelanguage.googleapis.com/v1beta',
                'logo' => 'gemini',
                'color' => '#4285F4',
            ],
        ];
    }

    /**
     * Curated fallback catalog of models per provider (refreshed July 2026).
     *
     * This is a static safety net used when a provider has no live models
     * endpoint (or the API call fails). Providers that support it can refresh
     * their list on demand from the API via {@see AiService::fetchModels()};
     * fetched models are stored and merged in by {@see self::availableModels()},
     * so the selectors stay current without editing this list every month.
     */
    public static function getDefaultModels(string $provider): array
    {
        $models = [
            'openai' => [
                ['model_id' => 'gpt-5.2', 'display_name' => 'GPT-5.2 (Najnowszy)'],
                ['model_id' => 'gpt-5-pro', 'display_name' => 'GPT-5 Pro'],
                ['model_id' => 'gpt-5-mini', 'display_name' => 'GPT-5 Mini'],
                ['model_id' => 'o3-full', 'display_name' => 'o3 (Reasoning)'],
                ['model_id' => 'o3-mini', 'display_name' => 'o3 Mini'],
                ['model_id' => 'o1-pro', 'display_name' => 'o1 Pro (Legacy)'],
                ['model_id' => 'gpt-4.5', 'display_name' => 'GPT-4.5 (Legacy)'],
            ],
            'anthropic' => [
                ['model_id' => 'claude-opus-4-8', 'display_name' => 'Claude Opus 4.8 (Najnowszy)'],
                ['model_id' => 'claude-sonnet-5', 'display_name' => 'Claude Sonnet 5'],
                ['model_id' => 'claude-haiku-4-5', 'display_name' => 'Claude Haiku 4.5 (Szybki)'],
                ['model_id' => 'claude-fable-5', 'display_name' => 'Claude Fable 5'],
                ['model_id' => 'claude-opus-4-6', 'display_name' => 'Claude Opus 4.6 (Legacy)'],
                ['model_id' => 'claude-sonnet-4-6', 'display_name' => 'Claude Sonnet 4.6 (Legacy)'],
                ['model_id' => 'claude-4-5-sonnet', 'display_name' => 'Claude 4.5 Sonnet (Legacy)'],
                ['model_id' => 'claude-4-haiku', 'display_name' => 'Claude 4 Haiku (Legacy)'],
            ],
            'grok' => [
                ['model_id' => 'grok-4', 'display_name' => 'Grok 4 (Najnowszy)'],
                ['model_id' => 'grok-3-ultra', 'display_name' => 'Grok 3 Ultra'],
                ['model_id' => 'grok-3', 'display_name' => 'Grok 3'],
                ['model_id' => 'grok-2', 'display_name' => 'Grok 2 (Legacy)'],
            ],
            'openrouter' => [
                ['model_id' => 'openai/gpt-5.2', 'display_name' => 'OpenAI GPT-5.2'],
                ['model_id' => 'openai/o3', 'display_name' => 'OpenAI o3'],
                ['model_id' => 'anthropic/claude-opus-4-8', 'display_name' => 'Claude Opus 4.8'],
                ['model_id' => 'anthropic/claude-sonnet-5', 'display_name' => 'Claude Sonnet 5'],
                ['model_id' => 'google/gemini-2.5-pro', 'display_name' => 'Gemini 2.5 Pro'],
                ['model_id' => 'meta-llama/llama-4-405b', 'display_name' => 'Llama 4 (Full)'],
                ['model_id' => 'x-ai/grok-4', 'display_name' => 'Grok 4'],
                ['model_id' => 'mistralai/mistral-large-3', 'display_name' => 'Mistral Large 3'],
                ['model_id' => 'moonshotai/kimi-k2.5', 'display_name' => 'MoonshotAI Kimi K2.5'],
            ],
            'ollama' => [
                ['model_id' => 'llama4.1', 'display_name' => 'Llama 4.1 (Latest)'],
                ['model_id' => 'llama4', 'display_name' => 'Llama 4'],
                ['model_id' => 'qwen3', 'display_name' => 'Qwen 3'],
                ['model_id' => 'mistral4', 'display_name' => 'Mistral 4'],
                ['model_id' => 'phi5', 'display_name' => 'Phi-5'],
                ['model_id' => 'deepseek-v4', 'display_name' => 'DeepSeek V4'],
                ['model_id' => 'kimi-k2.5:cloud', 'display_name' => 'Kimi K2.5 Cloud (Multimodal Agentic)'],
            ],
            'gemini' => [
                ['model_id' => 'gemini-2.5-pro', 'display_name' => 'Gemini 2.5 Pro (Najnowszy)'],
                ['model_id' => 'gemini-2.5-flash', 'display_name' => 'Gemini 2.5 Flash'],
                ['model_id' => 'gemini-2.5-flash-lite', 'display_name' => 'Gemini 2.5 Flash-Lite'],
                ['model_id' => 'gemini-2.0-pro', 'display_name' => 'Gemini 2.0 Pro (Legacy)'],
                ['model_id' => 'gemini-2.0-flash', 'display_name' => 'Gemini 2.0 Flash (Legacy)'],
            ],
        ];

        return $models[$provider] ?? [];
    }

    /**
     * Resolve the full model list shown in selectors: the current default
     * catalog merged with any stored models (custom entries or models
     * previously fetched from the provider API). Defaults are listed first so
     * newly released models always appear; stored rows override the display
     * name for matching IDs and append any extras.
     *
     * @return array<int, array{model_id: string, display_name: string, is_custom: bool}>
     */
    public function availableModels(): array
    {
        $models = collect(self::getDefaultModels($this->provider))
            ->keyBy('model_id')
            ->map(fn (array $m) => [
                'model_id' => $m['model_id'],
                'display_name' => $m['display_name'],
                'is_custom' => false,
            ]);

        foreach ($this->models as $model) {
            $models[$model->model_id] = [
                'model_id' => $model->model_id,
                'display_name' => $model->display_name,
                'is_custom' => (bool) $model->is_custom,
            ];
        }

        return $models->values()->all();
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
