<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * netsendo:bootstrap sets up a hosted installation without the first-run screen: the
 * administrator account (so the first visitor cannot claim it), its password by email
 * through the password relay, and the licence.
 */
class BootstrapCommandTest extends TestCase
{
    use RefreshDatabase;

    private const RELAY = 'https://a.gregciupek.com/webhook/6b7bebf0-b76f-4717-9d9f-fe8c7831d473';
    private const GOLD_KEY = 'Z29sZA==-1234567890-1234567890123-a1b2c3d4e5a1b2c3d4e5a1b2c3d4e5a1b2c3d4e5';

    /** Fakes the password relay and the licence server (n8n). */
    private function fakeServers(?array $licence = null, bool $relayOk = true): void
    {
        Http::fake(function (Request $request) use ($licence, $relayOk) {
            if ($request->url() === self::RELAY) {
                return $relayOk ? Http::response(['newPassword' => 'Mailed-Password-123']) : Http::response([], 500);
            }

            return Http::response($licence ?? ['valid' => false, 'message' => 'not found'], 200);
        });
    }

    private function setting(string $key): ?string
    {
        return Setting::where('key', $key)->value('value');
    }

    public function test_a_new_installation_gets_its_administrator_and_a_password_by_email(): void
    {
        $this->fakeServers();

        $this->artisan('netsendo:bootstrap', ['--admin-email' => 'Owner@Example.com', '--locale' => 'pl'])
            ->expectsOutputToContain('Password emailed to owner@example.com')
            ->assertSuccessful();

        $admin = User::where('email', 'owner@example.com')->firstOrFail();
        $this->assertSame('owner', $admin->name);
        $this->assertSame('pl', $admin->locale);
        $this->assertNotNull($admin->email_verified_at);
        $this->assertTrue(Hash::check('Mailed-Password-123', $admin->password));
        // A welcome email in the order's language with this instance's sign-in page, not a reset
        Http::assertSent(fn (Request $r) => $r->url() === self::RELAY && $r['email'] === 'owner@example.com'
            && $r['reason'] === 'welcome' && $r['locale'] === 'pl'
            && $r['login_url'] === rtrim((string) config('app.url'), '/') . '/login');

        // The first-run registration is closed: nobody else can become the administrator
        $this->get('/register')->assertRedirect(route('login'));
    }

    public function test_running_it_again_changes_nothing(): void
    {
        $this->fakeServers();

        $this->artisan('netsendo:bootstrap', ['--admin-email' => 'owner@example.com'])->assertSuccessful();
        $this->artisan('netsendo:bootstrap', ['--admin-email' => 'owner@example.com'])
            ->expectsOutputToContain('already exists')
            ->assertSuccessful();

        $this->assertSame(1, User::count());
        Http::assertSentCount(1);
    }

    public function test_an_installation_with_users_is_left_alone(): void
    {
        $this->fakeServers();
        User::factory()->create(['email' => 'first@example.com']);

        $this->artisan('netsendo:bootstrap', ['--admin-email' => 'owner@example.com'])->assertFailed();

        $this->assertSame(1, User::count());
        Http::assertNothingSent();
    }

    public function test_the_licence_is_activated_for_the_instance_domain(): void
    {
        config(['app.url' => 'https://acme.netsendo.app']);
        $this->fakeServers(licence: [['package' => 'GOLD', 'license_key' => self::GOLD_KEY, 'is_active' => true, 'expires_at' => '2027-12-14T10:00:00.000Z']]);

        $this->artisan('netsendo:bootstrap', ['--admin-email' => 'owner@example.com', '--license' => self::GOLD_KEY])
            ->expectsOutputToContain('Licence GOLD active for acme.netsendo.app')
            ->assertSuccessful();

        $this->assertSame(self::GOLD_KEY, $this->setting('license_key'));
        $this->assertSame('GOLD', $this->setting('license_plan'));
        Http::assertSent(fn (Request $r) => ($r['action'] ?? null) === 'verify_license' && $r['domain'] === 'acme.netsendo.app');
    }

    public function test_a_key_the_licence_server_does_not_confirm_is_not_stored(): void
    {
        $this->fakeServers();

        $this->artisan('netsendo:bootstrap', ['--admin-email' => 'owner@example.com', '--license' => self::GOLD_KEY])
            ->expectsOutputToContain('Licence not activated')
            ->assertFailed();

        $this->assertNull($this->setting('license_key'));
        $this->assertSame(1, User::count(), 'The administrator is still set up');
    }

    public function test_a_failed_password_email_is_reported(): void
    {
        $this->fakeServers(relayOk: false);

        $this->artisan('netsendo:bootstrap', ['--admin-email' => 'owner@example.com'])
            ->expectsOutputToContain('Forgot password')
            ->assertFailed();

        $this->assertSame(1, User::count());
    }

    public function test_bad_options_are_refused(): void
    {
        $this->artisan('netsendo:bootstrap', ['--admin-email' => 'not-an-email'])->assertExitCode(2);
        $this->artisan('netsendo:bootstrap', ['--admin-email' => 'owner@example.com', '--locale' => 'fr'])->assertExitCode(2);

        $this->assertSame(0, User::count());
    }
}
