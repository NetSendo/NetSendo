<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'key_prefix',
        'key_hash',
        'encrypted_key',
        'permissions',
        'is_mcp',
        'last_used_at',
        'expires_at',
    ];

    protected $casts = [
        'permissions' => 'array',
        'is_mcp' => 'boolean',
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

    /**
     * Scope to get MCP keys
     */
    public function scopeMcp($query)
    {
        return $query->where('is_mcp', true);
    }

    /**
     * Get the API key marked as MCP key (for MCP status testing)
     */
    public static function getMcpKey(): ?self
    {
        return static::where('is_mcp', true)->active()->first();
    }

    /**
     * Mark this key as the MCP key (unmarks all others)
     * @param string|null $plainKey Plain key to encrypt for MCP testing
     */
    public function markAsMcp(?string $plainKey = null): void
    {
        // Unmark all other keys for this user and clear their encrypted keys
        static::where('user_id', $this->user_id)
            ->where('id', '!=', $this->id)
            ->update(['is_mcp' => false, 'encrypted_key' => null]);

        $updateData = ['is_mcp' => true];

        // Store encrypted key if provided
        if ($plainKey !== null) {
            $updateData['encrypted_key'] = Crypt::encryptString($plainKey);
        }

        $this->update($updateData);
    }

    /**
     * Get the decrypted plain API key (only available for MCP keys)
     */
    public function getDecryptedKey(): ?string
    {
        if (empty($this->encrypted_key)) {
            return null;
        }

        try {
            return Crypt::decryptString($this->encrypted_key);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Check if this key has an encrypted key stored for MCP testing
     */
    public function hasEncryptedKey(): bool
    {
        return !empty($this->encrypted_key);
    }

    /**
     * Unmark this key as MCP and clear encrypted key for security
     */
    public function unmarkAsMcp(): void
    {
        $this->update(['is_mcp' => false, 'encrypted_key' => null]);
    }
}
