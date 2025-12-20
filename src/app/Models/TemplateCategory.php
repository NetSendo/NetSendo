<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TemplateCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'icon',
        'color',
        'description',
        'is_system',
        'sort_order',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($category) {
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });
    }

    /**
     * Relationship to user
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship to templates
     */
    public function templates()
    {
        return $this->hasMany(Template::class, 'category_id');
    }

    /**
     * Scope for system categories
     */
    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    /**
     * Scope for user categories
     */
    public function scopeForUser($query, ?int $userId)
    {
        return $query->where('is_system', true)
            ->orWhere('user_id', $userId);
    }

    /**
     * Get system default categories
     */
    public static function getSystemDefaults(): array
    {
        return [
            [
                'name' => 'Newsletter',
                'slug' => 'newsletter',
                'icon' => 'newspaper',
                'color' => '#6366f1',
                'description' => 'Regularne biuletyny i aktualizacje',
                'is_system' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Promocyjny',
                'slug' => 'promotional',
                'icon' => 'megaphone',
                'color' => '#f59e0b',
                'description' => 'Oferty specjalne i promocje',
                'is_system' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'E-commerce',
                'slug' => 'ecommerce',
                'icon' => 'shopping-cart',
                'color' => '#10b981',
                'description' => 'Szablony dla sklepów internetowych',
                'is_system' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Powitalny',
                'slug' => 'welcome',
                'icon' => 'hand-wave',
                'color' => '#8b5cf6',
                'description' => 'Wiadomości powitalne dla nowych subskrybentów',
                'is_system' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'Transakcyjny',
                'slug' => 'transactional',
                'icon' => 'receipt',
                'color' => '#64748b',
                'description' => 'Potwierdzenia zamówień, faktury',
                'is_system' => true,
                'sort_order' => 5,
            ],
            [
                'name' => 'Powiadomienie',
                'slug' => 'notification',
                'icon' => 'bell',
                'color' => '#ef4444',
                'description' => 'Alerty i powiadomienia systemowe',
                'is_system' => true,
                'sort_order' => 6,
            ],
        ];
    }

    /**
     * Seed system categories
     */
    public static function seedSystemCategories(): void
    {
        foreach (self::getSystemDefaults() as $category) {
            self::firstOrCreate(
                ['slug' => $category['slug']],
                $category
            );
        }
    }
}
