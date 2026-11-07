<?php

namespace Tests\Feature;

use App\Models\ContactList;
use App\Models\Setting;
use App\Models\Subscriber;
use App\Models\User;
use App\Services\Telemetry\TelemetryReport;
use App\Services\Telemetry\TelemetryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Opt-in, anonymous usage reports (docs/TELEMETRY.md): off by default, only the
 * administrator can switch them on, the report holds only documented fields with counts as
 * ranges, and switching off asks the server to delete the installation's reports.
 */
class TelemetryTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://telemetry.test/api/telemetry';

    /** Keys the server (lib/telemetry.ts on netsendo.com) accepts, per section */
    private const SCHEMA = [
        'app' => ['version', 'plan', 'deployment', 'locale'],
        'platform' => ['php', 'database', 'database_version', 'queue'],
        'usage' => ['contacts', 'email_lists', 'sms_lists', 'users', 'campaigns_sent_30d', 'emails_sent_30d', 'sms_sent_30d', 'active_funnels', 'active_automations', 'webinars', 'forms', 'mailboxes'],
        'features' => ['sms', 'ai', 'brain', 'mcp', 'api_keys', 'webhooks', 'crm', 'webinars', 'ab_tests', 'woocommerce', 'shopify', 'wordpress', 'n8n', 'stripe', 'polar', 'calendly', 'google_calendar', 'zoom'],
        'providers' => ['mail', 'sms', 'ai'],
    ];

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['netsendo.telemetry.url' => self::URL, 'netsendo.telemetry.enabled' => null]);
        Http::fake([self::URL . '*' => Http::response(['ok' => true], 202)]);
        $this->admin = User::factory()->create();
    }

    private function telemetry(): TelemetryService
    {
        return app(TelemetryService::class);
    }

    public function test_it_is_off_by_default_and_sends_nothing(): void
    {
        $this->assertFalse($this->telemetry()->enabled());
        $this->assertNull($this->telemetry()->instanceId());

        $this->artisan('netsendo:telemetry', ['--now' => true])->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_the_report_holds_only_documented_fields_and_nothing_identifying(): void
    {
        config(['app.url' => 'https://acme-secret-domain.example']);
        $list = ContactList::factory()->create(['user_id' => $this->admin->id]);
        Subscriber::factory()->count(12)->create(['user_id' => $this->admin->id]);
        Subscriber::factory()->create(['user_id' => $this->admin->id, 'email' => 'jan.kowalski@example.org']);
        $this->telemetry()->enable();

        $report = app(TelemetryReport::class)->build($this->telemetry()->instanceId());
        $json = json_encode($report);

        $this->assertSame(1, $report['schema']);
        $this->assertTrue(Str::isUuid($report['instance_id']));
        $this->assertSame(config('netsendo.version'), $report['app']['version']);
        $this->assertContains($report['app']['plan'], ['silver', 'gold', 'none']);
        $this->assertContains($report['app']['deployment'], ['docker', 'cloud', 'other']);
        $this->assertSame('11-100', $report['usage']['contacts']);

        foreach ($report as $section => $values) {
            if (in_array($section, ['schema', 'instance_id'], true)) {
                continue;
            }
            $this->assertArrayHasKey($section, self::SCHEMA, "unknown section {$section}");
            foreach (array_keys($values) as $key) {
                $this->assertContains($key, self::SCHEMA[$section], "undocumented field {$section}.{$key}");
            }
        }
        foreach ($report['usage'] as $value) {
            $this->assertContains($value, TelemetryReport::BUCKETS);
        }
        foreach (['acme-secret-domain', 'jan.kowalski', 'example.org', $this->admin->email, $this->admin->name, $list->name] as $secret) {
            $this->assertStringNotContainsString($secret, $json);
        }
    }

    public function test_counts_are_sent_as_ranges(): void
    {
        $this->assertSame('0', TelemetryReport::bucket(0));
        $this->assertSame('1-10', TelemetryReport::bucket(10));
        $this->assertSame('11-100', TelemetryReport::bucket(11));
        $this->assertSame('101-1k', TelemetryReport::bucket(1000));
        $this->assertSame('1k-10k', TelemetryReport::bucket(1001));
        $this->assertSame('100k-1m', TelemetryReport::bucket(1_000_000));
        $this->assertSame('1m+', TelemetryReport::bucket(1_000_001));
    }

    public function test_when_switched_on_the_report_is_sent_and_the_time_recorded(): void
    {
        $this->actingAs($this->admin)->post(route('settings.telemetry.update'), ['enabled' => true])->assertRedirect();
        $id = $this->telemetry()->instanceId();
        $this->assertTrue(Str::isUuid($id));

        $this->artisan('netsendo:telemetry', ['--now' => true])->assertSuccessful();

        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && $r->url() === self::URL
            && $r['schema'] === 1 && $r['instance_id'] === $id && isset($r['app']['version']));
        $this->assertNotNull($this->telemetry()->lastSentAt());
    }

    public function test_the_scheduler_sends_once_a_day_in_the_installation_hour(): void
    {
        $this->telemetry()->enable();
        $id = $this->telemetry()->instanceId();
        $hour = $this->telemetry()->reportHour($id);
        $day = Carbon::create(2026, 11, 10, $hour, 17, 0, 'UTC');

        // Never sent: due at once, whatever the hour
        $this->assertTrue($this->telemetry()->due($day->copy()->addHours(3)));

        // Sent yesterday at this hour: due in its own hour, not an hour later
        Setting::updateOrCreate(['key' => TelemetryService::LAST_SENT_KEY], ['value' => $day->copy()->subDay()->toIso8601String()]);
        $this->assertTrue($this->telemetry()->due($day));
        $this->assertFalse($this->telemetry()->due($day->copy()->addHour()));

        // Sent today: not again today
        Setting::updateOrCreate(['key' => TelemetryService::LAST_SENT_KEY], ['value' => $day->toIso8601String()]);
        $this->assertFalse($this->telemetry()->due($day->copy()->addMinutes(30)));

        // Last report three days old (server was down): due at once
        Setting::updateOrCreate(['key' => TelemetryService::LAST_SENT_KEY], ['value' => $day->copy()->subDays(3)->toIso8601String()]);
        $this->assertTrue($this->telemetry()->due($day->copy()->addHours(5)));
    }

    public function test_switching_off_asks_the_server_to_delete_the_reports_and_forgets_the_id(): void
    {
        $this->telemetry()->enable();
        $id = $this->telemetry()->instanceId();

        $this->actingAs($this->admin)->post(route('settings.telemetry.update'), ['enabled' => false])->assertRedirect();

        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && $r->url() === self::URL . '/' . $id);
        $this->assertFalse($this->telemetry()->enabled());
        $this->assertNull($this->telemetry()->instanceId());

        $this->artisan('netsendo:telemetry', ['--now' => true])->assertSuccessful();
        Http::assertSentCount(1);
    }

    public function test_only_the_administrator_can_see_or_change_telemetry(): void
    {
        $member = User::factory()->create(['admin_user_id' => $this->admin->id]);

        $this->actingAs($member)->get(route('settings.telemetry.index'))->assertForbidden();
        $this->actingAs($member)->post(route('settings.telemetry.update'), ['enabled' => true])->assertForbidden();
        $this->assertFalse($this->telemetry()->enabled());

        $this->actingAs($this->admin)->get(route('settings.telemetry.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Settings/Telemetry/Index')
                ->where('telemetry.enabled', false)
                ->where('telemetry.locked', false)
                ->has('telemetry.preview.app.version'));
    }

    public function test_the_environment_variable_overrides_the_panel(): void
    {
        config(['netsendo.telemetry.enabled' => 'false']);
        Setting::updateOrCreate(['key' => TelemetryService::ENABLED_KEY], ['value' => '1']);

        $this->assertFalse($this->telemetry()->enabled());
        $this->actingAs($this->admin)->post(route('settings.telemetry.update'), ['enabled' => true])
            ->assertSessionHas('error');
        $this->artisan('netsendo:telemetry', ['--now' => true])->assertSuccessful();
        Http::assertNothingSent();

        config(['netsendo.telemetry.enabled' => 'true']);
        $this->assertTrue($this->telemetry()->enabled());
    }

    public function test_the_administrator_is_asked_once(): void
    {
        $this->assertTrue($this->telemetry()->shouldAsk());

        $this->actingAs($this->admin)->post(route('settings.telemetry.dismiss'))->assertRedirect();

        $this->assertFalse($this->telemetry()->shouldAsk());
        $this->assertFalse($this->telemetry()->enabled());
        Http::assertNothingSent();
    }
}
