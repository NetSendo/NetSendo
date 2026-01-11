<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;

class WooCommerceSettings extends Model
{
    use HasFactory;

    protected $table = 'woocommerce_settings';

    protected $fillable = [
        'user_id',
        'name',
        'store_url',
        'consumer_key',
        'consumer_secret',
        'is_active',
        'last_synced_at',
        'connection_verified_at',
        'store_info',
        'is_default',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'last_synced_at' => 'datetime',
        'connection_verified_at' => 'datetime',
        'store_info' => 'array',
    ];

    protected $hidden = [
        'consumer_key',
        'consumer_secret',
    ];

    /**
     * Encrypt the consumer secret before saving
     */
    public function setConsumerSecretAttribute($value): void
    {
        $this->attributes['consumer_secret'] = Crypt::encryptString($value);
    }

    /**
     * Decrypt the consumer secret when reading
     */
    public function getConsumerSecretAttribute($value): ?string
    {
        if (!$value) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Relationship to user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all stores for a user
     */
    public static function forUser(int $userId): Collection
    {
        return static::where('user_id', $userId)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get default store for a user
     */
    public static function getDefaultForUser(int $userId): ?self
    {
        return static::where('user_id', $userId)
            ->where('is_default', true)
            ->first()
            ?? static::where('user_id', $userId)->first();
    }

    /**
     * Get a specific store by ID for a user
     */
    public static function getByIdForUser(int $id, int $userId): ?self
    {
        return static::where('id', $id)
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * Check if connection is verified
     */
    public function isConnected(): bool
    {
        return $this->is_active && $this->connection_verified_at !== null;
    }

    /**
     * Format store URL (ensure no trailing slash)
     */
    public function setStoreUrlAttribute($value): void
    {
        $this->attributes['store_url'] = rtrim(trim($value), '/');
    }

    /**
     * Get the WooCommerce API base URL
     */
    public function getApiUrl(): string
    {
        return $this->store_url . '/wp-json/wc/v3';
    }

    /**
     * Mark connection as verified
     */
    public function markAsVerified(array $storeInfo = []): void
    {
        $this->update([
            'connection_verified_at' => now(),
            'store_info' => $storeInfo,
        ]);
    }

    /**
     * Mark last sync time
     */
    public function markSynced(): void
    {
        $this->update(['last_synced_at' => now()]);
    }

    /**
     * Set this store as default and unset others for the same user
     */
    public function setAsDefault(): void
    {
        // Unset other defaults for this user
        static::where('user_id', $this->user_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        // Set this one as default
        $this->update(['is_default' => true]);
    }

    /**
     * Get display name (name or URL)
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->name ?: parse_url($this->store_url, PHP_URL_HOST) ?: $this->store_url;
    }
}
