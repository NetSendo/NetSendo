<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\LiveVisitorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Settings from .env must reach the app through config(). The Docker entrypoint runs
 * `php artisan config:cache`, and with a cached configuration env() returns null outside the
 * config files: the Live Visitors page then sent every browser to ws://localhost:8085 whatever
 * VITE_REVERB_* said, and MCP_API_KEY was never found.
 */
class RuntimeConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_live_visitors_page_gives_the_browser_the_configured_reverb_address(): void
    {
        $this->withoutVite();
        // The visitor list lives in Redis, which the test environment does not have
        $this->mock(LiveVisitorService::class, fn ($mock) => $mock->shouldReceive('getActiveVisitors')->andReturn([]));
        config(['broadcasting.browser' => [
            'key' => 'public-key',
            'host' => 'ws.example.com',
            'port' => 443,
            'scheme' => 'https',
        ]]);

        $this->actingAs(User::factory()->create())
            ->get(route('settings.pixel.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Settings/PixelSettings/Index')
                ->where('reverbConfig.key', 'public-key')
                ->where('reverbConfig.host', 'ws.example.com')
                ->where('reverbConfig.port', 443)
                ->where('reverbConfig.scheme', 'https'));
    }

    public function test_browser_reverb_settings_are_read_in_the_config_file(): void
    {
        $config = require config_path('broadcasting.php');

        $this->assertSame(['key', 'host', 'port', 'scheme'], array_keys($config['browser']));
        $this->assertIsInt($config['browser']['port']);
    }

    public function test_mcp_api_key_is_read_in_the_config_file(): void
    {
        $config = require config_path('services.php');

        $this->assertArrayHasKey('api_key', $config['mcp']);
    }

    public function test_no_env_calls_outside_the_config_files(): void
    {
        $files = Finder::create()->files()->name('*.php')
            ->in([app_path(), base_path('routes'), resource_path('views')]);

        $offenders = [];
        foreach ($files as $file) {
            foreach (preg_split('/\R/', $file->getContents()) as $number => $line) {
                if (preg_match('/(?<![\w>:$\\\\])env\(/', $line)) {
                    $offenders[] = $file->getRelativePathname().':'.($number + 1).'  '.trim($line);
                }
            }
        }

        $this->assertSame([], $offenders, "env() returns null once the configuration is cached; add a config key and read it with config():\n".implode("\n", $offenders));
    }
}
