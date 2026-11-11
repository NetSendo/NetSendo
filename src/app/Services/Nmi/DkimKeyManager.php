<?php

namespace App\Services\Nmi;

use App\Models\DedicatedIpAddress;
use App\Models\DomainConfiguration;
use App\Services\Deliverability\DnsLookupService;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class DkimKeyManager
{
    public function __construct(
        private DnsLookupService $dns
    ) {}

    /**
     * DKIM key size (2048 recommended, 1024 for compatibility)
     */
    private const KEY_SIZE = 2048;

    /**
     * Generate a new DKIM key pair for a domain or IP
     *
     * @param string $domain The sending domain
     * @param string|null $selector Custom selector (auto-generated if null)
     * @return array{selector: string, private_key: string, public_key: string, dns_record: string}
     */
    public function generateKeyPair(string $domain, ?string $selector = null): array
    {
        $selector = $selector ?? $this->generateSelector();

        // Generate the RSA key pair with PHP's OpenSSL extension. (The code used
        // phpseclib here, which was never a dependency of NetSendo: generating a key
        // ended in "Class phpseclib3\Crypt\RSA not found".)
        $key = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => max(1024, (int) config('nmi.dkim.key_size', self::KEY_SIZE)),
        ]);

        if ($key === false || ! openssl_pkey_export($key, $privateKeyPem)) {
            throw new RuntimeException('Could not generate a DKIM key: ' . (openssl_error_string() ?: 'OpenSSL error'));
        }

        // The public key as PEM (SubjectPublicKeyInfo): its base64 body is the DNS "p=" value
        $publicKeyPem = openssl_pkey_get_details($key)['key'];
        $publicKeyBase64 = $this->extractPublicKeyForDns($publicKeyPem);

        // Format the DNS TXT record value
        $dnsRecord = $this->formatDkimDnsRecord($publicKeyBase64);

        return [
            'selector' => $selector,
            'private_key' => $privateKeyPem,
            'public_key' => $publicKeyPem,
            'dns_record' => $dnsRecord,
            'dns_name' => "{$selector}._domainkey.{$domain}",
        ];
    }

    /**
     * Generate a DKIM key pair and save to a DedicatedIpAddress
     */
    public function generateForIp(DedicatedIpAddress $ip): array
    {
        $domain = $ip->domainConfiguration?->domain;

        if (!$domain) {
            throw new \InvalidArgumentException('IP must be assigned to a domain to generate DKIM keys');
        }

        $keyPair = $this->generateKeyPair($domain);

        $ip->update([
            'dkim_selector' => $keyPair['selector'],
            'dkim_private_key' => $keyPair['private_key'],
            'dkim_public_key' => $keyPair['public_key'],
            'dkim_generated_at' => now(),
        ]);

        Log::info('DKIM keys generated for IP', [
            'ip_id' => $ip->id,
            'ip_address' => $ip->ip_address,
            'domain' => $domain,
            'selector' => $keyPair['selector'],
        ]);

        return $keyPair;
    }

    /**
     * The domain's own DKIM key: generated once, kept until replaced on purpose.
     * A new key starts unverified, so mail is signed again only after its DNS record
     * has been published and checked (verifyForDomain).
     */
    public function generateForDomain(DomainConfiguration $domain, bool $replace = false): array
    {
        if ($domain->hasNmiDkimKey() && ! $replace) {
            return $this->getDomainDnsInstructions($domain);
        }

        $keyPair = $this->generateKeyPair($domain->domain);

        $domain->forceFill([
            'nmi_dkim_selector' => $keyPair['selector'],
            'nmi_dkim_private_key' => $keyPair['private_key'],
            'nmi_dkim_public_key' => $keyPair['public_key'],
            'nmi_dkim_generated_at' => now(),
            'nmi_dkim_verified_at' => null,
        ])->save();

        Log::info('DKIM key generated for domain', [
            'domain_id' => $domain->id,
            'domain' => $domain->domain,
            'selector' => $keyPair['selector'],
        ]);

        return $this->getDomainDnsInstructions($domain);
    }

    /**
     * The TXT record to publish for the domain's key, null when it has none
     */
    public function getDomainDnsInstructions(DomainConfiguration $domain): ?array
    {
        if (! $domain->hasNmiDkimKey()) {
            return null;
        }

        return [
            'record_type' => 'TXT',
            'selector' => $domain->nmi_dkim_selector,
            'name' => "{$domain->nmi_dkim_selector}._domainkey.{$domain->domain}",
            'value' => $this->formatDkimDnsRecord($this->extractPublicKeyForDns($domain->nmi_dkim_public_key)),
            'ttl' => 3600,
        ];
    }

    /**
     * Look the domain's DKIM record up in DNS. Mail is signed with the key from the moment
     * the record is seen; a record that has disappeared since switches signing off again.
     */
    public function verifyForDomain(DomainConfiguration $domain): bool
    {
        if (! $domain->hasNmiDkimKey()) {
            return false;
        }

        $name = "{$domain->nmi_dkim_selector}._domainkey.{$domain->domain}";
        $this->dns->clearCache($name);

        $expected = preg_replace('/\s+/', '', $this->extractPublicKeyForDns($domain->nmi_dkim_public_key));
        $found = false;

        foreach ($this->dns->lookupTxt($name) as $record) {
            // A long key is published as several quoted strings: compare without the seams
            if (str_contains(preg_replace('/[\s"]+/', '', (string) $record), $expected)) {
                $found = true;
                break;
            }
        }

        $domain->forceFill(['nmi_dkim_verified_at' => $found ? ($domain->nmi_dkim_verified_at ?? now()) : null])->save();

        return $found;
    }

    /**
     * Rotate DKIM keys for an IP (generate new keys)
     */
    public function rotateKeysForIp(DedicatedIpAddress $ip): array
    {
        $keyPair = $this->generateForIp($ip);

        $ip->update([
            'dkim_rotated_at' => now(),
        ]);

        Log::info('DKIM keys rotated for IP', [
            'ip_id' => $ip->id,
            'ip_address' => $ip->ip_address,
            'new_selector' => $keyPair['selector'],
        ]);

        return $keyPair;
    }

    /**
     * Get DNS instructions for setting up DKIM
     */
    public function getDnsInstructions(DedicatedIpAddress $ip): ?array
    {
        if (!$ip->dkim_selector || !$ip->dkim_public_key) {
            return null;
        }

        $domain = $ip->domainConfiguration?->domain;
        if (!$domain) {
            return null;
        }

        $publicKeyBase64 = $this->extractPublicKeyForDns($ip->dkim_public_key);

        return [
            'record_type' => 'TXT',
            'name' => "{$ip->dkim_selector}._domainkey.{$domain}",
            'value' => $this->formatDkimDnsRecord($publicKeyBase64),
            'ttl' => 3600,
        ];
    }

    /**
     * Verify DKIM DNS record is properly configured
     */
    public function verifyDkimRecord(DedicatedIpAddress $ip): bool
    {
        if (!$ip->dkim_selector || !$ip->dkim_public_key) {
            return false;
        }

        $domain = $ip->domainConfiguration?->domain;
        if (!$domain) {
            return false;
        }

        $dkimDomain = "{$ip->dkim_selector}._domainkey.{$domain}";

        try {
            $records = dns_get_record($dkimDomain, DNS_TXT);

            if (empty($records)) {
                return false;
            }

            $expectedKey = $this->extractPublicKeyForDns($ip->dkim_public_key);

            foreach ($records as $record) {
                $txt = $record['txt'] ?? '';

                // Remove whitespace and check if our key is present
                $normalizedTxt = preg_replace('/\s+/', '', $txt);
                $normalizedExpected = preg_replace('/\s+/', '', $expectedKey);

                if (str_contains($normalizedTxt, $normalizedExpected)) {
                    return true;
                }
            }

            return false;
        } catch (\Exception $e) {
            Log::warning('DKIM verification failed', [
                'ip_id' => $ip->id,
                'domain' => $dkimDomain,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Generate a unique selector
     */
    private function generateSelector(): string
    {
        // Format: ns-YYYYMMDD-XXXX
        return sprintf('ns-%s-%s', date('Ymd'), substr(md5(uniqid()), 0, 4));
    }

    /**
     * Extract public key content for DNS (without headers)
     */
    private function extractPublicKeyForDns(string $pemKey): string
    {
        $key = str_replace(['-----BEGIN PUBLIC KEY-----', '-----END PUBLIC KEY-----', "\n", "\r"], '', $pemKey);
        return trim($key);
    }

    /**
     * Format DKIM DNS TXT record value
     */
    private function formatDkimDnsRecord(string $publicKeyBase64): string
    {
        // Split into 255-character chunks for DNS TXT record limits
        $chunks = str_split($publicKeyBase64, 250);
        $publicKeyFormatted = implode('" "', $chunks);

        return "v=DKIM1; k=rsa; p={$publicKeyFormatted}";
    }

    /**
     * Check if keys need rotation (older than specified months)
     */
    public function needsRotation(DedicatedIpAddress $ip, int $monthsThreshold = 6): bool
    {
        if (!$ip->dkim_generated_at) {
            return true;
        }

        return $ip->dkim_generated_at->addMonths($monthsThreshold)->isPast();
    }

    /**
     * Get all IPs that need key rotation
     */
    public function getIpsNeedingRotation(int $monthsThreshold = 6): \Illuminate\Database\Eloquent\Collection
    {
        return DedicatedIpAddress::query()
            ->whereNotNull('dkim_generated_at')
            ->where('dkim_generated_at', '<', now()->subMonths($monthsThreshold))
            ->whereNotNull('domain_configuration_id')
            ->active()
            ->get();
    }
}
