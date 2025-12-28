<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StripeProduct extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'stripe_product_id',
        'stripe_price_id',
        'name',
        'description',
        'price',
        'currency',
        'type',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'price' => 'integer',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Get the user that owns the product.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the transactions for this product.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(StripeTransaction::class);
    }

    /**
     * Get formatted price with currency.
     */
    public function getFormattedPriceAttribute(): string
    {
        $amount = $this->price / 100;
        return number_format($amount, 2, ',', ' ') . ' ' . strtoupper($this->currency);
    }

    /**
     * Scope to get active products.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get products for a specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Get total revenue for this product.
     */
    public function getTotalRevenueAttribute(): int
    {
        return $this->transactions()
            ->where('status', 'completed')
            ->sum('amount');
    }

    /**
     * Get total sales count.
     */
    public function getSalesCountAttribute(): int
    {
        return $this->transactions()
            ->where('status', 'completed')
            ->count();
    }
}
