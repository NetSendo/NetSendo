<?php

namespace App\Services;

use App\Events\PixelVisitorActive;
use Illuminate\Support\Facades\Redis;

/**
 * Service for tracking live/active visitors via Redis
 */
class LiveVisitorService
{
    /**
     * TTL for active visitor entries (5 minutes)
     */
    protected const TTL_SECONDS = 300;

    /**
     * Redis key prefix for active visitors
     */
    protected const KEY_PREFIX = 'live_visitors:';

    /**
     * Mark a visitor as active and broadcast the event
     */
    public function markActive(
        int $userId,
        string $visitorToken,
        string $pageUrl,
        ?string $pageTitle,
        string $deviceType,
        ?string $browser
    ): void {
        $key = $this->getVisitorKey($userId, $visitorToken);
        $timestamp = now()->timestamp;

        $visitorData = [
            'visitor_token' => $visitorToken,
            'page_url' => $pageUrl,
            'page_title' => $pageTitle,
            'device_type' => $deviceType,
            'browser' => $browser,
            'last_seen' => $timestamp,
        ];

        // Store in Redis with TTL
        Redis::setex($key, self::TTL_SECONDS, json_encode($visitorData));

        // Add to user's active visitors set
        $setKey = $this->getUserSetKey($userId);
        Redis::sadd($setKey, $visitorToken);
        Redis::expire($setKey, self::TTL_SECONDS);

        // Broadcast to dashboard
        broadcast(new PixelVisitorActive(
            $userId,
            $visitorToken,
            $pageUrl,
            $pageTitle,
            $deviceType,
            $browser,
            $timestamp
        ))->toOthers();
    }

    /**
     * Get all active visitors for a user
     */
    public function getActiveVisitors(int $userId): array
    {
        $setKey = $this->getUserSetKey($userId);
        $visitorTokens = Redis::smembers($setKey);

        $visitors = [];
        $expiredTokens = [];

        foreach ($visitorTokens as $token) {
            $key = $this->getVisitorKey($userId, $token);
            $data = Redis::get($key);

            if ($data) {
                $visitors[] = json_decode($data, true);
            } else {
                // Token expired, remove from set
                $expiredTokens[] = $token;
            }
        }

        // Clean up expired tokens
        if (!empty($expiredTokens)) {
            Redis::srem($setKey, ...$expiredTokens);
        }

        // Sort by last_seen descending
        usort($visitors, fn($a, $b) => $b['last_seen'] <=> $a['last_seen']);

        return $visitors;
    }

    /**
     * Get the count of active visitors
     */
    public function getActiveVisitorCount(int $userId): int
    {
        return count($this->getActiveVisitors($userId));
    }

    /**
     * Remove a specific visitor (e.g., when they leave)
     */
    public function removeVisitor(int $userId, string $visitorToken): void
    {
        $key = $this->getVisitorKey($userId, $visitorToken);
        $setKey = $this->getUserSetKey($userId);

        Redis::del($key);
        Redis::srem($setKey, $visitorToken);
    }

    /**
     * Generate Redis key for a specific visitor
     */
    protected function getVisitorKey(int $userId, string $visitorToken): string
    {
        return self::KEY_PREFIX . $userId . ':' . $visitorToken;
    }

    /**
     * Generate Redis key for user's visitor set
     */
    protected function getUserSetKey(int $userId): string
    {
        return self::KEY_PREFIX . $userId . ':set';
    }
}
