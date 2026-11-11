<?php

namespace App\Services\Nmi\Providers;

use Illuminate\Http\Client\Response;

/**
 * Vultr (API v2, https://www.vultr.com/api/).
 *
 * An extra address is a "reserved IP": created in the region of the instance, then
 * attached to it. The instance gets the public address itself (as a /32 next to its main
 * address), so no address translation is involved; it still has to be configured in the
 * operating system by hand (Vultr: "After the IP is assigned, you must manually configure
 * it on the server"). Reverse DNS can be set through the API.
 *
 * Outbound port 25 is blocked on new accounts until Vultr's support lifts the block.
 */
class VultrProvider extends HttpIpProvider
{
    private const PER_PAGE = 500;

    public function id(): string
    {
        return 'vultr';
    }

    public function name(): string
    {
        return 'Vultr';
    }

    protected function baseUrl(): string
    {
        return 'https://api.vultr.com/v2';
    }

    protected function errorReason(Response $response): ?string
    {
        $error = $response->json('error');

        return is_string($error) && $error !== '' ? $error : null;
    }

    public function account(): array
    {
        $account = $this->request('GET', 'account')->json('account') ?? [];

        return [
            'name' => $account['name'] ?? null,
            'email' => $account['email'] ?? null,
        ];
    }

    public function servers(): array
    {
        $servers = [];
        $cursor = '';

        // meta.total is not reliable on paged requests: follow links.next until it is empty
        do {
            $page = $this->request('GET', 'instances', array_filter(['per_page' => self::PER_PAGE, 'cursor' => $cursor]))->json();

            foreach ($page['instances'] ?? [] as $instance) {
                $servers[] = [
                    'id' => (string) $instance['id'],
                    'label' => ($instance['label'] ?? '') ?: (($instance['hostname'] ?? '') ?: (string) $instance['id']),
                    'region' => $instance['region'] ?? null,
                    'addresses' => array_values(array_filter([$instance['main_ip'] ?? null], fn ($ip) => $ip && $ip !== '0.0.0.0')),
                ];
            }

            $cursor = (string) ($page['meta']['links']['next'] ?? '');
        } while ($cursor !== '' && count($servers) < 5000);

        return $servers;
    }

    public function addAddress(string $serverId, string $label): array
    {
        // A reserved IP only attaches to an instance in its own region
        $server = collect($this->servers())->firstWhere('id', $serverId);

        if (! $server || empty($server['region'])) {
            throw new IpProviderException(__('nmi.providers.errors.server_not_found', ['provider' => $this->name()]));
        }

        $reserved = $this->request('POST', 'reserved-ips', [
            'region' => $server['region'],
            'ip_type' => 'v4',
            'label' => mb_substr($label, 0, 60),
        ])->json('reserved_ip') ?? [];

        if (empty($reserved['id']) || empty($reserved['subnet'])) {
            throw new IpProviderException(__('nmi.providers.errors.refused', ['provider' => $this->name(), 'reason' => 'no address in the response']));
        }

        try {
            $this->request('POST', "reserved-ips/{$reserved['id']}/attach", ['instance_id' => $serverId]);
        } catch (IpProviderException $e) {
            // An address that is not on the server is of no use, and is billed all the same
            try {
                $this->releaseAddress($serverId, (string) $reserved['id'], (string) $reserved['subnet']);
            } catch (IpProviderException) {
                // The reason the attach failed is what the person needs to read
            }

            throw $e;
        }

        return [
            'address' => (string) $reserved['subnet'],
            'provider_id' => (string) $reserved['id'],
            'region' => $reserved['region'] ?? $server['region'],
            'bind_address' => null,
        ];
    }

    public function supportsReverseDns(): bool
    {
        return true;
    }

    /**
     * The reserved IP has an endpoint of its own for this; the instance's endpoint is the
     * older way and is tried when the first one does not take the address.
     */
    public function setReverseDns(string $serverId, string $providerId, string $address, string $hostname): void
    {
        try {
            $this->request('PUT', "reserved-ips/{$providerId}/reverse-dns/ipv4", ['v4' => $hostname]);

            return;
        } catch (IpProviderException $e) {
            if ($e->isAuthorization() || $e->status === null) {
                throw $e;
            }
        }

        $this->request('POST', "instances/{$serverId}/ipv4/reverse", ['ip' => $address, 'reverse' => $hostname]);
    }

    /**
     * Deleting a reserved IP also detaches it. One that is already gone counts as released.
     */
    public function releaseAddress(string $serverId, string $providerId, string $address): void
    {
        try {
            $this->request('DELETE', "reserved-ips/{$providerId}");
        } catch (IpProviderException $e) {
            if ($e->status !== 404) {
                throw $e;
            }
        }
    }
}
