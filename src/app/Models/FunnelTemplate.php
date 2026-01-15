<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class FunnelTemplate extends Model
{
    use HasFactory;

    protected $table = 'funnel_templates';

    // Categories
    public const CATEGORY_WELCOME = 'welcome';
    public const CATEGORY_REENGAGEMENT = 'reengagement';
    public const CATEGORY_LAUNCH = 'launch';
    public const CATEGORY_CART_ABANDONMENT = 'cart_abandonment';
    public const CATEGORY_WEBINAR = 'webinar';
    public const CATEGORY_ONBOARDING = 'onboarding';
    public const CATEGORY_SALES = 'sales';
    public const CATEGORY_CUSTOM = 'custom';

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'description',
        'category',
        'structure',
        'thumbnail',
        'is_public',
        'is_featured',
        'uses_count',
        'tags',
    ];

    protected $casts = [
        'structure' => 'array',
        'is_public' => 'boolean',
        'is_featured' => 'boolean',
        'uses_count' => 'integer',
        'tags' => 'array',
    ];

    // =====================================
    // Boot
    // =====================================

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($template) {
            if (empty($template->slug)) {
                $template->slug = static::generateUniqueSlug($template->name);
            }
        });
    }

    public static function generateUniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $count = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $count++;
        }

        return $slug;
    }

    // =====================================
    // Relationships
    // =====================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // =====================================
    // Scopes
    // =====================================

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('is_public', true)
              ->orWhere('user_id', $userId);
        });
    }

    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%");
        });
    }

    // =====================================
    // Methods
    // =====================================

    /**
     * Increment usage count when template is used.
     */
    public function recordUsage(): self
    {
        $this->increment('uses_count');
        return $this;
    }

    /**
     * Get step count from structure.
     */
    public function getStepCount(): int
    {
        return count($this->structure['nodes'] ?? []);
    }

    /**
     * Check if user can use this template.
     */
    public function canBeUsedBy(?int $userId): bool
    {
        return $this->is_public || $this->user_id === $userId;
    }

    // =====================================
    // Static helpers
    // =====================================

    public static function getCategories(): array
    {
        return [
            self::CATEGORY_WELCOME => 'Sekwencja powitalna',
            self::CATEGORY_REENGAGEMENT => 'Re-engagement',
            self::CATEGORY_LAUNCH => 'Premiera produktu',
            self::CATEGORY_CART_ABANDONMENT => 'Porzucony koszyk',
            self::CATEGORY_WEBINAR => 'Webinar',
            self::CATEGORY_ONBOARDING => 'Onboarding',
            self::CATEGORY_SALES => 'Sprzedaż',
            self::CATEGORY_CUSTOM => 'Własny',
        ];
    }

    public static function getCategoryIcon(string $category): string
    {
        return match ($category) {
            self::CATEGORY_WELCOME => '👋',
            self::CATEGORY_REENGAGEMENT => '🔄',
            self::CATEGORY_LAUNCH => '🚀',
            self::CATEGORY_CART_ABANDONMENT => '🛒',
            self::CATEGORY_WEBINAR => '🎥',
            self::CATEGORY_ONBOARDING => '📚',
            self::CATEGORY_SALES => '💰',
            default => '📋',
        };
    }
}
