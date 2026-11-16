<?php

namespace App\Services\Nmi\Providers;

use Illuminate\Http\Client\Response;

/**
 * DigitalOcean. Kept only to give back addresses that earlier versions ordered: a
 * Reserved IP cannot be used for sending mail.
 *
 * From DigitalOcean's documentation (Reserved IPs, limits): "SMTP traffic is restricted
 * on Reserved IPs. SMTP ports 25, 465, and 587 are blocked", "Reserved IPs do not support
 * PTR (rDNS) records", and a Droplet cannot have more than one of them.
 * https://docs.digitalocean.com/products/networking/reserved-ips/details/limits/
 */
class DigitalOceanProvider extends HttpIpProvider
{
    public function id(): string
    {
        return 'digitalocean';
    }

    public function name(): string
    {
        return 'DigitalOcean';
    }

    protected function baseUrl(): string
    {
        return 'https://api.digitalocean.com/v2';
    }

    protected function errorReason(Response $response): ?string
    {
        return $response->json('message');
    }

    public function account(): array
    {
        $account = $this->request('GET', 'account')->json('account') ?? [];

        return [
            'name' => $account['name'] ?? ($account['team']['name'] ?? null),
            'email' => $account['email'] ?? null,
        ];
    }

    public function servers(): array
    {
        return [];
    }

    public function addAddress(string $serverId, string $label): array
    {
        throw new IpProviderException(__('nmi.providers.errors.digitalocean_unusable'));
    }

    public function supportsReverseDns(): bool
    {
        return false;
    }

    public function setReverseDns(string $serverId, string $providerId, string $address, string $hostname): void
    {
        throw new IpProviderException(__('nmi.providers.errors.digitalocean_unusable'));
    }

    /**
     * Deleting a Reserved IP also unassigns it. An address that is already gone counts
     * as released.
     */
    public function releaseAddress(string $serverId, string $providerId, string $address): void
    {
        try {
            $this->request('DELETE', 'reserved_ips/' . rawurlencode($providerId ?: $address));
        } catch (IpProviderException $e) {
            if ($e->status !== 404) {
                throw $e;
            }
        }
    }
}
