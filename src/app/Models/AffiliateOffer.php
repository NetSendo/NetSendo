<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AffiliateOffer extends Model
{
    use HasFactory;

    protected $fillable = [
        'program_id',
        'name',
        'description',
        'type',
        'entity_id',
        'external_url',
        'commission_type',
        'commission_value',
        'is_public',
        'is_active',
        'image_url',
        'meta',
    ];

    protected $casts = [
        'entity_id' => 'integer',
        'commission_value' => 'decimal:2',
        'is_public' => 'boolean',
        'is_active' => 'boolean',
        'meta' => 'array',
    ];

    // Relationships

    public function program(): BelongsTo
    {
        return $this->belongsTo(AffiliateProgram::class, 'program_id');
    }

    public function links(): HasMany
    {
        return $this->hasMany(AffiliateLink::class, 'offer_id');
    }

    public function coupons(): HasMany
    {
        return $this->hasMany(AffiliateCoupon::class, 'offer_id');
    }

    public function conversions(): HasMany
    {
        return $this->hasMany(AffiliateConversion::class, 'offer_id');
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(AffiliateCommission::class, 'offer_id');
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(AffiliateClick::class, 'offer_id');
    }

    /**
     * Get the linked entity (SalesFunnel, ExternalPage, StripeProduct, etc.)
     */
    public function getEntityAttribute()
    {
        if (!$this->entity_id) {
            return null;
        }

        return match ($this->type) {
            'funnel' => SalesFunnel::find($this->entity_id),
            'landing' => ExternalPage::find($this->entity_id),
            'stripe_product' => StripeProduct::find($this->entity_id),
            'polar_product' => PolarProduct::find($this->entity_id),
            default => null,
        };
    }

    /**
     * Get the offer URL for affiliates to promote.
     */
    public function getOfferUrlAttribute(): ?string
    {
        if ($this->external_url) {
            return $this->external_url;
        }

        $entity = $this->entity;
        if (!$entity) {
            return null;
        }

        return match ($this->type) {
            'funnel' => route('sales-funnel.checkout', ['type' => 'stripe', 'product' => $entity->stripeProducts()->first()?->id ?? 0]),
            'landing' => route('page.show', $entity),
            'stripe_product' => route('sales-funnel.checkout', ['type' => 'stripe', 'product' => $entity->id]),
            'polar_product' => route('sales-funnel.checkout', ['type' => 'polar', 'product' => $entity->id]),
            default => null,
        };
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    // Accessors

    public function getTotalClicksAttribute(): int
    {
        return $this->clicks()->count();
    }

    public function getTotalConversionsAttribute(): int
    {
        return $this->conversions()->count();
    }

    public function getConversionRateAttribute(): float
    {
        $clicks = $this->total_clicks;
        if ($clicks === 0) {
            return 0;
        }
        return round(($this->total_conversions / $clicks) * 100, 2);
    }

    public function getEpcAttribute(): float
    {
        $clicks = $this->total_clicks;
        if ($clicks === 0) {
            return 0;
        }
        $revenue = $this->conversions()->where('type', 'purchase')->sum('amount');
        return round($revenue / $clicks, 2);
    }
}
