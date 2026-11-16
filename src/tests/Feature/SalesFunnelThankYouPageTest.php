<?php

namespace Tests\Feature;

use App\Models\SalesFunnel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * The default page after a sales funnel purchase showed raw translation keys
 * (issue #66): lang/{locale}/sales_funnels.php did not exist in any language.
 * The page could not be reached either: the checkout route took its URL.
 */
class SalesFunnelThankYouPageTest extends TestCase
{
    use RefreshDatabase;

    private const LOCALES = ['en', 'pl', 'de', 'es'];

    private function funnel(): SalesFunnel
    {
        return SalesFunnel::create([
            'user_id' => User::factory()->create()->id,
            'name' => 'Spring course',
        ]);
    }

    public function test_the_default_thank_you_page_is_translated(): void
    {
        $funnel = $this->funnel();

        $this->get(route('sales-funnel.success', $funnel), ['Accept-Language' => 'en-US,en;q=0.9'])
            ->assertOk()
            ->assertSee('Thank You for Your Purchase!')
            ->assertSee('Spring course')
            ->assertDontSee('sales_funnels.');

        $this->get(route('sales-funnel.success', $funnel), ['Accept-Language' => 'pl-PL,pl;q=0.9'])
            ->assertOk()
            ->assertSee('Dziękujemy za zakup!')
            ->assertDontSee('sales_funnels.');
    }

    public function test_the_success_url_is_not_taken_by_the_checkout_route(): void
    {
        // /checkout/{type}/{product} used to match /checkout/success/{funnel} first and answer 404
        $routes = app('router')->getRoutes();

        $this->assertSame('sales-funnel.success', $routes->match(Request::create('/checkout/success/5'))->getName());
        foreach (['stripe', 'polar', 'tpay'] as $type) {
            $this->assertSame('sales-funnel.checkout', $routes->match(Request::create("/checkout/{$type}/5"))->getName());
        }
    }

    public function test_a_custom_thank_you_url_still_redirects(): void
    {
        $funnel = $this->funnel();
        $funnel->update(['thank_you_url' => 'https://example.com/thanks']);

        $this->get(route('sales-funnel.success', $funnel))->assertRedirect('https://example.com/thanks');
    }

    public function test_the_funnel_save_messages_are_translated(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $response = $this->actingAs($user)->postJson(route('settings.sales-funnels.store'), ['name' => 'Webinar'])
            ->assertCreated()
            ->assertJsonPath('message', 'Funnel created.');
        $funnel = SalesFunnel::findOrFail($response->json('funnel.id'));

        $this->actingAs($user)->putJson(route('settings.sales-funnels.update', $funnel), ['name' => 'Webinar 2'])
            ->assertJsonPath('message', 'Funnel updated.');

        $this->actingAs($user)->deleteJson(route('settings.sales-funnels.destroy', $funnel))
            ->assertJsonPath('message', 'Funnel deleted.');
    }

    public function test_every_sales_funnel_key_exists_in_all_four_languages(): void
    {
        $keys = ['thank_you_title', 'thank_you_heading', 'thank_you_message', 'created', 'updated', 'deleted'];

        foreach (self::LOCALES as $locale) {
            foreach ($keys as $key) {
                $this->assertNotSame("sales_funnels.{$key}", trans("sales_funnels.{$key}", [], $locale), "{$locale}: sales_funnels.{$key} is missing");
            }
        }
    }
}
