<?php

namespace App\Services\Nmi;

use App\Models\DedicatedIpAddress;
use App\Services\Deliverability\DnsLookupService;

/**
 * Reverse DNS of a dedicated IP, as receiving mail servers check it.
 *
 * Most of them refuse or distrust mail from an address unless its PTR record names a
 * host, and that host resolves back to the address (forward-confirmed reverse DNS).
 * The MTA says that same host name in HELO.
 */
class ReverseDnsChecker
{
    public function __construct(
        private DnsLookupService $dns
    ) {}

    /**
     * Look the address up and store what was found on the IP.
     *
     * @return array{ptr: ?string, expected: ?string, matches: bool, forward_confirmed: bool, verified: bool}
     */
    public function check(DedicatedIpAddress $ip): array
    {
        $expected = strtolower(rtrim(trim((string) $ip->hostname), '.')) ?: null;
        $pointers = $this->dns->lookupPtr($ip->ip_address);

        // The record that names the IP's own host, or whatever is there
        $ptr = $expected !== null && in_array($expected, $pointers, true)
            ? $expected
            : ($pointers[0] ?? null);

        $matches = $expected !== null && $ptr === $expected;
        $forward = $matches && $this->resolvesTo($expected, $ip->ip_address);
        $verified = $matches && $forward;

        $ip->forceFill([
            'ptr_record' => $ptr,
            'ptr_verified' => $verified,
            'ptr_verified_at' => $verified ? ($ip->ptr_verified ? $ip->ptr_verified_at : now()) : null,
        ])->save();

        return [
            'ptr' => $ptr,
            'expected' => $expected,
            'matches' => $matches,
            'forward_confirmed' => $forward,
            'verified' => $verified,
        ];
    }

    private function resolvesTo(string $host, string $ip): bool
    {
        $wanted = @inet_pton($ip);

        foreach ($this->dns->lookupAddresses($host) as $address) {
            if ($wanted !== false && @inet_pton($address) === $wanted) {
                return true;
            }
        }

        return false;
    }
}
