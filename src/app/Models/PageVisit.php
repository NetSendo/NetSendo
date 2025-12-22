<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageVisit extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscriber_id',
        'user_id',
        'page_url',
        'page_title',
        'referrer',
        'ip_address',
        'user_agent',
        'visitor_token',
        'time_on_page_seconds',
        'visited_at',
    ];

    protected $casts = [
        'visited_at' => 'datetime',
    ];

    // Relationships

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForSubscriber($query, int $subscriberId)
    {
        return $query->where('subscriber_id', $subscriberId);
    }

    public function scopeForUrl($query, string $url)
    {
        return $query->where('page_url', $url);
    }

    public function scopeUrlContains($query, string $pattern)
    {
        return $query->where('page_url', 'like', '%' . $pattern . '%');
    }

    public function scopeWithinDays($query, int $days)
    {
        return $query->where('visited_at', '>=', now()->subDays($days));
    }

    // Static helpers

    /**
     * Get visit statistics for a user.
     */
    public static function getStatsForUser(int $userId, int $days = 7): array
    {
        $since = now()->subDays($days);

        $query = static::forUser($userId)->where('visited_at', '>=', $since);

        return [
            'total_visits' => $query->count(),
            'unique_visitors' => $query->distinct('visitor_token')->count('visitor_token'),
            'unique_subscribers' => $query->whereNotNull('subscriber_id')
                ->distinct('subscriber_id')
                ->count('subscriber_id'),
            'top_pages' => static::forUser($userId)
                ->where('visited_at', '>=', $since)
                ->select('page_url', \DB::raw('COUNT(*) as visits'))
                ->groupBy('page_url')
                ->orderByDesc('visits')
                ->limit(10)
                ->get(),
            'average_time_on_page' => round($query->avg('time_on_page_seconds') ?? 0),
        ];
    }

    /**
     * Check if a subscriber visited a specific URL pattern.
     */
    public static function subscriberVisitedUrl(int $subscriberId, string $urlPattern, ?int $withinDays = null): bool
    {
        $query = static::forSubscriber($subscriberId);

        if ($withinDays) {
            $query->withinDays($withinDays);
        }

        // Check for exact match or pattern match
        if (str_contains($urlPattern, '*')) {
            $pattern = str_replace('*', '%', $urlPattern);
            return $query->where('page_url', 'like', $pattern)->exists();
        }

        return $query->forUrl($urlPattern)->exists();
    }

    /**
     * Get all pages visited by a subscriber.
     */
    public static function getPagesForSubscriber(int $subscriberId, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return static::forSubscriber($subscriberId)
            ->orderByDesc('visited_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Try to identify a visitor by their token and link to subscriber.
     */
    public static function linkVisitorToSubscriber(string $visitorToken, int $subscriberId): int
    {
        return static::where('visitor_token', $visitorToken)
            ->whereNull('subscriber_id')
            ->update(['subscriber_id' => $subscriberId]);
    }
}
