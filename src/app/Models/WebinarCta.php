<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebinarCta extends Model
{
    use HasFactory;

    // Type constants
    public const TYPE_BUTTON = 'button';
    public const TYPE_COUNTDOWN = 'countdown';
    public const TYPE_BANNER = 'banner';
    public const TYPE_POPUP = 'popup';
    public const TYPE_STICKY_BAR = 'sticky_bar';

    protected $fillable = [
        'webinar_id',
        'type',
        'title',
        'subtitle',
        'button_text',
        'button_url',
        'webinar_product_id',
        'show_at_seconds',
        'hide_at_seconds',
        'show_permanently',
        'countdown_to',
        'countdown_seconds',
        'countdown_expired_text',
        'style',
        'is_active',
        'is_visible',
        'views_count',
        'clicks_count',
        'sort_order',
    ];

    protected $casts = [
        'show_at_seconds' => 'integer',
        'hide_at_seconds' => 'integer',
        'show_permanently' => 'boolean',
        'countdown_to' => 'datetime',
        'countdown_seconds' => 'integer',
        'style' => 'array',
        'is_active' => 'boolean',
        'is_visible' => 'boolean',
        'views_count' => 'integer',
        'clicks_count' => 'integer',
        'sort_order' => 'integer',
    ];

    /**
     * Default style settings.
     */
    public const DEFAULT_STYLE = [
        'position' => 'bottom',
        'background_color' => '#6366f1',
        'text_color' => '#ffffff',
        'button_color' => '#ffffff',
        'button_text_color' => '#6366f1',
        'animation' => 'slide',
        'size' => 'medium',
    ];

    // =====================================
    // Relationships
    // =====================================

    public function webinar(): BelongsTo
    {
        return $this->belongsTo(Webinar::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(WebinarProduct::class, 'webinar_product_id');
    }

    // =====================================
    // Scopes
    // =====================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    // =====================================
    // Accessors
    // =====================================

    /**
     * Get style with defaults merged.
     */
    public function getStyleWithDefaultsAttribute(): array
    {
        return array_merge(self::DEFAULT_STYLE, $this->style ?? []);
    }

    /**
     * Get the target URL (either button_url or product checkout).
     */
    public function getTargetUrlAttribute(): ?string
    {
        if ($this->button_url) {
            return $this->button_url;
        }

        if ($this->product) {
            return $this->product->checkout_url;
        }

        return null;
    }

    /**
     * Get click-through rate.
     */
    public function getCtrAttribute(): float
    {
        if ($this->views_count === 0) {
            return 0;
        }

        return round(($this->clicks_count / $this->views_count) * 100, 2);
    }

    /**
     * Get remaining countdown seconds.
     */
    public function getRemainingSecondsAttribute(): ?int
    {
        if ($this->countdown_to) {
            return max(0, now()->diffInSeconds($this->countdown_to, false));
        }

        if ($this->countdown_seconds && $this->is_visible) {
            // For auto-webinar: countdown from when it became visible
            return $this->countdown_seconds;
        }

        return null;
    }

    // =====================================
    // Methods
    // =====================================

    /**
     * Show CTA.
     */
    public function show(): void
    {
        $this->update(['is_visible' => true]);
        $this->increment('views_count');
    }

    /**
     * Hide CTA.
     */
    public function hide(): void
    {
        $this->update(['is_visible' => false]);
    }

    /**
     * Record click.
     */
    public function recordClick(): void
    {
        $this->increment('clicks_count');
    }

    /**
     * Check if should show at given time.
     */
    public function shouldShowAt(int $seconds): bool
    {
        return $this->show_at_seconds !== null && $this->show_at_seconds === $seconds;
    }

    /**
     * Check if should hide at given time.
     */
    public function shouldHideAt(int $seconds): bool
    {
        if ($this->show_permanently) {
            return false;
        }

        return $this->hide_at_seconds !== null && $this->hide_at_seconds === $seconds;
    }

    /**
     * Check if countdown expired.
     */
    public function isCountdownExpired(): bool
    {
        if (!$this->countdown_to) {
            return false;
        }

        return now()->greaterThan($this->countdown_to);
    }

    /**
     * Get data for frontend display.
     */
    public function toDisplayArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'button_text' => $this->button_text ?? $this->product?->cta_text ?? 'Kliknij tutaj',
            'target_url' => $this->target_url,
            'style' => $this->style_with_defaults,
            'countdown_to' => $this->countdown_to?->toIso8601String(),
            'countdown_seconds' => $this->countdown_seconds,
            'countdown_expired_text' => $this->countdown_expired_text,
            'remaining_seconds' => $this->remaining_seconds,
            'is_visible' => $this->is_visible,
            'product' => $this->product?->toDisplayArray(),
        ];
    }

    /**
     * Get type options.
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_BUTTON => 'Przycisk',
            self::TYPE_COUNTDOWN => 'Licznik',
            self::TYPE_BANNER => 'Baner',
            self::TYPE_POPUP => 'Popup',
            self::TYPE_STICKY_BAR => 'Pasek przyklejony',
        ];
    }

    /**
     * Get position options.
     */
    public static function getPositions(): array
    {
        return [
            'top' => 'Góra',
            'bottom' => 'Dół',
            'overlay' => 'Nakładka',
            'sidebar' => 'Pasek boczny',
        ];
    }

    /**
     * Get animation options.
     */
    public static function getAnimations(): array
    {
        return [
            'none' => 'Brak',
            'fade' => 'Zanikanie',
            'slide' => 'Wysuwanie',
            'bounce' => 'Odbijanie',
            'pulse' => 'Pulsowanie',
        ];
    }
}
