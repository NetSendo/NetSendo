<?php

namespace App\Services\Nmi;

use App\Models\DedicatedIpAddress;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class BlacklistMonitorService
{
    /**
     * DNS-based blacklists to check
     */
    private const BLACKLISTS = [
        'spamhaus' => [
            'zone' => 'zen.spamhaus.org',
            'name' => 'Spamhaus ZEN',
            'severity' => 'critical',
        ],
        'spamcop' => [
            'zone' => 'bl.spamcop.net',
            'name' => 'SpamCop',
            'severity' => 'high',
        ],
        'barracuda' => [
            'zone' => 'b.barracudacentral.org',
            'name' => 'Barracuda',
            'severity' => 'high',
        ],
        'sorbs' => [
            'zone' => 'dnsbl.sorbs.net',
            'name' => 'SORBS',
            'severity' => 'medium',
        ],
        'uceprotect_1' => [
            'zone' => 'dnsbl-1.uceprotect.net',
            'name' => 'UCEPROTECT Level 1',
            'severity' => 'medium',
        ],
        'uceprotect_2' => [
            'zone' => 'dnsbl-2.uceprotect.net',
            'name' => 'UCEPROTECT Level 2',
            'severity' => 'low',
        ],
        'cbl' => [
            'zone' => 'cbl.abuseat.org',
            'name' => 'CBL',
            'severity' => 'high',
        ],
        'psbl' => [
            'zone' => 'psbl.surriel.com',
            'name' => 'Passive Spam Block List',
            'severity' => 'medium',
        ],
    ];

    /**
     * Cache TTL for blacklist checks (1 hour)
     */
    private const CACHE_TTL = 3600;

    /**
     * Check an IP against all blacklists
     */
    public function checkIp(string $ipAddress): array
    {
        $results = [];
        $reversed = $this->reverseIp($ipAddress);

        foreach (self::BLACKLISTS as $key => $config) {
            $results[$key] = [
                'name' => $config['name'],
                'listed' => $this->checkDnsbl($reversed, $config['zone']),
                'severity' => $config['severity'],
                'zone' => $config['zone'],
            ];
        }

        return $results;
    }

    /**
     * Check and update blacklist status for a DedicatedIpAddress
     */
    public function checkAndUpdate(DedicatedIpAddress $ip): array
    {
        $cacheKey = "blacklist_check:{$ip->ip_address}";

        // Check cache first
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $results = $this->checkIp($ip->ip_address);

        // Convert to simple status array for storage
        $status = [];
        foreach ($results as $key => $result) {
            $status[$key] = $result['listed'];
        }

        $ip->update([
            'blacklist_status' => $status,
            'blacklist_checked_at' => now(),
        ]);

        // Cache results
        Cache::put($cacheKey, $results, self::CACHE_TTL);

        Log::info('Blacklist check completed', [
            'ip_id' => $ip->id,
            'ip_address' => $ip->ip_address,
            'listed_count' => count(array_filter($status)),
        ]);

        return $results;
    }

    /**
     * Get summary for an IP
     */
    public function getSummary(DedicatedIpAddress $ip): array
    {
        $status = $ip->blacklist_status ?? [];
        $listedCount = count(array_filter($status));
        $totalChecked = count($status);

        // Determine overall status
        $overallStatus = 'clean';
        if ($listedCount > 0) {
            $results = $this->checkIp($ip->ip_address);
            $hasCritical = collect($results)
                ->filter(fn($r) => $r['listed'] && $r['severity'] === 'critical')
                ->isNotEmpty();

            $overallStatus = $hasCritical ? 'critical' : 'warning';
        }

        return [
            'status' => $overallStatus,
            'listed_count' => $listedCount,
            'total_checked' => $totalChecked,
            'last_checked' => $ip->blacklist_checked_at?->toIso8601String(),
            'needs_check' => $this->needsCheck($ip),
        ];
    }

    /**
     * Get detailed blacklist info for an IP
     */
    public function getDetails(DedicatedIpAddress $ip): array
    {
        $status = $ip->blacklist_status ?? [];
        $details = [];

        foreach (self::BLACKLISTS as $key => $config) {
            $details[] = [
                'key' => $key,
                'name' => $config['name'],
                'listed' => $status[$key] ?? false,
                'severity' => $config['severity'],
                'delist_url' => $this->getDelistUrl($key, $ip->ip_address),
            ];
        }

        return $details;
    }

    /**
     * Check if IP needs a fresh blacklist check
     */
    public function needsCheck(DedicatedIpAddress $ip): bool
    {
        if (!$ip->blacklist_checked_at) {
            return true;
        }

        // If listed, check more frequently (every 6 hours)
        $threshold = $ip->isBlacklisted() ? 6 : 24;

        return $ip->blacklist_checked_at->addHours($threshold)->isPast();
    }

    /**
     * Get all IPs that need blacklist check
     */
    public function getIpsNeedingCheck(): \Illuminate\Database\Eloquent\Collection
    {
        return DedicatedIpAddress::query()
            ->active()
            ->where(function ($q) {
                $q->whereNull('blacklist_checked_at')
                  ->orWhere('blacklist_checked_at', '<', now()->subHours(24));
            })
            ->get();
    }

    /**
     * Process blacklist checks for all IPs (scheduled task)
     */
    public function processScheduledChecks(): int
    {
        $ips = $this->getIpsNeedingCheck();
        $checked = 0;

        foreach ($ips as $ip) {
            try {
                $this->checkAndUpdate($ip);
                $checked++;

                // Small delay to avoid rate limiting
                usleep(100000); // 100ms
            } catch (\Exception $e) {
                Log::error('Blacklist check failed', [
                    'ip_id' => $ip->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $checked;
    }

    /**
     * Reverse an IP address for DNSBL lookup
     */
    private function reverseIp(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return implode('.', array_reverse(explode('.', $ip)));
        }

        // IPv6 support
        $ip = inet_pton($ip);
        $hex = bin2hex($ip);
        return implode('.', array_reverse(str_split($hex)));
    }

    /**
     * Check a single DNSBL
     */
    private function checkDnsbl(string $reversedIp, string $zone): bool
    {
        $lookup = $reversedIp . '.' . $zone;

        try {
            $results = dns_get_record($lookup, DNS_A);
            return !empty($results);
        } catch (\Exception $e) {
            // DNS error - assume not listed
            return false;
        }
    }

    /**
     * Get delist URL for a blacklist
     */
    private function getDelistUrl(string $blacklist, string $ip): ?string
    {
        $urls = [
            'spamhaus' => "https://check.spamhaus.org/listed/?searchterm={$ip}",
            'spamcop' => "https://www.spamcop.net/bl.shtml?{$ip}",
            'barracuda' => "https://www.barracudacentral.org/lookups/lookup-reputation?lookup_entry={$ip}",
            'sorbs' => "http://www.sorbs.net/lookup.shtml?{$ip}",
            'cbl' => "https://www.abuseat.org/lookup.cgi?ip={$ip}",
        ];

        return $urls[$blacklist] ?? null;
    }
}
