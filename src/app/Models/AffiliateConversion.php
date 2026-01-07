<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AffiliateConversion extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'affiliate_id',
        'offer_id',
        'click_id',
        'type',
        'entity_type',
        'entity_id',
        'amount',
        'currency',
        'customer_email',
        'customer_name',
        'order_id',
        'meta',
        'created_at',
    ];

    protected $casts = [
        'entity_id' => 'integer',
        'amount' => 'decimal:2',
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    // Relationships

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(AffiliateOffer::class, 'offer_id');
    }

    public function click(): BelongsTo
    {
        return $this->belongsTo(AffiliateClick::class, 'click_id');
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(AffiliateCommission::class, 'conversion_id');
    }

    public function fraudFlags(): HasMany
    {
        return $this->hasMany(AffiliateFraudFlag::class, 'conversion_id');
    }

    // Scopes

    public function scopeLeads($query)
    {
        return $query->where('type', 'lead');
    }

    public function scopePurchases($query)
    {
        return $query->where('type', 'purchase');
    }

    public function scopeRefunds($query)
    {
        return $query->where('type', 'refund');
    }

    public function scopeForAffiliate($query, $affiliateId)
    {
        return $query->where('affiliate_id', $affiliateId);
    }

    public function scopeForOffer($query, $offerId)
    {
        return $query->where('offer_id', $offerId);
    }

    // Accessors

    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount, 2, ',', ' ') . ' ' . strtoupper($this->currency);
    }

    public function getIsPurchaseAttribute(): bool
    {
        return $this->type === 'purchase';
    }

    public function getIsLeadAttribute(): bool
    {
        return $this->type === 'lead';
    }

    // Static Methods

    public static function recordLead(
        int $affiliateId,
        int $offerId,
        ?int $clickId,
        ?string $entityType,
        ?int $entityId,
        ?string $customerEmail,
        ?string $customerName,
        ?array $meta = null
    ): self {
        return self::create([
            'affiliate_id' => $affiliateId,
            'offer_id' => $offerId,
            'click_id' => $clickId,
            'type' => 'lead',
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'amount' => 0,
            'currency' => 'PLN',
            'customer_email' => $customerEmail,
            'customer_name' => $customerName,
            'meta' => $meta,
            'created_at' => now(),
        ]);
    }

    public static function recordPurchase(
        int $affiliateId,
        int $offerId,
        ?int $clickId,
        float $amount,
        string $currency,
        ?string $entityType,
        ?int $entityId,
        ?string $orderId,
        ?string $customerEmail,
        ?string $customerName,
        ?array $meta = null
    ): self {
        return self::create([
            'affiliate_id' => $affiliateId,
            'offer_id' => $offerId,
            'click_id' => $clickId,
            'type' => 'purchase',
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'amount' => $amount,
            'currency' => $currency,
            'order_id' => $orderId,
            'customer_email' => $customerEmail,
            'customer_name' => $customerName,
            'meta' => $meta,
            'created_at' => now(),
        ]);
    }
}
