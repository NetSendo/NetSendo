<?php

namespace App\Services\Nmi;

use App\Models\DedicatedIpAddress;
use App\Models\IpPool;
use Illuminate\Support\Facades\Log;

class IpWarmingService
{
    /**
     * Start warming process for an IP
     */
    public function startWarming(DedicatedIpAddress $ip): void
    {
        if ($ip->warming_status !== DedicatedIpAddress::WARMING_NEW) {
            throw new \InvalidArgumentException('IP is already warming or warmed');
        }

        $ip->startWarming();

        Log::info('IP warming started', [
            'ip_id' => $ip->id,
            'ip_address' => $ip->ip_address,
        ]);
    }

    /**
     * Advance warming day (called daily via scheduler)
     */
    public function advanceWarmingDay(DedicatedIpAddress $ip): void
    {
        if ($ip->warming_status !== DedicatedIpAddress::WARMING_WARMING) {
            return;
        }

        // Check if warming is complete
        $schedule = $ip->pool?->getWarmingScheduleWithDefaults() ?? IpPool::DEFAULT_WARMING_SCHEDULE;
        $maxDays = max(array_keys($schedule));

        if ($ip->warming_day >= $maxDays) {
            $ip->completeWarming();

            Log::info('IP warming completed', [
                'ip_id' => $ip->id,
                'ip_address' => $ip->ip_address,
                'total_days' => $ip->warming_day,
            ]);
            return;
        }

        // Calculate current daily limit based on warming day
        $ip->warming_daily_limit = $ip->getCurrentWarmingLimit();
        $ip->save();
    }

    /**
     * Get warming progress percentage
     */
    public function getWarmingProgress(DedicatedIpAddress $ip): float
    {
        if ($ip->warming_status === DedicatedIpAddress::WARMING_WARMED) {
            return 100.0;
        }

        if ($ip->warming_status === DedicatedIpAddress::WARMING_NEW) {
            return 0.0;
        }

        $schedule = $ip->pool?->getWarmingScheduleWithDefaults() ?? IpPool::DEFAULT_WARMING_SCHEDULE;
        $maxDays = max(array_keys($schedule));

        return min(100.0, round(($ip->warming_day / $maxDays) * 100, 1));
    }

    /**
     * Get warming status details for UI
     */
    public function getWarmingStatus(DedicatedIpAddress $ip): array
    {
        $schedule = $ip->pool?->getWarmingScheduleWithDefaults() ?? IpPool::DEFAULT_WARMING_SCHEDULE;
        $maxDays = max(array_keys($schedule));

        return [
            'status' => $ip->warming_status,
            'current_day' => $ip->warming_day,
            'total_days' => $maxDays,
            'progress_percent' => $this->getWarmingProgress($ip),
            'daily_limit' => $ip->getCurrentWarmingLimit(),
            'sent_today' => $ip->sent_today,
            'remaining_today' => max(0, $ip->getCurrentWarmingLimit() - $ip->sent_today),
            'started_at' => $ip->warming_started_at?->toIso8601String(),
            'estimated_completion' => $this->estimateCompletionDate($ip),
        ];
    }

    /**
     * Estimate warming completion date
     */
    public function estimateCompletionDate(DedicatedIpAddress $ip): ?string
    {
        if ($ip->warming_status !== DedicatedIpAddress::WARMING_WARMING) {
            return null;
        }

        $schedule = $ip->pool?->getWarmingScheduleWithDefaults() ?? IpPool::DEFAULT_WARMING_SCHEDULE;
        $maxDays = max(array_keys($schedule));
        $remainingDays = $maxDays - $ip->warming_day;

        return now()->addDays($remainingDays)->toDateString();
    }

    /**
     * Get recommended actions for warming issues
     */
    public function getWarmingRecommendations(DedicatedIpAddress $ip): array
    {
        $recommendations = [];

        // Check reputation
        if ($ip->reputation_score < 70) {
            $recommendations[] = [
                'type' => 'warning',
                'message' => 'nmi.warming.low_reputation',
                'action' => 'Reduce sending volume and check email content',
            ];
        }

        // Check blacklists
        if ($ip->isBlacklisted()) {
            $recommendations[] = [
                'type' => 'critical',
                'message' => 'nmi.warming.blacklisted',
                'action' => 'Pause sending and request delisting',
            ];
        }

        // Check bounce rate
        if ($ip->getBounceRate() > 5) {
            $recommendations[] = [
                'type' => 'warning',
                'message' => 'nmi.warming.high_bounces',
                'action' => 'Clean email list and verify addresses',
            ];
        }

        // Check complaint rate
        if ($ip->getComplaintRate() > 0.1) {
            $recommendations[] = [
                'type' => 'warning',
                'message' => 'nmi.warming.high_complaints',
                'action' => 'Review email content and sending frequency',
            ];
        }

        return $recommendations;
    }

    /**
     * Reset warming (when IP is problematic)
     */
    public function resetWarming(DedicatedIpAddress $ip): void
    {
        $ip->update([
            'warming_status' => DedicatedIpAddress::WARMING_NEW,
            'warming_started_at' => null,
            'warming_completed_at' => null,
            'warming_day' => 0,
            'warming_daily_limit' => null,
        ]);

        Log::info('IP warming reset', [
            'ip_id' => $ip->id,
            'ip_address' => $ip->ip_address,
        ]);
    }

    /**
     * Get all IPs currently warming
     */
    public function getWarmingIps(): \Illuminate\Database\Eloquent\Collection
    {
        return DedicatedIpAddress::warming()->active()->get();
    }

    /**
     * Process daily warming updates (called by scheduler)
     */
    public function processDailyWarmingUpdates(): int
    {
        $warmingIps = $this->getWarmingIps();
        $processed = 0;

        foreach ($warmingIps as $ip) {
            try {
                $this->advanceWarmingDay($ip);
                $processed++;
            } catch (\Exception $e) {
                Log::error('Failed to advance warming day', [
                    'ip_id' => $ip->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $processed;
    }
}
