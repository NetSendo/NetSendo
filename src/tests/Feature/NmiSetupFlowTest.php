<?php

namespace Tests\Feature;

use App\Models\DedicatedIpAddress;
use App\Models\DomainConfiguration;
use App\Models\IpPool;
use App\Models\Mailbox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Concerns\ActivatesGold;
use Tests\TestCase;

/**
 * NetSendo MTA (NMI, GOLD) could not be set up from the panel (2.7.2 review, item 7):
 * - the mailboxes table had no domain_configuration_id / dedicated_ip_id, although the
 *   Mailbox relations and MailProviderService (NmiProvider needs a domain) read them;
 * - MailboxController accepted only smtp, sendgrid and gmail, while the form offered NetSendo MTA;
 * - nothing assigned a dedicated IP to a domain, so "Generate DKIM keys" always failed with
 *   "IP must be assigned to a domain";
 * - nothing switched NMI on for a domain or wrote the SMTP credentials NmiProvider logs in with.
 * Also: "Add IP" stored ip_version as 4/6 in an ENUM('ipv4','ipv6'), deleting an IP queried the
 * missing mailbox column, the dashboard read a config key that does not exist, and the backend
 * NMI messages had no translations.
 */
class NmiSetupFlowTest extends TestCase
{
    use RefreshDatabase;
    use ActivatesGold;

    private User $user;
    private User $other;
    private int $ipCounter = 10;

    protected function setUp(): void
    {
        parent::setUp();

        config(['nmi.features.enabled' => true, 'nmi.features.allow_new_mailboxes' => true]);
        $this->user = User::factory()->create();
        $this->other = User::factory()->create();
    }

    private function domain(array $attributes = [], ?User $owner = null): DomainConfiguration
    {
        return DomainConfiguration::create(array_merge([
            'user_id' => ($owner ?? $this->user)->id,
            'domain' => 'example.com',
            'cname_verified' => true,
            'cname_verified_at' => now(),
        ], $attributes));
    }

    private function pool(?User $owner = null): IpPool
    {
        return IpPool::create([
            'user_id' => ($owner ?? $this->user)->id,
            'name' => 'Main pool',
            'type' => IpPool::TYPE_DEDICATED,
            'is_active' => true,
            'max_ips' => 10,
        ]);
    }

    private function ip(?IpPool $pool = null, array $attributes = []): DedicatedIpAddress
    {
        return DedicatedIpAddress::create(array_merge([
            'ip_pool_id' => ($pool ?? $this->pool())->id,
            'ip_address' => '203.0.113.' . $this->ipCounter++,
            'hostname' => 'mail1.example.com',
            'ip_version' => 'ipv4',
            'provider' => DedicatedIpAddress::PROVIDER_MANUAL,
            'warming_status' => DedicatedIpAddress::WARMING_NEW,
            'reputation_score' => 100,
            'is_active' => true,
        ], $attributes));
    }

    /** A domain with NMI switched on and an IP assigned to it with DKIM keys */
    private function readySetup(): array
    {
        $domain = $this->domain();
        $domain->forceFill([
            'nmi_enabled' => true,
            'nmi_enabled_at' => now(),
            'nmi_smtp_username' => 'nmi-' . $domain->id . '-abcdefgh',
            'nmi_smtp_password' => 's3cret-password',
        ])->save();

        $ip = $this->ip(null, [
            'domain_configuration_id' => $domain->id,
            'dkim_selector' => 'ns-20261108-abcd',
            'dkim_private_key' => 'private-key',
            'dkim_public_key' => "-----BEGIN PUBLIC KEY-----\nMIIB\n-----END PUBLIC KEY-----",
            'dkim_generated_at' => now(),
        ]);
        $domain->forceFill(['dedicated_ip_id' => $ip->id])->save();

        return [$domain->fresh(), $ip->fresh()];
    }

    private function nmiPayload(DomainConfiguration $domain, DedicatedIpAddress $ip, array $extra = []): array
    {
        return array_merge([
            'name' => 'NMI',
            'provider' => 'nmi',
            'from_email' => 'news@example.com',
            'from_name' => 'Example',
            'is_active' => true,
            'allowed_types' => ['broadcast', 'autoresponder', 'system'],
            'daily_limit' => null,
            'domain_configuration_id' => $domain->id,
            'dedicated_ip_id' => $ip->id,
        ], $extra);
    }

    private function nmiMailbox(DomainConfiguration $domain, DedicatedIpAddress $ip, array $attributes = []): Mailbox
    {
        $mailbox = Mailbox::create(array_merge([
            'user_id' => $this->user->id,
            'name' => 'NMI',
            'provider' => 'nmi',
            'from_email' => 'news@example.com',
            'from_name' => 'Example',
            'is_default' => true,
            'is_active' => true,
            'allowed_types' => ['broadcast', 'autoresponder', 'system'],
            'credentials' => [],
        ], $attributes));
        $mailbox->forceFill(['domain_configuration_id' => $domain->id, 'dedicated_ip_id' => $ip->id])->save();

        return $mailbox;
    }

    // --- Schema ---

    public function test_mailboxes_have_the_nmi_domain_and_ip_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('mailboxes', ['domain_configuration_id', 'dedicated_ip_id']));
    }

    // --- Assigning a dedicated IP to a domain ---

    public function test_an_ip_is_assigned_to_a_domain_of_the_same_user_in_both_directions(): void
    {
        $this->activateGold();
        $domain = $this->domain();
        $ip = $this->ip();

        $this->actingAs($this->user)
            ->postJson(route('settings.nmi.ips.domain.assign', $ip), ['domain_configuration_id' => $domain->id])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.domain.id', $domain->id);

        $this->assertSame($domain->id, $ip->fresh()->domain_configuration_id);
        $this->assertSame($ip->id, $domain->fresh()->dedicated_ip_id);
    }

    public function test_an_ip_cannot_be_assigned_to_a_domain_of_another_user(): void
    {
        $this->activateGold();
        $foreign = $this->domain(['domain' => 'other.example'], $this->other);
        $ip = $this->ip();

        $this->actingAs($this->user)
            ->postJson(route('settings.nmi.ips.domain.assign', $ip), ['domain_configuration_id' => $foreign->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('domain_configuration_id');

        $this->assertNull($ip->fresh()->domain_configuration_id);
        $this->assertNull($foreign->fresh()->dedicated_ip_id);
    }

    public function test_an_ip_of_another_user_cannot_be_assigned(): void
    {
        $this->activateGold();
        $domain = $this->domain();
        $foreignIp = $this->ip($this->pool($this->other));

        $this->actingAs($this->user)
            ->postJson(route('settings.nmi.ips.domain.assign', $foreignIp), ['domain_configuration_id' => $domain->id])
            ->assertForbidden();

        $this->assertNull($foreignIp->fresh()->domain_configuration_id);
    }

    public function test_assigning_an_ip_needs_gold(): void
    {
        $domain = $this->domain();
        $ip = $this->ip();

        $this->actingAs($this->user)
            ->postJson(route('settings.nmi.ips.domain.assign', $ip), ['domain_configuration_id' => $domain->id])
            ->assertStatus(402)
            ->assertJsonPath('feature', 'mail_infrastructure');

        $this->assertNull($ip->fresh()->domain_configuration_id);
    }

    public function test_moving_an_ip_to_another_domain_moves_its_primary_ip_link_too(): void
    {
        $this->activateGold();
        $first = $this->domain();
        $second = $this->domain(['domain' => 'second.example']);
        $ip = $this->ip();

        $this->actingAs($this->user)
            ->postJson(route('settings.nmi.ips.domain.assign', $ip), ['domain_configuration_id' => $first->id])
            ->assertOk();
        $this->actingAs($this->user)
            ->postJson(route('settings.nmi.ips.domain.assign', $ip), ['domain_configuration_id' => $second->id])
            ->assertOk();

        $this->assertSame($second->id, $ip->fresh()->domain_configuration_id);
        $this->assertNull($first->fresh()->dedicated_ip_id);
        $this->assertSame($ip->id, $second->fresh()->dedicated_ip_id);
    }

    public function test_the_primary_ip_of_a_domain_passes_to_its_other_ip_when_one_leaves(): void
    {
        $this->activateGold();
        $domain = $this->domain();
        $pool = $this->pool();
        $first = $this->ip($pool, ['ip_address' => '203.0.113.1']);
        $second = $this->ip($pool, ['ip_address' => '203.0.113.2']);

        foreach ([$first, $second] as $ip) {
            $this->actingAs($this->user)
                ->postJson(route('settings.nmi.ips.domain.assign', $ip), ['domain_configuration_id' => $domain->id])
                ->assertOk();
        }
        $this->assertSame($first->id, $domain->fresh()->dedicated_ip_id);

        $this->actingAs($this->user)
            ->deleteJson(route('settings.nmi.ips.domain.unassign', $first))
            ->assertOk();

        $this->assertNull($first->fresh()->domain_configuration_id);
        $this->assertSame($second->id, $domain->fresh()->dedicated_ip_id);
    }

    public function test_unassigning_an_ip_clears_both_directions_without_gold(): void
    {
        [$domain, $ip] = $this->readySetup();

        $this->actingAs($this->user)
            ->deleteJson(route('settings.nmi.ips.domain.unassign', $ip))
            ->assertOk();

        $this->assertNull($ip->fresh()->domain_configuration_id);
        $this->assertNull($domain->fresh()->dedicated_ip_id);
    }

    public function test_an_ip_a_mailbox_sends_through_cannot_be_moved_or_unassigned(): void
    {
        $this->activateGold();
        [$domain, $ip] = $this->readySetup();
        $this->nmiMailbox($domain, $ip);
        $second = $this->domain(['domain' => 'second.example']);

        $this->actingAs($this->user)
            ->postJson(route('settings.nmi.ips.domain.assign', $ip), ['domain_configuration_id' => $second->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('domain_configuration_id');

        $this->actingAs($this->user)
            ->deleteJson(route('settings.nmi.ips.domain.unassign', $ip))
            ->assertStatus(422);

        $this->assertSame($domain->id, $ip->fresh()->domain_configuration_id);
    }

    public function test_dkim_keys_can_be_generated_once_the_ip_has_a_domain(): void
    {
        $this->activateGold();
        $domain = $this->domain();
        $ip = $this->ip();

        $this->actingAs($this->user)
            ->postJson(route('settings.nmi.ips.domain.assign', $ip), ['domain_configuration_id' => $domain->id])
            ->assertOk();

        $response = $this->actingAs($this->user)
            ->postJson(route('settings.nmi.ips.dkim.generate', $ip))
            ->assertOk()
            ->assertJsonPath('success', true);

        $ip->refresh();
        $this->assertNotNull($ip->dkim_selector);
        $this->assertSame("{$ip->dkim_selector}._domainkey.example.com", $response->json('data.dns_name'));
        $this->assertStringStartsWith('v=DKIM1; k=rsa; p=', $response->json('data.dns_record'));
    }

    // --- Switching NMI on for a domain ---

    public function test_nmi_is_enabled_on_a_verified_domain_with_generated_credentials(): void
    {
        $this->activateGold();
        $domain = $this->domain();

        $response = $this->actingAs($this->user)
            ->postJson(route('settings.nmi.domains.enable', $domain))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.nmi_enabled', true);

        $domain->refresh();
        $this->assertTrue($domain->nmi_enabled);
        $this->assertNotNull($domain->nmi_enabled_at);
        $this->assertMatchesRegularExpression('/^nmi-' . $domain->id . '-[a-z0-9]{8}$/', $domain->nmi_smtp_username);
        $this->assertSame($domain->nmi_smtp_username, $response->json('data.nmi_smtp_username'));

        // Stored encrypted, never sent to the browser
        $password = $domain->getDecryptedNmiPassword();
        $this->assertSame(40, strlen($password));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]+$/', $password);
        $this->assertNotSame($password, DB::table('domain_configurations')->where('id', $domain->id)->value('nmi_smtp_password'));
        $this->assertStringNotContainsString($password, $response->getContent());
        $this->assertArrayNotHasKey('nmi_smtp_password', $domain->toArray());
    }

    public function test_nmi_cannot_be_enabled_on_an_unverified_domain(): void
    {
        $this->activateGold();
        $domain = $this->domain(['cname_verified' => false, 'cname_verified_at' => null]);

        $this->actingAs($this->user)
            ->postJson(route('settings.nmi.domains.enable', $domain))
            ->assertStatus(422)
            ->assertJsonValidationErrors('domain');

        $this->assertFalse((bool) $domain->fresh()->nmi_enabled);
        $this->assertNull($domain->fresh()->nmi_smtp_username);
    }

    public function test_nmi_cannot_be_enabled_on_a_domain_of_another_user(): void
    {
        $this->activateGold();
        $foreign = $this->domain(['domain' => 'other.example'], $this->other);

        $this->actingAs($this->user)
            ->postJson(route('settings.nmi.domains.enable', $foreign))
            ->assertForbidden();

        $this->assertFalse((bool) $foreign->fresh()->nmi_enabled);
    }

    public function test_enabling_nmi_needs_gold(): void
    {
        $domain = $this->domain();

        $this->actingAs($this->user)
            ->postJson(route('settings.nmi.domains.enable', $domain))
            ->assertStatus(402);

        $this->assertFalse((bool) $domain->fresh()->nmi_enabled);
    }

    public function test_switching_nmi_off_and_on_again_keeps_the_credentials(): void
    {
        $this->activateGold();
        $domain = $this->domain();

        $this->actingAs($this->user)->postJson(route('settings.nmi.domains.enable', $domain))->assertOk();
        $username = $domain->fresh()->nmi_smtp_username;
        $password = $domain->fresh()->getDecryptedNmiPassword();

        $this->actingAs($this->user)->postJson(route('settings.nmi.domains.disable', $domain))->assertOk();
        $this->assertFalse($domain->fresh()->nmi_enabled);

        $this->actingAs($this->user)->postJson(route('settings.nmi.domains.enable', $domain))->assertOk();
        $this->assertSame($username, $domain->fresh()->nmi_smtp_username);
        $this->assertSame($password, $domain->fresh()->getDecryptedNmiPassword());
    }

    public function test_nmi_cannot_be_switched_off_while_a_mailbox_sends_through_it(): void
    {
        [$domain, $ip] = $this->readySetup();
        $this->nmiMailbox($domain, $ip);

        $this->actingAs($this->user)
            ->postJson(route('settings.nmi.domains.disable', $domain))
            ->assertStatus(422)
            ->assertJsonValidationErrors('domain');

        $this->assertTrue($domain->fresh()->nmi_enabled);
    }

    // --- The NMI mailbox ---

    public function test_an_nmi_mailbox_is_created_with_its_domain_and_ip(): void
    {
        $this->activateGold();
        [$domain, $ip] = $this->readySetup();

        $this->actingAs($this->user)
            ->post(route('settings.mailboxes.store'), $this->nmiPayload($domain, $ip))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $mailbox = Mailbox::where('user_id', $this->user->id)->firstOrFail();
        $this->assertSame('nmi', $mailbox->provider);
        $this->assertSame($domain->id, $mailbox->domain_configuration_id);
        $this->assertSame($ip->id, $mailbox->dedicated_ip_id);
        $this->assertSame($domain->id, $mailbox->domainConfiguration->id);
        $this->assertSame($ip->id, $mailbox->dedicatedIp->id);
        $this->assertSame([], $mailbox->getDecryptedCredentials());
    }

    public function test_an_nmi_mailbox_may_send_from_a_subdomain_but_not_from_another_domain(): void
    {
        $this->activateGold();
        [$domain, $ip] = $this->readySetup();

        $this->actingAs($this->user)
            ->post(route('settings.mailboxes.store'), $this->nmiPayload($domain, $ip, ['from_email' => 'news@other.example']))
            ->assertSessionHasErrors('from_email');

        $this->actingAs($this->user)
            ->post(route('settings.mailboxes.store'), $this->nmiPayload($domain, $ip, ['from_email' => 'news@notexample.com']))
            ->assertSessionHasErrors('from_email');

        $this->actingAs($this->user)
            ->post(route('settings.mailboxes.store'), $this->nmiPayload($domain, $ip, ['from_email' => 'news@mail.example.com']))
            ->assertSessionHasNoErrors();
    }

    public function test_an_nmi_mailbox_needs_its_domain_and_ip(): void
    {
        $this->activateGold();
        [$domain, $ip] = $this->readySetup();

        $this->actingAs($this->user)
            ->post(route('settings.mailboxes.store'), $this->nmiPayload($domain, $ip, [
                'domain_configuration_id' => null,
                'dedicated_ip_id' => null,
            ]))
            ->assertSessionHasErrors(['domain_configuration_id', 'dedicated_ip_id']);

        $this->assertSame(0, Mailbox::count());
    }

    public function test_an_nmi_mailbox_needs_a_verified_domain_with_nmi_switched_on(): void
    {
        $this->activateGold();
        [$domain, $ip] = $this->readySetup();

        $domain->update(['cname_verified' => false]);
        $this->actingAs($this->user)
            ->post(route('settings.mailboxes.store'), $this->nmiPayload($domain, $ip))
            ->assertSessionHasErrors('domain_configuration_id');

        $domain->update(['cname_verified' => true, 'nmi_enabled' => false]);
        $this->actingAs($this->user)
            ->post(route('settings.mailboxes.store'), $this->nmiPayload($domain, $ip))
            ->assertSessionHasErrors('domain_configuration_id');

        $domain->update(['nmi_enabled' => true, 'nmi_smtp_username' => null]);
        $this->actingAs($this->user)
            ->post(route('settings.mailboxes.store'), $this->nmiPayload($domain, $ip))
            ->assertSessionHasErrors('domain_configuration_id');

        $this->assertSame(0, Mailbox::count());
    }

    public function test_an_nmi_mailbox_needs_an_ip_of_its_domain_with_dkim_keys(): void
    {
        $this->activateGold();
        [$domain, $ip] = $this->readySetup();

        $unassigned = $this->ip(null, ['ip_address' => '203.0.113.99', 'dkim_selector' => 'ns-x', 'dkim_private_key' => 'k', 'dkim_public_key' => 'p']);
        $this->actingAs($this->user)
            ->post(route('settings.mailboxes.store'), $this->nmiPayload($domain, $unassigned))
            ->assertSessionHasErrors('dedicated_ip_id');

        $ip->update(['dkim_selector' => null, 'dkim_private_key' => null, 'dkim_public_key' => null]);
        $this->actingAs($this->user)
            ->post(route('settings.mailboxes.store'), $this->nmiPayload($domain, $ip))
            ->assertSessionHasErrors('dedicated_ip_id');

        $this->assertSame(0, Mailbox::count());
    }

    public function test_an_nmi_mailbox_cannot_use_a_domain_or_ip_of_another_user(): void
    {
        $this->activateGold();
        [$domain, $ip] = $this->readySetup();
        $foreignDomain = $this->domain(['domain' => 'other.example', 'nmi_enabled' => true], $this->other);
        $foreignIp = $this->ip($this->pool($this->other), ['ip_address' => '198.51.100.7']);

        $this->actingAs($this->user)
            ->post(route('settings.mailboxes.store'), $this->nmiPayload($foreignDomain, $ip, ['from_email' => 'news@other.example']))
            ->assertSessionHasErrors('domain_configuration_id');

        $this->actingAs($this->user)
            ->post(route('settings.mailboxes.store'), $this->nmiPayload($domain, $foreignIp))
            ->assertSessionHasErrors('dedicated_ip_id');

        $this->assertSame(0, Mailbox::count());
    }

    public function test_an_nmi_mailbox_needs_nmi_switched_on_for_the_installation(): void
    {
        $this->activateGold();
        [$domain, $ip] = $this->readySetup();

        config(['nmi.features.enabled' => false]);
        $this->actingAs($this->user)
            ->post(route('settings.mailboxes.store'), $this->nmiPayload($domain, $ip))
            ->assertSessionHasErrors('provider');

        config(['nmi.features.enabled' => true, 'nmi.features.allow_new_mailboxes' => false]);
        $this->actingAs($this->user)
            ->post(route('settings.mailboxes.store'), $this->nmiPayload($domain, $ip))
            ->assertSessionHasErrors('provider');

        $this->assertSame(0, Mailbox::count());
    }

    public function test_setting_up_an_nmi_mailbox_needs_gold(): void
    {
        [$domain, $ip] = $this->readySetup();

        $this->actingAs($this->user)
            ->postJson(route('settings.mailboxes.store'), $this->nmiPayload($domain, $ip))
            ->assertStatus(402)
            ->assertJsonPath('feature', 'mail_infrastructure');

        $this->assertSame(0, Mailbox::count());
    }

    public function test_an_existing_nmi_mailbox_can_be_edited_without_gold(): void
    {
        [$domain, $ip] = $this->readySetup();
        $mailbox = $this->nmiMailbox($domain, $ip);

        $this->actingAs($this->user)
            ->put(route('settings.mailboxes.update', $mailbox), $this->nmiPayload($domain, $ip, ['name' => 'Renamed']))
            ->assertSessionHasNoErrors();

        $this->assertSame('Renamed', $mailbox->fresh()->name);
        $this->assertSame($ip->id, $mailbox->fresh()->dedicated_ip_id);
    }

    public function test_an_nmi_mailbox_can_be_switched_off_after_its_domain_lost_its_verification(): void
    {
        [$domain, $ip] = $this->readySetup();
        $mailbox = $this->nmiMailbox($domain, $ip);
        $domain->update(['cname_verified' => false]);
        config(['nmi.features.enabled' => false]);

        // What the active toggle on the mailbox card sends
        $this->actingAs($this->user)
            ->put(route('settings.mailboxes.update', $mailbox), $this->nmiPayload($domain, $ip, ['is_active' => false]))
            ->assertSessionHasNoErrors();

        $this->assertFalse($mailbox->fresh()->is_active);
    }

    public function test_an_smtp_mailbox_can_be_switched_to_nmi_and_back(): void
    {
        $this->activateGold();
        [$domain, $ip] = $this->readySetup();
        $mailbox = Mailbox::create([
            'user_id' => $this->user->id,
            'name' => 'SMTP',
            'provider' => 'smtp',
            'from_email' => 'news@example.com',
            'from_name' => 'Example',
            'is_default' => true,
            'is_active' => true,
            'allowed_types' => ['broadcast', 'autoresponder', 'system'],
            'credentials' => ['host' => 'smtp.example.com', 'port' => 587, 'username' => 'u', 'password' => 'p'],
        ]);

        $this->actingAs($this->user)
            ->put(route('settings.mailboxes.update', $mailbox), $this->nmiPayload($domain, $ip))
            ->assertSessionHasNoErrors();
        $mailbox->refresh();
        $this->assertSame('nmi', $mailbox->provider);
        $this->assertSame($domain->id, $mailbox->domain_configuration_id);
        $this->assertSame($ip->id, $mailbox->dedicated_ip_id);

        $this->actingAs($this->user)
            ->put(route('settings.mailboxes.update', $mailbox), [
                'name' => 'SMTP',
                'provider' => 'smtp',
                'from_email' => 'news@example.com',
                'from_name' => 'Example',
                'is_active' => true,
                'allowed_types' => ['broadcast'],
                'daily_limit' => null,
                'credentials' => ['host' => 'smtp.example.com', 'port' => 587, 'username' => 'u', 'password' => 'p'],
                'domain_configuration_id' => $domain->id,
                'dedicated_ip_id' => $ip->id,
            ])
            ->assertSessionHasNoErrors();
        $mailbox->refresh();
        $this->assertSame('smtp', $mailbox->provider);
        $this->assertNull($mailbox->domain_configuration_id);
        $this->assertNull($mailbox->dedicated_ip_id);
    }

    public function test_the_mailbox_page_offers_the_nmi_domains_and_ips_of_the_user(): void
    {
        [$domain, $ip] = $this->readySetup();
        $this->domain(['domain' => 'other.example'], $this->other);
        $mailbox = $this->nmiMailbox($domain, $ip);

        $this->actingAs($this->user)
            ->get(route('settings.mailboxes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Mailboxes/Index')
                ->where('nmi.enabled', true)
                ->has('nmi.domains', 1)
                ->where('nmi.domains.0.id', $domain->id)
                ->where('nmi.domains.0.domain', 'example.com')
                ->where('nmi.domains.0.ready', true)
                ->where('nmi.domains.0.ips.0.id', $ip->id)
                ->where('nmi.domains.0.ips.0.dkim_ready', true)
                ->where('mailboxes.0.id', $mailbox->id)
                ->where('mailboxes.0.domain_configuration_id', $domain->id)
                ->where('mailboxes.0.dedicated_ip_id', $ip->id)
                ->where('mailboxes.0.nmi.domain', 'example.com')
                ->where('mailboxes.0.nmi.ip_address', $ip->ip_address)
            );
    }

    // --- The NMI pages ---

    public function test_the_ip_page_shows_the_domain_choices_and_the_dkim_record(): void
    {
        [$domain, $ip] = $this->readySetup();
        $this->domain(['domain' => 'other.example'], $this->other);

        $this->actingAs($this->user)
            ->getJson(route('settings.nmi.ips.show', $ip))
            ->assertOk()
            ->assertJsonPath('data.ip.domain.id', $domain->id)
            ->assertJsonPath('data.ip.domain.domain', 'example.com')
            ->assertJsonPath('data.ip.domain.nmi_enabled', true)
            ->assertJsonCount(1, 'data.domains')
            ->assertJsonPath('data.domains.0.domain', 'example.com')
            ->assertJsonPath('data.dkim.name', 'ns-20261108-abcd._domainkey.example.com');
    }

    public function test_the_domain_page_shows_the_nmi_setup(): void
    {
        [$domain, $ip] = $this->readySetup();

        $this->actingAs($this->user)
            ->get(route('deliverability.domains.show', $domain))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Deliverability/DomainStatus')
                ->where('nmi.available', true)
                ->where('nmi.enabled', true)
                ->where('nmi.smtp_username', 'nmi-' . $domain->id . '-abcdefgh')
                ->where('nmi.ips.0.id', $ip->id)
                ->where('nmi.ips.0.dkim_ready', true)
                ->where('nmi.mailboxes_count', 0)
                ->missing('nmi.smtp_password')
            );
    }

    public function test_the_dashboard_reports_whether_nmi_is_switched_on(): void
    {
        $this->actingAs($this->user)
            ->getJson(route('settings.nmi.dashboard'))
            ->assertOk()
            ->assertJsonPath('data.nmiEnabled', true);

        config(['nmi.features.enabled' => false]);
        $this->actingAs($this->user)
            ->getJson(route('settings.nmi.dashboard'))
            ->assertJsonPath('data.nmiEnabled', false);
    }

    public function test_an_ip_address_can_be_added_to_a_pool(): void
    {
        $this->activateGold();
        $pool = $this->pool();

        $this->actingAs($this->user)
            ->postJson(route('settings.nmi.pools.ips.store', $pool), ['ip_address' => '203.0.113.20', 'hostname' => 'mail2.example.com'])
            ->assertCreated();
        $this->actingAs($this->user)
            ->postJson(route('settings.nmi.pools.ips.store', $pool), ['ip_address' => '2001:db8::25', 'hostname' => 'mail6.example.com'])
            ->assertCreated();

        $this->assertSame('ipv4', DedicatedIpAddress::where('ip_address', '203.0.113.20')->value('ip_version'));
        $this->assertSame('ipv6', DedicatedIpAddress::where('ip_address', '2001:db8::25')->value('ip_version'));
    }

    public function test_an_ip_a_mailbox_sends_through_cannot_be_deleted(): void
    {
        [$domain, $ip] = $this->readySetup();
        $this->nmiMailbox($domain, $ip);

        $this->actingAs($this->user)
            ->deleteJson(route('settings.nmi.ips.destroy', $ip))
            ->assertStatus(400)
            ->assertJsonPath('success', false);

        $this->assertNotNull($ip->fresh());
    }

    public function test_the_nmi_messages_are_translated(): void
    {
        $keys = [
            'nmi.ip_added', 'nmi.pool_created', 'nmi.warming_started', 'nmi.dkim_generated', 'nmi.ip_has_mailboxes',
            'nmi.ip_assigned', 'nmi.ip_unassigned', 'nmi.ip_in_use', 'nmi.domain_enabled', 'nmi.domain_disabled',
            'nmi.domain_not_verified', 'nmi.domain_in_use',
            'mailboxes.nmi.disabled', 'mailboxes.nmi.new_mailboxes_disabled', 'mailboxes.nmi.domain_not_verified',
            'mailboxes.nmi.domain_not_enabled', 'mailboxes.nmi.ip_not_assigned', 'mailboxes.nmi.ip_no_dkim',
            'mailboxes.nmi.ip_inactive', 'mailboxes.nmi.from_email_domain',
            'mailboxes.nmi.test.not_enabled', 'mailboxes.nmi.test.success',
        ];

        foreach (['en', 'pl', 'de', 'es'] as $locale) {
            App::setLocale($locale);
            foreach ($keys as $key) {
                $this->assertNotSame($key, __($key), "{$key} is missing in lang/{$locale}");
            }
        }
    }
}
