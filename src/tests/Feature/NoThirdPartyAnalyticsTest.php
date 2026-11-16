<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// The panel layout (app.blade.php) loads no third-party analytics. Whoever runs an installation
// is the controller of its panel data, so NetSendo itself must not collect page views from it.
// Until 2.5.1 every installation sent its panel page views to NetSendo's Google Analytics.
class NoThirdPartyAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private const TRACKERS = [
        'googletagmanager.com',
        'google-analytics.com',
        'G-96J7WQ3SMT',
    ];

    public function test_the_sign_in_page_loads_no_third_party_analytics(): void
    {
        $this->assertNoTrackers($this->get('/login')->assertOk()->getContent());
    }

    public function test_the_panel_loads_no_third_party_analytics(): void
    {
        $user = User::factory()->create();

        $this->assertNoTrackers($this->actingAs($user)->get('/profile')->assertOk()->getContent());
    }

    private function assertNoTrackers(string $html): void
    {
        $this->assertStringContainsString('<html', $html);

        foreach (self::TRACKERS as $tracker) {
            $this->assertStringNotContainsString($tracker, $html);
        }
    }
}
