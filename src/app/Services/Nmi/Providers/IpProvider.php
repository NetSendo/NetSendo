<?php

namespace App\Services\Nmi\Providers;

/**
 * A hosting company that can give a server one more public IPv4 address through its API.
 *
 * An address can only be used by a server of the same company: the NetSendo MTA has to
 * run on one of the servers `servers()` lists. Every method throws IpProviderException
 * when the API refuses or cannot be reached.
 */
interface IpProvider
{
    /** Key used in settings and on IP records: 'vultr', 'linode', 'digitalocean' */
    public function id(): string;

    /**
     * Check the API key by asking whose it is.
     *
     * @return array{name: ?string, email: ?string}
     */
    public function account(): array;

    /**
     * The servers the key can see.
     *
     * @return array<int, array{id: string, label: string, region: ?string, addresses: string[]}>
     */
    public function servers(): array;

    /**
     * Give a server one more public IPv4 address.
     *
     * `provider_id` is what the provider calls the address (needed to release it).
     * `bind_address` is set where the provider translates the public address to a private
     * one on the server: the MTA has to bind to that one (NMI_BIND_MAP). It is null when
     * the server gets the public address itself, or when the API does not tell.
     *
     * @return array{address: string, provider_id: string, region: ?string, bind_address: ?string}
     */
    public function addAddress(string $serverId, string $label): array;

    /** Whether reverse DNS of an added address can be set through the API */
    public function supportsReverseDns(): bool;

    /** Point the address back at a host name (its PTR record) */
    public function setReverseDns(string $serverId, string $providerId, string $address, string $hostname): void;

    /** Give the address back to the provider, so it is no longer billed */
    public function releaseAddress(string $serverId, string $providerId, string $address): void;
}
