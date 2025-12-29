<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PolarTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'polar_product_id',
        'polar_checkout_id',
        'polar_order_id',
        'polar_subscription_id',
        'polar_customer_id',
        'customer_email',
        'customer_name',
        'amount',
        'currency',
        'status',
        'type',
        'subscriber_id',
        'metadata',
        'refunded_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'metadata' => 'array',
        'refunded_at' => 'datetime',
    ];

    /**
     * Get formatted amount with currency.
     */
    public function getFormattedAmountAttribute(): string
    {
        $amount = $this->amount / 100;
        return number_format($amount, 2) . ' ' . strtoupper($this->currency);
    }

    /**
     * Get the user that owns the transaction.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the product associated with this transaction.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(PolarProduct::class, 'polar_product_id');
    }

    /**
     * Get the subscriber associated with this transaction.
     */
    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class);
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

    /**
     * Scope for completed transactions.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for transactions by user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for transactions by email.
     */
    public function scopeByEmail($query, $email)
    {
        return $query->where('customer_email', $email);
    }
}
