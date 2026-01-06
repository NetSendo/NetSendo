<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriberDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscriber_id',
        'user_id',
        'visitor_token',
        'device_fingerprint',
        'device_type',
        'browser',
        'browser_version',
        'os',
        'os_version',
        'screen_resolution',
        'language',
        'timezone',
        'ip_address',
        'user_agent',
        'first_seen_at',
        'last_seen_at',
    ];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    /**
     * Device type constants
     */
    public const TYPE_DESKTOP = 'desktop';
    public const TYPE_MOBILE = 'mobile';
    public const TYPE_TABLET = 'tablet';

    // Relationships

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pixelEvents(): HasMany
    {
        return $this->hasMany(PixelEvent::class);
    }

    // Scopes

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForVisitor($query, string $visitorToken)
    {
        return $query->where('visitor_token', $visitorToken);
    }

    public function scopeIdentified($query)
    {
        return $query->whereNotNull('subscriber_id');
    }

    public function scopeAnonymous($query)
    {
        return $query->whereNull('subscriber_id');
    }

    // Static methods

    /**
     * Find or create a device for a visitor token
     */
    public static function findOrCreateForVisitor(
        string $visitorToken,
        int $userId,
        array $deviceInfo = []
    ): self {
        $device = static::forUser($userId)
            ->forVisitor($visitorToken)
            ->first();

        if ($device) {
            // Update last seen and device info if provided
            $device->update([
                'last_seen_at' => now(),
                ...$deviceInfo,
            ]);
            return $device;
        }

        // Create new device
        return static::create([
            'visitor_token' => $visitorToken,
            'user_id' => $userId,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            ...$deviceInfo,
        ]);
    }

    /**
     * Link this device to a subscriber
     */
    public function linkToSubscriber(Subscriber $subscriber): void
    {
        if ($this->subscriber_id === $subscriber->id) {
            return;
        }

        $this->update(['subscriber_id' => $subscriber->id]);

        // Also link all events from this device to the subscriber
        $this->pixelEvents()
            ->whereNull('subscriber_id')
            ->update(['subscriber_id' => $subscriber->id]);
    }

    /**
     * Link all devices with the same visitor token to a subscriber
     */
    public static function linkVisitorToSubscriber(
        string $visitorToken,
        int $userId,
        int $subscriberId
    ): int {
        $devices = static::forUser($userId)
            ->forVisitor($visitorToken)
            ->whereNull('subscriber_id')
            ->get();

        foreach ($devices as $device) {
            $device->update(['subscriber_id' => $subscriberId]);

            // Link events too
            $device->pixelEvents()
                ->whereNull('subscriber_id')
                ->update(['subscriber_id' => $subscriberId]);
        }

        return $devices->count();
    }

    /**
     * Get device statistics for a user
     */
    public static function getStatsForUser(int $userId, int $days = 30): array
    {
        $since = now()->subDays($days);

        return [
            'total_devices' => static::forUser($userId)
                ->where('first_seen_at', '>=', $since)
                ->count(),
            'identified_devices' => static::forUser($userId)
                ->where('first_seen_at', '>=', $since)
                ->identified()
                ->count(),
            'by_type' => static::forUser($userId)
                ->where('first_seen_at', '>=', $since)
                ->selectRaw('device_type, COUNT(*) as count')
                ->groupBy('device_type')
                ->pluck('count', 'device_type')
                ->toArray(),
            'by_browser' => static::forUser($userId)
                ->where('first_seen_at', '>=', $since)
                ->whereNotNull('browser')
                ->selectRaw('browser, COUNT(*) as count')
                ->groupBy('browser')
                ->orderByDesc('count')
                ->limit(10)
                ->pluck('count', 'browser')
                ->toArray(),
        ];
    }

    /**
     * Get formatted device label
     */
    public function getDeviceLabelAttribute(): string
    {
        $parts = [];

        if ($this->browser) {
            $parts[] = $this->browser;
            if ($this->browser_version) {
                $parts[count($parts) - 1] .= ' ' . $this->browser_version;
            }
        }

        if ($this->os) {
            $parts[] = $this->os;
        }

        if ($this->device_type && $this->device_type !== self::TYPE_DESKTOP) {
            $parts[] = ucfirst($this->device_type);
        }

        return implode(' / ', $parts) ?: 'Unknown Device';
    }
}
