<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class UserZoomConnection extends Model
{
    protected $fillable = [
        'user_id',
        'zoom_user_id',
        'zoom_email',
        'granted_scopes',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'is_active',
    ];

    /**
     * Get parsed scopes as array.
     */
    public function getParsedScopes(): array
    {
        if (!$this->granted_scopes) {
            return [];
        }

        return array_filter(array_map('trim', explode(' ', $this->granted_scopes)));
    }

    protected $casts = [
        'token_expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Get the user that owns this connection.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the access token is expired.
     */
    public function isTokenExpired(): bool
    {
        if (!$this->token_expires_at) {
            return true;
        }

        // Consider token expired 5 minutes before actual expiration
        return $this->token_expires_at->subMinutes(5)->isPast();
    }

    /**
     * Get decrypted access token.
     */
    public function getDecryptedAccessToken(): ?string
    {
        if (!$this->access_token) {
            return null;
        }

        try {
            return Crypt::decryptString($this->access_token);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get decrypted refresh token.
     */
    public function getDecryptedRefreshToken(): ?string
    {
        if (!$this->refresh_token) {
            return null;
        }

        try {
            return Crypt::decryptString($this->refresh_token);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Update tokens from OAuth response.
     */
    public function updateTokens(array $tokens): void
    {
        $this->update([
            'access_token' => Crypt::encryptString($tokens['access_token']),
            'refresh_token' => isset($tokens['refresh_token'])
                ? Crypt::encryptString($tokens['refresh_token'])
                : $this->refresh_token,
            'token_expires_at' => now()->addSeconds($tokens['expires_in'] ?? 3600),
        ]);
    }

    /**
     * Scope to get active connections.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get connection for a specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}
