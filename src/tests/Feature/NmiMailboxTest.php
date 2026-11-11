<?php

namespace Tests\Feature;

use App\Models\DedicatedIpAddress;
use App\Models\DomainConfiguration;
use App\Models\IpPool;
use App\Models\Mailbox;
use App\Models\User;
use App\Services\Mail\MailProviderService;
use App\Services\Mail\Providers\NmiProvider;
use App\Services\Nmi\MtaCredentialService;
use App\Services\Nmi\MtaStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia;
use Tests\Feature\Concerns\ActivatesGold;
use Tests\TestCase;

/**
 * Settings → Mailboxes, provider "NetSendo MTA" (NMI): a mailbox sends as one of the
 * account's verified domains, optionally through a dedicated IP, and logs in to the MTA
 * (Haraka) with a login NetSendo issues for the domain and writes to the MTA's login file.
 * Until 2026-11 the provider was offered but could never be saved, let alone send.
 */
class NmiMailboxTest extends TestCase
{
    use RefreshDatabase;
    use ActivatesGold;

    private User $user;

    private string $authFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->authFile = storage_path('framework/testing/nmi-mta-' . uniqid() . '/auth.json');

        config([
            'nmi.features.enabled' => true,
            'nmi.mta_auth_file' => $this->authFile,
        ]);

        $this->activateGold();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(dirname($this->authFile));

        parent::tearDown();
    }

    private function silver(): void
    {
        \App\Models\Setting::whereIn('key', ['license_key', 'license_plan', 'license_expires_at'])->delete();
    }

    private function domain(string $name = 'example.com', array $extra = [], ?User $owner = null): DomainConfiguration
    {
        return DomainConfiguration::create(array_merge([
            'user_id' => ($owner ?? $this->user)->id,
            'domain' => $name,
            'cname_verified' => true,
            'cname_verified_at' => now(),
        ], $extra));
    }

    private function ip(string $address = '203.0.113.10', array $extra = [], ?User $owner = null): DedicatedIpAddress
    {
        $pool = IpPool::create([
            'user_id' => ($owner ?? $this->user)->id,
            'name' => 'Pool ' . $address,
            'type' => IpPool::TYPE_DEDICATED,
            'is_active' => true,
        ]);

        return DedicatedIpAddress::create(array_merge([
            'ip_pool_id' => $pool->id,
            'ip_address' => $address,
            'hostname' => 'mail.example.com',
            'ip_version' => 'ipv4',
            'provider' => DedicatedIpAddress::PROVIDER_MANUAL,
            'warming_status' => DedicatedIpAddress::WARMING_NEW,
            'is_active' => true,
        ], $extra));
    }

    private function payload(DomainConfiguration $domain, array $extra = []): array
    {
        return array_merge([
            'name' => 'Newsletter',
            'provider' => 'nmi',
            'from_email' => 'news@' . $domain->domain,
            'from_name' => 'News',
            'reply_to' => null,
            'is_active' => true,
            'allowed_types' => ['broadcast', 'autoresponder', 'system'],
            'credentials' => [],
            'domain_configuration_id' => $domain->id,
            'dedicated_ip_id' => null,
            'daily_limit' => null,
            'time_restriction' => 2,
            'custom_headers' => [],
            'tracking_enabled' => true,
        ], $extra);
    }

    private function store(array $payload)
    {
        return $this->actingAs($this->user)
            ->from(route('settings.mailboxes.index'))
            ->post(route('settings.mailboxes.store'), $payload);
    }

    private function nmiMailbox(DomainConfiguration $domain, ?DedicatedIpAddress $ip = null): Mailbox
    {
        $this->store($this->payload($domain, ['dedicated_ip_id' => $ip?->id]))->assertSessionHasNoErrors();

        return Mailbox::where('user_id', $this->user->id)->latest('id')->firstOrFail();
    }

    private function logins(): array
    {
        return json_decode(File::get($this->authFile), true)['users'];
    }

    // --- The form ---

    public function test_the_form_offers_nmi_with_the_accounts_verified_domains_and_ips(): void
    {
        $this->withoutVite();

        $verified = $this->domain('example.com');
        $this->domain('pending.example', ['cname_verified' => false]);
        $this->domain('theirs.example', [], User::factory()->create());

        $ip = $this->ip('203.0.113.10');
        $this->ip('203.0.113.11', ['is_active' => false]);
        $this->ip('203.0.113.12', [], User::factory()->create());

        $this->actingAs($this->user)
            ->get(route('settings.mailboxes.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Settings/Mailboxes/Index')
                ->where('providers', fn ($providers) => array_keys(collect($providers)->all()) === ['smtp', 'sendgrid', 'gmail', 'nmi'])
                ->where('nmi.enabled', true)
                ->where('nmi.domains', fn ($domains) => collect($domains)->pluck('id')->all() === [$verified->id])
                ->where('nmi.ips', fn ($ips) => collect($ips)->pluck('id')->all() === [$ip->id])
                ->where('license.features.mail_infrastructure', true)
            );
    }

    public function test_nmi_is_not_offered_where_the_mta_is_switched_off(): void
    {
        $this->withoutVite();
        config(['nmi.features.enabled' => false]);
        $domain = $this->domain();

        $this->actingAs($this->user)
            ->get(route('settings.mailboxes.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('providers', fn ($providers) => array_keys(collect($providers)->all()) === ['smtp', 'sendgrid', 'gmail'])
                ->where('nmi.enabled', false)
                ->where('nmi.domains', [])
            );

        $this->store($this->payload($domain))->assertSessionHasErrors('provider');
        $this->assertSame(0, Mailbox::count());
    }

    // --- Saving ---

    public function test_an_nmi_mailbox_is_saved_with_its_domain_and_the_domain_gets_an_mta_login(): void
    {
        $domain = $this->domain();

        $this->store($this->payload($domain))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('settings.mailboxes.index'));

        $mailbox = Mailbox::where('user_id', $this->user->id)->sole();
        $this->assertSame('nmi', $mailbox->provider);
        $this->assertSame($domain->id, $mailbox->domain_configuration_id);
        $this->assertNull($mailbox->dedicated_ip_id);
        $this->assertSame([], $mailbox->getDecryptedCredentials());

        $domain->refresh();
        $this->assertTrue($domain->nmi_enabled);
        $this->assertNotNull($domain->nmi_enabled_at);
        $this->assertMatchesRegularExpression('/^nmi-' . $domain->id . '-[a-z0-9]{10}$/', $domain->nmi_smtp_username);

        $password = $domain->getDecryptedNmiPassword();
        $this->assertSame(48, strlen($password));
        // Encrypted at rest, never part of what a page receives
        $this->assertNotSame($password, $domain->getRawOriginal('nmi_smtp_password'));
        $this->assertArrayNotHasKey('nmi_smtp_password', $domain->toArray());

        // The MTA's login file holds the hash, not the password
        $this->assertSame([$domain->nmi_smtp_username => hash('sha256', $password)], $this->logins());
        $this->assertStringNotContainsString($password, File::get($this->authFile));
    }

    public function test_a_second_mailbox_of_the_domain_reuses_the_login(): void
    {
        $domain = $this->domain();
        $this->nmiMailbox($domain);
        $first = $domain->refresh()->getDecryptedNmiPassword();

        $this->store($this->payload($domain, ['name' => 'Second', 'from_email' => 'shop@example.com']))
            ->assertSessionHasNoErrors();

        $this->assertSame($first, $domain->refresh()->getDecryptedNmiPassword());
        $this->assertCount(1, $this->logins());
        $this->assertSame(2, $domain->nmiMailboxes()->count());
    }

    public function test_the_domain_must_be_the_accounts_own_verified_domain(): void
    {
        $pending = $this->domain('pending.example', ['cname_verified' => false]);
        $theirs = $this->domain('theirs.example', [], User::factory()->create());

        $this->store($this->payload($pending))->assertSessionHasErrors('domain_configuration_id');
        $this->store($this->payload($theirs))->assertSessionHasErrors('domain_configuration_id');
        $this->store($this->payload($this->domain(), ['domain_configuration_id' => null]))
            ->assertSessionHasErrors('domain_configuration_id');

        $this->assertSame(0, Mailbox::count());
        $this->assertFalse($pending->refresh()->nmi_enabled);
        $this->assertFalse($theirs->refresh()->nmi_enabled);
    }

    public function test_the_sender_address_must_be_in_the_chosen_domain(): void
    {
        $domain = $this->domain('example.com');

        $this->store($this->payload($domain, ['from_email' => 'news@other.example']))
            ->assertSessionHasErrors('from_email');
        $this->store($this->payload($domain, ['from_email' => 'news@sub.example.com']))
            ->assertSessionHasErrors('from_email');

        $this->assertSame(0, Mailbox::count());

        $this->store($this->payload($domain, ['from_email' => 'News@EXAMPLE.com']))->assertSessionHasNoErrors();
        $this->assertSame(1, Mailbox::count());
    }

    public function test_a_mailbox_that_could_not_log_in_is_not_saved(): void
    {
        // A path below a file: the login file cannot be written
        config(['nmi.mta_auth_file' => base_path('artisan') . '/nmi/auth.json']);
        $domain = $this->domain();

        $this->store($this->payload($domain))->assertSessionHasErrors('domain_configuration_id');

        $this->assertSame(0, Mailbox::count());
    }

    // --- Dedicated IP ---

    public function test_a_free_dedicated_ip_is_assigned_to_the_domain_of_the_mailbox(): void
    {
        $domain = $this->domain();
        $ip = $this->ip();

        $mailbox = $this->nmiMailbox($domain, $ip);

        $this->assertSame($ip->id, $mailbox->dedicated_ip_id);
        $this->assertSame($domain->id, $ip->refresh()->domain_configuration_id);
        $this->assertSame(1, $ip->mailboxes()->count());

        // The IP cannot be removed while a mailbox sends through it (NmiController::deleteIp)
        $this->actingAs($this->user)
            ->deleteJson(route('settings.nmi.ips.destroy', $ip->id))
            ->assertStatus(400);
        $this->assertNotNull($ip->fresh());
    }

    public function test_an_ip_of_another_domain_account_or_an_inactive_one_is_refused(): void
    {
        $domain = $this->domain('example.com');
        $other = $this->domain('other.example');

        $taken = $this->ip('203.0.113.20', ['domain_configuration_id' => $other->id]);
        $inactive = $this->ip('203.0.113.21', ['is_active' => false]);
        $theirs = $this->ip('203.0.113.22', [], User::factory()->create());

        foreach ([$taken, $inactive, $theirs] as $ip) {
            $this->store($this->payload($domain, ['dedicated_ip_id' => $ip->id]))
                ->assertSessionHasErrors('dedicated_ip_id');
        }

        $this->assertSame(0, Mailbox::count());
        $this->assertSame($other->id, $taken->refresh()->domain_configuration_id);
    }

    // --- Updating ---

    public function test_the_active_switch_keeps_the_domain_and_ip(): void
    {
        $domain = $this->domain();
        $ip = $this->ip();
        $mailbox = $this->nmiMailbox($domain, $ip);

        // What the switch in the list sends: no domain, no IP, no credentials
        $this->actingAs($this->user)
            ->put(route('settings.mailboxes.update', $mailbox->id), [
                'name' => $mailbox->name,
                'provider' => 'nmi',
                'from_email' => $mailbox->from_email,
                'from_name' => $mailbox->from_name,
                'is_active' => false,
                'allowed_types' => $mailbox->allowed_types,
                'daily_limit' => null,
            ])
            ->assertSessionHasNoErrors();

        $mailbox->refresh();
        $this->assertFalse($mailbox->is_active);
        $this->assertSame($domain->id, $mailbox->domain_configuration_id);
        $this->assertSame($ip->id, $mailbox->dedicated_ip_id);
    }

    public function test_a_link_that_stopped_qualifying_does_not_block_editing_the_mailbox(): void
    {
        $domain = $this->domain();
        $ip = $this->ip();
        $mailbox = $this->nmiMailbox($domain, $ip);

        // The domain lost its verification and the IP was paused after the mailbox was set up
        $domain->update(['cname_verified' => false]);
        $ip->update(['is_active' => false]);

        $this->actingAs($this->user)
            ->put(route('settings.mailboxes.update', $mailbox->id), $this->payload($domain, ['name' => 'Renamed', 'dedicated_ip_id' => $ip->id]))
            ->assertSessionHasNoErrors();

        $mailbox->refresh();
        $this->assertSame('Renamed', $mailbox->name);
        $this->assertSame($domain->id, $mailbox->domain_configuration_id);
        $this->assertSame($ip->id, $mailbox->dedicated_ip_id);

        // A new mailbox cannot be set up on them
        $this->store($this->payload($domain, ['name' => 'Second']))->assertSessionHasErrors('domain_configuration_id');
    }

    public function test_switching_to_another_provider_drops_the_domain_and_ip(): void
    {
        $domain = $this->domain();
        $mailbox = $this->nmiMailbox($domain, $this->ip());

        $this->actingAs($this->user)
            ->put(route('settings.mailboxes.update', $mailbox->id), $this->payload($domain, [
                'provider' => 'smtp',
                'credentials' => ['host' => 'smtp.example.com', 'port' => 587, 'encryption' => 'tls', 'username' => 'u', 'password' => 'p'],
                'domain_configuration_id' => null,
            ]))
            ->assertSessionHasNoErrors();

        $mailbox->refresh();
        $this->assertSame('smtp', $mailbox->provider);
        $this->assertNull($mailbox->domain_configuration_id);
        $this->assertNull($mailbox->dedicated_ip_id);
        $this->assertSame('smtp.example.com', $mailbox->getDecryptedCredentials()['host']);
    }

    public function test_an_smtp_mailbox_switched_to_nmi_forgets_its_smtp_login(): void
    {
        $domain = $this->domain();
        $mailbox = Mailbox::create([
            'user_id' => $this->user->id,
            'name' => 'Old',
            'provider' => 'smtp',
            'from_email' => 'news@example.com',
            'from_name' => 'News',
            'allowed_types' => ['broadcast'],
            'credentials' => ['host' => 'smtp.example.com', 'password' => 'secret'],
        ]);

        $this->actingAs($this->user)
            ->put(route('settings.mailboxes.update', $mailbox->id), $this->payload($domain))
            ->assertSessionHasNoErrors();

        $mailbox->refresh();
        $this->assertSame('nmi', $mailbox->provider);
        $this->assertSame($domain->id, $mailbox->domain_configuration_id);
        $this->assertSame([], $mailbox->getDecryptedCredentials());
    }

    // --- GOLD ---

    public function test_setting_nmi_up_needs_gold(): void
    {
        $this->silver();
        $domain = $this->domain();

        $this->actingAs($this->user)
            ->withHeader('X-Inertia', 'true')
            ->from(route('settings.mailboxes.index'))
            ->post(route('settings.mailboxes.store'), $this->payload($domain))
            ->assertSessionHas('gold_required', 'mail_infrastructure')
            ->assertSessionHasErrors('gold_required');

        $this->assertSame(0, Mailbox::count());
        $this->assertFalse($domain->refresh()->nmi_enabled);
    }

    public function test_an_nmi_mailbox_set_up_under_gold_stays_editable_and_keeps_sending_without_it(): void
    {
        $domain = $this->domain();
        $other = $this->domain('other.example');
        $mailbox = $this->nmiMailbox($domain);

        $this->silver();

        // Saving the form with the same domain: no licence needed
        $this->actingAs($this->user)
            ->put(route('settings.mailboxes.update', $mailbox->id), $this->payload($domain, ['name' => 'Renamed']))
            ->assertSessionHasNoErrors();
        $this->assertSame('Renamed', $mailbox->refresh()->name);

        // Sending through it still works
        $this->assertInstanceOf(NmiProvider::class, app(MailProviderService::class)->getProvider($mailbox));

        // Moving it to another domain is setting NMI up again
        $this->actingAs($this->user)
            ->withHeader('X-Inertia', 'true')
            ->from(route('settings.mailboxes.index'))
            ->put(route('settings.mailboxes.update', $mailbox->id), $this->payload($other, ['from_email' => 'news@other.example']))
            ->assertSessionHasErrors('gold_required');
        $this->assertSame($domain->id, $mailbox->refresh()->domain_configuration_id);
    }

    public function test_an_nmi_mailbox_stays_editable_after_the_mta_is_switched_off(): void
    {
        $domain = $this->domain();
        $mailbox = $this->nmiMailbox($domain);

        config(['nmi.features.enabled' => false]);

        $this->actingAs($this->user)
            ->put(route('settings.mailboxes.update', $mailbox->id), $this->payload($domain, ['name' => 'Renamed']))
            ->assertSessionHasNoErrors();

        $this->assertSame('Renamed', $mailbox->refresh()->name);
    }

    // --- Sending ---

    public function test_the_provider_logs_in_with_the_domains_login(): void
    {
        $domain = $this->domain();
        $ip = $this->ip();
        $mailbox = $this->nmiMailbox($domain, $ip);

        $provider = app(MailProviderService::class)->getProvider($mailbox->fresh());

        $this->assertInstanceOf(NmiProvider::class, $provider);
        $this->assertSame('example.com', $provider->getStatus()['domain']);
        $this->assertSame($ip->ip_address, $provider->getStatus()['dedicated_ip']);
    }

    public function test_mail_is_signed_with_the_dkim_key_of_the_ip_assigned_to_the_domain(): void
    {
        $domain = $this->domain();
        $ip = $this->ip();
        $mailbox = $this->nmiMailbox($domain, $ip);
        $dkimEnabled = fn () => app(MailProviderService::class)->getProvider($mailbox->fresh())->getStatus()['dkim_enabled'];

        // No key yet: sent unsigned
        $this->assertFalse($dkimEnabled());

        // Saving the mailbox assigned the IP to the domain, so its key can be generated
        // (the IP's page, "Generate DKIM"); before, this failed for want of a domain
        $response = $this->actingAs($this->user)
            ->postJson(route('settings.nmi.ips.dkim.generate', $ip->id))
            ->assertOk();
        $this->assertStringEndsWith('._domainkey.example.com', $response->json('data.dns_name'));

        $this->assertTrue($dkimEnabled());

        // The DNS record carries the public half of the key the mail is signed with
        $ip->refresh();
        $record = app(\App\Services\Nmi\DkimKeyManager::class)->getDnsInstructions($ip);
        $this->assertStringStartsWith('v=DKIM1; k=rsa; p=', $record['value']);
        $publicKey = "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(str_replace('" "', '', substr($record['value'], strlen('v=DKIM1; k=rsa; p='))), 64, "\n")
            . "-----END PUBLIC KEY-----\n";
        openssl_sign('message', $signature, $ip->getDecryptedDkimPrivateKey(), OPENSSL_ALGO_SHA256);
        $this->assertSame(1, openssl_verify('message', $signature, $publicKey, OPENSSL_ALGO_SHA256));

        // A key published under another domain is never used for this one
        $ip->update(['domain_configuration_id' => $this->domain('other.example')->id]);
        $this->assertFalse($dkimEnabled());
    }

    public function test_a_lost_login_is_issued_again_when_the_mailbox_sends(): void
    {
        $domain = $this->domain();
        $mailbox = $this->nmiMailbox($domain);

        $domain->forceFill(['nmi_smtp_password' => null])->save();
        File::delete($this->authFile);

        app(MailProviderService::class)->getProvider($mailbox->fresh());

        $domain->refresh();
        $this->assertSame([$domain->nmi_smtp_username => hash('sha256', $domain->getDecryptedNmiPassword())], $this->logins());
    }

    public function test_a_mailbox_whose_domain_was_removed_says_so_instead_of_crashing(): void
    {
        $domain = $this->domain();
        $mailbox = $this->nmiMailbox($domain);
        $this->assertCount(1, $this->logins());

        $domain->delete();

        // The removed domain can no longer log in to the MTA
        $this->assertSame([], $this->logins());

        $this->actingAs($this->user)
            ->postJson(route('settings.mailboxes.test', $mailbox->id))
            ->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', __('mailboxes.nmi.errors.domain_missing'));

        // The form asks for a domain again, but the switch in the list still turns it off
        $this->actingAs($this->user)
            ->put(route('settings.mailboxes.update', $mailbox->id), $this->payload($domain, ['domain_configuration_id' => null]))
            ->assertSessionHasErrors('domain_configuration_id');

        $this->actingAs($this->user)
            ->put(route('settings.mailboxes.update', $mailbox->id), [
                'name' => $mailbox->name,
                'provider' => 'nmi',
                'from_email' => $mailbox->from_email,
                'from_name' => $mailbox->from_name,
                'is_active' => false,
                'allowed_types' => $mailbox->allowed_types,
                'daily_limit' => null,
            ])
            ->assertSessionHasNoErrors();
        $this->assertFalse($mailbox->refresh()->is_active);
    }

    // --- The MTA's login file ---

    public function test_rotating_a_login_replaces_its_hash_and_revoking_removes_it(): void
    {
        $domain = $this->domain();
        $this->nmiMailbox($domain);
        $domain->refresh();
        $old = $this->logins()[$domain->nmi_smtp_username];

        app(MtaCredentialService::class)->rotate($domain);

        $new = $this->logins()[$domain->nmi_smtp_username];
        $this->assertNotSame($old, $new);
        $this->assertSame(hash('sha256', $domain->refresh()->getDecryptedNmiPassword()), $new);

        app(MtaCredentialService::class)->revoke($domain);
        $this->assertSame([], $this->logins());
    }

    public function test_the_sync_command_restores_a_lost_login_file(): void
    {
        $domain = $this->domain();
        $this->nmiMailbox($domain);
        $before = File::get($this->authFile);

        File::delete($this->authFile);

        $this->artisan('nmi:sync-mta-auth')->assertSuccessful();
        $this->assertSame($before, File::get($this->authFile));

        // Nothing is written where the MTA is switched off
        File::delete($this->authFile);
        config(['nmi.features.enabled' => false]);

        $this->artisan('nmi:sync-mta-auth')->assertSuccessful();
        $this->assertFileDoesNotExist($this->authFile);
    }

    // --- The MTA itself ---

    public function test_the_mta_status_says_offline_when_nothing_answers(): void
    {
        // A port nothing listens on
        config(['nmi.mta_host' => '127.0.0.1', 'nmi.mta_port' => 1]);

        $this->actingAs($this->user)
            ->getJson(route('settings.nmi.mta.status'))
            ->assertOk()
            ->assertJsonPath('data.online', false)
            ->assertJsonPath('data.host', '127.0.0.1')
            ->assertJsonPath('data.port', 1);
    }

    public function test_the_mta_status_says_online_on_an_smtp_greeting(): void
    {
        $this->mock(MtaStatusService::class)->shouldReceive('probe')->once()->andReturn([
            'online' => true, 'host' => 'netsendo-mta', 'port' => 25, 'message' => 'ok', 'checked_at' => now()->toIso8601String(),
        ]);

        $this->actingAs($this->user)
            ->getJson(route('settings.nmi.mta.status'))
            ->assertJsonPath('data.online', true);
    }

    public function test_the_nmi_dashboard_knows_the_mta_is_switched_on(): void
    {
        $this->withoutVite();

        $this->actingAs($this->user)
            ->get(route('settings.nmi.dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('nmiEnabled', true));
    }

    public function test_an_ip_added_by_hand_is_stored_with_its_version(): void
    {
        $pool = IpPool::create(['user_id' => $this->user->id, 'name' => 'Pool', 'type' => IpPool::TYPE_DEDICATED, 'is_active' => true]);

        $this->actingAs($this->user)
            ->postJson(route('settings.nmi.pools.ips.store', $pool->id), ['ip_address' => '203.0.113.30', 'hostname' => 'mail.example.com'])
            ->assertCreated();
        $this->actingAs($this->user)
            ->postJson(route('settings.nmi.pools.ips.store', $pool->id), ['ip_address' => '2001:db8::30', 'hostname' => 'mail6.example.com'])
            ->assertCreated();

        $this->assertSame('ipv4', DedicatedIpAddress::where('ip_address', '203.0.113.30')->value('ip_version'));
        $this->assertSame('ipv6', DedicatedIpAddress::where('ip_address', '2001:db8::30')->value('ip_version'));
    }

    /**
     * End to end against a running MTA container (not part of the normal suite):
     *
     *   NMI_E2E_MTA_HOST=127.0.0.1 NMI_E2E_MTA_PORT=12525 NMI_E2E_AUTH_FILE=/path/shared/with/the/mta/auth.json \
     *   php artisan test --filter=test_a_saved_nmi_mailbox_is_accepted_by_a_running_mta
     *
     * NMI_E2E_AUTH_FILE is the file the container reads as /opt/haraka/netsendo/auth.json.
     * The test message goes to a reserved domain (example.org), so nothing is delivered.
     */
    public function test_a_saved_nmi_mailbox_is_accepted_by_a_running_mta(): void
    {
        if (! env('NMI_E2E_MTA_HOST')) {
            $this->markTestSkipped('Needs a running NetSendo MTA: set NMI_E2E_MTA_HOST, NMI_E2E_MTA_PORT and NMI_E2E_AUTH_FILE.');
        }

        config([
            'nmi.mta_host' => env('NMI_E2E_MTA_HOST'),
            'nmi.mta_port' => (int) env('NMI_E2E_MTA_PORT', 25),
            'nmi.mta_auth_file' => env('NMI_E2E_AUTH_FILE'),
        ]);

        $this->actingAs($this->user)
            ->getJson(route('settings.nmi.mta.status'))
            ->assertJsonPath('data.online', true);

        $domain = $this->domain();
        $ip = $this->ip();
        $mailbox = $this->nmiMailbox($domain, $ip);

        $result = app(MailProviderService::class)->getProvider($mailbox)->testConnection('nobody@example.org');
        $this->assertTrue($result['success'], $result['message']);
        $this->assertFalse($result['details']['dkim_enabled']);

        // Signed with the IP's DKIM key, and counted against the IP
        app(\App\Services\Nmi\DkimKeyManager::class)->generateForIp($ip->refresh());

        $provider = app(MailProviderService::class)->getProvider($mailbox->fresh());
        $this->assertTrue($provider->getStatus()['dkim_enabled']);
        $this->assertTrue($provider->send('nobody@example.org', 'Nobody', 'NMI end to end', '<p>Hello</p>'));
        $this->assertSame(1, $ip->refresh()->sent_today);

        // A rotated password works at once, the old one is refused
        $stale = app(MailProviderService::class)->getProvider($mailbox);
        app(MtaCredentialService::class)->rotate($domain->refresh());

        $rotated = app(MailProviderService::class)->getProvider($mailbox->fresh())->testConnection('nobody@example.org');
        $this->assertTrue($rotated['success'], $rotated['message']);
        $refused = $stale->testConnection('nobody@example.org');
        $this->assertFalse($refused['success']);
        $this->assertStringContainsString('535', $refused['message']);

        // Leave no login of the test domain behind in the shared file
        app(MtaCredentialService::class)->revoke($domain->refresh());
    }
}
