<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AffiliatePayout extends Model
{
    use HasFactory;

    protected $fillable = [
        'program_id',
        'affiliate_id',
        'period_start',
        'period_end',
        'total_amount',
        'currency',
        'status',
        'payment_reference',
        'meta',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'total_amount' => 'decimal:2',
        'meta' => 'array',
    ];

    // Relationships

    public function program(): BelongsTo
    {
        return $this->belongsTo(AffiliateProgram::class, 'program_id');
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(AffiliatePayoutItem::class, 'payout_id');
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(AffiliateCommission::class, 'payout_id');
    }

    // Scopes

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    // Accessors

    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->total_amount, 2, ',', ' ') . ' ' . strtoupper($this->currency);
    }

    public function getPeriodLabelAttribute(): string
    {
        return $this->period_start->format('d.m.Y') . ' - ' . $this->period_end->format('d.m.Y');
    }

    public function getCommissionsCountAttribute(): int
    {
        return $this->items()->count();
    }

    // Methods

    public function markAsProcessing(): void
    {
        $this->update(['status' => 'processing']);
    }

    public function markAsCompleted(?string $reference = null): void
    {
        $this->update([
            'status' => 'completed',
            'payment_reference' => $reference,
        ]);

        // Mark all associated commissions as paid
        $this->commissions()->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);
    }

    public function markAsFailed(): void
    {
        $this->update(['status' => 'failed']);
    }
}
