<?php

namespace Tests\Feature;

use App\Models\DedicatedIpAddress;
use App\Models\DomainConfiguration;
use App\Models\IpPool;
use App\Models\Mailbox;
use App\Models\Setting;
use App\Models\User;
use App\Services\Deliverability\DnsLookupService;
use App\Services\Nmi\DkimKeyManager;
use App\Services\Nmi\HostingProviders;
use App\Services\Nmi\MtaEndpoint;
use App\Services\Nmi\MtaProbe;
use App\Services\Nmi\NmiSwitch;
use App\Services\Nmi\OutboundPortCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Feature\Concerns\ActivatesGold;
use Tests\TestCase;

/**
 * The own sending server is run from the panel, without editing files on the server:
 * - the MTA ships in docker-compose.yml on the server's network and NetSendo reaches it at
 *   netsendo-mta:2525 (also with the NMI_MTA_HOST / NMI_MTA_PORT of an older .env);
 * - NetSendo MTA is switched on and off for the installation in the panel (admin, GOLD to
 *   switch on); NMI_ENABLED=true in .env still forces it on;
 * - a setup guide shows each step with its live state;
 * - the hosting providers the guide compares, chosen when an IP is added.
 */
class NmiServerSetupTest extends TestCase
{
    use RefreshDatabase;
    use ActivatesGold;

    private User $admin;
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir() . '/nmi-server-setup-' . bin2hex(random_bytes(4));
        config([
            'app.url' => 'https://netsendo.example.com',
            'nmi.hostname' => null,
            // .env without NMI_ENABLED: the panel decides
            'nmi.features.enabled' => false,
            'nmi.mta_auth_file' => $this->dir . '/mta-users.json',
        ]);

        $this->admin = User::factory()->create();
        $this->activateGold();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);

        parent::tearDown();
    }

    private function fakeServer(?array $addresses = ['10.58.0.1', '172.17.0.1', '198.51.100.7'], bool $online = true, ?bool $port25 = true): void
    {
        $this->app->instance(MtaProbe::class, new class($addresses, $online) extends MtaProbe {
            public function __construct(private ?array $addresses, private bool $online)
            {
            }

            public function probe(): array
            {
                return [
                    'online' => $this->online,
                    'message' => 'test',
                    'addresses' => $this->online ? $this->addresses : null,
                ];
            }
        });
        $this->app->instance(OutboundPortCheck::class, new class($port25) extends OutboundPortCheck {
            public function __construct(private ?bool $open)
            {
            }

            public function port25Open(): ?bool
            {
                return $this->open;
            }
        });
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

    private function fakeDkimRecords(bool $published): void
    {
        $this->app->instance(DkimKeyManager::class, new class($published) extends DkimKeyManager {
            public function __construct(private bool $published)
            {
            }

            public function verifyDkimRecord(DedicatedIpAddress $ip): bool
            {
                return $this->published && $ip->hasDkimKeys();
            }
        });
    }

    private function switchOn(): void
    {
        Setting::updateOrCreate(['key' => NmiSwitch::SETTING], ['value' => '1']);
    }

    private function guide(): array
    {
        $steps = $this->actingAs($this->admin)
            ->getJson(route('settings.nmi.guide'))
            ->assertOk()
            ->json('data.steps');

        return collect($steps)->keyBy('key')->all();
    }

    /** A domain with NMI on, an IP with DKIM keys and an NMI mailbox */
    private function fullSetup(string $address = '198.51.100.7', string $hostname = 'mail1.example.com'): array
    {
        $domain = DomainConfiguration::create([
            'user_id' => $this->admin->id,
            'domain' => 'example.com',
            'cname_verified' => true,
            'cname_verified_at' => now(),
        ]);
        $this->actingAs($this->admin)->postJson(route('settings.nmi.domains.enable', $domain))->assertOk();

        $pool = IpPool::create([
            'user_id' => $this->admin->id,
            'name' => 'Main pool',
            'type' => IpPool::TYPE_DEDICATED,
            'is_active' => true,
            'max_ips' => 10,
        ]);
        $ip = DedicatedIpAddress::create([
            'ip_pool_id' => $pool->id,
            'domain_configuration_id' => $domain->id,
            'ip_address' => $address,
            'hostname' => $hostname,
            'ip_version' => 'ipv4',
            'provider' => 'hetzner',
            'warming_status' => DedicatedIpAddress::WARMING_NEW,
            'reputation_score' => 100,
            'is_active' => true,
            'dkim_selector' => 'ns-20261108-abcd',
            'dkim_private_key' => 'private-key',
            'dkim_public_key' => "-----BEGIN PUBLIC KEY-----\nMIIB\n-----END PUBLIC KEY-----",
            'dkim_generated_at' => now(),
        ]);
        $domain->forceFill(['dedicated_ip_id' => $ip->id])->save();

        $mailbox = Mailbox::create([
            'user_id' => $this->admin->id,
            'name' => 'NMI',
            'provider' => 'nmi',
            'from_email' => 'news@example.com',
            'from_name' => 'Example',
            'is_default' => true,
            'is_active' => true,
            'allowed_types' => ['broadcast', 'autoresponder', 'system'],
            'credentials' => [],
        ]);
        $mailbox->forceFill(['domain_configuration_id' => $domain->id, 'dedicated_ip_id' => $ip->id])->save();

        return [$domain->fresh(), $ip->fresh(), $mailbox];
    }

    // --- Where NetSendo reaches the MTA ---

    public function test_the_mta_is_reached_at_netsendo_mta_port_2525_by_default(): void
    {
        config(['nmi.mta_host' => null, 'nmi.mta_port' => null]);
        $this->assertSame('netsendo-mta', MtaEndpoint::host());
        $this->assertSame(2525, MtaEndpoint::port());

        // NMI_MTA_HOST=netsendo-mta, NMI_MTA_PORT=25 of an older .env.example
        config(['nmi.mta_host' => 'netsendo-mta', 'nmi.mta_port' => '25']);
        $this->assertSame(2525, MtaEndpoint::port());

        // An MTA set up elsewhere keeps its address
        config(['nmi.mta_host' => 'mta.internal.example', 'nmi.mta_port' => '25']);
        $this->assertSame('mta.internal.example', MtaEndpoint::host());
        $this->assertSame(25, MtaEndpoint::port());
    }

    public function test_the_compose_file_runs_the_mta_on_the_servers_network_without_a_profile(): void
    {
        $compose = File::get(base_path('../docker-compose.yml'));
        $service = substr($compose, strpos($compose, "\n  nmi-mta:"));
        $service = substr($service, 0, strpos($service, "\nnetworks:"));

        $this->assertStringContainsString('network_mode: host', $service);
        $this->assertStringNotContainsString('profiles:', $service);
        $this->assertStringContainsString('NMI_MTA_LISTEN=${NMI_MTA_LISTEN:-10.58.0.1:2525}', $service);
        // The containers that send find the MTA under its old name
        $this->assertSame(3, substr_count($compose, '"netsendo-mta:10.58.0.1"'));
        $this->assertStringContainsString('subnet: 10.58.0.0/16', $compose);
    }

    // --- The switch in the panel ---

    public function test_netsendo_mta_is_off_until_an_admin_switches_it_on_in_the_panel(): void
    {
        $this->assertFalse(NmiSwitch::enabled());
        $this->actingAs($this->admin)->getJson(route('settings.nmi.dashboard'))
            ->assertJsonPath('data.nmiEnabled', false)
            ->assertJsonPath('data.nmiSwitch.can_toggle', true)
            ->assertJsonPath('data.nmiSwitch.forced_by_env', false);

        $this->actingAs($this->admin)->postJson(route('settings.nmi.installation.enable'))->assertOk();

        $this->assertTrue(NmiSwitch::enabled());
        $this->assertSame('1', Setting::where('key', NmiSwitch::SETTING)->value('value'));
        $this->actingAs($this->admin)->getJson(route('settings.nmi.dashboard'))->assertJsonPath('data.nmiEnabled', true);
    }

    public function test_switching_on_needs_an_admin_and_gold(): void
    {
        $member = User::factory()->create(['admin_user_id' => $this->admin->id]);
        $this->actingAs($member)->postJson(route('settings.nmi.installation.enable'))->assertForbidden();
        $this->actingAs($member)->getJson(route('settings.nmi.dashboard'))->assertJsonPath('data.nmiSwitch.can_toggle', false);

        Setting::where('key', 'license_plan')->update(['value' => 'SILVER']);
        $this->actingAs($this->admin)->postJson(route('settings.nmi.installation.enable'))->assertStatus(402);

        $this->assertFalse(NmiSwitch::enabled());
    }

    public function test_switching_off_takes_the_logins_away_from_the_mta_and_on_brings_them_back(): void
    {
        $this->switchOn();
        [$domain] = $this->fullSetup();
        $file = config('nmi.mta_auth_file');
        $this->assertArrayHasKey($domain->nmi_smtp_username, json_decode(File::get($file), true)['users']);

        // Switching off is allowed without GOLD
        Setting::where('key', 'license_plan')->update(['value' => 'SILVER']);
        $this->actingAs($this->admin)->postJson(route('settings.nmi.installation.disable'))->assertOk();

        $this->assertFalse(NmiSwitch::enabled());
        $this->assertSame([], json_decode(File::get($file), true)['users']);
        $this->artisan('nmi:sync-mta-auth')->assertSuccessful();
        $this->assertSame([], json_decode(File::get($file), true)['users']);

        $this->activateGold();
        $this->actingAs($this->admin)->postJson(route('settings.nmi.installation.enable'))->assertOk();
        $this->assertArrayHasKey($domain->nmi_smtp_username, json_decode(File::get($file), true)['users']);
    }

    public function test_nmi_enabled_true_in_env_keeps_it_on(): void
    {
        config(['nmi.features.enabled' => true]);

        $this->assertTrue(NmiSwitch::enabled());
        $this->actingAs($this->admin)->getJson(route('settings.nmi.dashboard'))
            ->assertJsonPath('data.nmiSwitch.forced_by_env', true);
        $this->actingAs($this->admin)
            ->postJson(route('settings.nmi.installation.disable'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('nmi');
        $this->assertTrue(NmiSwitch::enabled());
    }

    // --- The setup guide ---

    public function test_the_guide_starts_with_the_switch_and_what_the_server_can_do(): void
    {
        $this->fakeServer(port25: false);
        $this->fakeDns();

        $steps = $this->guide();

        $this->assertSame(
            ['switch', 'mta', 'port25', 'server_name', 'domain', 'ip', 'dkim', 'ip_on_server', 'ptr', 'mailbox', 'test'],
            array_keys($steps)
        );
        $this->assertSame('todo', $steps['switch']['status']);
        $this->assertSame('ok', $steps['mta']['status']);
        $this->assertSame('error', $steps['port25']['status']);
        $this->assertSame('warning', $steps['server_name']['status']);
        $this->assertSame('netsendo.example.com', $steps['server_name']['data']['name']);
        $this->assertSame('todo', $steps['domain']['status']);
        $this->assertSame('todo', $steps['ip']['status']);
        // Public addresses of the server, to add as the first IP
        $this->assertSame(['198.51.100.7'], $steps['ip']['data']['server_addresses']);
        $this->assertSame('todo', $steps['mailbox']['status']);
    }

    public function test_the_guide_says_when_the_mta_does_not_answer(): void
    {
        $this->switchOn();
        $this->fakeServer(online: false, port25: null);
        $this->fakeDns();

        $steps = $this->guide();

        $this->assertSame('ok', $steps['switch']['status']);
        $this->assertSame('error', $steps['mta']['status']);
        $this->assertSame('unknown', $steps['port25']['status']);
        $this->assertSame('netsendo-mta', $steps['mta']['data']['host']);
        $this->assertSame(2525, $steps['mta']['data']['port']);
    }

    public function test_the_guide_is_all_done_for_a_complete_setup(): void
    {
        $this->switchOn();
        $this->fakeServer();
        $this->fakeDns(
            ['198.51.100.7' => 'mail1.example.com', '198.51.100.1' => 'netsendo.example.com'],
            ['netsendo.example.com' => ['198.51.100.1'], 'mail1.example.com' => ['198.51.100.7']]
        );
        $this->fakeDkimRecords(true);
        $this->fullSetup();

        $steps = $this->guide();

        foreach (['switch', 'mta', 'port25', 'server_name', 'domain', 'ip', 'dkim', 'ip_on_server', 'ptr', 'mailbox'] as $key) {
            $this->assertSame('ok', $steps[$key]['status'], $key);
        }
        $this->assertSame('info', $steps['test']['status']);
    }

    public function test_the_guide_points_at_what_is_missing_for_an_ip(): void
    {
        $this->switchOn();
        // The IP is not on the server, its PTR is the provider's default, the DKIM record is missing
        $this->fakeServer(['10.58.0.1', '198.51.100.1']);
        $this->fakeDns(['198.51.100.7' => 'static.7.100.51.198.provider.example'], ['mail1.example.com' => ['198.51.100.7']]);
        $this->fakeDkimRecords(false);
        [, $ip] = $this->fullSetup();

        $steps = $this->guide();

        $this->assertSame('warning', $steps['dkim']['status']);
        $this->assertSame('warning', $steps['ip_on_server']['status']);
        $this->assertSame('warning', $steps['ptr']['status']);
        $this->assertSame($ip->id, $steps['ip_on_server']['data']['ips'][0]['id']);
        $this->assertSame('198.51.100.7', $steps['ptr']['data']['ips'][0]['ip_address']);
        $this->assertSame('hetzner', $steps['ip_on_server']['data']['ips'][0]['provider']);
    }

    // --- Hosting providers ---

    public function test_the_hosting_providers_are_listed_with_their_documentation(): void
    {
        $providers = HostingProviders::all();

        $this->assertNotEmpty($providers);
        foreach ($providers as $provider) {
            $this->assertMatchesRegularExpression('/^[a-z0-9_]+$/', $provider['key']);
            $this->assertContains($provider['port25'], ['open', 'on_request', 'blocked', 'unconfirmed']);
            $this->assertContains($provider['ptr'], ['panel', 'panel_api', 'api', 'ticket', 'none']);
            $this->assertContains($provider['fit'], ['recommended', 'possible', 'avoid']);
            $this->assertIsBool($provider['extra_ips']);
            foreach ($provider['links'] as $url) {
                $this->assertStringStartsWith('https://', $url);
            }
        }

        $this->assertSame('blocked', HostingProviders::find('google_cloud')['port25']);
        // The provider keys of IPs reserved through the provider integration have instructions
        foreach (['vultr', 'linode', 'digitalocean'] as $key) {
            $this->assertNotNull(HostingProviders::find($key), $key);
        }

        $this->fakeServer();
        $this->actingAs($this->admin)->getJson(route('settings.nmi.dashboard'))
            ->assertJsonPath('data.hostingProviders.0.key', $providers[0]['key']);
    }

    public function test_the_guide_and_provider_texts_exist_in_every_language(): void
    {
        foreach (['en', 'pl', 'de', 'es'] as $locale) {
            $messages = json_decode(File::get(resource_path("js/locales/{$locale}.json")), true);

            foreach (['switch', 'mta', 'port25', 'server_name', 'domain', 'ip', 'dkim', 'ip_on_server', 'ptr', 'mailbox', 'test'] as $step) {
                $this->assertIsString(data_get($messages, "nmi.guide.{$step}.title"), "{$locale}: nmi.guide.{$step}.title");
            }
            foreach (HostingProviders::keys() as $key) {
                $this->assertIsString(data_get($messages, "nmi.hosting.notes.{$key}"), "{$locale}: nmi.hosting.notes.{$key}");
            }
        }
    }

    public function test_an_ip_is_added_with_its_hosting_provider(): void
    {
        $this->switchOn();
        $pool = IpPool::create([
            'user_id' => $this->admin->id,
            'name' => 'Main pool',
            'type' => IpPool::TYPE_DEDICATED,
            'is_active' => true,
            'max_ips' => 10,
        ]);
        $provider = HostingProviders::all()[0]['key'];

        $this->actingAs($this->admin)
            ->postJson(route('settings.nmi.pools.ips.store', $pool), [
                'ip_address' => '198.51.100.7',
                'hostname' => 'mail1.example.com',
                'provider' => $provider,
            ])
            ->assertSuccessful();
        $this->assertSame($provider, DedicatedIpAddress::where('ip_address', '198.51.100.7')->value('provider'));

        $this->actingAs($this->admin)
            ->postJson(route('settings.nmi.pools.ips.store', $pool), [
                'ip_address' => '198.51.100.8',
                'hostname' => 'mail2.example.com',
                'provider' => 'nonexistent',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('provider');
    }
}
