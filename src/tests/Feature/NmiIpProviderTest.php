<?php

namespace Tests\Feature;

use App\Models\DedicatedIpAddress;
use App\Models\IpPool;
use App\Models\IpProviderSetting;
use App\Models\User;
use App\Services\Deliverability\DnsLookupService;
use App\Services\Nmi\IpProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Concerns\ActivatesGold;
use Tests\TestCase;

/**
 * Dedicated IPs ordered from a hosting company through its API (Settings → Mail
 * Infrastructure): connecting a provider, ordering an address for the server the MTA runs
 * on, reverse DNS, and giving the address back.
 *
 * The providers' APIs are faked with the request and response shapes of their published
 * references (Vultr API v2, Linode API v4); no real account is called.
 */
class NmiIpProviderTest extends TestCase
{
    use RefreshDatabase;
    use ActivatesGold;

    private User $user;

    private IpPool $pool;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->pool = IpPool::create(['user_id' => $this->user->id, 'name' => 'Pool', 'type' => IpPool::TYPE_DEDICATED, 'is_active' => true]);

        config(['nmi.features.enabled' => true]);
        $this->activateGold();
        Http::preventStrayRequests();
    }

    private function connected(string $provider, string $key = 'test-key'): void
    {
        IpProviderSetting::create(['user_id' => $this->user->id, 'provider' => $provider, 'api_key' => $key, 'enabled' => true]);
    }

    private function vultrInstances(): array
    {
        return [
            'instances' => [
                ['id' => 'cb676a46-66fd-4dfb-b839-443f2e6c0b60', 'label' => 'netsendo', 'hostname' => 'netsendo', 'main_ip' => '192.0.2.10', 'region' => 'fra', 'status' => 'active'],
                ['id' => '4f0f12e5-1f84-404f-aa84-85f431ea5ec2', 'label' => '', 'hostname' => 'db1', 'main_ip' => '192.0.2.11', 'region' => 'ams', 'status' => 'active'],
            ],
            'meta' => ['total' => 2, 'links' => ['next' => '', 'prev' => '']],
        ];
    }

    private function sent(string $method, string $url): int
    {
        return Http::recorded(fn (Request $request) => $request->method() === $method && $request->url() === $url)->count();
    }

    // --- Connecting ---

    public function test_a_provider_is_connected_only_with_a_key_the_provider_accepts(): void
    {
        Http::fake([
            'api.vultr.com/v2/account' => Http::sequence()
                ->push(['error' => 'Invalid API token.', 'status' => 401], 401)
                ->push(['account' => ['name' => 'Example Ltd', 'email' => 'ops@example.com', 'acls' => ['manage_users']]]),
        ]);

        $this->actingAs($this->user)
            ->postJson(route('settings.nmi.providers.store'), ['provider' => 'vultr', 'api_key' => 'wrong'])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', fn ($message) => str_contains($message, 'Vultr') && str_contains($message, 'Invalid API token.'));
        $this->assertSame(0, IpProviderSetting::count());

        $this->actingAs($this->user)
            ->postJson(route('settings.nmi.providers.store'), ['provider' => 'vultr', 'api_key' => ' right-key '])
            ->assertOk()
            ->assertJsonPath('data.account.name', 'Example Ltd')
            ->assertJsonPath('data.providers.vultr.configured', true);

        // Stored encrypted, sent as a bearer token, never returned
        $setting = IpProviderSetting::sole();
        $this->assertSame('right-key', $setting->api_key);
        $this->assertNotSame('right-key', $setting->getRawOriginal('api_key'));
        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer right-key'));

        // An empty key disconnects
        $this->actingAs($this->user)
            ->postJson(route('settings.nmi.providers.store'), ['provider' => 'vultr', 'api_key' => ''])
            ->assertOk()
            ->assertJsonPath('data.providers.vultr.configured', false);
        $this->assertSame(0, IpProviderSetting::count());
    }

    public function test_connecting_needs_gold_and_unknown_providers_are_refused(): void
    {
        $this->actingAs($this->user)
            ->postJson(route('settings.nmi.providers.store'), ['provider' => 'hetzner', 'api_key' => 'x'])
            ->assertStatus(422);

        \App\Models\Setting::whereIn('key', ['license_key', 'license_plan'])->delete();

        $this->actingAs($this->user)
            ->postJson(route('settings.nmi.providers.store'), ['provider' => 'vultr', 'api_key' => 'x'])
            ->assertStatus(402);
        $this->actingAs($this->user)
            ->getJson(route('settings.nmi.providers.servers', 'vultr'))
            ->assertStatus(402);
    }

    // --- Vultr ---

    public function test_vultr_servers_are_listed_page_by_page(): void
    {
        $this->connected('vultr');
        $pages = $this->vultrInstances();

        Http::fake([
            'api.vultr.com/v2/instances?per_page=500' => Http::response([
                'instances' => [$pages['instances'][0]],
                'meta' => ['total' => 1, 'links' => ['next' => 'bmV4dA==', 'prev' => '']],
            ]),
            'api.vultr.com/v2/instances?per_page=500&cursor=bmV4dA%3D%3D' => Http::response([
                'instances' => [$pages['instances'][1]],
                'meta' => ['total' => 1, 'links' => ['next' => '', 'prev' => 'cHJldg==']],
            ]),
        ]);

        $this->actingAs($this->user)
            ->getJson(route('settings.nmi.providers.servers', 'vultr'))
            ->assertOk()
            ->assertJsonPath('data.servers', [
                ['id' => 'cb676a46-66fd-4dfb-b839-443f2e6c0b60', 'label' => 'netsendo', 'region' => 'fra', 'addresses' => ['192.0.2.10']],
                ['id' => '4f0f12e5-1f84-404f-aa84-85f431ea5ec2', 'label' => 'db1', 'region' => 'ams', 'addresses' => ['192.0.2.11']],
            ]);
    }

    public function test_a_vultr_address_is_reserved_in_the_servers_region_attached_and_given_reverse_dns(): void
    {
        $this->connected('vultr');
        $server = 'cb676a46-66fd-4dfb-b839-443f2e6c0b60';
        $reserved = '5ed1bb5b-8f4c-4a2a-b8a8-6f1d8b0a2f6e';

        Http::fake([
            'api.vultr.com/v2/instances*' => Http::response($this->vultrInstances()),
            'api.vultr.com/v2/reserved-ips' => Http::response(['reserved_ip' => [
                'id' => $reserved, 'region' => 'fra', 'ip_type' => 'v4', 'subnet' => '203.0.113.40', 'subnet_size' => 32, 'label' => 'mail2.example.com', 'instance_id' => '',
            ]], 201),
            "api.vultr.com/v2/reserved-ips/{$reserved}/attach" => Http::response(null, 204),
            "api.vultr.com/v2/reserved-ips/{$reserved}/reverse-dns/ipv4" => Http::response(null, 204),
        ]);

        $this->actingAs($this->user)
            ->postJson(route('settings.nmi.pools.provision', $this->pool->id), ['provider' => 'vultr', 'server_id' => $server, 'hostname' => 'Mail2.Example.com'])
            ->assertCreated()
            ->assertJsonPath('data.ip_address', '203.0.113.40')
            ->assertJsonPath('data.provider_managed', true)
            ->assertJsonPath('data.reverse_dns', ['supported' => true, 'set' => true, 'message' => null]);

        $ip = DedicatedIpAddress::sole();
        $this->assertSame('vultr', $ip->provider);
        $this->assertSame($reserved, $ip->provider_id);
        $this->assertSame($server, $ip->provider_server_id);
        $this->assertSame('fra', $ip->provider_region);
        $this->assertSame('mail2.example.com', $ip->hostname);
        $this->assertSame('ipv4', $ip->ip_version);
        $this->assertNull($ip->bind_address);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.vultr.com/v2/reserved-ips'
            && $request->method() === 'POST'
            && $request->data() === ['region' => 'fra', 'ip_type' => 'v4', 'label' => 'mail2.example.com']);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/attach') && $request->data() === ['instance_id' => $server]);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/reverse-dns/ipv4')
            && $request->method() === 'PUT'
            && $request->data() === ['v4' => 'mail2.example.com']);
    }

    public function test_a_vultr_address_that_cannot_be_attached_is_given_back_at_once(): void
    {
        $this->connected('vultr');
        $reserved = '5ed1bb5b-8f4c-4a2a-b8a8-6f1d8b0a2f6e';

        Http::fake([
            'api.vultr.com/v2/instances*' => Http::response($this->vultrInstances()),
            "api.vultr.com/v2/reserved-ips/{$reserved}/attach" => Http::response(['error' => 'Unable to attach reserved IP: instance is locked', 'status' => 400], 400),
            "api.vultr.com/v2/reserved-ips/{$reserved}" => Http::response(null, 204),
            'api.vultr.com/v2/reserved-ips' => Http::response(['reserved_ip' => ['id' => $reserved, 'region' => 'fra', 'ip_type' => 'v4', 'subnet' => '203.0.113.40', 'subnet_size' => 32]], 201),
        ]);

        $this->actingAs($this->user)
            ->postJson(route('settings.nmi.pools.provision', $this->pool->id), ['provider' => 'vultr', 'server_id' => 'cb676a46-66fd-4dfb-b839-443f2e6c0b60', 'hostname' => 'mail2.example.com'])
            ->assertStatus(422)
            ->assertJsonPath('message', fn ($message) => str_contains($message, 'instance is locked'));

        $this->assertSame(0, DedicatedIpAddress::count());
        $this->assertSame(1, $this->sent('DELETE', "https://api.vultr.com/v2/reserved-ips/{$reserved}"));
    }

    public function test_reverse_dns_that_the_provider_refuses_does_not_lose_the_address(): void
    {
        $this->connected('vultr');
        $server = 'cb676a46-66fd-4dfb-b839-443f2e6c0b60';
        $reserved = '5ed1bb5b-8f4c-4a2a-b8a8-6f1d8b0a2f6e';

        Http::fake([
            'api.vultr.com/v2/instances/*/ipv4/reverse' => Http::sequence()
                ->push(['error' => 'Invalid reverse DNS', 'status' => 400], 400)
                ->push(null, 204),
            'api.vultr.com/v2/instances*' => Http::response($this->vultrInstances()),
            "api.vultr.com/v2/reserved-ips/{$reserved}/attach" => Http::response(null, 204),
            "api.vultr.com/v2/reserved-ips/{$reserved}/reverse-dns/ipv4" => Http::response(['error' => 'Not found.', 'status' => 404], 404),
            'api.vultr.com/v2/reserved-ips' => Http::response(['reserved_ip' => ['id' => $reserved, 'region' => 'fra', 'ip_type' => 'v4', 'subnet' => '203.0.113.40', 'subnet_size' => 32]], 201),
        ]);

        // The address is ordered and kept; the reason reverse DNS failed comes back with it
        $this->actingAs($this->user)
            ->postJson(route('settings.nmi.pools.provision', $this->pool->id), ['provider' => 'vultr', 'server_id' => $server, 'hostname' => 'mail2.example.com'])
            ->assertCreated()
            ->assertJsonPath('data.reverse_dns.set', false)
            ->assertJsonPath('data.reverse_dns.message', fn ($message) => str_contains($message, 'Invalid reverse DNS'));

        $ip = DedicatedIpAddress::sole();

        // Set again from the IP's page: the instance's endpoint takes it this time, and the
        // record is looked up right after
        $this->mock(DnsLookupService::class, function ($mock) {
            $mock->shouldReceive('lookupPtr')->with('203.0.113.40')->andReturn(['mail2.example.com']);
            $mock->shouldReceive('lookupAddresses')->with('mail2.example.com')->andReturn(['203.0.113.40']);
        });

        $this->actingAs($this->user)
            ->postJson(route('settings.nmi.ips.ptr.set', $ip->id))
            ->assertOk()
            ->assertJsonPath('data.setup.ptr_verified', true)
            ->assertJsonPath('data.setup.ptr_record', 'mail2.example.com');

        Http::assertSent(fn (Request $request) => $request->url() === "https://api.vultr.com/v2/instances/{$server}/ipv4/reverse"
            && $request->data() === ['ip' => '203.0.113.40', 'reverse' => 'mail2.example.com']);
        $this->assertTrue($ip->refresh()->ptr_verified);
    }

    // --- Linode ---

    public function test_linode_servers_are_listed_with_their_public_addresses_and_smtp_restriction(): void
    {
        $this->connected('linode');

        Http::fake([
            'api.linode.com/v4/linode/instances?page=1&page_size=500' => Http::response([
                'data' => [
                    ['id' => 82378677, 'label' => 'netsendo', 'region' => 'eu-central', 'ipv4' => ['203.0.113.60', '192.168.139.20'], 'status' => 'running', 'capabilities' => ['SMTP Enabled']],
                    ['id' => 82378678, 'label' => 'new-account', 'region' => 'us-east', 'ipv4' => ['203.0.113.61'], 'status' => 'running', 'capabilities' => []],
                ],
                'page' => 1, 'pages' => 1, 'results' => 2,
            ]),
        ]);

        $this->actingAs($this->user)
            ->getJson(route('settings.nmi.providers.servers', 'linode'))
            ->assertOk()
            ->assertJsonPath('data.servers.0', ['id' => '82378677', 'label' => 'netsendo', 'region' => 'eu-central', 'addresses' => ['203.0.113.60'], 'smtp_restricted' => false])
            ->assertJsonPath('data.servers.1.smtp_restricted', true);
    }

    public function test_a_linode_address_is_allocated_and_reverse_dns_waits_for_the_forward_record(): void
    {
        $this->connected('linode');

        Http::fake([
            'api.linode.com/v4/linode/instances/82378677/ips' => Http::response([
                'address' => '203.0.113.70', 'gateway' => '203.0.113.1', 'subnet_mask' => '255.255.255.0', 'prefix' => 24, 'type' => 'ipv4', 'public' => true,
                'rdns' => '203-0-113-70.ip.linodeusercontent.com', 'linode_id' => 82378677, 'region' => 'eu-central', 'reserved' => false,
            ]),
            'api.linode.com/v4/linode/instances/82378677/ips/203.0.113.70' => Http::sequence()
                ->push(['errors' => [['reason' => "We were unable to perform a lookup for 'mail4.example.com' at this time.", 'field' => 'rdns']]], 400)
                ->push(['address' => '203.0.113.70', 'rdns' => 'mail4.example.com', 'linode_id' => 82378677])
                ->push([], 200),
        ]);

        // Ordered; reverse DNS is refused because the A record is not there yet
        $this->actingAs($this->user)
            ->postJson(route('settings.nmi.pools.provision', $this->pool->id), ['provider' => 'linode', 'server_id' => '82378677', 'hostname' => 'mail4.example.com'])
            ->assertCreated()
            ->assertJsonPath('data.ip_address', '203.0.113.70')
            ->assertJsonPath('data.reverse_dns.set', false)
            ->assertJsonPath('data.reverse_dns.message', fn ($message) => str_contains($message, 'unable to perform a lookup'));

        $ip = DedicatedIpAddress::sole();
        $this->assertSame('linode', $ip->provider);
        $this->assertSame('203.0.113.70', $ip->provider_id);
        $this->assertSame('82378677', $ip->provider_server_id);
        $this->assertSame('eu-central', $ip->provider_region);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.linode.com/v4/linode/instances/82378677/ips'
            && $request->method() === 'POST'
            && $request->data() === ['type' => 'ipv4', 'public' => true]);

        // After the A record exists: set from the IP's page
        $this->mock(DnsLookupService::class, function ($mock) {
            $mock->shouldReceive('lookupPtr')->andReturn(['mail4.example.com']);
            $mock->shouldReceive('lookupAddresses')->andReturn(['203.0.113.70']);
        });

        $this->actingAs($this->user)
            ->postJson(route('settings.nmi.ips.ptr.set', $ip->id))
            ->assertOk()
            ->assertJsonPath('data.setup.ptr_verified', true);

        Http::assertSent(fn (Request $request) => $request->method() === 'PUT' && $request->data() === ['rdns' => 'mail4.example.com']);

        // Deleting gives it back to the Linode's account
        $this->actingAs($this->user)->deleteJson(route('settings.nmi.ips.destroy', $ip->id))->assertOk();
        $this->assertSame(1, $this->sent('DELETE', 'https://api.linode.com/v4/linode/instances/82378677/ips/203.0.113.70'));
    }

    public function test_linodes_refusal_without_support_approval_reaches_the_person_as_it_is(): void
    {
        $this->connected('linode');

        Http::fake([
            'api.linode.com/v4/linode/instances/82378677/ips' => Http::response(['errors' => [
                ['reason' => 'Additional IPv4 addresses require technical justification. Please open a Support Ticket describing your requirement'],
            ]], 400),
        ]);

        $this->actingAs($this->user)
            ->postJson(route('settings.nmi.pools.provision', $this->pool->id), ['provider' => 'linode', 'server_id' => '82378677', 'hostname' => 'mail4.example.com'])
            ->assertStatus(422)
            ->assertJsonPath('message', fn ($message) => str_contains($message, 'Linode') && str_contains($message, 'require technical justification'));

        $this->assertSame(0, DedicatedIpAddress::count());
    }

    public function test_an_unreachable_provider_and_a_bad_host_name_are_reported(): void
    {
        $this->connected('linode');

        Http::fake(['api.linode.com/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('timed out')]);

        $this->actingAs($this->user)
            ->getJson(route('settings.nmi.providers.servers', 'linode'))
            ->assertStatus(422)
            ->assertJsonPath('message', __('nmi.providers.errors.unreachable', ['provider' => 'Linode']));

        // A host name is needed for reverse DNS and HELO: a label is not one
        $this->actingAs($this->user)
            ->postJson(route('settings.nmi.pools.provision', $this->pool->id), ['provider' => 'linode', 'server_id' => '1', 'hostname' => 'mail server'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('hostname');

        // Not connected at all
        $this->actingAs($this->user)
            ->postJson(route('settings.nmi.pools.provision', $this->pool->id), ['provider' => 'vultr', 'server_id' => '1', 'hostname' => 'mail.example.com'])
            ->assertStatus(422)
            ->assertJsonPath('message', __('nmi.provider_not_configured'));
    }

    // --- Giving an address back ---

    public function test_deleting_an_ordered_address_releases_it_at_the_provider_first(): void
    {
        $this->connected('vultr');
        $ip = DedicatedIpAddress::create([
            'ip_pool_id' => $this->pool->id, 'ip_address' => '203.0.113.40', 'hostname' => 'mail2.example.com', 'ip_version' => 'ipv4',
            'provider' => 'vultr', 'provider_id' => 'res-1', 'provider_server_id' => 'srv-1', 'warming_status' => 'new', 'is_active' => true,
        ]);

        Http::fake([
            'api.vultr.com/v2/reserved-ips/res-1' => Http::sequence()
                ->push(['error' => 'Rate limit exceeded', 'status' => 429], 429)
                ->push(null, 204),
        ]);

        // The provider refuses: the address stays, with the reason, because it is still billed
        $this->actingAs($this->user)
            ->deleteJson(route('settings.nmi.ips.destroy', $ip->id))
            ->assertStatus(422)
            ->assertJsonPath('release_failed', true)
            ->assertJsonPath('message', fn ($message) => str_contains($message, 'Rate limit exceeded'));
        $this->assertNotNull($ip->fresh());

        $this->actingAs($this->user)
            ->deleteJson(route('settings.nmi.ips.destroy', $ip->id))
            ->assertOk();
        $this->assertSoftDeleted($ip);
        $this->assertSame(2, $this->sent('DELETE', 'https://api.vultr.com/v2/reserved-ips/res-1'));
    }

    public function test_an_address_that_was_removed_before_can_be_added_again(): void
    {
        // Removed addresses are kept (soft-deleted) and ip_address is unique in the table:
        // a provider that hands the same address out again must not hit that index
        $this->connected('linode');
        DedicatedIpAddress::create([
            'ip_pool_id' => $this->pool->id, 'ip_address' => '203.0.113.70', 'ip_version' => 'ipv4',
            'provider' => 'manual', 'warming_status' => 'new', 'is_active' => true,
        ])->delete();

        Http::fake([
            'api.linode.com/v4/linode/instances/82378677/ips' => Http::response(['address' => '203.0.113.70', 'region' => 'eu-central']),
            'api.linode.com/v4/linode/instances/82378677/ips/203.0.113.70' => Http::response(['address' => '203.0.113.70', 'rdns' => 'mail4.example.com']),
        ]);

        $this->actingAs($this->user)
            ->postJson(route('settings.nmi.pools.provision', $this->pool->id), ['provider' => 'linode', 'server_id' => '82378677', 'hostname' => 'mail4.example.com'])
            ->assertCreated();

        $ordered = DedicatedIpAddress::sole();
        $this->assertSame('linode', $ordered->provider);
        $ordered->delete();

        // And by hand
        $this->actingAs($this->user)
            ->postJson(route('settings.nmi.pools.ips.store', $this->pool->id), ['ip_address' => '203.0.113.70', 'hostname' => 'mail4.example.com'])
            ->assertCreated();
        $this->assertSame('manual', DedicatedIpAddress::sole()->provider);
    }

    public function test_an_address_can_be_removed_from_netsendo_only_and_a_manual_one_calls_nobody(): void
    {
        $managed = DedicatedIpAddress::create([
            'ip_pool_id' => $this->pool->id, 'ip_address' => '203.0.113.40', 'ip_version' => 'ipv4',
            'provider' => 'vultr', 'provider_id' => 'res-1', 'provider_server_id' => 'srv-1', 'warming_status' => 'new', 'is_active' => true,
        ]);
        $manual = DedicatedIpAddress::create([
            'ip_pool_id' => $this->pool->id, 'ip_address' => '203.0.113.41', 'ip_version' => 'ipv4',
            'provider' => 'manual', 'warming_status' => 'new', 'is_active' => true,
        ]);

        // No provider is connected and none is faked: a request to one would fail the test
        $this->actingAs($this->user)
            ->deleteJson(route('settings.nmi.ips.destroy', $managed->id), ['keep_at_provider' => true])
            ->assertOk();
        $this->actingAs($this->user)
            ->deleteJson(route('settings.nmi.ips.destroy', $manual->id))
            ->assertOk();

        $this->assertSame(0, DedicatedIpAddress::count());
        Http::assertNothingSent();
    }

    // --- Reverse DNS ---

    public function test_reverse_dns_is_verified_only_when_it_names_the_host_and_the_host_points_back(): void
    {
        $ip = DedicatedIpAddress::create([
            'ip_pool_id' => $this->pool->id, 'ip_address' => '203.0.113.41', 'hostname' => 'Mail3.Example.com.', 'ip_version' => 'ipv4',
            'provider' => 'manual', 'warming_status' => 'new', 'is_active' => true,
        ]);
        $records = ['ptr' => ['41.113.0.203.host.example.net'], 'a' => []];

        // Plain closures: an arrow function would keep the records as they are now
        $this->mock(DnsLookupService::class, function ($mock) use (&$records) {
            $mock->shouldReceive('lookupPtr')->andReturnUsing(function () use (&$records) {
                return $records['ptr'];
            });
            $mock->shouldReceive('lookupAddresses')->andReturnUsing(function () use (&$records) {
                return $records['a'];
            });
        });
        $check = fn () => $this->actingAs($this->user)->postJson(route('settings.nmi.ips.ptr.check', $ip->id))->assertOk()->json('data');

        // The provider's default name
        $data = $check();
        $this->assertFalse($data['check']['verified']);
        $this->assertSame('41.113.0.203.host.example.net', $data['setup']['ptr_record']);
        $this->assertFalse($data['setup']['can_set_ptr']);

        // The right name, but it does not resolve back yet
        $records['ptr'] = ['mail3.example.com'];
        $data = $check();
        $this->assertTrue($data['check']['matches']);
        $this->assertFalse($data['check']['forward_confirmed']);
        $this->assertFalse($ip->refresh()->ptr_verified);

        // Both ways
        $records['a'] = ['203.0.113.41'];
        $this->assertTrue($check()['check']['verified']);
        $this->assertTrue($ip->refresh()->ptr_verified);
        $this->assertNotNull($ip->ptr_verified_at);

        // Gone again
        $records['ptr'] = [];
        $this->assertFalse($check()['check']['verified']);
        $this->assertFalse($ip->refresh()->ptr_verified);
        $this->assertNull($ip->ptr_verified_at);

        // Another account's address
        $this->actingAs(User::factory()->create())->postJson(route('settings.nmi.ips.ptr.check', $ip->id))->assertForbidden();
    }

    // --- DigitalOcean ---

    public function test_digitalocean_addresses_are_no_longer_ordered_but_old_ones_are_released(): void
    {
        $this->connected('digitalocean');
        $old = DedicatedIpAddress::create([
            'ip_pool_id' => $this->pool->id, 'ip_address' => '203.0.113.50', 'ip_version' => 'ipv4',
            'provider' => 'digitalocean', 'provider_id' => '203.0.113.50', 'warming_status' => 'new', 'is_active' => true,
        ]);

        Http::fake(['api.digitalocean.com/v2/reserved_ips/203.0.113.50' => Http::response(null, 204)]);

        $this->actingAs($this->user)
            ->postJson(route('settings.nmi.pools.provision', $this->pool->id), ['provider' => 'digitalocean', 'server_id' => '1', 'hostname' => 'mail.example.com'])
            ->assertStatus(422)
            ->assertJsonPath('message', __('nmi.providers.errors.digitalocean_unusable'));

        $this->actingAs($this->user)->deleteJson(route('settings.nmi.ips.destroy', $old->id))->assertOk();
        $this->assertSame(1, $this->sent('DELETE', 'https://api.digitalocean.com/v2/reserved_ips/203.0.113.50'));
    }

    // --- The pages ---

    public function test_every_text_the_mail_infrastructure_pages_use_exists_in_all_four_languages(): void
    {
        $locales = [];
        foreach (['en', 'pl', 'de', 'es'] as $locale) {
            $locales[$locale] = json_decode(\Illuminate\Support\Facades\File::get(resource_path("js/locales/{$locale}.json")), true);
        }

        $keys = [];
        foreach (['Index', 'IpDetail', 'PoolDetail'] as $page) {
            $source = \Illuminate\Support\Facades\File::get(resource_path("js/Pages/Nmi/{$page}.vue"));
            preg_match_all('/\$?\bt\(\s*["\']([a-z_]+\.[a-z0-9_.]+)["\']\s*[,)]/', $source, $matches);
            $keys = array_merge($keys, $matches[1]);

            // Keys built at run time: the connection guides and the steps after an order
            if ($page === 'Index') {
                preg_match('/const GUIDE_STEPS = \{([^}]+)\}/', $source, $guide);
                preg_match_all('/(\w+):\s*(\d+)/', $guide[1], $steps, PREG_SET_ORDER);
                $this->assertNotEmpty($steps);
                foreach ($steps as [, $provider, $count]) {
                    for ($n = 1; $n <= (int) $count; $n++) {
                        $keys[] = "nmi.providers.guide.{$provider}.s{$n}";
                    }
                }

                preg_match_all('/keys\.push\(([^)]+)\)|const keys = \[([^\]]+)\]/', $source, $next);
                preg_match_all('/"([a-z_]+)"/', implode(' ', array_merge($next[1], $next[2])), $nextKeys);
                $this->assertContains('a_record', $nextKeys[1]);
                foreach ($nextKeys[1] as $key) {
                    $keys[] = "nmi.provision.next.{$key}";
                }
            }
        }

        $this->assertGreaterThan(120, count(array_unique($keys)));

        foreach (array_unique($keys) as $key) {
            foreach ($locales as $locale => $messages) {
                $this->assertIsString(data_get($messages, $key), "{$key} is missing in {$locale}.json");
            }
        }
    }

    public function test_the_ip_page_gets_its_setup_and_a_new_address_can_start_warming(): void
    {
        $this->withoutVite();
        $ip = DedicatedIpAddress::create([
            'ip_pool_id' => $this->pool->id, 'ip_address' => '203.0.113.40', 'hostname' => 'mail2.example.com', 'ip_version' => 'ipv4',
            'provider' => 'vultr', 'provider_id' => 'res-1', 'provider_server_id' => 'srv-1', 'provider_region' => 'fra',
            'warming_status' => 'new', 'is_active' => true,
        ]);

        $this->actingAs($this->user)
            ->get(route('settings.nmi.ips.show', $ip->id))
            ->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->component('Nmi/IpDetail')
                ->where('setup.managed', true)
                ->where('setup.provider', 'vultr')
                ->where('setup.region', 'fra')
                ->where('setup.can_set_ptr', true)
                ->where('setup.ptr_verified', false)
                ->where('ip.warming_status', 'new')
            );

        // The page offered "Start warming" only for a status no address ever has ("cold")
        $page = \Illuminate\Support\Facades\File::get(resource_path('js/Pages/Nmi/IpDetail.vue'));
        $this->assertStringContainsString("ip.warming_status === 'new'", $page);
    }
}
