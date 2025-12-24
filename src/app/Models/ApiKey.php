<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'key_prefix',
        'key_hash',
        'permissions',
        'last_used_at',
        'expires_at',
    ];

    protected $casts = [
        'permissions' => 'array',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Available API permissions
     */
    public const PERMISSIONS = [
        'subscribers:read',
        'subscribers:write',
        'lists:read',
        'tags:read',
        'webhooks:read',
        'webhooks:write',
        'sms:read',
        'sms:write',
    ];

    /**
     * Generate a new API key
     * Returns the plain key (only shown once!) and the model
     */
    public static function generate(int $userId, string $name, array $permissions = []): array
    {
        // Generate a secure random key: ns_live_<32 random chars>
        $plainKey = 'ns_live_' . Str::random(32);
        $prefix = substr($plainKey, 0, 12); // ns_live_xxxx

        $apiKey = static::create([
            'user_id' => $userId,
            'name' => $name,
            'key_prefix' => $prefix,
            'key_hash' => hash('sha256', $plainKey),
            'permissions' => $permissions ?: self::PERMISSIONS,
        ]);

        return [
            'key' => $plainKey,
            'model' => $apiKey,
        ];
    }

    /**
     * Find API key by plain key
     */
    public static function findByKey(string $plainKey): ?self
    {
        if (!str_starts_with($plainKey, 'ns_live_')) {
            return null;
        }

        $hash = hash('sha256', $plainKey);

        return static::where('key_hash', $hash)->first();
    }

    /**
     * Verify if a plain key matches this API key
     */
    public function verify(string $plainKey): bool
    {
        return hash_equals($this->key_hash, hash('sha256', $plainKey));
    }

    /**
     * Check if key has a specific permission
     */
    public function hasPermission(string $permission): bool
    {
        if (empty($this->permissions)) {
            return false;
        }

        // Check exact match
        if (in_array($permission, $this->permissions)) {
            return true;
        }

        // Check wildcard (e.g., permission 'subscribers:write' grants 'subscribers:read')
        $parts = explode(':', $permission);
        if (count($parts) === 2 && $parts[1] === 'read') {
            $writePermission = $parts[0] . ':write';
            return in_array($writePermission, $this->permissions);
        }

        return false;
    }

    /**
     * Check if key is expired
     */
    public function isExpired(): bool
    {
        if (!$this->expires_at) {
            return false;
        }

        return $this->expires_at->isPast();
    }

    /**
     * Mark the key as used (update last_used_at)
     */
    public function markAsUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Get the user that owns the API key
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to get active (non-expired) keys
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }
}
