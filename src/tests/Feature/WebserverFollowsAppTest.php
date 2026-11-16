<?php

namespace Tests\Feature;

use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * 2026-11-12, rollout of 2.11.0 on the hosted installations: 7 of 16 answered 502 after the
 * update. nginx resolves `fastcgi_pass app:9000` once, at start; the recreated app container
 * got a new address and the webserver kept sending to the old one until it was restarted by
 * hand. Compose now restarts the webserver whenever it recreates the app.
 */
class WebserverFollowsAppTest extends TestCase
{
    public function test_the_webserver_is_restarted_with_the_app(): void
    {
        foreach (['docker-compose.yml', 'docker-compose.dev.yml'] as $file) {
            $compose = Yaml::parseFile(base_path("../{$file}"));
            $dependency = $compose['services']['webserver']['depends_on']['app'] ?? null;

            $this->assertIsArray($dependency, "{$file}: webserver must depend on app in the long form");
            $this->assertTrue($dependency['restart'] ?? false, "{$file}: webserver must restart with app");
        }
    }

    /**
     * 2026-11-13: every webserver on the hosted installations was "unhealthy" while the
     * sites worked. In the nginx image localhost resolves to ::1 first and nginx listens
     * on IPv4 only (listen 80), so `wget http://localhost/` was refused. The opt-in `mcp`
     * service waits for a healthy webserver and so never started.
     */
    public function test_the_webserver_healthcheck_asks_the_address_nginx_listens_on(): void
    {
        $nginx = file_get_contents(base_path('../docker/nginx/default.conf'));

        foreach (['docker-compose.yml', 'docker-compose.dev.yml'] as $file) {
            $compose = Yaml::parseFile(base_path("../{$file}"));
            $test = $compose['services']['webserver']['healthcheck']['test'] ?? null;

            if ($test === null) {
                continue;
            }

            $command = implode(' ', (array) $test);

            if (!str_contains($nginx, '[::]:80')) {
                $this->assertStringNotContainsString('localhost', $command, "{$file}: localhost is ::1 first, nginx listens on IPv4 only");
                $this->assertStringContainsString('127.0.0.1', $command, "{$file}: the webserver healthcheck must ask 127.0.0.1");
            }
        }
    }

    public function test_nginx_still_reaches_the_app_by_its_service_name(): void
    {
        // A resolver for a variable upstream would differ between Docker (127.0.0.11) and
        // Podman (the network gateway): the plain name works on both
        $this->assertStringContainsString(
            'fastcgi_pass app:9000;',
            file_get_contents(base_path('../docker/nginx/default.conf'))
        );
    }
}
