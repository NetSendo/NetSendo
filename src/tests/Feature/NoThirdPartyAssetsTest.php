<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Webinar;
use App\Models\WebinarRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// The panel and the public webinar pages load their scripts, styles, fonts and images from the
// installation itself. A request to another host hands it the IP address of the admin or the
// subscriber who opened the page. Until 2.5.2 the panel hot-linked its logos and favicon from
// gregciupek.com and its fonts from fonts.bunny.net, and the webinar pages loaded the Tailwind
// Play CDN (cdn.tailwindcss.com).
class NoThirdPartyAssetsTest extends TestCase
{
    use RefreshDatabase;

    private const FORMER_HOSTS = [
        'gregciupek.com/wp-content',
        'cdn.tailwindcss.com',
        'fonts.bunny.net',
    ];

    public function test_the_sign_in_page_loads_assets_only_from_the_installation(): void
    {
        $this->assertOnlyLocalAssets($this->get('/login')->assertOk()->getContent());
    }

    public function test_the_panel_loads_assets_only_from_the_installation(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)->get('/profile')->assertOk()->getContent();

        $this->assertOnlyLocalAssets($html);
        $this->assertStringContainsString('images/brand/netsendo-icon.png', $html);
        $this->assertStringContainsString('images/brand/netsendo-og.png', $html);
    }

    public function test_the_public_webinar_pages_load_assets_only_from_the_installation(): void
    {
        $webinar = Webinar::create([
            'user_id' => User::factory()->create()->id,
            'name' => 'Lejki e-mail',
            'slug' => 'lejki',
            'type' => 'live',
            'status' => Webinar::STATUS_SCHEDULED,
            'scheduled_at' => now()->addDays(2),
            'timezone' => 'Europe/Warsaw',
        ]);
        $registration = WebinarRegistration::create([
            'webinar_id' => $webinar->id,
            'email' => 'widz@example.com',
            'first_name' => 'Anna',
            'access_token' => 'token-lejki',
            'status' => 'registered',
        ]);

        $pages = [
            'register' => $this->get('/webinar/lejki'),
            'registered' => $this->post('/webinar/lejki', ['email' => 'nowy@example.com']),
            'thank-you' => $this->get('/webinar/lejki/thank-you'),
            'watch' => $this->get('/webinar/lejki/watch/' . $registration->access_token),
            'replay' => $this->get('/webinar/lejki/replay/' . $registration->access_token),
        ];

        foreach ($pages as $page => $response) {
            $response->assertOk();
            $this->assertOnlyLocalAssets($response->getContent(), $page);
        }
    }

    public function test_the_former_third_party_hosts_are_gone_from_the_frontend_sources(): void
    {
        $files = array_merge(
            $this->files(resource_path('views'), 'php'),
            $this->files(resource_path('js'), 'vue'),
            $this->files(resource_path('js'), 'js'),
            $this->files(resource_path('css'), 'css'),
        );

        foreach ($files as $file) {
            $source = file_get_contents($file);
            foreach (self::FORMER_HOSTS as $host) {
                $this->assertStringNotContainsString($host, $source, "{$file} still references {$host}");
            }
        }
    }

    // Every @vite() entry a view asks for has to be a build input, or the page answers 500
    // ("Unable to locate file in Vite manifest") once the app is built.
    public function test_every_vite_entry_used_by_a_view_is_a_build_input(): void
    {
        $config = file_get_contents(base_path('vite.config.js'));

        foreach ($this->files(resource_path('views'), 'php') as $file) {
            preg_match_all('/@vite\((.*?)\)/s', file_get_contents($file), $calls);
            foreach ($calls[1] as $arguments) {
                if (str_contains($arguments, '$')) {
                    continue; // app.blade.php adds the current Inertia page component
                }
                preg_match_all("/['\"]([^'\"]+)['\"]/", $arguments, $entries);
                foreach ($entries[1] as $entry) {
                    $this->assertStringContainsString("'{$entry}'", $config, "{$file} uses @vite('{$entry}'), which vite.config.js does not build");
                }
            }
        }
    }

    private function assertOnlyLocalAssets(string $html, string $page = 'page'): void
    {
        $this->assertStringContainsString('<html', $html);

        foreach (self::FORMER_HOSTS as $host) {
            $this->assertStringNotContainsString($host, $html, "{$page} references {$host}");
        }

        // Everything the browser fetches by itself: scripts, styles, icons, preconnects, images,
        // media and frames, plus the share image other sites fetch.
        preg_match_all('/<(?:script|img|iframe|source|video|audio)\b[^>]*?\ssrc="([^"]*)"/i', $html, $src);
        preg_match_all('/<link\b[^>]*?\shref="([^"]*)"/i', $html, $href);
        preg_match_all('/<meta\b[^>]*?(?:og|twitter):image"[^>]*?content="([^"]*)"/i', $html, $meta);

        $appHost = parse_url(config('app.url'), PHP_URL_HOST);

        foreach (array_merge($src[1], $href[1], $meta[1]) as $url) {
            $host = parse_url(html_entity_decode($url), PHP_URL_HOST);
            $this->assertTrue($host === null || $host === $appHost, "{$page} loads {$url} from another host");
        }
    }

    /** @return list<string> */
    private function files(string $directory, string $extension): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === $extension) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
