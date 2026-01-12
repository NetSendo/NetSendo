<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class PluginConnection extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plugin_type',
        'site_url',
        'site_name',
        'plugin_version',
        'wp_version',
        'wc_version',
        'php_version',
        'last_heartbeat_at',
        'site_info',
        'is_active',
    ];

    protected $casts = [
        'site_info' => 'array',
        'is_active' => 'boolean',
        'last_heartbeat_at' => 'datetime',
    ];

    protected $appends = [
        'update_available',
        'latest_version',
        'is_stale',
    ];

    /**
     * Relationship to user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if plugin needs an update
     */
    public function needsUpdate(): bool
    {
        $latestVersion = $this->getLatestVersion();
        if (!$latestVersion) {
            return false;
        }

        return version_compare($this->plugin_version, $latestVersion, '<');
    }

    /**
     * Get the latest available version for this plugin type
     */
    public function getLatestVersion(): ?string
    {
        return config("netsendo.plugins.{$this->plugin_type}.version");
    }

    /**
     * Attribute: update_available
     */
    public function getUpdateAvailableAttribute(): bool
    {
        return $this->needsUpdate();
    }

    /**
     * Attribute: latest_version
     */
    public function getLatestVersionAttribute(): ?string
    {
        return $this->getLatestVersion();
    }

    /**
     * Check if connection is stale (no heartbeat in 7 days)
     */
    public function isStale(): bool
    {
        if (!$this->last_heartbeat_at) {
            return true;
        }

        return $this->last_heartbeat_at->lt(now()->subDays(7));
    }

    /**
     * Attribute: is_stale
     */
    public function getIsStaleAttribute(): bool
    {
        return $this->isStale();
    }

    /**
     * Get connections for a user
     */
    public static function forUser(int $userId)
    {
        return static::where('user_id', $userId)
            ->where('is_active', true)
            ->orderBy('plugin_type')
            ->orderBy('site_url')
            ->get();
    }

    /**
     * Get connections that need updates for a user
     */
    public static function needingUpdatesForUser(int $userId)
    {
        return static::forUser($userId)->filter(fn($conn) => $conn->needsUpdate());
    }

    /**
     * Get stale connections for a user
     */
    public static function staleForUser(int $userId)
    {
        return static::forUser($userId)->filter(fn($conn) => $conn->isStale());
    }

    /**
     * Format site URL for display (remove protocol)
     */
    public function getDisplayUrlAttribute(): string
    {
        return preg_replace('#^https?://#', '', rtrim($this->site_url, '/'));
    }

    /**
     * Get display name (site name or URL)
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->site_name ?: $this->display_url;
    }

    /**
     * Scope: active connections
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: by plugin type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('plugin_type', $type);
    }

    /**
     * Mark connection as active with current heartbeat
     */
    public function recordHeartbeat(array $data = []): void
    {
        $updateData = [
            'last_heartbeat_at' => now(),
            'is_active' => true,
        ];

        if (isset($data['plugin_version'])) {
            $updateData['plugin_version'] = $data['plugin_version'];
        }
        if (isset($data['wp_version'])) {
            $updateData['wp_version'] = $data['wp_version'];
        }
        if (isset($data['wc_version'])) {
            $updateData['wc_version'] = $data['wc_version'];
        }
        if (isset($data['php_version'])) {
            $updateData['php_version'] = $data['php_version'];
        }
        if (isset($data['site_name'])) {
            $updateData['site_name'] = $data['site_name'];
        }
        if (isset($data['site_info'])) {
            $updateData['site_info'] = $data['site_info'];
        }

        $this->update($updateData);
    }
}
