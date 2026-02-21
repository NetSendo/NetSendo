<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeEntry extends Model
{
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
