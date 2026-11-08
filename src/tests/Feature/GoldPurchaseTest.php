<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\GoldPurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * GOLD bought from the licence page: the installation opens the NetSendo store with only the
 * SHA-256 of a secret it keeps, then fetches its key with the secret once the payment is
 * confirmed. Also covers the licence lifecycle around it: renewals keep the local expiry in
 * step, an ended GOLD falls back to SILVER, and the public activation webhook only stores
 * keys the licence server confirms.
 */
class GoldPurchaseTest extends TestCase
{
    use RefreshDatabase;

    private const STORE = 'https://store.test';
    private const GOLD_KEY = 'Z29sZA==-1234567890-1234567890123-a1b2c3d4e5a1b2c3d4e5a1b2c3d4e5a1b2c3d4e5';
    private const SILVER_KEY = 'c2lsdmVy-1234567890-1234567890123-f1e2d3c4b5f1e2d3c4b5f1e2d3c4b5f1e2d3c4b5';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        config(['netsendo.store_url' => self::STORE]);
        $this->user = User::factory()->create();
    }

    private function setting(string $key): ?string
    {
        return Setting::where('key', $key)->value('value');
    }

    /** Fakes the store's claim endpoint and the licence server (n8n), by request action. */
    private function fakeServers(array $claim, int $claimStatus = 200, ?array $licence = null, ?array $silverRequest = null): void
    {
        Http::fake(function (Request $request) use ($claim, $claimStatus, $licence, $silverRequest) {
            if (str_starts_with($request->url(), self::STORE . '/api/billing/claim')) {
                return Http::response($claim, $claimStatus);
            }
            if (($request['action'] ?? null) === 'request_license') {
                return Http::response($silverRequest ?? [], 200);
            }
            // verify_license / check_status on the licence server
            return Http::response($licence ?? ['valid' => false, 'message' => 'not found'], 200);
        });
    }

    private function startPurchase(): array
    {
        $response = $this->actingAs($this->user)->postJson(route('license.buy-gold'))->assertOk();
        $url = $response->json('url');
        parse_str(parse_url($url, PHP_URL_QUERY), $query);

        return [$url, $query];
    }

    public function test_buy_gold_opens_the_store_with_only_a_hash_of_the_kept_secret(): void
    {
        [$url, $query] = $this->startPurchase();

        $this->assertMatchesRegularExpression('#^https://store\.test/(pl|en|de|es)/checkout\?#', $url);
        $this->assertSame('gold', $query['plan']);
        $this->assertSame('app', $query['source']);
        $this->assertSame('localhost', $query['domain']);
        $this->assertSame($this->user->email, $query['email']);
        $this->assertSame(route('license.index', ['purchase' => 'return']), $query['return']);

        $stored = $this->setting(GoldPurchaseService::SECRET_KEY);
        $secret = Crypt::decryptString($stored);
        $this->assertNotSame($secret, $stored, 'the secret is stored encrypted');
        $this->assertSame(hash('sha256', $secret), $query['claim']);
        $this->assertStringNotContainsString($secret, $url, 'the secret never goes into the URL');
        $this->assertTrue(app(GoldPurchaseService::class)->hasPendingPurchase());
    }

    public function test_a_paid_purchase_activates_gold_after_the_licence_server_confirms_the_key(): void
    {
        $this->startPurchase();
        $secret = Crypt::decryptString($this->setting(GoldPurchaseService::SECRET_KEY));
        $this->fakeServers(
            ['status' => 'paid', 'license_key' => self::GOLD_KEY, 'plan' => 'GOLD', 'expires_at' => '2026-12-14T10:00:00.000Z'],
            licence: [['package' => 'GOLD', 'license_key' => self::GOLD_KEY, 'is_active' => true, 'expires_at' => '2026-12-14T10:00:00.000Z']],
        );

        $this->actingAs($this->user)->postJson(route('license.claim-gold'))
            ->assertOk()
            ->assertJson(['status' => 'activated', 'plan' => 'GOLD']);

        $this->assertSame(self::GOLD_KEY, $this->setting('license_key'));
        $this->assertSame('GOLD', $this->setting('license_plan'));
        $this->assertSame('2026-12-14T10:00:00.000Z', $this->setting('license_expires_at'));
        $this->assertNull($this->setting(GoldPurchaseService::SECRET_KEY), 'the purchase is finished');
        Http::assertSent(fn (Request $request) => $request->url() === self::STORE . '/api/billing/claim' && $request['secret'] === $secret);
    }

    public function test_an_unpaid_or_not_yet_placed_order_keeps_waiting(): void
    {
        $this->startPurchase();

        $this->fakeServers(['error' => 'not_found'], 404);
        $this->actingAs($this->user)->postJson(route('license.claim-gold'))->assertJson(['status' => 'pending']);

        $this->fakeServers(['status' => 'pending']);
        $this->actingAs($this->user)->postJson(route('license.claim-gold'))->assertJson(['status' => 'pending']);

        $this->assertNotNull($this->setting(GoldPurchaseService::SECRET_KEY));
        $this->assertNull($this->setting('license_key'));
    }

    public function test_an_ended_subscription_closes_the_purchase(): void
    {
        $this->startPurchase();
        $this->fakeServers(['status' => 'inactive']);

        $this->actingAs($this->user)->postJson(route('license.claim-gold'))->assertJson(['status' => 'inactive']);

        $this->assertNull($this->setting(GoldPurchaseService::SECRET_KEY));
        $this->assertNull($this->setting('license_key'));
    }

    public function test_a_key_the_licence_server_does_not_confirm_is_not_stored(): void
    {
        $this->startPurchase();
        $this->fakeServers(
            ['status' => 'paid', 'license_key' => self::GOLD_KEY, 'plan' => 'GOLD'],
            licence: ['valid' => false, 'error' => 'LICENSE_NOT_FOUND', 'message' => 'not found'],
        );

        $this->actingAs($this->user)->postJson(route('license.claim-gold'))->assertJson(['status' => 'error']);

        $this->assertNull($this->setting('license_key'));
        $this->assertNotNull($this->setting(GoldPurchaseService::SECRET_KEY), 'can be retried');
    }

    public function test_without_a_started_purchase_there_is_nothing_to_claim(): void
    {
        Http::fake();
        $this->actingAs($this->user)->postJson(route('license.claim-gold'))->assertJson(['status' => 'none']);
        $this->artisan('license:claim-gold')->assertSuccessful();
        Http::assertNothingSent();
    }

    public function test_the_scheduled_command_activates_a_paid_purchase(): void
    {
        $this->startPurchase();
        $this->fakeServers(
            ['status' => 'paid', 'license_key' => self::GOLD_KEY, 'plan' => 'GOLD', 'expires_at' => '2026-12-14T10:00:00.000Z'],
            licence: [['package' => 'GOLD', 'license_key' => self::GOLD_KEY, 'is_active' => true, 'expires_at' => '2026-12-14T10:00:00.000Z']],
        );

        $this->artisan('license:claim-gold')->expectsOutputToContain('activated')->assertSuccessful();
        $this->assertSame('GOLD', $this->setting('license_plan'));
    }

    public function test_the_public_activation_webhook_rejects_unconfirmed_keys(): void
    {
        $this->fakeServers([], licence: ['valid' => false, 'error' => 'LICENSE_NOT_FOUND', 'message' => 'not found']);

        $this->postJson('/api/license/webhook', ['licenseKey' => self::GOLD_KEY, 'package' => 'GOLD'])->assertStatus(422);

        $this->assertNull($this->setting('license_key'));
        $this->assertNull($this->setting('license_plan'));
    }

    public function test_the_public_activation_webhook_takes_the_plan_from_the_licence_server(): void
    {
        $this->fakeServers([], licence: [['package' => 'SILVER', 'license_key' => self::SILVER_KEY, 'is_active' => true]]);

        // the request claims GOLD, the licence server says SILVER
        $this->postJson('/api/license/webhook', ['licenseKey' => self::SILVER_KEY, 'package' => 'GOLD'])->assertOk();

        $this->assertSame(self::SILVER_KEY, $this->setting('license_key'));
        $this->assertSame('SILVER', $this->setting('license_plan'));
    }

    public function test_the_daily_check_keeps_a_renewed_gold_expiry_in_step(): void
    {
        Setting::create(['key' => 'license_key', 'value' => self::GOLD_KEY]);
        Setting::create(['key' => 'license_plan', 'value' => 'GOLD']);
        Setting::create(['key' => 'license_expires_at', 'value' => '2026-12-14T10:00:00.000Z']);
        $this->fakeServers([], licence: [['package' => 'GOLD', 'license_key' => self::GOLD_KEY, 'is_active' => true, 'expires_at' => '2027-01-14T10:00:00.000Z']]);

        $this->artisan('license:verify --deactivate')->assertSuccessful();

        $this->assertSame('2027-01-14T10:00:00.000Z', $this->setting('license_expires_at'));
        $this->assertSame(self::GOLD_KEY, $this->setting('license_key'));
    }

    public function test_the_daily_check_drops_the_expiry_when_a_granted_gold_gets_no_end_date(): void
    {
        // a contributor's 12-month GOLD becomes GOLD without an end date on the same key
        Setting::create(['key' => 'license_key', 'value' => self::GOLD_KEY]);
        Setting::create(['key' => 'license_plan', 'value' => 'GOLD']);
        Setting::create(['key' => 'license_expires_at', 'value' => '2026-12-14T10:00:00.000Z']);
        $this->fakeServers([], licence: [['package' => 'GOLD', 'license_key' => self::GOLD_KEY, 'is_active' => true, 'expires_at' => null]]);

        $this->artisan('license:verify --deactivate')->assertSuccessful();

        $this->assertNull($this->setting('license_expires_at'));
        $this->assertSame(self::GOLD_KEY, $this->setting('license_key'));
        $this->travelTo('2027-06-01 12:00:00');
        $this->assertSame('GOLD', app(\App\Services\LicenseService::class)->getCurrentPlan());
    }

    public function test_the_daily_check_keeps_the_expiry_when_the_answer_has_no_expiry_field(): void
    {
        Setting::create(['key' => 'license_key', 'value' => self::GOLD_KEY]);
        Setting::create(['key' => 'license_plan', 'value' => 'GOLD']);
        Setting::create(['key' => 'license_expires_at', 'value' => '2026-12-14T10:00:00.000Z']);
        $this->fakeServers([], licence: [['package' => 'GOLD', 'license_key' => self::GOLD_KEY, 'is_active' => true]]);

        $this->artisan('license:verify --deactivate')->assertSuccessful();

        $this->assertSame('2026-12-14T10:00:00.000Z', $this->setting('license_expires_at'));
    }

    public function test_an_ended_gold_falls_back_to_silver(): void
    {
        Setting::create(['key' => 'license_key', 'value' => self::GOLD_KEY]);
        Setting::create(['key' => 'license_plan', 'value' => 'GOLD']);
        Setting::create(['key' => 'license_email', 'value' => 'owner@example.com']);
        Setting::create(['key' => 'license_expires_at', 'value' => '2026-12-14T10:00:00.000Z']);
        $this->fakeServers(
            [],
            licence: [['package' => 'GOLD', 'license_key' => self::GOLD_KEY, 'is_active' => false]],
            silverRequest: ['license_key' => self::SILVER_KEY],
        );

        $this->artisan('license:verify --deactivate')->assertSuccessful();

        $this->assertSame(self::SILVER_KEY, $this->setting('license_key'));
        $this->assertSame('SILVER', $this->setting('license_plan'));
        $this->assertNull($this->setting('license_expires_at'));
    }

    public function test_an_ended_licence_without_a_silver_key_is_removed_and_works_as_silver(): void
    {
        Setting::create(['key' => 'license_key', 'value' => self::GOLD_KEY]);
        Setting::create(['key' => 'license_plan', 'value' => 'GOLD']);
        // the licence server gives no SILVER key
        $this->fakeServers([], licence: [['package' => 'GOLD', 'license_key' => self::GOLD_KEY, 'is_active' => false]]);

        $this->artisan('license:verify --deactivate')->assertSuccessful();

        $this->assertNull($this->setting('license_key'));
        $this->assertSame('SILVER', app(\App\Services\LicenseService::class)->getCurrentPlan());
    }

    private function storeLicence(string $plan, ?string $key, ?string $expiresAt): void
    {
        Setting::create(['key' => 'license_plan', 'value' => $plan]);
        if ($key) {
            Setting::create(['key' => 'license_key', 'value' => $key]);
        }
        if ($expiresAt) {
            Setting::create(['key' => 'license_expires_at', 'value' => $expiresAt]);
        }
    }

    /** The plan every page gets (sidebar, dashboard, feature gates in the UI). */
    private function assertSharedPlan(string $expected): void
    {
        $this->actingAs($this->user)->get(route('license.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('license.plan', $expected));
    }

    public function test_an_active_gold_licence_unlocks_gold(): void
    {
        $this->storeLicence('GOLD', self::GOLD_KEY, now()->addDays(20)->toIso8601String());

        $this->assertSame('GOLD', app(\App\Services\LicenseService::class)->getCurrentPlan());
        $this->assertNull(app(\App\Services\LicenseService::class)->getCampaignLimit());
        $this->assertSharedPlan('GOLD');
    }

    public function test_an_expired_gold_licence_works_as_silver(): void
    {
        $this->storeLicence('GOLD', self::GOLD_KEY, now()->subDay()->toIso8601String());
        $licence = app(\App\Services\LicenseService::class);

        $this->assertSame('SILVER', $licence->getCurrentPlan());
        $this->assertTrue($licence->isGoldExpired());
        $this->assertSame(\App\Services\LicenseService::SILVER_CAMPAIGN_LIMIT, $licence->getCampaignLimit());
        $this->assertSharedPlan('SILVER');
    }

    public function test_gold_without_a_key_or_without_a_licence_works_as_silver(): void
    {
        $licence = app(\App\Services\LicenseService::class);
        $this->assertSame('SILVER', $licence->getCurrentPlan(), 'no licence at all');

        $this->storeLicence('GOLD', null, null);
        $this->assertSame('SILVER', $licence->getCurrentPlan(), 'plan GOLD but no key');
        $this->assertSharedPlan('SILVER');
    }

    public function test_an_ended_gold_without_a_licence_email_asks_silver_for_the_account_owner(): void
    {
        $this->storeLicence('GOLD', self::GOLD_KEY, now()->addDay()->toIso8601String());
        $this->fakeServers(
            [],
            licence: [['package' => 'GOLD', 'license_key' => self::GOLD_KEY, 'is_active' => false]],
            silverRequest: ['license_key' => self::SILVER_KEY],
        );

        $this->artisan('license:verify --deactivate')->assertSuccessful();

        $this->assertSame('SILVER', $this->setting('license_plan'));
        Http::assertSent(fn (Request $request) => ($request['action'] ?? null) === 'request_license' && $request['email'] === $this->user->email);
    }

    public function test_the_licence_page_offers_the_store_purchase(): void
    {
        $this->actingAs($this->user)->get(route('license.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('License/Activate')
                ->where('goldPurchaseAvailable', true)
                ->where('goldPurchasePending', false));
    }
}
