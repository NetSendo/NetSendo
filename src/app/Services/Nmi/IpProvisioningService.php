<?php

namespace App\Services\Nmi;

use App\Models\DedicatedIpAddress;
use App\Models\IpPool;
use App\Models\IpProviderSetting;
use App\Services\Nmi\Providers\DigitalOceanProvider;
use App\Services\Nmi\Providers\IpProvider;
use App\Services\Nmi\Providers\IpProviderException;
use App\Services\Nmi\Providers\LinodeProvider;
use App\Services\Nmi\Providers\VultrProvider;
use Illuminate\Support\Facades\Log;

/**
 * Dedicated IP addresses ordered from a hosting company through its API.
 *
 * Each account connects a provider with its own API key (IpProviderSetting). Ordering an
 * address means: add one more public IPv4 to the server the NetSendo MTA runs on, point
 * its reverse DNS at the IP's host name where the provider's API can, and remember what
 * is needed to give the address back. What the API cannot do (configuring the address in
 * the server's operating system, the forward DNS record) is listed for the person as
 * next steps by the panel.
 */
class IpProvisioningService
{
    /**
     * @var array<string, class-string<IpProvider>>
     */
    public const PROVIDERS = [
        'vultr' => VultrProvider::class,
        'linode' => LinodeProvider::class,
        'digitalocean' => DigitalOceanProvider::class,
    ];

    /**
     * A provider's API with a given key
     */
    public function client(string $provider, string $apiKey): IpProvider
    {
        $class = self::PROVIDERS[$provider] ?? null;

        if ($class === null) {
            throw new IpProviderException("Unknown provider: {$provider}");
        }

        return new $class($apiKey);
    }

    /**
     * A provider's API with the key the account connected
     */
    public function clientFor(int $userId, string $provider): IpProvider
    {
        $apiKey = IpProviderSetting::getApiKey($userId, $provider);

        if (! $apiKey) {
            throw new IpProviderException(__('nmi.provider_not_configured'));
        }

        return $this->client($provider, $apiKey);
    }

    /**
     * Connect a provider: the key is stored only after the provider has accepted it.
     *
     * @return array{name: ?string, email: ?string} whose account the key belongs to
     */
    public function connect(int $userId, string $provider, string $apiKey): array
    {
        $account = $this->client($provider, trim($apiKey))->account();

        IpProviderSetting::updateOrCreate(
            ['user_id' => $userId, 'provider' => $provider],
            ['api_key' => trim($apiKey), 'enabled' => true]
        );

        return $account;
    }

    public function disconnect(int $userId, string $provider): void
    {
        IpProviderSetting::where('user_id', $userId)->where('provider', $provider)->delete();
    }

    /**
     * The account's servers at the provider: one of them runs the NetSendo MTA
     */
    public function servers(int $userId, string $provider): array
    {
        return $this->clientFor($userId, $provider)->servers();
    }

    /**
     * Order an address for a server and add it to the pool.
     *
     * Reverse DNS is attempted right away; when the provider refuses (typically because
     * the host name does not resolve to the new address yet) the address is kept and the
     * reason returned, so it can be set again later (setReverseDns).
     *
     * @return array{ip: DedicatedIpAddress, reverse_dns: array{supported: bool, set: bool, message: ?string}}
     */
    public function provision(int $userId, IpPool $pool, string $provider, string $serverId, string $hostname): array
    {
        $client = $this->clientFor($userId, $provider);
        $hostname = strtolower(trim($hostname));

        $address = $client->addAddress($serverId, $hostname);

        try {
            DedicatedIpAddress::forgetRemoved($address['address']);

            $ip = DedicatedIpAddress::create([
                'ip_pool_id' => $pool->id,
                'ip_address' => $address['address'],
                'hostname' => $hostname,
                'ip_version' => 'ipv4',
                'provider' => $provider,
                'provider_id' => $address['provider_id'],
                'provider_server_id' => $serverId,
                'provider_region' => $address['region'],
                'bind_address' => $address['bind_address'],
                'warming_status' => DedicatedIpAddress::WARMING_NEW,
                'reputation_score' => 100,
                'is_active' => true,
            ]);
        } catch (\Throwable $e) {
            // Never leave a paid address at the provider that NetSendo knows nothing about
            try {
                $client->releaseAddress($serverId, $address['provider_id'], $address['address']);
            } catch (IpProviderException $release) {
                Log::error('NMI: an ordered address could be neither stored nor released', [
                    'provider' => $provider,
                    'address' => $address['address'],
                    'error' => $release->getMessage(),
                ]);
            }

            throw $e;
        }

        Log::info('NMI: address ordered from provider', [
            'provider' => $provider,
            'ip_id' => $ip->id,
            'address' => $ip->ip_address,
            'server' => $serverId,
        ]);

        return ['ip' => $ip, 'reverse_dns' => $this->trySetReverseDns($client, $ip)];
    }

    /**
     * Point the address back at the IP's host name through the provider's API.
     *
     * @return array{supported: bool, set: bool, message: ?string}
     */
    public function setReverseDns(int $userId, DedicatedIpAddress $ip): array
    {
        if (! $this->isManaged($ip)) {
            return ['supported' => false, 'set' => false, 'message' => null];
        }

        return $this->trySetReverseDns($this->clientFor($userId, $ip->provider), $ip);
    }

    /**
     * Give the address back to the provider. An address added by hand has nothing to
     * give back.
     *
     * @throws IpProviderException when the provider refuses: the address is still billed
     */
    public function release(int $userId, DedicatedIpAddress $ip): void
    {
        if (! $this->isManaged($ip)) {
            return;
        }

        $this->clientFor($userId, $ip->provider)
            ->releaseAddress((string) $ip->provider_server_id, (string) $ip->provider_id, $ip->ip_address);

        Log::info('NMI: address released at provider', [
            'provider' => $ip->provider,
            'ip_id' => $ip->id,
            'address' => $ip->ip_address,
        ]);
    }

    /**
     * Whether the address was ordered through a provider's API (and can be released there)
     */
    public function isManaged(DedicatedIpAddress $ip): bool
    {
        return isset(self::PROVIDERS[$ip->provider]) && filled($ip->provider_id);
    }

    /**
     * Whether the address's reverse DNS can be set through the API of its provider
     */
    public function canSetReverseDns(DedicatedIpAddress $ip): bool
    {
        return $this->isManaged($ip) && $this->client($ip->provider, '')->supportsReverseDns();
    }

    private function trySetReverseDns(IpProvider $client, DedicatedIpAddress $ip): array
    {
        if (! $client->supportsReverseDns()) {
            return ['supported' => false, 'set' => false, 'message' => null];
        }

        if (! filled($ip->hostname)) {
            return ['supported' => true, 'set' => false, 'message' => __('nmi.ptr.no_hostname')];
        }

        try {
            $client->setReverseDns((string) $ip->provider_server_id, (string) $ip->provider_id, $ip->ip_address, $ip->hostname);
        } catch (IpProviderException $e) {
            return ['supported' => true, 'set' => false, 'message' => $e->getMessage()];
        }

        return ['supported' => true, 'set' => true, 'message' => null];
    }
}
