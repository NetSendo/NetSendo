<?php

namespace App\Services\Mail;

use App\Models\Mailbox;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class MailboxReputationService
{
    /**
     * Domain-based blacklists to check (DNSBL/URIBL)
     */
    private const BLACKLISTS = [
        'spamhaus_dbl' => [
            'zone' => 'dbl.spamhaus.org',
            'name' => 'Spamhaus DBL',
            'severity' => 'critical',
            'description' => 'Main domain blacklist maintained by Spamhaus',
        ],
        'surbl' => [
            'zone' => 'multi.surbl.org',
            'name' => 'SURBL',
            'severity' => 'high',
            'description' => 'Spam URI Realtime Blocklist',
        ],
        'uribl' => [
            'zone' => 'multi.uribl.com',
            'name' => 'URIBL',
            'severity' => 'high',
            'description' => 'URI-based blacklist',
        ],
        'spamhaus_zrd' => [
            'zone' => 'zrd.spamhaus.org',
            'name' => 'Spamhaus ZRD',
            'severity' => 'medium',
            'description' => 'Zero Reputation Domain list — newly registered domains',
        ],
        'barracuda_domain' => [
            'zone' => 'bsb.spamlookup.net',
            'name' => 'Barracuda Domain BL',
            'severity' => 'medium',
            'description' => 'Barracuda domain-level blocklist',
        ],
        'sem_fresh' => [
            'zone' => 'fresh.spameatingmonkey.net',
            'name' => 'SEM FRESH',
            'severity' => 'low',
            'description' => 'Newly registered domains list',
        ],
        'sem_uri' => [
            'zone' => 'uribl.spameatingmonkey.net',
            'name' => 'SEM URIBL',
            'severity' => 'low',
            'description' => 'Spam Eating Monkey URI blocklist',
        ],
    ];

    /**
     * Cache TTL for reputation checks (2 hours)
     */
    private const CACHE_TTL = 7200;

    /**
     * Check a domain against all blacklists
     */
    public function checkDomain(string $domain): array
    {
        $results = [];

        foreach (self::BLACKLISTS as $key => $config) {
            $results[$key] = [
                'name' => $config['name'],
                'listed' => $this->checkDomainDnsbl($domain, $config['zone']),
                'severity' => $config['severity'],
                'zone' => $config['zone'],
                'description' => $config['description'],
            ];
        }

        return $results;
    }

    /**
     * Check and update reputation status for a Mailbox
     */
    public function checkAndUpdateMailbox(Mailbox $mailbox): array
    {
        $domain = $this->extractDomain($mailbox->from_email);

        if (!$domain) {
            Log::warning('Mailbox reputation check: invalid from_email', [
                'mailbox_id' => $mailbox->id,
                'from_email' => $mailbox->from_email,
            ]);

            return [];
        }

        $cacheKey = "reputation_check:{$domain}";

        // Check cache first (multiple mailboxes may share a domain)
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            // Still update the mailbox record even if cached
            $this->persistResults($mailbox, $cached);
            return $cached;
        }

        $results = $this->checkDomain($domain);

        // Convert to simple status array for storage
        $status = [];
        foreach ($results as $key => $result) {
            $status[$key] = $result['listed'];
        }

        $this->persistResults($mailbox, $results);

        // Cache results by domain (shared across mailboxes with same domain)
        Cache::put($cacheKey, $results, self::CACHE_TTL);

        Log::info('Mailbox reputation check completed', [
            'mailbox_id' => $mailbox->id,
            'domain' => $domain,
            'listed_count' => count(array_filter($status)),
        ]);

        return $results;
    }

    /**
     * Persist check results to the mailbox
     */
    private function persistResults(Mailbox $mailbox, array $results): void
    {
        $status = [];
        foreach ($results as $key => $result) {
            $status[$key] = $result['listed'];
        }

        $listedCount = count(array_filter($status));
        $overall = 'clean';

        if ($listedCount > 0) {
            $hasCritical = collect($results)
                ->filter(fn($r) => $r['listed'] && in_array($r['severity'], ['critical', 'high']))
                ->isNotEmpty();

            $overall = $hasCritical ? 'critical' : 'warning';
        }

        $mailbox->update([
            'reputation_status' => $status,
            'reputation_checked_at' => now(),
            'reputation_overall' => $overall,
        ]);
    }

    /**
     * Get summary for a mailbox
     */
    public function getSummary(Mailbox $mailbox): array
    {
        $status = $mailbox->reputation_status ?? [];
        $listedCount = count(array_filter($status));
        $totalChecked = count($status);

        return [
            'overall' => $mailbox->reputation_overall ?? 'unchecked',
            'listed_count' => $listedCount,
            'total_checked' => $totalChecked,
            'last_checked' => $mailbox->reputation_checked_at?->toIso8601String(),
            'needs_check' => $this->needsCheck($mailbox),
            'domain' => $this->extractDomain($mailbox->from_email),
        ];
    }

    /**
     * Get detailed blacklist info for a mailbox
     */
    public function getDetails(Mailbox $mailbox): array
    {
        $status = $mailbox->reputation_status ?? [];
        $details = [];
        $domain = $this->extractDomain($mailbox->from_email);

        foreach (self::BLACKLISTS as $key => $config) {
            $details[] = [
                'key' => $key,
                'name' => $config['name'],
                'listed' => $status[$key] ?? false,
                'severity' => $config['severity'],
                'zone' => $config['zone'],
                'description' => $config['description'],
                'lookup_url' => $this->getLookupUrl($key, $domain),
            ];
        }

        return $details;
    }

    /**
     * Check if mailbox needs a fresh reputation check
     */
    public function needsCheck(Mailbox $mailbox): bool
    {
        if (!$mailbox->reputation_checked_at) {
            return true;
        }

        // If listed, check more frequently (every 4 hours)
        $threshold = ($mailbox->reputation_overall === 'critical' || $mailbox->reputation_overall === 'warning') ? 4 : 12;

        return $mailbox->reputation_checked_at->addHours($threshold)->isPast();
    }

    /**
     * Get all mailboxes that need reputation check
     */
    public function getMailboxesNeedingCheck(): \Illuminate\Database\Eloquent\Collection
    {
        return Mailbox::query()
            ->active()
            ->where(function ($q) {
                $q->whereNull('reputation_checked_at')
                  ->orWhere('reputation_checked_at', '<', now()->subHours(12));
            })
            ->get();
    }

    /**
     * Process reputation checks for all mailboxes (scheduled task)
     */
    public function processScheduledChecks(): int
    {
        $mailboxes = $this->getMailboxesNeedingCheck();
        $checked = 0;
        $domainsChecked = [];

        foreach ($mailboxes as $mailbox) {
            try {
                $domain = $this->extractDomain($mailbox->from_email);

                if (!$domain) {
                    continue;
                }

                // Skip DNS lookups if we already checked this domain in this batch
                // (but still update the mailbox record via cache)
                $this->checkAndUpdateMailbox($mailbox);
                $checked++;

                // Small delay to avoid DNS rate limiting (only for new domains)
                if (!in_array($domain, $domainsChecked)) {
                    $domainsChecked[] = $domain;
                    usleep(200000); // 200ms
                }
            } catch (\Exception $e) {
                Log::error('Mailbox reputation check failed', [
                    'mailbox_id' => $mailbox->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $checked;
    }

    /**
     * Extract domain from email address
     */
    public function extractDomain(?string $email): ?string
    {
        if (!$email || !str_contains($email, '@')) {
            return null;
        }

        return strtolower(substr($email, strpos($email, '@') + 1));
    }

    /**
     * Check a single DNSBL for a domain
     *
     * Domain-based blocklists work differently from IP-based ones:
     * Just query domain.zone directly (no IP reversal needed)
     *
     * IMPORTANT: Many DNSBLs (Spamhaus, SURBL, URIBL) return special error codes
     * when queries come from public/shared DNS resolvers (Google 8.8.8.8, Cloudflare, etc.).
     * These must NOT be treated as "listed" results.
     */
    private function checkDomainDnsbl(string $domain, string $zone): bool
    {
        $lookup = $domain . '.' . $zone;

        try {
            $results = @dns_get_record($lookup, DNS_A);

            if (empty($results)) {
                return false;
            }

            // Validate each returned IP — filter out error/test/block responses
            foreach ($results as $record) {
                $ip = $record['ip'] ?? '';

                if (empty($ip)) {
                    continue;
                }

                // Spamhaus error/block responses (public resolver detected):
                // 127.255.255.252 = Typing error in DNSBL name
                // 127.255.255.254 = Query via public/open resolver
                // 127.255.255.255 = Excessive query volume
                if (str_starts_with($ip, '127.255.255.')) {
                    Log::debug("DNSBL {$zone}: error response {$ip} for {$domain} (public resolver?)");
                    continue;
                }

                // Some DNSBLs use 127.0.0.1 or 127.0.0.2 as test/error
                // Valid listing responses are typically 127.0.0.x (x > 1) or 127.0.1.x
                // But 127.0.0.0/24 with x=1 is sometimes "test"
                if ($ip === '127.0.0.1') {
                    continue;
                }

                // SURBL/URIBL error responses — 127.0.0.x where x > 128 can indicate errors
                // Valid SURBL responses are bit-masked in the last octet (2, 4, 8, 16, 32, 64)
                if ($zone === 'multi.surbl.org' || $zone === 'multi.uribl.com') {
                    $lastOctet = (int) substr($ip, strrpos($ip, '.') + 1);
                    // Valid listing bits: 2, 4, 8, 16, 32, 64 (values 1-127)
                    // Error range: >= 128
                    if ($lastOctet >= 128) {
                        Log::debug("DNSBL {$zone}: error response {$ip} for {$domain}");
                        continue;
                    }
                }

                // If we reach here, it's a genuine listing response
                Log::info("DNSBL {$zone}: domain {$domain} IS LISTED (response: {$ip})");
                return true;
            }

            // All responses were error/block codes — domain is NOT actually listed
            return false;
        } catch (\Exception $e) {
            // DNS error — assume not listed
            Log::debug("DNSBL {$zone}: DNS error for {$domain}: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Get lookup URL for a blacklist so user can investigate / delist
     */
    private function getLookupUrl(string $blacklist, ?string $domain): ?string
    {
        if (!$domain) {
            return null;
        }

        $urls = [
            'spamhaus_dbl' => "https://check.spamhaus.org/listed/?searchterm={$domain}",
            'surbl' => "https://surbl.org/surbl-analysis?d={$domain}",
            'uribl' => "https://lookup.uribl.com/?domain={$domain}",
            'spamhaus_zrd' => "https://check.spamhaus.org/listed/?searchterm={$domain}",
            'barracuda_domain' => "https://www.barracudacentral.org/lookups",
            'sem_fresh' => "https://spameatingmonkey.com/lookup/{$domain}",
            'sem_uri' => "https://spameatingmonkey.com/lookup/{$domain}",
        ];

        return $urls[$blacklist] ?? null;
    }

    /**
     * Get available blacklist list (for UI display)
     */
    public static function getBlacklists(): array
    {
        return self::BLACKLISTS;
    }
}
