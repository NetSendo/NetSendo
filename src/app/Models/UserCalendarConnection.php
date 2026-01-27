<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class UserCalendarConnection extends Model
{
    protected $fillable = [
        'user_id',
        'google_integration_id',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'calendar_id',
        'connected_email',
        'channel_id',
        'resource_id',
        'channel_expires_at',
        'is_active',
        'auto_sync_tasks',
        'sync_settings',
        'task_type_colors',
        'sync_token',
        'last_synced_at',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'channel_expires_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'is_active' => 'boolean',
        'auto_sync_tasks' => 'boolean',
        'sync_settings' => 'array',
        'task_type_colors' => 'array',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    /**
     * Get the user that owns this connection.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the Google integration used for this connection.
     */
    public function googleIntegration(): BelongsTo
    {
        return $this->belongsTo(GoogleIntegration::class);
    }

    /**
     * Check if the access token is expired.
     */
    public function isTokenExpired(): bool
    {
        if (!$this->token_expires_at) {
            return true;
        }

        // Consider expired if less than 5 minutes remaining
        return $this->token_expires_at->subMinutes(5)->isPast();
    }

    /**
     * Check if the connection is active.
     */
    public function isActive(): bool
    {
        return $this->is_active && !$this->isTokenExpired();
    }

    /**
     * Check if the webhook channel is expired.
     */
    public function isChannelExpired(): bool
    {
        if (!$this->channel_expires_at) {
            return true;
        }

        return $this->channel_expires_at->isPast();
    }

    /**
     * Check if push notifications are set up.
     */
    public function hasPushNotifications(): bool
    {
        return !empty($this->channel_id) && !empty($this->resource_id) && !$this->isChannelExpired();
    }

    /**
     * Get decrypted access token.
     */
    public function getDecryptedAccessToken(): ?string
    {
        if (empty($this->access_token)) {
            return null;
        }

        try {
            return Crypt::decryptString($this->access_token);
        } catch (\Exception $e) {
            // Token might be stored unencrypted (legacy)
            return $this->access_token;
        }
    }

    /**
     * Get decrypted refresh token.
     */
    public function getDecryptedRefreshToken(): ?string
    {
        if (empty($this->refresh_token)) {
            return null;
        }

        try {
            return Crypt::decryptString($this->refresh_token);
        } catch (\Exception $e) {
            // Token might be stored unencrypted (legacy)
            return $this->refresh_token;
        }
    }

    /**
     * Set encrypted access token.
     */
    public function setAccessTokenAttribute($value): void
    {
        $this->attributes['access_token'] = $value ? Crypt::encryptString($value) : null;
    }

    /**
     * Set encrypted refresh token.
     */
    public function setRefreshTokenAttribute($value): void
    {
        $this->attributes['refresh_token'] = $value ? Crypt::encryptString($value) : null;
    }

    /**
     * Update tokens from OAuth response.
     */
    public function updateTokens(array $tokenData): void
    {
        $this->access_token = $tokenData['access_token'];
        $this->token_expires_at = now()->addSeconds($tokenData['expires_in'] ?? 3600);

        if (isset($tokenData['refresh_token'])) {
            $this->refresh_token = $tokenData['refresh_token'];
        }

        $this->save();
    }

    /**
     * Update push notification channel.
     */
    public function updateChannel(string $channelId, string $resourceId, int $expirationSeconds): void
    {
        $this->update([
            'channel_id' => $channelId,
            'resource_id' => $resourceId,
            'channel_expires_at' => now()->addSeconds($expirationSeconds),
        ]);
    }

    /**
     * Clear push notification channel.
     */
    public function clearChannel(): void
    {
        $this->update([
            'channel_id' => null,
            'resource_id' => null,
            'channel_expires_at' => null,
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
     * Scope to get connections with auto-sync enabled.
     */
    public function scopeAutoSync($query)
    {
        return $query->where('auto_sync_tasks', true);
    }

    /**
     * Scope to get connections needing channel refresh.
     */
    public function scopeNeedsChannelRefresh($query)
    {
        return $query->active()
            ->where(function ($q) {
                $q->whereNull('channel_expires_at')
                    ->orWhere('channel_expires_at', '<', now()->addHours(1));
            });
    }

    /**
     * Default colors for task types.
     */
    public const DEFAULT_TASK_TYPE_COLORS = [
        'call' => '#8B5CF6',      // Purple
        'email' => '#3B82F6',     // Blue
        'meeting' => '#EF4444',   // Red
        'task' => '#10B981',      // Green
        'follow_up' => '#F59E0B', // Amber
    ];

    /**
     * Get color for a specific task type.
     */
    public function getTaskTypeColor(string $type): string
    {
        $colors = $this->task_type_colors ?? [];

        return $colors[$type] ?? self::DEFAULT_TASK_TYPE_COLORS[$type] ?? '#6B7280';
    }

    /**
     * Get all task type colors (merged with defaults).
     */
    public function getAllTaskTypeColors(): array
    {
        return array_merge(
            self::DEFAULT_TASK_TYPE_COLORS,
            $this->task_type_colors ?? []
        );
    }
}
