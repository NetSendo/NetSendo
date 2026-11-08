<?php

namespace App\Services\Nmi;

use App\Models\DedicatedIpAddress;
use App\Services\Deliverability\DnsLookupService;

/**
 * The name NetSendo MTA gives itself to the recipients' mail servers (HELO), decided here
 * and passed to the MTA in the login file (MtaAuthFile), so nothing is set in the MTA:
 * - from the server's main address: NMI_HOSTNAME, otherwise the panel's domain (APP_URL);
 * - from a dedicated IP: the name of that IP, set in the panel.
 *
 * Receiving servers look up the PTR record of the sending IP (set at the provider of the
 * server, not in the domain's DNS) and expect it to point to this name, and the name to
 * point back to the IP. check() and checkIp() say whether that is so.
 */
class MailServerName
{
    public function __construct(private DnsLookupService $dns)
    {
    }

    /**
     * Name for mail from the server's main address, or null when there is no usable one
     * (APP_URL on localhost or on an IP address): the MTA then uses its own config/me
     */
    public function default(): ?string
    {
        $name = trim((string) config('nmi.hostname'));
        if ($name === '') {
            $name = (string) parse_url((string) config('app.url'), PHP_URL_HOST);
        }

        return self::normalize($name);
    }

    /**
     * Name for mail from a dedicated IP, or null (the default name is used then)
     */
    public function forIp(DedicatedIpAddress $ip): ?string
    {
        return self::normalize((string) $ip->hostname);
    }

    /**
     * A host name a mail server can introduce itself with: at least two labels of
     * letters, digits and hyphens, not an IP address
     */
    public static function isValid(string $name): bool
    {
        $name = rtrim(trim($name), '.');

        return strlen($name) <= 253
            && filter_var($name, FILTER_VALIDATE_IP) === false
            && preg_match('/^(?=.{1,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z][a-z0-9-]{0,61}[a-z0-9]$/i', $name) === 1;
    }

    public static function normalize(string $name): ?string
    {
        $name = strtolower(rtrim(trim($name), '.'));

        return self::isValid($name) ? $name : null;
    }

    /**
     * Address in the form the MTA compares (IPv6 shortened, lower case), or null
     */
    public static function normalizeIp(string $ip): ?string
    {
        $packed = @inet_pton(trim($ip));

        return $packed === false ? null : inet_ntop($packed);
    }

    /**
     * Whether the default name is ready for the receiving servers: an address it points to
     * has a PTR record pointing back to it.
     *
     * @return array{name: ?string, ok: bool, addresses: list<array{ip: string, ptr: ?string}>}
     */
    public function check(): array
    {
        $name = $this->default();
        if ($name === null) {
            return ['name' => null, 'ok' => false, 'addresses' => []];
        }

        $addresses = array_map(
            fn (string $ip) => ['ip' => $ip, 'ptr' => $this->dns->reverse($ip)],
            $this->dns->addresses($name)
        );

        return [
            'name' => $name,
            'ok' => collect($addresses)->contains(fn (array $address) => $address['ptr'] === $name),
            'addresses' => $addresses,
        ];
    }

    /**
     * Whether a dedicated IP and its name point at each other (PTR of the IP = the name,
     * A/AAAA record of the name = the IP)
     *
     * @return array{name: ?string, ptr: ?string, name_addresses: list<string>, ok: bool}
     */
    public function checkIp(DedicatedIpAddress $ip): array
    {
        $name = $this->forIp($ip);
        $address = self::normalizeIp((string) $ip->ip_address);
        $ptr = $address ? $this->dns->reverse($address) : null;
        $nameAddresses = $name ? $this->dns->addresses($name) : [];

        return [
            'name' => $name,
            'ptr' => $ptr,
            'name_addresses' => $nameAddresses,
            'ok' => $name !== null && $ptr === $name && in_array($address, $nameAddresses, true),
        ];
    }
}
