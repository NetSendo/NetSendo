<?php

namespace App\Services\Nmi;

use App\Models\DedicatedIpAddress;
use App\Models\DomainConfiguration;
use Illuminate\Support\Facades\Log;
use phpseclib3\Crypt\RSA;

class DkimKeyManager
{
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

        // Generate RSA key pair
        $privateKey = RSA::createKey(self::KEY_SIZE);
        $publicKey = $privateKey->getPublicKey();

        // Extract the public key in format suitable for DNS
        $publicKeyPem = $publicKey->toString('PKCS8');
        $publicKeyBase64 = $this->extractPublicKeyForDns($publicKeyPem);

        // Format the DNS TXT record value
        $dnsRecord = $this->formatDkimDnsRecord($publicKeyBase64);

        return [
            'selector' => $selector,
            'private_key' => $privateKey->toString('PKCS8'),
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
