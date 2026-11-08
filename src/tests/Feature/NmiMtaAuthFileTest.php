<?php

namespace Tests\Feature;

use App\Models\DomainConfiguration;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Feature\Concerns\ActivatesGold;
use Tests\TestCase;

/**
 * NetSendo MTA (Haraka) relays only for a NetSendo login: one per domain with NMI switched on.
 * NetSendo writes the logins to a file on a volume shared with the MTA container (the
 * netsendo_auth plugin reads it at every AUTH). The file holds SHA-256 hashes of the
 * generated passwords, never the passwords.
 */
class NmiMtaAuthFileTest extends TestCase
{
    use RefreshDatabase;
    use ActivatesGold;

    private User $user;
    private string $dir;
    private string $file;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir() . '/nmi-auth-test-' . bin2hex(random_bytes(4));
        $this->file = $this->dir . '/mta-users.json';
        config(['nmi.features.enabled' => true, 'nmi.mta_auth_file' => $this->file]);

        $this->user = User::factory()->create();
        $this->activateGold();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);

        parent::tearDown();
    }

    private function domain(string $name = 'example.com', array $attributes = []): DomainConfiguration
    {
        return DomainConfiguration::create(array_merge([
            'user_id' => $this->user->id,
            'domain' => $name,
            'cname_verified' => true,
            'cname_verified_at' => now(),
        ], $attributes));
    }

    private function users(): array
    {
        $this->assertFileExists($this->file);

        return json_decode(File::get($this->file), true)['users'];
    }

    public function test_switching_nmi_on_writes_the_login_with_a_hash_of_the_password(): void
    {
        $domain = $this->domain();

        $this->actingAs($this->user)->postJson(route('settings.nmi.domains.enable', $domain))->assertOk();

        $domain->refresh();
        $password = $domain->getDecryptedNmiPassword();
        $users = $this->users();

        $this->assertSame([$domain->nmi_smtp_username], array_keys($users));
        $this->assertSame(hash('sha256', $password), $users[$domain->nmi_smtp_username]['sha256']);
        $this->assertSame('example.com', $users[$domain->nmi_smtp_username]['domain']);
        $this->assertStringNotContainsString($password, File::get($this->file));
        // The MTA runs as another user: readable by everyone, written only by NetSendo
        $this->assertSame('0644', substr(sprintf('%o', fileperms($this->file)), -4));
    }

    public function test_switching_nmi_off_or_removing_the_domain_takes_the_login_away(): void
    {
        $first = $this->domain();
        $second = $this->domain('second.example');
        foreach ([$first, $second] as $domain) {
            $this->actingAs($this->user)->postJson(route('settings.nmi.domains.enable', $domain))->assertOk();
        }
        $this->assertCount(2, $this->users());

        $this->actingAs($this->user)->postJson(route('settings.nmi.domains.disable', $first))->assertOk();
        $this->assertSame([$second->fresh()->nmi_smtp_username], array_keys($this->users()));

        $this->actingAs($this->user)->delete(route('deliverability.domains.destroy', $second))->assertRedirect();
        $this->assertSame([], $this->users());
    }

    public function test_nmi_cannot_be_switched_on_for_a_domain_while_the_installation_has_it_off(): void
    {
        config(['nmi.features.enabled' => false]);
        $domain = $this->domain();

        $this->actingAs($this->user)
            ->postJson(route('settings.nmi.domains.enable', $domain))
            ->assertStatus(422)
            ->assertJsonValidationErrors('domain');

        $this->assertFalse((bool) $domain->fresh()->nmi_enabled);
        $this->assertFileDoesNotExist($this->file);
    }

    public function test_the_sync_command_rebuilds_the_file_from_the_database(): void
    {
        $domain = $this->domain();
        $this->actingAs($this->user)->postJson(route('settings.nmi.domains.enable', $domain))->assertOk();
        File::delete($this->file);

        $this->artisan('nmi:sync-mta-auth')->assertSuccessful();

        $this->assertArrayHasKey($domain->fresh()->nmi_smtp_username, $this->users());
    }

    public function test_the_sync_command_does_nothing_while_nmi_is_off_for_the_installation(): void
    {
        $this->domain('example.com', ['nmi_enabled' => true, 'nmi_smtp_username' => 'nmi-1-abcdefgh', 'nmi_smtp_password' => 'secret']);
        config(['nmi.features.enabled' => false]);
        File::deleteDirectory($this->dir);

        $this->artisan('nmi:sync-mta-auth')->assertSuccessful();

        $this->assertFileDoesNotExist($this->file);
    }

    public function test_the_sync_command_fails_when_the_file_cannot_be_written(): void
    {
        File::ensureDirectoryExists($this->dir);
        File::put($this->dir . '/not-a-directory', 'x');
        config(['nmi.mta_auth_file' => $this->dir . '/not-a-directory/mta-users.json']);

        $this->artisan('nmi:sync-mta-auth')->assertFailed();
    }

    public function test_the_logins_are_synced_every_five_minutes_also_without_gold(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains($event->command, 'nmi:sync-mta-auth'));

        $this->assertNotNull($event);
        $this->assertSame('*/5 * * * *', $event->expression);

        // NMI set up under GOLD keeps sending after the licence ends
        \App\Models\Setting::where('key', 'license_plan')->update(['value' => 'SILVER']);
        $this->assertTrue($event->filtersPass($this->app));
    }
}
