<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\UpdateCheckService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Update check (client log 2026-11-11: "netsendo:check-updates failed with exit code [1]"
 * every hour). Client instances share a host and an IP; the sidebar asked GitHub on every
 * page view and the scheduler once an hour, so GitHub's 60 requests an hour per IP ran out.
 */
class UpdateCheckTest extends TestCase
{
    use RefreshDatabase;

    private const RELEASES_URL = 'https://api.github.com/repos/NetSendo/NetSendo/releases';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'netsendo.github_repo' => 'NetSendo/NetSendo',
            'netsendo.version' => '2.8.7',
        ]);
        Cache::flush();
    }

    private function releases(): array
    {
        return [
            ['tag_name' => 'v2.9.0-beta', 'name' => 'v2.9.0-beta', 'prerelease' => true, 'published_at' => '2026-11-12T10:00:00Z', 'body' => 'beta', 'html_url' => 'https://github.com/NetSendo/NetSendo/releases/tag/v2.9.0-beta'],
            ['tag_name' => 'v2.8.9', 'name' => 'v2.8.9 – Fix', 'prerelease' => false, 'published_at' => '2026-11-11T10:00:00Z', 'body' => str_repeat('a', 400), 'html_url' => 'https://github.com/NetSendo/NetSendo/releases/tag/v2.8.9'],
            ['tag_name' => 'v2.8.8', 'name' => 'v2.8.8', 'prerelease' => false, 'published_at' => '2026-11-10T10:00:00Z', 'body' => 'b', 'html_url' => 'https://github.com/NetSendo/NetSendo/releases/tag/v2.8.8'],
            ['tag_name' => 'v2.8.7', 'name' => 'v2.8.7', 'prerelease' => false, 'published_at' => '2026-11-09T10:00:00Z', 'body' => 'c', 'html_url' => 'https://github.com/NetSendo/NetSendo/releases/tag/v2.8.7'],
        ];
    }

    private function rateLimited(): void
    {
        Http::fake([self::RELEASES_URL => Http::response(
            ['message' => 'API rate limit exceeded'],
            403,
            ['X-RateLimit-Remaining' => '0', 'X-RateLimit-Reset' => (string) (now()->getTimestamp() + 1200)]
        )]);
    }

    public function test_page_views_share_one_github_request(): void
    {
        Http::fake([self::RELEASES_URL => Http::response($this->releases())]);
        $user = User::factory()->create();

        foreach (range(1, 3) as $view) {
            $this->actingAs($user)->getJson('/api/version/check')
                ->assertOk()
                ->assertJson([
                    'current_version' => '2.8.7',
                    'latest_version' => '2.8.9',
                    'updates_available' => true,
                    'update_count' => 2,
                ])
                ->assertJsonPath('new_versions.0.body', str_repeat('a', 300) . '...');
        }
        $this->actingAs($user)->getJson('/api/version/changelog')
            ->assertOk()
            ->assertJsonCount(4, 'releases')
            ->assertJsonPath('releases.0.prerelease', true);

        Http::assertSentCount(1);
    }

    public function test_after_an_update_the_status_is_right_without_asking_again(): void
    {
        Http::fake([self::RELEASES_URL => Http::response($this->releases())]);
        $service = app(UpdateCheckService::class);

        $this->assertTrue($service->status()['updates_available']);

        config(['netsendo.version' => '2.8.9']);
        $status = $service->status();

        $this->assertFalse($status['updates_available']);
        $this->assertSame('2.8.9', $status['latest_version']);
        Http::assertSentCount(1);
    }

    public function test_refresh_asks_github_again(): void
    {
        Http::fake([self::RELEASES_URL => Http::response($this->releases())]);
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/version/check')->assertOk();
        $this->actingAs($user)->getJson('/api/version/refresh')->assertOk()->assertJson(['updates_available' => true]);

        Http::assertSentCount(2);
    }

    public function test_the_scheduled_command_does_not_fail_on_githubs_rate_limit(): void
    {
        $this->rateLimited();

        $this->artisan('netsendo:check-updates')
            ->expectsOutputToContain('rate limit')
            ->assertExitCode(0);

        // The pause is remembered: neither the next run nor the sidebar asks GitHub again
        $this->artisan('netsendo:check-updates')->assertExitCode(0);
        $this->actingAs(User::factory()->create())->getJson('/api/version/check')
            ->assertOk()
            ->assertJson(['current_version' => '2.8.7', 'updates_available' => false])
            ->assertJsonStructure(['error']);

        Http::assertSentCount(1);
    }

    public function test_the_scheduled_command_does_not_fail_without_a_connection(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('Could not resolve host'));

        $this->artisan('netsendo:check-updates')
            ->expectsOutputToContain('Could not check for updates')
            ->assertExitCode(0);
    }

    public function test_a_cached_list_is_used_while_a_failure_pause_runs(): void
    {
        Http::fake([self::RELEASES_URL => Http::sequence()
            ->push($this->releases())
            ->push(['message' => 'API rate limit exceeded'], 403, ['X-RateLimit-Remaining' => '0'])]);
        $service = app(UpdateCheckService::class);

        $this->assertTrue($service->status()['updates_available']);
        $this->assertArrayHasKey('error', $service->status(fresh: true));
        $this->assertTrue($service->lastRateLimited());

        // The earlier list still answers the sidebar
        $this->assertTrue($service->status()['updates_available']);
        Http::assertSentCount(2);
    }

    public function test_the_command_reports_available_updates(): void
    {
        Http::fake([self::RELEASES_URL => Http::response($this->releases())]);

        $this->artisan('netsendo:check-updates', ['--force' => true])
            ->expectsOutputToContain('2 update(s) available')
            ->assertExitCode(0);
    }
}
