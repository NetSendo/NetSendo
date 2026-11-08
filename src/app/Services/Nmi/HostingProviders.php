<?php

namespace App\Services\Nmi;

/**
 * Hosting providers compared in the setup guide (Mail Infrastructure dashboard) and offered
 * when an IP is added, so the IP page links to that provider's instructions. For an own
 * sending server a provider has to let outgoing port 25 through, sell additional IPv4
 * addresses for the server and let the PTR record of each address be set.
 *
 * From the providers' own documentation, terms and price pages, as of CHECKED_AT. The notes
 * are in the panel's language files (nmi.hosting.notes.<key>).
 */
class HostingProviders
{
    public const CHECKED_AT = '2026-11-08';

    /**
     * port25: open | on_request | blocked | unconfirmed (the provider's pages do not say);
     * ptr: panel | panel_api | api | ticket | none; fit: recommended | possible | avoid;
     * price: per price_period (month | hour | null when the page gives none)
     *
     * @return list<array{key: string, name: string, products: string, port25: string, extra_ips: bool,
     *     extra_ip_product: ?string, price: ?string, price_period: ?string, ptr: string, fit: string, checked_at: string,
     *     links: array<string, string>}>
     */
    public static function all(): array
    {
        return array_map(function (array $provider) {
            $provider['checked_at'] = self::CHECKED_AT;
            $provider['links'] = array_filter($provider['links'] ?? []);

            return $provider;
        }, self::PROVIDERS);
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_column(self::PROVIDERS, 'key');
    }

    public static function find(?string $key): ?array
    {
        foreach (self::all() as $provider) {
            if ($provider['key'] === $key) {
                return $provider;
            }
        }

        return null;
    }

    private const PROVIDERS = [
        [
            'key' => 'ovh',
            'name' => 'OVHcloud',
            'products' => 'VPS, Dedicated',
            'port25' => 'open',
            'extra_ips' => true,
            'extra_ip_product' => 'Additional IP',
            'price' => '€1.99',
            'price_period' => 'month',
            'ptr' => 'panel_api',
            'fit' => 'recommended',
            'links' => [
                'extra_ips' => 'https://www.ovhcloud.com/en-ie/bare-metal/ip/',
                'configure' => 'https://docs.ovhcloud.com/en/guides/bare-metal-cloud/virtual-private-servers/configuring-ip-aliasing',
                'ptr' => 'https://docs.ovhcloud.com/en/guides/bare-metal-cloud/virtual-private-servers/configuring-reverse-dns',
                'port25' => 'https://docs.ovhcloud.com/en/guides/bare-metal-cloud/dedicated-servers/mail-sending-optimization',
            ],
        ],
        [
            'key' => 'scaleway',
            'name' => 'Scaleway',
            'products' => 'Elastic Metal, Instances',
            'port25' => 'on_request',
            'extra_ips' => true,
            'extra_ip_product' => 'Flexible IP',
            'price' => '€0.005',
            'price_period' => 'hour',
            'ptr' => 'panel',
            'fit' => 'recommended',
            'links' => [
                'extra_ips' => 'https://www.scaleway.com/en/docs/instances/how-to/use-flexips/',
                'port25' => 'https://www.scaleway.com/en/docs/elastic-metal/how-to/enable-smtp/',
            ],
        ],
        [
            'key' => 'hetzner',
            'name' => 'Hetzner',
            'products' => 'Dedicated (Robot)',
            'port25' => 'unconfirmed',
            'extra_ips' => true,
            'extra_ip_product' => 'Additional IP',
            'price' => '€1.70',
            'price_period' => 'month',
            'ptr' => 'panel_api',
            'fit' => 'possible',
            'links' => [
                'extra_ips' => 'https://docs.hetzner.com/robot/dedicated-server/ip/ip-addresses/',
                'ptr' => 'https://docs.hetzner.com/robot/dedicated-server/ip/ip-addresses/',
            ],
        ],
        [
            'key' => 'hetzner_cloud',
            'name' => 'Hetzner Cloud',
            'products' => 'Cloud servers',
            'port25' => 'on_request',
            'extra_ips' => true,
            'extra_ip_product' => 'Floating IP',
            'price' => '€3.00',
            'price_period' => 'month',
            'ptr' => 'panel_api',
            'fit' => 'possible',
            'links' => [
                'extra_ips' => 'https://docs.hetzner.com/cloud/floating-ips/overview/',
                'port25' => 'https://docs.hetzner.com/cloud/servers/faq/',
            ],
        ],
        [
            'key' => 'netcup',
            'name' => 'netcup',
            'products' => 'Root servers, VPS',
            'port25' => 'unconfirmed',
            'extra_ips' => true,
            'extra_ip_product' => 'Additional IPv4',
            'price' => '€2.00',
            'price_period' => null,
            'ptr' => 'panel_api',
            'fit' => 'possible',
            'links' => [
                'extra_ips' => 'https://www.netcup.com/en/server/kvm-server-extensions',
                'configure' => 'https://www.netcup.com/en/helpcenter/documentation/server/ip',
            ],
        ],
        [
            'key' => 'linode',
            'name' => 'Akamai (Linode)',
            'products' => 'Compute instances',
            'port25' => 'on_request',
            'extra_ips' => true,
            'extra_ip_product' => 'Additional IPv4',
            'price' => null,
            'price_period' => null,
            'ptr' => 'panel_api',
            'fit' => 'possible',
            'links' => [
                'extra_ips' => 'https://techdocs.akamai.com/cloud-computing/docs/managing-ip-addresses-on-a-compute-instance',
                'port25' => 'https://techdocs.akamai.com/cloud-computing/docs/send-email',
            ],
        ],
        [
            'key' => 'upcloud',
            'name' => 'UpCloud',
            'products' => 'Cloud servers',
            'port25' => 'on_request',
            'extra_ips' => true,
            'extra_ip_product' => 'Additional IPv4',
            'price' => null,
            'price_period' => null,
            'ptr' => 'panel_api',
            'fit' => 'possible',
            'links' => [
                'extra_ips' => 'https://upcloud.com/docs/guides/attaching-new-ip-addresses/',
                'ptr' => 'https://upcloud.com/docs/products/networking/dns/',
                'port25' => 'https://upcloud.com/docs/guides/sending-email-smtp-best-practices/',
            ],
        ],
        [
            'key' => 'vultr',
            'name' => 'Vultr',
            'products' => 'Cloud Compute',
            'port25' => 'on_request',
            'extra_ips' => true,
            'extra_ip_product' => 'Additional IPv4',
            'price' => null,
            'price_period' => null,
            'ptr' => 'panel_api',
            'fit' => 'possible',
            'links' => [
                'extra_ips' => 'https://docs.vultr.com/products/compute/instances/cloud-compute/networking/ipv4',
                'port25' => 'https://docs.vultr.com/support/products/compute/why-is-smtp-blocked',
            ],
        ],
        [
            'key' => 'ionos',
            'name' => 'IONOS',
            'products' => 'VPS, Cloud Server',
            'port25' => 'on_request',
            'extra_ips' => true,
            'extra_ip_product' => 'Public IP',
            'price' => '€5',
            'price_period' => 'month',
            'ptr' => 'panel',
            'fit' => 'possible',
            'links' => [
                'extra_ips' => 'https://www.ionos.com/help/server-cloud-infrastructure/ip-addresses-vps/creating-a-public-ip-address-vps-and-migrated-cloud-servers/',
                'port25' => 'https://www.ionos.com/help/server-cloud-infrastructure/firewall-policies/unblocking-port-25-for-sending-emails/',
            ],
        ],
        [
            'key' => 'homepl',
            'name' => 'home.pl',
            'products' => 'VPS Linux',
            'port25' => 'unconfirmed',
            'extra_ips' => true,
            'extra_ip_product' => 'IPv4',
            'price' => null,
            'price_period' => null,
            'ptr' => 'panel',
            'fit' => 'possible',
            'links' => [
                'extra_ips' => 'https://pomoc.home.pl/baza-wiedzy/jak-dokupic-adres-ipv4-do-serwera-vps',
                'ptr' => 'https://pomoc.home.pl/baza-wiedzy/ustawienie-revdns-dla-adresu-ipv4-na-serwerze-vps-w-home-pl',
            ],
        ],
        [
            'key' => 'aws',
            'name' => 'AWS',
            'products' => 'EC2',
            'port25' => 'on_request',
            'extra_ips' => true,
            'extra_ip_product' => 'Elastic IP',
            'price' => '$0.005',
            'price_period' => 'hour',
            'ptr' => 'panel_api',
            'fit' => 'possible',
            'links' => [
                'ptr' => 'https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/Using_Elastic_Addressing_Reverse_DNS.html',
            ],
        ],
        [
            'key' => 'contabo',
            'name' => 'Contabo',
            'products' => 'VPS, VDS',
            'port25' => 'unconfirmed',
            'extra_ips' => true,
            'extra_ip_product' => 'Additional IPv4',
            'price' => '€3.50',
            'price_period' => 'month',
            'ptr' => 'panel_api',
            'fit' => 'avoid',
            'links' => [
                'extra_ips' => 'https://help.contabo.com/support/solutions/articles/103000269701',
                'port25' => 'https://contabo.com/blog/kb/103000280507-is-there-a-limit-to-how-many-emails-can-be-sent-from-my-server/',
            ],
        ],
        [
            'key' => 'nazwapl',
            'name' => 'nazwa.pl',
            'products' => 'VPS',
            'port25' => 'unconfirmed',
            'extra_ips' => false,
            'extra_ip_product' => null,
            'price' => null,
            'price_period' => null,
            'ptr' => 'panel',
            'fit' => 'avoid',
            'links' => [
                'extra_ips' => 'https://www.nazwa.pl/vps.md',
            ],
        ],
        [
            'key' => 'digitalocean',
            'name' => 'DigitalOcean',
            'products' => 'Droplets',
            'port25' => 'blocked',
            'extra_ips' => true,
            'extra_ip_product' => 'Reserved IP',
            'price' => null,
            'price_period' => null,
            'ptr' => 'none',
            'fit' => 'avoid',
            'links' => [
                'port25' => 'https://docs.digitalocean.com/support/why-is-smtp-blocked/',
            ],
        ],
        [
            'key' => 'google_cloud',
            'name' => 'Google Cloud',
            'products' => 'Compute Engine',
            'port25' => 'blocked',
            'extra_ips' => false,
            'extra_ip_product' => null,
            'price' => null,
            'price_period' => null,
            'ptr' => 'panel_api',
            'fit' => 'avoid',
            'links' => [
                'port25' => 'https://docs.cloud.google.com/compute/docs/tutorials/sending-mail',
            ],
        ],
        [
            'key' => 'azure',
            'name' => 'Microsoft Azure',
            'products' => 'Virtual Machines',
            'port25' => 'blocked',
            'extra_ips' => true,
            'extra_ip_product' => 'Public IP',
            'price' => null,
            'price_period' => null,
            'ptr' => 'api',
            'fit' => 'avoid',
            'links' => [
                'port25' => 'https://learn.microsoft.com/en-us/azure/virtual-network/troubleshoot-outbound-smtp-connectivity',
            ],
        ],
    ];
}
