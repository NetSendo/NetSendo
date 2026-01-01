<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebinarAnalytic extends Model
{
    public $timestamps = false;

    // Event type constants
    public const EVENT_PAGE_VIEW = 'page_view';
    public const EVENT_REGISTRATION = 'registration';
    public const EVENT_JOIN = 'join';
    public const EVENT_LEAVE = 'leave';
    public const EVENT_VIDEO_PLAY = 'video_play';
    public const EVENT_VIDEO_PAUSE = 'video_pause';
    public const EVENT_VIDEO_PROGRESS = 'video_progress';
    public const EVENT_CHAT_SENT = 'chat_sent';
    public const EVENT_PRODUCT_VIEW = 'product_view';
    public const EVENT_PRODUCT_CLICK = 'product_click';
    public const EVENT_CTA_VIEW = 'cta_view';
    public const EVENT_CTA_CLICK = 'cta_click';
    public const EVENT_PURCHASE = 'purchase';
    public const EVENT_SHARE = 'share';
    public const EVENT_REACTION = 'reaction';
    public const EVENT_POLL_VOTE = 'poll_vote';

    protected $fillable = [
        'webinar_id',
        'webinar_session_id',
        'registration_id',
        'event_type',
        'video_time_seconds',
        'metadata',
        'ip_address',
        'user_agent',
        'device_type',
        'browser',
        'country',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'video_time_seconds' => 'integer',
        'created_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($analytic) {
            if (!$analytic->created_at) {
                $analytic->created_at = now();
            }
        });
    }

    // =====================================
    // Relationships
    // =====================================

    public function webinar(): BelongsTo
    {
        return $this->belongsTo(Webinar::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(WebinarSession::class, 'webinar_session_id');
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(WebinarRegistration::class, 'registration_id');
    }

    // =====================================
    // Scopes
    // =====================================

    public function scopeOfType($query, string $type)
    {
        return $query->where('event_type', $type);
    }

    public function scopeForSession($query, int $sessionId)
    {
        return $query->where('webinar_session_id', $sessionId);
    }

    public function scopeWithinPeriod($query, $start, $end)
    {
        return $query->whereBetween('created_at', [$start, $end]);
    }

    public function scopeConversions($query)
    {
        return $query->whereIn('event_type', [
            self::EVENT_PRODUCT_CLICK,
            self::EVENT_CTA_CLICK,
            self::EVENT_PURCHASE,
        ]);
    }

    // =====================================
    // Static Methods
    // =====================================

    /**
     * Track an event.
     */
    public static function track(
        Webinar $webinar,
        string $eventType,
        ?WebinarSession $session = null,
        ?WebinarRegistration $registration = null,
        ?int $videoTimeSeconds = null,
        ?array $metadata = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): self {
        $deviceInfo = self::parseUserAgent($userAgent);

        return self::create([
            'webinar_id' => $webinar->id,
            'webinar_session_id' => $session?->id,
            'registration_id' => $registration?->id,
            'event_type' => $eventType,
            'video_time_seconds' => $videoTimeSeconds,
            'metadata' => $metadata,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'device_type' => $deviceInfo['device_type'],
            'browser' => $deviceInfo['browser'],
            'country' => self::getCountryFromIp($ipAddress),
        ]);
    }

    /**
     * Parse user agent to get device and browser info.
     */
    protected static function parseUserAgent(?string $userAgent): array
    {
        $deviceType = 'desktop';
        $browser = 'unknown';

        if (!$userAgent) {
            return compact('device_type', 'browser');
        }

        // Simple device detection
        if (preg_match('/Mobile|Android|iPhone|iPad/i', $userAgent)) {
            $deviceType = preg_match('/iPad|Tablet/i', $userAgent) ? 'tablet' : 'mobile';
        }

        // Simple browser detection
        if (preg_match('/Chrome/i', $userAgent)) {
            $browser = 'Chrome';
        } elseif (preg_match('/Firefox/i', $userAgent)) {
            $browser = 'Firefox';
        } elseif (preg_match('/Safari/i', $userAgent)) {
            $browser = 'Safari';
        } elseif (preg_match('/Edge/i', $userAgent)) {
            $browser = 'Edge';
        }

        return [
            'device_type' => $deviceType,
            'browser' => $browser,
        ];
    }

    /**
     * Get country from IP (placeholder - implement with GeoIP service).
     */
    protected static function getCountryFromIp(?string $ip): ?string
    {
        // TODO: Integrate with GeoIP service (MaxMind, IP2Location, etc.)
        return null;
    }

    /**
     * Get aggregated stats for a webinar.
     */
    public static function getWebinarStats(Webinar $webinar): array
    {
        $query = self::where('webinar_id', $webinar->id);

        return [
            'page_views' => (clone $query)->ofType(self::EVENT_PAGE_VIEW)->count(),
            'registrations' => (clone $query)->ofType(self::EVENT_REGISTRATION)->count(),
            'joins' => (clone $query)->ofType(self::EVENT_JOIN)->count(),
            'chat_messages' => (clone $query)->ofType(self::EVENT_CHAT_SENT)->count(),
            'product_clicks' => (clone $query)->ofType(self::EVENT_PRODUCT_CLICK)->count(),
            'cta_clicks' => (clone $query)->ofType(self::EVENT_CTA_CLICK)->count(),
            'purchases' => (clone $query)->ofType(self::EVENT_PURCHASE)->count(),
            'total_revenue' => (clone $query)
                ->ofType(self::EVENT_PURCHASE)
                ->get()
                ->sum(fn($e) => $e->metadata['amount'] ?? 0),
        ];
    }

    /**
     * Get engagement timeline.
     */
    public static function getEngagementTimeline(Webinar $webinar, ?WebinarSession $session = null): array
    {
        $query = self::where('webinar_id', $webinar->id)
            ->whereNotNull('video_time_seconds')
            ->whereIn('event_type', [self::EVENT_JOIN, self::EVENT_LEAVE]);

        if ($session) {
            $query->where('webinar_session_id', $session->id);
        }

        // Group by minute
        $events = $query->get()->groupBy(fn($e) => floor($e->video_time_seconds / 60));

        $timeline = [];
        foreach ($events as $minute => $minuteEvents) {
            $joins = $minuteEvents->where('event_type', self::EVENT_JOIN)->count();
            $leaves = $minuteEvents->where('event_type', self::EVENT_LEAVE)->count();
            $timeline[$minute] = [
                'minute' => $minute,
                'joins' => $joins,
                'leaves' => $leaves,
                'net_change' => $joins - $leaves,
            ];
        }

        return array_values($timeline);
    }

    /**
     * Get conversion funnel.
     */
    public static function getConversionFunnel(Webinar $webinar): array
    {
        $query = self::where('webinar_id', $webinar->id);

        $pageViews = (clone $query)->ofType(self::EVENT_PAGE_VIEW)->distinct('ip_address')->count('ip_address');
        $registrations = (clone $query)->ofType(self::EVENT_REGISTRATION)->count();
        $joins = (clone $query)->ofType(self::EVENT_JOIN)->distinct('registration_id')->count('registration_id');
        $productClicks = (clone $query)->ofType(self::EVENT_PRODUCT_CLICK)->distinct('registration_id')->count('registration_id');
        $purchases = (clone $query)->ofType(self::EVENT_PURCHASE)->distinct('registration_id')->count('registration_id');

        return [
            ['stage' => 'Page Views', 'count' => $pageViews, 'rate' => 100],
            ['stage' => 'Registrations', 'count' => $registrations, 'rate' => $pageViews > 0 ? round(($registrations / $pageViews) * 100, 1) : 0],
            ['stage' => 'Joined', 'count' => $joins, 'rate' => $registrations > 0 ? round(($joins / $registrations) * 100, 1) : 0],
            ['stage' => 'Product Clicks', 'count' => $productClicks, 'rate' => $joins > 0 ? round(($productClicks / $joins) * 100, 1) : 0],
            ['stage' => 'Purchases', 'count' => $purchases, 'rate' => $productClicks > 0 ? round(($purchases / $productClicks) * 100, 1) : 0],
        ];
    }

    /**
     * Get device breakdown.
     */
    public static function getDeviceBreakdown(Webinar $webinar): array
    {
        return self::where('webinar_id', $webinar->id)
            ->ofType(self::EVENT_JOIN)
            ->selectRaw('device_type, COUNT(*) as count')
            ->groupBy('device_type')
            ->pluck('count', 'device_type')
            ->toArray();
    }

    /**
     * Get event types.
     */
    public static function getEventTypes(): array
    {
        return [
            self::EVENT_PAGE_VIEW => 'Page View',
            self::EVENT_REGISTRATION => 'Registration',
            self::EVENT_JOIN => 'Join',
            self::EVENT_LEAVE => 'Leave',
            self::EVENT_VIDEO_PLAY => 'Video Play',
            self::EVENT_VIDEO_PAUSE => 'Video Pause',
            self::EVENT_VIDEO_PROGRESS => 'Video Progress',
            self::EVENT_CHAT_SENT => 'Chat Sent',
            self::EVENT_PRODUCT_VIEW => 'Product View',
            self::EVENT_PRODUCT_CLICK => 'Product Click',
            self::EVENT_CTA_VIEW => 'CTA View',
            self::EVENT_CTA_CLICK => 'CTA Click',
            self::EVENT_PURCHASE => 'Purchase',
            self::EVENT_SHARE => 'Share',
            self::EVENT_REACTION => 'Reaction',
            self::EVENT_POLL_VOTE => 'Poll Vote',
        ];
    }
}
