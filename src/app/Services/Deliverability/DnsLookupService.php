<?php

namespace App\Services\Deliverability;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DnsLookupService
{
    /**
     * Cache TTL in seconds (5 minutes)
     */
    private const CACHE_TTL = 300;

    /**
     * Lookup TXT records for a domain
     */
    public function lookupTxt(string $domain): array
    {
        $cacheKey = "dns_txt_{$domain}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($domain) {
            try {
                $records = dns_get_record($domain, DNS_TXT);
                return array_map(fn($r) => $r['txt'] ?? '', $records ?: []);
            } catch (\Exception $e) {
                Log::warning("DNS TXT lookup failed for {$domain}: " . $e->getMessage());
                return [];
            }
        });
    }

    /**
     * Lookup CNAME record for a domain
     */
    public function lookupCname(string $subdomain): ?string
    {
        $cacheKey = "dns_cname_{$subdomain}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($subdomain) {
            try {
                $records = dns_get_record($subdomain, DNS_CNAME);
                return $records[0]['target'] ?? null;
            } catch (\Exception $e) {
                Log::warning("DNS CNAME lookup failed for {$subdomain}: " . $e->getMessage());
                return null;
            }
        });
    }

    /**
     * The name the PTR record of an IP address points to (reverse DNS), or null
     */
    public function reverse(string $ip): ?string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        return Cache::remember("dns_ptr_{$ip}", 60, function () use ($ip) {
            $name = @gethostbyaddr($ip);

            // gethostbyaddr answers with the address itself when there is no PTR record
            if (!is_string($name) || $name === '' || $name === $ip) {
                return null;
            }

            return strtolower(rtrim($name, '.'));
        });
    }

    /**
     * IPv4 and IPv6 addresses a name points to (A and AAAA records)
     *
     * @return list<string>
     */
    public function addresses(string $name): array
    {
        $name = strtolower(rtrim($name, '.'));
        if ($name === '') {
            return [];
        }

        return Cache::remember("dns_addresses_{$name}", 60, function () use ($name) {
            try {
                $records = @dns_get_record($name, DNS_A | DNS_AAAA) ?: [];
            } catch (\Throwable $e) {
                Log::warning("DNS A/AAAA lookup failed for {$name}: " . $e->getMessage());
                $records = [];
            }

            $addresses = [];
            foreach ($records as $record) {
                $address = $record['ip'] ?? $record['ipv6'] ?? null;
                if (is_string($address) && ($packed = @inet_pton($address)) !== false) {
                    $addresses[] = inet_ntop($packed);
                }
            }

            return array_values(array_unique($addresses));
        });
    }

    /**
     * Get SPF record for a domain
     */
    public function getSpfRecord(string $domain): ?string
    {
        $txtRecords = $this->lookupTxt($domain);

        foreach ($txtRecords as $record) {
            if (str_starts_with(strtolower($record), 'v=spf1')) {
                return $record;
            }
        }

        return null;
    }

    /**
     * Get DKIM record for a domain and selector
     */
    public function getDkimRecord(string $domain, string $selector = 'netsendo'): ?string
    {
        $dkimDomain = "{$selector}._domainkey.{$domain}";
        $txtRecords = $this->lookupTxt($dkimDomain);

        foreach ($txtRecords as $record) {
            $lower = strtolower($record);
            // "v=DKIM1" is optional (RFC 6376 3.6.1): Amazon SES and others publish only "p=..."
            if (str_contains($lower, 'v=dkim1') || preg_match('/(^|;)\s*p=/', $lower)) {
                return $record;
            }
        }

        return null;
    }

    /**
     * Get DMARC record for a domain
     */
    public function getDmarcRecord(string $domain): ?string
    {
        $dmarcDomain = "_dmarc.{$domain}";
        $txtRecords = $this->lookupTxt($dmarcDomain);

        foreach ($txtRecords as $record) {
            if (str_starts_with(strtolower($record), 'v=dmarc1')) {
                return $record;
            }
        }

        return null;
    }

    /**
     * Parse SPF record into components
     */
    public function parseSpfRecord(string $spf): array
    {
        $result = [
            'version' => 'spf1',
            'mechanisms' => [],
            'includes' => [],
            'all' => null,
            'is_valid' => true,
        ];

        $parts = preg_split('/\s+/', $spf);

        foreach ($parts as $part) {
            $part = strtolower(trim($part));

            if ($part === 'v=spf1') {
                continue;
            }

            if (str_starts_with($part, 'include:')) {
                $result['includes'][] = substr($part, 8);
            } elseif (preg_match('/^[+\-~?]?all$/', $part)) {
                $result['all'] = $part;
            } elseif (!empty($part)) {
                $result['mechanisms'][] = $part;
            }
        }

        return $result;
    }

    /**
     * Parse DMARC record into components
     */
    public function parseDmarcRecord(string $dmarc): array
    {
        $result = [
            'version' => 'DMARC1',
            'policy' => 'none',
            'rua' => null,
            'ruf' => null,
            'pct' => 100,
            'is_valid' => true,
        ];

        $parts = explode(';', $dmarc);

        foreach ($parts as $part) {
            $part = trim($part);

            if (str_starts_with(strtolower($part), 'v=dmarc1')) {
                continue;
            }

            if (preg_match('/^p=(none|quarantine|reject)$/i', $part, $matches)) {
                $result['policy'] = strtolower($matches[1]);
            } elseif (preg_match('/^rua=(.+)$/i', $part, $matches)) {
                $result['rua'] = $matches[1];
            } elseif (preg_match('/^ruf=(.+)$/i', $part, $matches)) {
                $result['ruf'] = $matches[1];
            } elseif (preg_match('/^pct=(\d+)$/i', $part, $matches)) {
                $result['pct'] = (int) $matches[1];
            }
        }

        return $result;
    }

    /**
     * Forget the cached PTR record of an IP and the addresses of a name
     */
    public function forgetAddressRecords(string $ip, string $name): void
    {
        Cache::forget("dns_ptr_{$ip}");
        Cache::forget('dns_addresses_' . strtolower(rtrim($name, '.')));
    }

    /**
     * Clear DNS cache for a domain
     */
    public function clearCache(string $domain): void
    {
        Cache::forget("dns_txt_{$domain}");
        Cache::forget("dns_cname__netsendo.{$domain}");
        Cache::forget("dns_txt__dmarc.{$domain}");
    }
}
