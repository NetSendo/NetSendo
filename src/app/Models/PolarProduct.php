<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PolarProduct extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'polar_product_id',
        'polar_price_id',
        'name',
        'description',
        'price',
        'currency',
        'type',
        'billing_interval',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'price' => 'integer',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Get formatted price with currency.
     */
    public function getFormattedPriceAttribute(): string
    {
        $amount = $this->price / 100;
        return number_format($amount, 2) . ' ' . strtoupper($this->currency);
    }

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
        return $this->hasMany(PolarTransaction::class);
    }

    /**
     * Scope for active products.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for products by user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}
