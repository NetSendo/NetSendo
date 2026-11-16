<?php

namespace App\Services\Nmi\Providers;

use Illuminate\Http\Client\Response;

/**
 * Linode / Akamai Connected Cloud (API v4, https://techdocs.akamai.com/linode-api/reference/api).
 *
 * An extra address is allocated straight to a Linode; the Linode gets the public address
 * itself. Three things are Akamai's rules, not NetSendo's:
 *
 *  - additional IPv4 addresses need their support's approval first ("Additional IPv4
 *    addresses require technical justification"), otherwise the request is refused;
 *  - reverse DNS is only accepted once the host name already resolves to the address
 *    ("Forward DNS must already be set up for reverse DNS to be applied");
 *  - the address appears in the operating system after a reboot (Network Helper) or
 *    after it is added by hand.
 *
 * A token with the "Linodes: Read/Write" scope is enough for everything done here.
 */
class LinodeProvider extends HttpIpProvider
{
    private const PAGE_SIZE = 500;

    /** Capability a Linode has when outbound SMTP is not restricted on it */
    private const SMTP_CAPABILITY = 'SMTP Enabled';

    public function id(): string
    {
        return 'linode';
    }

    public function name(): string
    {
        return 'Linode';
    }

    protected function baseUrl(): string
    {
        return 'https://api.linode.com/v4';
    }

    /** {"errors": [{"reason": "...", "field": "rdns"}]}: there can be several */
    protected function errorReason(Response $response): ?string
    {
        $reasons = array_filter(array_map(
            fn ($error) => is_array($error) ? ($error['reason'] ?? null) : null,
            (array) $response->json('errors')
        ));

        return $reasons === [] ? null : implode(' ', $reasons);
    }

    /** /profile needs no scope, so it works with any valid token */
    public function account(): array
    {
        $profile = $this->request('GET', 'profile')->json() ?? [];

        return [
            'name' => $profile['username'] ?? null,
            'email' => $profile['email'] ?? null,
        ];
    }

    public function servers(): array
    {
        $servers = [];
        $page = 1;

        do {
            $result = $this->request('GET', 'linode/instances', ['page' => $page, 'page_size' => self::PAGE_SIZE])->json() ?? [];

            foreach ($result['data'] ?? [] as $linode) {
                $capabilities = $linode['capabilities'] ?? null;

                $servers[] = [
                    'id' => (string) $linode['id'],
                    'label' => ($linode['label'] ?? '') ?: (string) $linode['id'],
                    'region' => $linode['region'] ?? null,
                    // `ipv4` mixes public and private addresses
                    'addresses' => array_values(array_filter(
                        (array) ($linode['ipv4'] ?? []),
                        fn ($ip) => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
                    )),
                    // Outbound ports 25, 465 and 587 are restricted on some newer accounts
                    'smtp_restricted' => is_array($capabilities) ? ! in_array(self::SMTP_CAPABILITY, $capabilities, true) : null,
                ];
            }

            $pages = (int) ($result['pages'] ?? 1);
        } while (++$page <= $pages && $page <= 20);

        return $servers;
    }

    public function addAddress(string $serverId, string $label): array
    {
        $ip = $this->request('POST', "linode/instances/{$serverId}/ips", ['type' => 'ipv4', 'public' => true])->json() ?? [];

        if (empty($ip['address'])) {
            throw new IpProviderException(__('nmi.providers.errors.refused', ['provider' => $this->name(), 'reason' => 'no address in the response']));
        }

        return [
            'address' => (string) $ip['address'],
            // Linode has no id for an address other than the address
            'provider_id' => (string) $ip['address'],
            'region' => $ip['region'] ?? null,
            'bind_address' => null,
        ];
    }

    public function supportsReverseDns(): bool
    {
        return true;
    }

    public function setReverseDns(string $serverId, string $providerId, string $address, string $hostname): void
    {
        $this->request('PUT', "linode/instances/{$serverId}/ips/{$address}", ['rdns' => $hostname]);
    }

    /**
     * An address that is already gone counts as released.
     */
    public function releaseAddress(string $serverId, string $providerId, string $address): void
    {
        try {
            $this->request('DELETE', "linode/instances/{$serverId}/ips/{$address}");
        } catch (IpProviderException $e) {
            if ($e->status !== 404) {
                throw $e;
            }
        }
    }
}
