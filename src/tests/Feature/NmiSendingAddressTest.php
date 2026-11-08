<?php

namespace Tests\Feature;

use App\Models\DedicatedIpAddress;
use App\Models\DomainConfiguration;
use App\Models\IpPool;
use App\Models\User;
use App\Services\Deliverability\DnsLookupService;
use App\Services\Mail\Providers\NmiProvider;
use App\Services\Nmi\MtaProbe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\RawMessage;
use Tests\Feature\Concerns\ActivatesGold;
use Tests\TestCase;

/**
 * Which address and name NetSendo MTA sends with, decided in NetSendo:
 * - the name the MTA gives itself to the receiving servers (HELO) is the panel's domain
 *   (APP_URL), or NMI_HOSTNAME when set: no second subdomain and nothing set in the MTA;
 * - mail of a mailbox leaves from its dedicated IP under that IP's name, when the IP is
 *   configured on the server (the MTA reports which addresses it has);
 * - NetSendo checks the PTR record of each name and says what to set at the provider.
 * The MTA learns all this from the login file NetSendo writes (MtaAuthFile).
 */
class NmiSendingAddressTest extends TestCase
{
    use RefreshDatabase;
    use ActivatesGold;

    private User $user;
    private string $dir;
    private string $file;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir() . '/nmi-sending-address-' . bin2hex(random_bytes(4));
        $this->file = $this->dir . '/mta-users.json';
        config([
            'app.url' => 'https://netsendo.firma.pl',
            'nmi.hostname' => null,
            'nmi.features.enabled' => true,
            'nmi.mta_auth_file' => $this->file,
        ]);

        $this->user = User::factory()->create();
        $this->activateGold();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);

        parent::tearDown();
    }

    private function fakeDns(array $ptr = [], array $addresses = []): void
    {
        $this->app->instance(DnsLookupService::class, new class($ptr, $addresses) extends DnsLookupService {
            public function __construct(private array $ptr, private array $addresses)
            {
            }

            public function reverse(string $ip): ?string
            {
                return $this->ptr[$ip] ?? null;
            }

            public function addresses(string $name): array
            {
                return $this->addresses[$name] ?? [];
            }
        });
    }

    private function fakeMta(?array $addresses, bool $online = true): void
    {
        $this->app->instance(MtaProbe::class, new class($addresses, $online) extends MtaProbe {
            public function __construct(private ?array $addresses, private bool $online)
            {
            }

            public function probe(): array
            {
                return ['online' => $this->online, 'message' => 'test', 'addresses' => $this->addresses];
            }
        });
    }

    /** A domain with NMI on and its IP */
    private function nmiDomainWithIp(string $ipAddress = '203.0.113.10', string $hostname = 'mail1.firma.pl'): array
    {
        $domain = DomainConfiguration::create([
            'user_id' => $this->user->id,
            'domain' => 'firma.pl',
            'cname_verified' => true,
            'cname_verified_at' => now(),
        ]);
        $this->actingAs($this->user)->postJson(route('settings.nmi.domains.enable', $domain))->assertOk();

        $pool = IpPool::create([
            'user_id' => $this->user->id,
            'name' => 'Main pool',
            'type' => IpPool::TYPE_DEDICATED,
            'is_active' => true,
            'max_ips' => 10,
        ]);
        $ip = DedicatedIpAddress::create([
            'ip_pool_id' => $pool->id,
            'ip_address' => $ipAddress,
            'hostname' => $hostname,
            'ip_version' => str_contains($ipAddress, ':') ? 'ipv6' : 'ipv4',
            'provider' => DedicatedIpAddress::PROVIDER_MANUAL,
            'warming_status' => DedicatedIpAddress::WARMING_NEW,
            'reputation_score' => 100,
            'is_active' => true,
        ]);
        $this->actingAs($this->user)
            ->postJson(route('settings.nmi.ips.domain.assign', $ip), ['domain_configuration_id' => $domain->id])
            ->assertOk();

        return [$domain->fresh(), $ip->fresh()];
    }

    private function mtaFile(): array
    {
        $this->assertFileExists($this->file);

        return json_decode(File::get($this->file), true);
    }

    // --- The name of the mail server ---

    public function test_the_mta_is_named_after_the_panel_domain(): void
    {
        $this->nmiDomainWithIp();

        $this->assertSame('netsendo.firma.pl', $this->mtaFile()['helo']);
    }

    public function test_nmi_hostname_overrides_the_name(): void
    {
        config(['nmi.hostname' => 'Mail.Firma.pl']);
        $this->nmiDomainWithIp();

        $this->assertSame('mail.firma.pl', $this->mtaFile()['helo']);
    }

    public function test_a_panel_on_localhost_or_on_an_ip_address_gives_no_name(): void
    {
        foreach (['http://localhost', 'http://203.0.113.5:8080', 'http://netsendo'] as $url) {
            config(['app.url' => $url]);
            $this->assertNull(app(\App\Services\Nmi\MailServerName::class)->default(), $url);
        }
    }

    // --- What the MTA learns about the IPs ---

    public function test_each_login_lists_the_ips_of_its_domain_with_their_names(): void
    {
        [$domain, $ip] = $this->nmiDomainWithIp();

        $users = $this->mtaFile()['users'];

        $this->assertSame(['203.0.113.10' => 'mail1.firma.pl'], $users[$domain->nmi_smtp_username]['ips']);
    }

    public function test_an_ipv6_address_is_written_in_its_short_form(): void
    {
        [$domain] = $this->nmiDomainWithIp('2001:0DB8:0000:0000:0000:0000:0000:0010', 'mail6.firma.pl');

        $users = $this->mtaFile()['users'];

        $this->assertSame(['2001:db8::10' => 'mail6.firma.pl'], $users[$domain->nmi_smtp_username]['ips']);
    }

    public function test_renaming_or_removing_an_ip_updates_the_mta_file(): void
    {
        [$domain, $ip] = $this->nmiDomainWithIp();

        $this->actingAs($this->user)
            ->patchJson(route('settings.nmi.ips.update', $ip), ['hostname' => 'poczta.firma.pl'])
            ->assertOk()
            ->assertJsonPath('data.hostname', 'poczta.firma.pl');
        $this->assertSame(['203.0.113.10' => 'poczta.firma.pl'], $this->mtaFile()['users'][$domain->nmi_smtp_username]['ips']);

        $this->actingAs($this->user)->deleteJson(route('settings.nmi.ips.domain.unassign', $ip))->assertOk();
        $this->assertSame([], $this->mtaFile()['users'][$domain->nmi_smtp_username]['ips']);
    }

    public function test_the_name_of_an_ip_must_be_a_host_name(): void
    {
        [, $ip] = $this->nmiDomainWithIp();

        foreach (['', 'localhost', '203.0.113.10', 'mail firma.pl', 'mail.firma.pl/x'] as $hostname) {
            $this->actingAs($this->user)
                ->patchJson(route('settings.nmi.ips.update', $ip), ['hostname' => $hostname])
                ->assertStatus(422)
                ->assertJsonValidationErrors('hostname');
        }

        $this->assertSame('mail1.firma.pl', $ip->fresh()->hostname);
    }

    public function test_the_ip_of_another_user_cannot_be_renamed_or_checked(): void
    {
        [, $ip] = $this->nmiDomainWithIp();
        $other = User::factory()->create();

        $this->actingAs($other)
            ->patchJson(route('settings.nmi.ips.update', $ip), ['hostname' => 'evil.example'])
            ->assertForbidden();
        $this->actingAs($other)->postJson(route('settings.nmi.ips.ptr.verify', $ip))->assertForbidden();
    }

    public function test_the_connection_test_goes_out_from_the_ip_of_the_mailbox_too(): void
    {
        [$domain, $ip] = $this->nmiDomainWithIp();
        $transport = new class extends AbstractTransport {
            public array $sent = [];

            protected function doSend(SentMessage $message): void
            {
                $this->sent[] = $message->getOriginalMessage();
            }

            public function __toString(): string
            {
                return 'fake://';
            }
        };

        $result = (new NmiProvider($domain, $ip, 'news@firma.pl', 'Firma', null, $transport))->testConnection('ja@example.com');

        $this->assertTrue($result['success'], $result['message']);
        $this->assertStringContainsString('X-NMI-IP: 203.0.113.10', $transport->sent[0]->toString());
    }

    // --- PTR records ---

    public function test_the_ptr_check_passes_when_the_ip_and_its_name_point_at_each_other(): void
    {
        [, $ip] = $this->nmiDomainWithIp();
        $this->fakeDns(['203.0.113.10' => 'mail1.firma.pl'], ['mail1.firma.pl' => ['203.0.113.10']]);

        $this->actingAs($this->user)
            ->postJson(route('settings.nmi.ips.ptr.verify', $ip))
            ->assertOk()
            ->assertJsonPath('data.ok', true)
            ->assertJsonPath('data.ptr', 'mail1.firma.pl')
            ->assertJsonPath('data.name_addresses', ['203.0.113.10']);

        $ip->refresh();
        $this->assertTrue($ip->ptr_verified);
        $this->assertSame('mail1.firma.pl', $ip->ptr_record);
        $this->assertNotNull($ip->ptr_verified_at);
    }

    public function test_the_ptr_check_fails_when_the_ptr_points_elsewhere_or_the_name_does_not_lead_back(): void
    {
        [, $ip] = $this->nmiDomainWithIp();

        // The provider's default PTR
        $this->fakeDns(['203.0.113.10' => 'static.10.113.0.203.provider.example'], ['mail1.firma.pl' => ['203.0.113.10']]);
        $this->actingAs($this->user)
            ->postJson(route('settings.nmi.ips.ptr.verify', $ip))
            ->assertOk()
            ->assertJsonPath('data.ok', false)
            ->assertJsonPath('data.ptr', 'static.10.113.0.203.provider.example');
        $this->assertFalse($ip->fresh()->ptr_verified);

        // PTR right, but no A record of the name
        $this->fakeDns(['203.0.113.10' => 'mail1.firma.pl'], []);
        $this->actingAs($this->user)
            ->postJson(route('settings.nmi.ips.ptr.verify', $ip))
            ->assertJsonPath('data.ok', false)
            ->assertJsonPath('data.name_addresses', []);
        $this->assertFalse($ip->fresh()->ptr_verified);
    }

    public function test_the_mta_status_reports_the_server_name_and_its_ptr(): void
    {
        $this->fakeMta([]);
        $this->fakeDns(['198.51.100.7' => 'netsendo.firma.pl'], ['netsendo.firma.pl' => ['198.51.100.7']]);

        $this->actingAs($this->user)
            ->getJson(route('settings.nmi.mta.status'))
            ->assertOk()
            ->assertJsonPath('data.server_name.name', 'netsendo.firma.pl')
            ->assertJsonPath('data.server_name.ok', true)
            ->assertJsonPath('data.server_name.addresses.0.ip', '198.51.100.7')
            ->assertJsonPath('data.server_name.addresses.0.ptr', 'netsendo.firma.pl');

        $this->fakeDns(['198.51.100.7' => null], ['netsendo.firma.pl' => ['198.51.100.7']]);
        $this->actingAs($this->user)
            ->getJson(route('settings.nmi.mta.status'))
            ->assertJsonPath('data.server_name.ok', false)
            ->assertJsonPath('data.server_name.addresses.0.ptr', null);
    }

    // --- Which addresses the MTA has ---

    public function test_the_mta_status_lists_the_addresses_the_mta_can_send_from(): void
    {
        $this->fakeDns();
        $this->fakeMta(['10.58.0.9', '203.0.113.10']);

        $this->actingAs($this->user)
            ->getJson(route('settings.nmi.mta.status'))
            ->assertOk()
            ->assertJsonPath('data.online', true)
            ->assertJsonPath('data.addresses', ['10.58.0.9', '203.0.113.10']);

        // An MTA image without the report: not known
        $this->fakeMta(null);
        $this->actingAs($this->user)
            ->getJson(route('settings.nmi.mta.status'))
            ->assertJsonPath('data.addresses', null);
    }

    public function test_the_addresses_are_read_from_the_ehlo_answer_of_the_mta(): void
    {
        $lines = [
            '250-netsendo-mta Hello [10.58.0.4], Haraka is at your service.',
            '250-PIPELINING',
            '250-AUTH PLAIN LOGIN',
            '250-X-NETSENDO-MTA 1',
            '250-X-NETSENDO-ADDRESS 10.58.0.9',
            '250-X-NETSENDO-ADDRESS 2001:DB8::10',
            '250 X-NETSENDO-ADDRESS 203.0.113.10',
        ];

        $this->assertSame(['10.58.0.9', '2001:db8::10', '203.0.113.10'], MtaProbe::parseAddresses($lines));
        $this->assertNull(MtaProbe::parseAddresses(['250-netsendo-mta Hello', '250 AUTH PLAIN LOGIN']));
    }
}
