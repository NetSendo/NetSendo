<?php

namespace Tests\Feature;

use App\Models\DomainConfiguration;
use App\Models\Mailbox;
use App\Models\User;
use App\Services\Deliverability\DnsLookupService;
use App\Services\Deliverability\DomainVerificationService;
use App\Services\Nmi\MtaStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * NetSendo MTA on by default (2.11.0, owner's decision 2026-11-12): the MTA container runs
 * in every installation and works for GOLD; NMI_DISABLED=true switches it off. The MTA
 * reports whether the server can deliver (XNMI-EGRESS: port 25, public address, PTR) and
 * the SPF record of an NMI domain names the address the mail leaves from.
 */
class NmiDefaultOnTest extends TestCase
{
    use RefreshDatabase;

    private const EHLO = [
        '250-netsendo-mta Hello [172.18.0.4]Haraka is at your service.',
        '250-PIPELINING',
        '250-AUTH PLAIN',
        '250-XNMI-BIND 172.18.0.7',
        '250 XNMI-EGRESS port25=open ip=34.40.28.155 ptr=confirmed helo=155.28.40.34.bc.googleusercontent.com helo_from=ptr checked=1794485442',
    ];

    private array $savedEnv = [];

    protected function tearDown(): void
    {
        foreach ($this->savedEnv as $name => $value) {
            $value === false ? putenv($name) : putenv("{$name}={$value}");
        }
        parent::tearDown();
    }

    private function nmiEnabledWith(array $env): bool
    {
        foreach (['NMI_DISABLED', 'NMI_ENABLED'] as $name) {
            $this->savedEnv[$name] ??= getenv($name);
            array_key_exists($name, $env) ? putenv("{$name}={$env[$name]}") : putenv($name);
        }

        return (bool) (require config_path('nmi.php'))['features']['enabled'];
    }

    public function test_nmi_is_on_unless_switched_off(): void
    {
        $this->assertTrue($this->nmiEnabledWith([]));
        // .env files made from the example before 2.11.0 say so without anyone choosing it
        $this->assertTrue($this->nmiEnabledWith(['NMI_ENABLED' => 'false']));
        $this->assertFalse($this->nmiEnabledWith(['NMI_DISABLED' => 'true']));
        $this->assertTrue($this->nmiEnabledWith(['NMI_DISABLED' => 'false']));
    }

    public function test_the_app_reaches_the_mta_by_its_service_name(): void
    {
        $this->savedEnv['NMI_MTA_HOST'] ??= getenv('NMI_MTA_HOST');
        putenv('NMI_MTA_HOST');

        $this->assertSame('nmi-mta', (require config_path('nmi.php'))['mta_host']);
    }

    public function test_the_mta_starts_with_the_stack_from_the_published_image(): void
    {
        $compose = Yaml::parseFile(base_path('../docker-compose.yml'));
        $mta = $compose['services']['nmi-mta'];

        $this->assertSame('ghcr.io/netsendo/netsendo:mta-${NETSENDO_VERSION:-latest}', $mta['image']);
        $this->assertArrayNotHasKey('profiles', $mta, 'the MTA must start without --profile');
        $this->assertArrayNotHasKey('build', $mta, 'installations have no docker/haraka to build from');
        $this->assertArrayNotHasKey('ports', $mta, 'several stacks on one host would fight over host ports');

        // Nothing mounted from the installation's folder: install.sh does not download docker/haraka
        foreach ($mta['volumes'] as $volume) {
            $this->assertStringStartsNotWith('./', $volume);
        }

        foreach (['app', 'scheduler', 'queue'] as $service) {
            $this->assertContains('nmi-auth:/var/www/storage/app/nmi-mta', $compose['services'][$service]['volumes']);
        }

        $this->assertContains('NMI_EGRESS_PROBE=${NMI_EGRESS_PROBE:-true}', $mta['environment']);
    }

    public function test_the_mta_image_is_published_with_the_app(): void
    {
        $workflow = Yaml::parseFile(base_path('../.github/workflows/docker-publish.yml'));
        $steps = collect($workflow['jobs']['build-and-push']['steps']);

        $build = $steps->firstWhere('name', 'Build and push MTA image');
        $this->assertSame('./docker/haraka', $build['with']['context']);
        $this->assertStringContainsString('prefix=mta-', $steps->firstWhere('name', 'Extract MTA metadata')['with']['flavor']);
    }

    public function test_the_mtas_report_on_sending_is_read(): void
    {
        $egress = app(MtaStatusService::class)->egress(self::EHLO);

        $this->assertSame([
            'port25' => 'open',
            'public_ip' => '34.40.28.155',
            'ptr' => 'confirmed',
            'helo' => '155.28.40.34.bc.googleusercontent.com',
            'helo_from' => 'ptr',
            'checked_at' => date(DATE_ATOM, 1794485442),
        ], $egress);

        $this->assertSame($egress, app(MtaStatusService::class)->capabilities(self::EHLO)['egress']);
    }

    public function test_reports_that_say_little_or_nothing(): void
    {
        $service = app(MtaStatusService::class);

        // An MTA image older than 2.11.0
        $this->assertNull($service->egress(array_slice(self::EHLO, 0, 4)));

        $this->assertSame(
            ['port25' => 'pending', 'public_ip' => null, 'ptr' => null, 'helo' => null, 'helo_from' => null, 'checked_at' => null],
            $service->egress(['250 XNMI-EGRESS port25=pending'])
        );

        // Nonsense is not passed on
        $blocked = $service->egress(['250 XNMI-EGRESS port25=blocked ip=not-an-ip helo=-bad- ptr=maybe']);
        $this->assertSame('blocked', $blocked['port25']);
        $this->assertNull($blocked['public_ip']);
        $this->assertNull($blocked['helo']);
        $this->assertNull($blocked['ptr']);
        $this->assertSame('unknown', $service->egress(['250 XNMI-EGRESS port25=whatever'])['port25']);
    }

    public function test_spf_covers_addresses_and_ranges(): void
    {
        $this->assertTrue(DomainVerificationService::spfCoversIp(['ip4:34.40.28.155'], '34.40.28.155'));
        $this->assertTrue(DomainVerificationService::spfCoversIp(['+ip4:34.40.28.0/24'], '34.40.28.155'));
        $this->assertTrue(DomainVerificationService::spfCoversIp(['IP4:34.40.0.0/13'], '34.40.28.155'));
        $this->assertFalse(DomainVerificationService::spfCoversIp(['ip4:34.40.28.0/30'], '34.40.28.155'));
        $this->assertFalse(DomainVerificationService::spfCoversIp(['include:_spf.google.com', 'a', 'mx'], '34.40.28.155'));
        $this->assertTrue(DomainVerificationService::spfCoversIp(['ip6:2001:db8::/32'], '2001:db8:0:1::5'));
        $this->assertFalse(DomainVerificationService::spfCoversIp(['ip4:34.40.28.155'], '2001:db8::1'));

        $this->assertSame('ip4:34.40.28.155', DomainVerificationService::spfIpMechanism('34.40.28.155'));
        $this->assertSame('ip6:2001:db8::1', DomainVerificationService::spfIpMechanism('2001:db8::1'));
    }

    private function nmiDomain(?string $spf): DomainConfiguration
    {
        $user = User::factory()->create();
        $domain = DomainConfiguration::create([
            'user_id' => $user->id,
            'domain' => 'shop.example',
            'cname_verified' => true,
            'dns_records' => ['spf' => $spf],
        ]);
        Mailbox::create([
            'user_id' => $user->id,
            'name' => 'Shop',
            'provider' => Mailbox::PROVIDER_NMI,
            'from_email' => 'news@shop.example',
            'from_name' => 'Shop',
            'is_active' => true,
            'allowed_types' => ['broadcast', 'autoresponder', 'system'],
            'credentials' => [],
            'domain_configuration_id' => $domain->id,
        ]);

        return $domain;
    }

    public function test_the_spf_record_of_an_nmi_domain_must_name_the_servers_address(): void
    {
        Cache::put(MtaStatusService::SERVER_ADDRESS_CACHE_KEY, '34.40.28.155', 60);
        $service = app(DomainVerificationService::class);
        $domain = $this->nmiDomain('v=spf1 include:_spf.google.com ~all');

        $config = $service->getAggregatedProviderConfig($domain);
        $this->assertSame(['34.40.28.155'], $config['spf_ips']);
        // No include of the installation's host: nobody publishes an SPF record there
        $this->assertSame([], $config['spf_includes']);

        $record = $service->generateOptimalSpfRecord($domain)['optimal'];
        $this->assertSame('v=spf1 include:_spf.google.com ip4:34.40.28.155 -all', $record['value']);
        $this->assertSame(['ip4:34.40.28.155'], $record['added_includes']);

        $analysis = (fn (?string $spf) => $this->analyzeSpfRecord($spf, $config))->call($service, 'v=spf1 include:_spf.google.com ~all');
        $issue = collect($analysis->issues)->firstWhere('code', 'spf_no_ip');
        $this->assertNotNull($issue);
        $this->assertSame('ip4:34.40.28.155', $issue['context']['mechanisms']);
        $this->assertStringContainsString('ip4:34.40.28.155', __($issue['message_key'], $issue['context'], 'pl'));

        $ok = (fn (?string $spf) => $this->analyzeSpfRecord($spf, $config))->call($service, 'v=spf1 ip4:34.40.28.0/24 ~all');
        $this->assertNull(collect($ok->issues)->firstWhere('code', 'spf_no_ip'));
    }

    public function test_an_unknown_server_address_is_not_guessed(): void
    {
        // The MTA does not answer here: no address, so nothing to demand in the record
        config(['nmi.mta_host' => '127.0.0.1', 'nmi.mta_port' => 1]);
        $service = app(DomainVerificationService::class);
        $domain = $this->nmiDomain('v=spf1 include:_spf.google.com ~all');

        $this->assertSame([], $service->getAggregatedProviderConfig($domain)['spf_ips']);
        // ...and it is not asked again on every page for a while
        $this->assertTrue(Cache::has(MtaStatusService::SERVER_ADDRESS_CACHE_KEY . ':miss'));
    }
}
