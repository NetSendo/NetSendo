<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebinarProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'webinar_id',
        'name',
        'description',
        'price',
        'currency',
        'original_price',
        'stripe_product_id',
        'polar_product_id',
        'external_checkout_url',
        'image_url',
        'cta_text',
        'cta_color',
        'cta_text_color',
        'pin_at_seconds',
        'unpin_at_seconds',
        'show_countdown',
        'countdown_minutes',
        'limited_quantity',
        'sold_count',
        'bonuses',
        'is_active',
        'is_pinned',
        'pinned_at',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'original_price' => 'decimal:2',
        'pin_at_seconds' => 'integer',
        'unpin_at_seconds' => 'integer',
        'show_countdown' => 'boolean',
        'countdown_minutes' => 'integer',
        'limited_quantity' => 'integer',
        'sold_count' => 'integer',
        'bonuses' => 'array',
        'is_active' => 'boolean',
        'is_pinned' => 'boolean',
        'pinned_at' => 'datetime',
        'sort_order' => 'integer',
    ];

    // =====================================
    // Relationships
    // =====================================

    public function webinar(): BelongsTo
    {
        return $this->belongsTo(Webinar::class);
    }

    public function stripeProduct(): BelongsTo
    {
        return $this->belongsTo(StripeProduct::class);
    }

    public function polarProduct(): BelongsTo
    {
        return $this->belongsTo(PolarProduct::class);
    }

    // =====================================
    // Scopes
    // =====================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePinned($query)
    {
        return $query->where('is_pinned', true);
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('limited_quantity')
                  ->orWhereRaw('sold_count < limited_quantity');
            });
    }

    // =====================================
    // Accessors
    // =====================================

    /**
     * Get formatted price.
     */
    public function getFormattedPriceAttribute(): string
    {
        $formatter = new \NumberFormatter(app()->getLocale(), \NumberFormatter::CURRENCY);
        return $formatter->formatCurrency($this->price, $this->currency);
    }

    /**
     * Get formatted original price.
     */
    public function getFormattedOriginalPriceAttribute(): ?string
    {
        if (!$this->original_price) {
            return null;
        }

        $formatter = new \NumberFormatter(app()->getLocale(), \NumberFormatter::CURRENCY);
        return $formatter->formatCurrency($this->original_price, $this->currency);
    }

    /**
     * Get discount percentage.
     */
    public function getDiscountPercentageAttribute(): ?int
    {
        if (!$this->original_price || $this->original_price <= $this->price) {
            return null;
        }

        return (int) round((($this->original_price - $this->price) / $this->original_price) * 100);
    }

    /**
     * Get remaining quantity.
     */
    public function getRemainingQuantityAttribute(): ?int
    {
        if (!$this->limited_quantity) {
            return null;
        }

        return max(0, $this->limited_quantity - $this->sold_count);
    }

    /**
     * Check if sold out.
     */
    public function getIsSoldOutAttribute(): bool
    {
        if (!$this->limited_quantity) {
            return false;
        }

        return $this->sold_count >= $this->limited_quantity;
    }

    /**
     * Get checkout URL.
     */
    public function getCheckoutUrlAttribute(): ?string
    {
        if ($this->external_checkout_url) {
            return $this->external_checkout_url;
        }

        if ($this->stripe_product_id) {
            return route('stripe.checkout', ['product' => $this->stripe_product_id]);
        }

        if ($this->polar_product_id) {
            return route('polar.checkout', ['product' => $this->polar_product_id]);
        }

        return null;
    }

    // =====================================
    // Methods
    // =====================================

    /**
     * Pin product in chat.
     */
    public function pin(): void
    {
        // Unpin other products first
        $this->webinar->products()
            ->where('id', '!=', $this->id)
            ->where('is_pinned', true)
            ->update(['is_pinned' => false, 'pinned_at' => null]);

        $this->update([
            'is_pinned' => true,
            'pinned_at' => now(),
        ]);

        // Create product message in chat
        WebinarChatMessage::createProductMessage($this->webinar, $this);
    }

    /**
     * Unpin product.
     */
    public function unpin(): void
    {
        $this->update([
            'is_pinned' => false,
            'pinned_at' => null,
        ]);

        // Remove pinned product messages
        $this->webinar->chatMessages()
            ->where('message_type', WebinarChatMessage::TYPE_PRODUCT)
            ->where('is_pinned', true)
            ->update(['is_pinned' => false]);
    }

    /**
     * Increment sold count.
     */
    public function incrementSold(int $quantity = 1): void
    {
        $this->increment('sold_count', $quantity);
    }

    /**
     * Check if should be pinned at given time.
     */
    public function shouldPinAt(int $seconds): bool
    {
        return $this->pin_at_seconds !== null && $this->pin_at_seconds === $seconds;
    }

    /**
     * Check if should be unpinned at given time.
     */
    public function shouldUnpinAt(int $seconds): bool
    {
        return $this->unpin_at_seconds !== null && $this->unpin_at_seconds === $seconds;
    }

    /**
     * Get data for frontend display.
     */
    public function toDisplayArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'formatted_price' => $this->formatted_price,
            'original_price' => $this->original_price,
            'formatted_original_price' => $this->formatted_original_price,
            'discount_percentage' => $this->discount_percentage,
            'currency' => $this->currency,
            'image_url' => $this->image_url,
            'cta_text' => $this->cta_text,
            'cta_color' => $this->cta_color,
            'cta_text_color' => $this->cta_text_color,
            'checkout_url' => $this->checkout_url,
            'show_countdown' => $this->show_countdown,
            'countdown_minutes' => $this->countdown_minutes,
            'limited_quantity' => $this->limited_quantity,
            'remaining_quantity' => $this->remaining_quantity,
            'is_sold_out' => $this->is_sold_out,
            'bonuses' => $this->bonuses,
            'is_pinned' => $this->is_pinned,
            'pinned_at' => $this->pinned_at?->toIso8601String(),
        ];
    }
}
