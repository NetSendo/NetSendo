<?php

namespace App\Services\Nmi;

use App\Models\DedicatedIpAddress;
use App\Models\IpPool;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IpProvisioningService
{
    /**
     * Supported providers
     */
    private const PROVIDERS = ['vultr', 'linode', 'digitalocean'];

    /**
     * Get list of available (configured) providers
     */
    public function getAvailableProviders(): array
    {
        $providers = [];

        foreach (self::PROVIDERS as $provider) {
            $config = config("nmi.ip_providers.{$provider}");

            $providers[$provider] = [
                'name' => $this->getProviderName($provider),
                'enabled' => $config['enabled'] ?? false,
                'configured' => !empty($config['api_key']),
            ];
        }

        return $providers;
    }

    /**
     * Get provider display name
     */
    private function getProviderName(string $provider): string
    {
        return match ($provider) {
            'vultr' => 'Vultr',
            'linode' => 'Linode',
            'digitalocean' => 'DigitalOcean',
            default => ucfirst($provider),
        };
    }

    /**
     * Provision a new IP address from provider
     */
    public function provisionIp(string $provider, IpPool $pool, string $region = 'ams'): DedicatedIpAddress
    {
        $config = config("nmi.ip_providers.{$provider}");

        if (!$config['enabled'] || empty($config['api_key'])) {
            throw new \Exception("Provider {$provider} is not configured.");
        }

        $apiKey = $config['api_key'];

        return match ($provider) {
            'vultr' => $this->provisionFromVultr($apiKey, $pool, $region),
            'linode' => $this->provisionFromLinode($apiKey, $pool, $region),
            'digitalocean' => $this->provisionFromDigitalOcean($apiKey, $pool, $region),
            default => throw new \Exception("Unknown provider: {$provider}"),
        };
    }

    /**
     * Provision IP from Vultr
     */
    private function provisionFromVultr(string $apiKey, IpPool $pool, string $region): DedicatedIpAddress
    {
        // Vultr API: Create Reserved IP
        // https://www.vultr.com/api/#operation/create-reserved-ip
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type' => 'application/json',
        ])->post('https://api.vultr.com/v2/reserved-ips', [
            'region' => $region,
            'ip_type' => 'v4',
            'label' => "nmi-pool-{$pool->id}",
        ]);

        if (!$response->successful()) {
            Log::error('Vultr IP provisioning failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Failed to provision IP from Vultr: ' . $response->json('error.message', 'Unknown error'));
        }

        $data = $response->json('reserved_ip');

        return DedicatedIpAddress::create([
            'ip_pool_id' => $pool->id,
            'ip_address' => $data['subnet'],
            'hostname' => $data['label'] ?? 'vultr-' . $data['id'],
            'ip_version' => 4,
            'provider' => DedicatedIpAddress::PROVIDER_VULTR,
            'provider_id' => $data['id'],
            'warming_status' => DedicatedIpAddress::WARMING_NEW,
            'reputation_score' => 100,
            'is_active' => true,
            'status_message' => "Provisioned from Vultr region {$region}",
        ]);
    }

    /**
     * Provision IP from Linode
     */
    private function provisionFromLinode(string $apiKey, IpPool $pool, string $region): DedicatedIpAddress
    {
        // Linode API: Allocate IP Address
        // https://www.linode.com/docs/api/networking/#ip-address-allocate
        // Note: Linode requires a Linode instance to allocate additional IPs

        throw new \Exception('Linode IP provisioning requires an existing Linode instance. Please use manual IP addition or contact support.');
    }

    /**
     * Provision IP from DigitalOcean
     */
    private function provisionFromDigitalOcean(string $apiKey, IpPool $pool, string $region): DedicatedIpAddress
    {
        // DigitalOcean API: Create Reserved IP
        // https://docs.digitalocean.com/reference/api/api-reference/#operation/reservedIPs_create
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type' => 'application/json',
        ])->post('https://api.digitalocean.com/v2/reserved_ips', [
            'region' => $this->mapToDoRegion($region),
        ]);

        if (!$response->successful()) {
            Log::error('DigitalOcean IP provisioning failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Failed to provision IP from DigitalOcean: ' . $response->json('message', 'Unknown error'));
        }

        $data = $response->json('reserved_ip');

        return DedicatedIpAddress::create([
            'ip_pool_id' => $pool->id,
            'ip_address' => $data['ip'],
            'hostname' => 'do-' . str_replace('.', '-', $data['ip']),
            'ip_version' => 4,
            'provider' => DedicatedIpAddress::PROVIDER_DIGITALOCEAN,
            'provider_id' => $data['ip'], // DO uses IP as identifier
            'warming_status' => DedicatedIpAddress::WARMING_NEW,
            'reputation_score' => 100,
            'is_active' => true,
            'status_message' => "Provisioned from DigitalOcean region {$data['region']['slug']}",
        ]);
    }

    /**
     * Map generic region code to DigitalOcean region
     */
    private function mapToDoRegion(string $region): string
    {
        return match ($region) {
            'ams' => 'ams3',
            'nyc' => 'nyc3',
            'sfo' => 'sfo3',
            'lon' => 'lon1',
            'fra' => 'fra1',
            'sgp' => 'sgp1',
            default => 'ams3',
        };
    }

    /**
     * Release IP back to provider
     */
    public function releaseIp(DedicatedIpAddress $ip): bool
    {
        if (empty($ip->provider_id)) {
            // Manual IP, just delete
            return true;
        }

        $config = config("nmi.ip_providers.{$ip->provider}");

        if (empty($config['api_key'])) {
            Log::warning('Cannot release IP - provider not configured', [
                'ip_id' => $ip->id,
                'provider' => $ip->provider,
            ]);
            return true; // Allow deletion anyway
        }

        $apiKey = $config['api_key'];

        try {
            return match ($ip->provider) {
                DedicatedIpAddress::PROVIDER_VULTR => $this->releaseVultrIp($apiKey, $ip),
                DedicatedIpAddress::PROVIDER_DIGITALOCEAN => $this->releaseDoIp($apiKey, $ip),
                default => true,
            };
        } catch (\Exception $e) {
            Log::error('Failed to release IP from provider', [
                'ip_id' => $ip->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Release Vultr IP
     */
    private function releaseVultrIp(string $apiKey, DedicatedIpAddress $ip): bool
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
        ])->delete("https://api.vultr.com/v2/reserved-ips/{$ip->provider_id}");

        return $response->successful() || $response->status() === 404;
    }

    /**
     * Release DigitalOcean IP
     */
    private function releaseDoIp(string $apiKey, DedicatedIpAddress $ip): bool
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
        ])->delete("https://api.digitalocean.com/v2/reserved_ips/{$ip->provider_id}");

        return $response->successful() || $response->status() === 404;
    }

    /**
     * Get available regions for a provider
     */
    public function getRegions(string $provider, ?string $apiKey = null): array
    {
        return match ($provider) {
            'vultr' => [
                'ams' => 'Amsterdam',
                'atl' => 'Atlanta',
                'cdg' => 'Paris',
                'dfw' => 'Dallas',
                'ewr' => 'New Jersey',
                'fra' => 'Frankfurt',
                'lhr' => 'London',
                'nrt' => 'Tokyo',
                'ord' => 'Chicago',
                'sea' => 'Seattle',
                'sgp' => 'Singapore',
                'sjc' => 'Silicon Valley',
                'syd' => 'Sydney',
            ],
            'digitalocean' => [
                'ams' => 'Amsterdam',
                'nyc' => 'New York',
                'sfo' => 'San Francisco',
                'lon' => 'London',
                'fra' => 'Frankfurt',
                'sgp' => 'Singapore',
                'blr' => 'Bangalore',
                'tor' => 'Toronto',
                'syd' => 'Sydney',
            ],
            'linode' => [
                'eu-west' => 'London, UK',
                'us-east' => 'Newark, NJ',
                'us-west' => 'Fremont, CA',
                'ap-south' => 'Singapore',
                'eu-central' => 'Frankfurt',
            ],
            default => [],
        };
    }

    /**
     * Provision IP with user's API key from database
     */
    public function provisionIpWithUserKey(string $provider, IpPool $pool, string $region, string $apiKey): DedicatedIpAddress
    {
        return match ($provider) {
            'vultr' => $this->provisionFromVultr($apiKey, $pool, $region),
            'linode' => $this->provisionFromLinode($apiKey, $pool, $region),
            'digitalocean' => $this->provisionFromDigitalOcean($apiKey, $pool, $region),
            default => throw new \Exception("Unknown provider: {$provider}"),
        };
    }
}

