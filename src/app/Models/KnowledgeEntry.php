<?php

namespace App\Models;

use App\Jobs\IndexKnowledgeEntry;
use App\Licensing\Feature;
use App\Services\LicenseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;

class KnowledgeEntry extends Model
{
    /** Longest content of an entry on SILVER, where Brain puts whole entries into prompts. */
    public const CONTENT_LIMIT = 10000;

    /** Longest content with the semantic knowledge base (GOLD), where Brain reads matching chunks. */
    public const CONTENT_LIMIT_SEMANTIC = 100000;

    protected static function booted(): void
    {
        // Chunks follow the entry: changed text drops the old vectors (on any plan, so nothing
        // stale is ever searched), and with GOLD the entry is embedded again in the background
        static::saved(function (KnowledgeEntry $entry) {
            if (!$entry->wasRecentlyCreated && !$entry->wasChanged(['title', 'content'])) {
                return;
            }

            if (!$entry->wasRecentlyCreated) {
                $entry->chunks()->delete();
            }

            if (app(LicenseService::class)->allows(Feature::SemanticKnowledge)) {
                // Saving never fails because of the AI provider (with QUEUE_CONNECTION=sync the
                // job runs right here); the hourly brain:index-knowledge sweep retries
                try {
                    IndexKnowledgeEntry::dispatch($entry->id)->afterCommit();
                } catch (\Throwable $e) {
                    Log::warning('Knowledge entry saved without embedding', [
                        'entry_id' => $entry->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });
    }

    /** The longest content the licence in effect allows. */
    public static function contentLimit(): int
    {
        return app(LicenseService::class)->allows(Feature::SemanticKnowledge)
            ? self::CONTENT_LIMIT_SEMANTIC
            : self::CONTENT_LIMIT;
    }

    /** A known category; anything else (e.g. one an AI made up) becomes "insights". */
    public static function normalizeCategory(?string $category): string
    {
        return array_key_exists((string) $category, self::CATEGORIES) ? $category : 'insights';
    }

    protected $fillable = [
        'user_id',
        'category',
        'title',
        'content',
        'content_embedding',
        'source',
        'source_reference',
        'tags',
        'confidence',
        'is_verified',
        'is_active',
        'usage_count',
        'last_used_at',
    ];

    protected $casts = [
        'tags' => 'array',
        'confidence' => 'float',
        'is_verified' => 'boolean',
        'is_active' => 'boolean',
        'usage_count' => 'integer',
        'last_used_at' => 'datetime',
    ];

    /**
     * Available categories for knowledge entries.
     */
    public const CATEGORIES = [
        'company' => 'Informacje o firmie',
        'products' => 'Produkty i usługi',
        'brand_voice' => 'Ton głosu i styl marki',
        'audience' => 'Grupa docelowa',
        'best_practices' => 'Best practices',
        'insights' => 'Wnioski z kampanii',
        'templates' => 'Szablony i wzorce',
        'competitors' => 'Konkurencja',
        'goals' => 'Cele biznesowe',
        'style_preference' => 'Preferencje stylu i tonu',
        'performance_pattern' => 'Wzorce skutecznych kampanii',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(KnowledgeChunk::class);
    }

    /** Fingerprint of the text the chunks are made from. */
    public function contentHash(): string
    {
        return hash('sha256', $this->title . "\n" . $this->content);
    }

    /**
     * Record usage of this entry in AI context.
     */
    public function recordUsage(): void
    {
        $this->update([
            'usage_count' => $this->usage_count + 1,
            'last_used_at' => now(),
        ]);
    }

    /**
     * Mark as verified by user.
     */
    public function verify(): void
    {
        $this->update([
            'is_verified' => true,
            'confidence' => 1.0,
        ]);
    }

    // Scopes
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    public function scopeSearch($query, string $searchTerm)
    {
        return $query->whereRaw(
            'MATCH(title, content) AGAINST(? IN BOOLEAN MODE)',
            [$searchTerm . '*']
        );
    }

    /**
     * Scope for high-confidence entries first.
     */
    public function scopeByRelevance($query)
    {
        return $query->orderByDesc('is_verified')
            ->orderByDesc('confidence')
            ->orderByDesc('usage_count');
    }
}
