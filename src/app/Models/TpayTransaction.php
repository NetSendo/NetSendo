<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TpayTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'tpay_product_id',
        'subscriber_id',
        'tpay_transaction_id',
        'tpay_title',
        'customer_email',
        'customer_name',
        'amount',
        'currency',
        'status',
        'payment_method',
        'tr_crc',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * Get the user that owns the transaction.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the product for this transaction.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(TpayProduct::class, 'tpay_product_id');
    }

    /**
     * Get the subscriber linked to this transaction.
     */
    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class);
    }

    /**
     * Get formatted amount with currency.
     */
    public function getFormattedAmountAttribute(): string
    {
        $amount = $this->amount / 100;
        return number_format($amount, 2, ',', ' ') . ' ' . strtoupper($this->currency);
    }

    /**
     * Scope to get completed transactions.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope to get transactions for a specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get transactions by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Check if transaction is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if transaction is refunded.
     */
    public function isRefunded(): bool
    {
        return $this->status === 'refunded';
    }
}
