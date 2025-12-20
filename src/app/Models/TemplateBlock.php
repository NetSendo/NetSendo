<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TemplateBlock extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'type',
        'content',
        'settings',
        'thumbnail',
        'is_global',
        'usage_count',
    ];

    protected $casts = [
        'content' => 'array',
        'settings' => 'array',
        'is_global' => 'boolean',
    ];

    /**
     * Available block types
     */
    public const BLOCK_TYPES = [
        'header' => [
            'label' => 'Nagłówek',
            'icon' => 'layout-navbar',
            'category' => 'structure',
        ],
        'text' => [
            'label' => 'Tekst',
            'icon' => 'align-left',
            'category' => 'content',
        ],
        'image' => [
            'label' => 'Obraz',
            'icon' => 'image',
            'category' => 'content',
        ],
        'button' => [
            'label' => 'Przycisk',
            'icon' => 'square',
            'category' => 'content',
        ],
        'divider' => [
            'label' => 'Separator',
            'icon' => 'minus',
            'category' => 'structure',
        ],
        'spacer' => [
            'label' => 'Odstęp',
            'icon' => 'arrows-vertical',
            'category' => 'structure',
        ],
        'columns' => [
            'label' => 'Kolumny',
            'icon' => 'columns',
            'category' => 'structure',
        ],
        'product' => [
            'label' => 'Produkt',
            'icon' => 'package',
            'category' => 'ecommerce',
        ],
        'product_grid' => [
            'label' => 'Siatka produktów',
            'icon' => 'grid-dots',
            'category' => 'ecommerce',
        ],
        'social' => [
            'label' => 'Social Media',
            'icon' => 'share',
            'category' => 'content',
        ],
        'footer' => [
            'label' => 'Stopka',
            'icon' => 'layout-bottombar',
            'category' => 'structure',
        ],
    ];

    /**
     * Block categories for grouping in UI
     */
    public const BLOCK_CATEGORIES = [
        'structure' => 'Struktura',
        'content' => 'Treść',
        'ecommerce' => 'E-commerce',
    ];

    /**
     * Relationship to user
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for global blocks
     */
    public function scopeGlobal($query)
    {
        return $query->where('is_global', true);
    }

    /**
     * Scope for blocks by type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for blocks available to user (own + global)
     */
    public function scopeAvailableToUser($query, int $userId)
    {
        return $query->where('user_id', $userId)
            ->orWhere('is_global', true);
    }

    /**
     * Increment usage count
     */
    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }

    /**
     * Get default content for block type
     */
    public static function getDefaultContent(string $type): array
    {
        return match ($type) {
            'header' => [
                'logo' => null,
                'logoWidth' => 150,
                'backgroundColor' => '#6366f1',
                'padding' => '20px',
                'alignment' => 'center',
            ],
            'text' => [
                'html' => '<p>Wpisz tutaj swoją treść...</p>',
                'padding' => '20px',
                'alignment' => 'left',
            ],
            'image' => [
                'src' => null,
                'alt' => '',
                'href' => null,
                'width' => '100%',
                'alignment' => 'center',
                'padding' => '10px',
            ],
            'button' => [
                'text' => 'Kliknij tutaj',
                'href' => '#',
                'backgroundColor' => '#6366f1',
                'textColor' => '#ffffff',
                'borderRadius' => '8px',
                'padding' => '12px 24px',
                'alignment' => 'center',
            ],
            'divider' => [
                'color' => '#e2e8f0',
                'width' => '100%',
                'padding' => '10px 0',
            ],
            'spacer' => [
                'height' => '30px',
            ],
            'columns' => [
                'columns' => 2,
                'gap' => '20px',
                'children' => [[], []],
            ],
            'product' => [
                'image' => null,
                'title' => 'Nazwa produktu',
                'description' => 'Opis produktu...',
                'price' => '99.00',
                'oldPrice' => null,
                'currency' => 'PLN',
                'buttonText' => 'Kup teraz',
                'buttonUrl' => '#',
            ],
            'product_grid' => [
                'columns' => 2,
                'products' => [],
            ],
            'social' => [
                'icons' => [
                    ['type' => 'facebook', 'url' => '#'],
                    ['type' => 'twitter', 'url' => '#'],
                    ['type' => 'instagram', 'url' => '#'],
                ],
                'iconSize' => 32,
                'alignment' => 'center',
                'padding' => '20px',
            ],
            'footer' => [
                'companyName' => 'Twoja Firma',
                'address' => 'ul. Przykładowa 1, 00-001 Warszawa',
                'unsubscribeText' => 'Wypisz się z newslettera',
                'unsubscribeUrl' => '{{unsubscribe_url}}',
                'copyright' => '© ' . date('Y') . ' Wszelkie prawa zastrzeżone',
                'backgroundColor' => '#1e293b',
                'textColor' => '#94a3b8',
                'padding' => '30px 20px',
            ],
            default => [],
        };
    }

    /**
     * Get default settings for block type
     */
    public static function getDefaultSettings(string $type): array
    {
        return [
            'backgroundColor' => 'transparent',
            'padding' => '0',
            'margin' => '0',
            'borderRadius' => '0',
        ];
    }
}
