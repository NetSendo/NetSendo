<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesFunnel extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'thank_you_page_id',
        'thank_you_url',
        'target_list_id',
        'purchase_tag',
        'embed_settings',
        'is_active',
    ];

    protected $casts = [
        'embed_settings' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Default embed settings.
     */
    public const DEFAULT_EMBED_SETTINGS = [
        'button_text' => 'Kup teraz',
        'button_color' => '#6366f1',
        'button_text_color' => '#ffffff',
        'button_style' => 'rounded', // rounded, square, pill
    ];

    /**
     * Get the user that owns the sales funnel.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the thank you page.
     */
    public function thankYouPage(): BelongsTo
    {
        return $this->belongsTo(ExternalPage::class, 'thank_you_page_id');
    }

    /**
     * Get the target list for subscribers.
     */
    public function targetList(): BelongsTo
    {
        return $this->belongsTo(ContactList::class, 'target_list_id');
    }

    /**
     * Get Stripe products using this funnel.
     */
    public function stripeProducts(): HasMany
    {
        return $this->hasMany(StripeProduct::class);
    }

    /**
     * Get Polar products using this funnel.
     */
    public function polarProducts(): HasMany
    {
        return $this->hasMany(PolarProduct::class);
    }

    /**
     * Scope for active funnels.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for user's funnels.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Get the thank you URL (from page or custom).
     */
    public function getThankYouUrlAttribute(): ?string
    {
        if ($this->attributes['thank_you_url']) {
            return $this->attributes['thank_you_url'];
        }

        if ($this->thankYouPage) {
            return route('external-page.show', $this->thankYouPage);
        }

        return null;
    }

    /**
     * Get embed settings with defaults.
     */
    public function getEmbedSettingsWithDefaultsAttribute(): array
    {
        return array_merge(self::DEFAULT_EMBED_SETTINGS, $this->embed_settings ?? []);
    }

    /**
     * Generate success URL for checkout.
     */
    public function generateSuccessUrl(string $sessionId = '{CHECKOUT_SESSION_ID}'): string
    {
        $baseUrl = route('sales-funnel.success', ['funnel' => $this->id]);
        return $baseUrl . '?session_id=' . $sessionId;
    }
}
