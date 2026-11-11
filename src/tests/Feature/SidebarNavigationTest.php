<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The side menu is data (resources/js/Components/Sidebar/navigation.js). A regrouping must not
 * drop a page or point at a route that does not exist: a typo there breaks the menu on every page.
 */
class SidebarNavigationTest extends TestCase
{
    /** Every page the menu had before it was regrouped into nine sections, listed once */
    private const PAGES = [
        'dashboard', 'webinars.index',
        'messages.index', 'sms.index', 'settings.system-emails.index', 'settings.system-pages.index',
        'subscribers.index', 'subscribers.import', 'mailing-lists.index', 'sms-lists.index',
        'groups.index', 'tags.index', 'forms.index', 'settings.fields.index', 'segmentation.index',
        'crm.dashboard', 'crm.contacts.index', 'crm.companies.index', 'crm.deals.index', 'crm.tasks.index',
        'crm.sequences.index', 'crm.scoring.index', 'crm.cardintel.index', 'crm.import.index', 'crm.guide',
        'brain.index', 'brain.monitor', 'brain.settings', 'brain.guide',
        'templates.index', 'inserts.index', 'external-pages.index', 'funnels.index', 'automations.index',
        'campaign-architect.index', 'campaign-auditor.index', 'campaign-stats.index',
        'media.index', 'brands.index',
        'settings.stripe-products.index', 'settings.polar-products.index', 'settings.tpay-products.index',
        'affiliate.index',
        'settings.tracked-links.index', 'settings.stats.index', 'settings.activity-logs.index',
        'settings.names.index', 'settings.users.index', 'profile.edit', 'license.index',
        'settings.ai-integrations.index', 'settings.stripe.index', 'settings.polar.index', 'settings.tpay.index',
        'settings.pixel.index', 'settings.woocommerce.index', 'settings.integrations.index',
        'settings.calendar.index', 'settings.mailboxes.index', 'deliverability.index', 'settings.nmi.dashboard',
        'settings.sms-providers.index', 'settings.cron.index', 'settings.api-keys.index',
        'settings.backup.index', 'settings.telemetry.index',
    ];

    /** Pages the menu links by path */
    private const PATHS = ['/defaults', '/update', '/marketplace'];

    private function source(): string
    {
        return File::get(resource_path('js/Components/Sidebar/navigation.js'));
    }

    private function linkedRoutes(): array
    {
        preg_match_all('/href: route\("([^"]+)"\)/', $this->source(), $matches);

        return $matches[1];
    }

    public function test_every_route_the_menu_links_to_exists(): void
    {
        $routes = $this->linkedRoutes();

        $this->assertNotEmpty($routes);
        foreach ($routes as $name) {
            $this->assertTrue(Route::has($name), "The side menu links to a route that does not exist: {$name}");
        }
    }

    public function test_no_page_of_the_old_menu_is_missing(): void
    {
        $this->assertSame([], array_values(array_diff(self::PAGES, $this->linkedRoutes())));

        foreach (self::PATHS as $path) {
            $this->assertStringContainsString("href: \"{$path}\"", $this->source());
        }
    }

    public function test_a_page_is_listed_once(): void
    {
        $routes = $this->linkedRoutes();

        $this->assertSame([], array_keys(array_filter(array_count_values($routes), fn (int $times) => $times > 1)));
    }
}
