<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateCommission extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversion_id',
        'affiliate_id',
        'offer_id',
        'level',
        'commission_amount',
        'currency',
        'status',
        'available_at',
        'approved_at',
        'paid_at',
        'payout_id',
        'rejection_reason',
        'meta',
    ];

    protected $casts = [
        'level' => 'integer',
        'commission_amount' => 'decimal:2',
        'available_at' => 'datetime',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
        'meta' => 'array',
    ];

    // Relationships

    public function conversion(): BelongsTo
    {
        return $this->belongsTo(AffiliateConversion::class, 'conversion_id');
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(AffiliateOffer::class, 'offer_id');
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(AffiliatePayout::class, 'payout_id');
    }

    // Scopes

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePayable($query)
    {
        return $query->where('status', 'payable');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeUnpaid($query)
    {
        return $query->whereIn('status', ['pending', 'approved', 'payable']);
    }

    public function scopeForAffiliate($query, $affiliateId)
    {
        return $query->where('affiliate_id', $affiliateId);
    }

    // Accessors

    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->commission_amount, 2, ',', ' ') . ' ' . strtoupper($this->currency);
    }

    public function getIsPendingAttribute(): bool
    {
        return $this->status === 'pending';
    }

    public function getCanBeApprovedAttribute(): bool
    {
        return in_array($this->status, ['pending']);
    }

    public function getCanBeRejectedAttribute(): bool
    {
        return in_array($this->status, ['pending', 'approved']);
    }

    public function getCanBePaidAttribute(): bool
    {
        return $this->status === 'payable';
    }

    // Methods

    public function approve(): void
    {
        $this->update([
            'status' => 'approved',
            'approved_at' => now(),
        ]);
    }

    public function makePayable(): void
    {
        $this->update([
            'status' => 'payable',
            'available_at' => now(),
        ]);
    }

    public function markAsPaid(?int $payoutId = null): void
    {
        $this->update([
            'status' => 'paid',
            'paid_at' => now(),
            'payout_id' => $payoutId,
        ]);
    }

    public function reject(string $reason): void
    {
        $this->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
        ]);
    }

    public function reverse(string $reason = 'Refund'): void
    {
        $this->update([
            'status' => 'reversed',
            'rejection_reason' => $reason,
        ]);
    }
}
