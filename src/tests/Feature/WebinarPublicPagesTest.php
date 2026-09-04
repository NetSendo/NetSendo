<?php

namespace Tests\Feature;

use App\Models\ContactList;
use App\Models\User;
use App\Models\Webinar;
use App\Models\WebinarRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Public webinar funnel pages (registration form, post-registration screen and
 * the standalone thank-you page).
 *
 * Regression guard: Blade compiles `@php ... @endphp` blocks with the regex
 * `/(?<!@)@php(.*?)@endphp/s`, so an inline `@php(...)` placed before a block
 * in the same file is swallowed into that block and the rest of the template
 * is emitted as raw text — every registration then ended in a 500.
 */
class WebinarPublicPagesTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected ContactList $list;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->list = ContactList::create([
            'user_id' => $this->user->id,
            'name' => 'Webinar signups',
            'type' => 'email',
        ]);
    }

    protected function makeWebinar(array $attributes = []): Webinar
    {
        return Webinar::create(array_merge([
            'user_id' => $this->user->id,
            'name' => 'Niezależność finansowa',
            'slug' => 'jak-test',
            'type' => 'live',
            'status' => Webinar::STATUS_SCHEDULED,
            'scheduled_at' => now()->addDays(3),
            'timezone' => 'Europe/Warsaw',
            'target_list_id' => $this->list->id,
        ], $attributes));
    }

    public function test_registration_page_renders(): void
    {
        $this->makeWebinar();

        $this->get('/webinar/jak-test')->assertOk();
    }

    public function test_registration_submit_renders_thank_you_screen(): void
    {
        $webinar = $this->makeWebinar();

        $response = $this->post('/webinar/jak-test', [
            'email' => 'uczestnik@example.com',
            'first_name' => 'Grzegorz',
            'last_name' => 'Ciupek',
            'timezone' => 'Europe/Warsaw',
        ]);

        $response->assertOk();
        $response->assertSee('uczestnik@example.com');

        $registration = WebinarRegistration::where('email', 'uczestnik@example.com')->first();
        $this->assertNotNull($registration);
        $response->assertSee($registration->watch_url);

        // Subscriber landed on the configured contact list.
        $this->assertDatabaseHas('subscribers', [
            'email' => 'uczestnik@example.com',
            'user_id' => $this->user->id,
        ]);
        $this->assertSame(1, $this->list->subscribers()->count());
    }

    public function test_registration_submit_renders_without_scheduled_time(): void
    {
        $this->makeWebinar(['scheduled_at' => null]);

        $this->post('/webinar/jak-test', [
            'email' => 'brak-terminu@example.com',
        ])->assertOk();
    }

    /**
     * Analytics tracking used to blow up on requests without a User-Agent
     * header (compact() on undefined names → warning → exception → 500).
     */
    public function test_registration_works_without_a_user_agent_header(): void
    {
        $this->makeWebinar();

        $this->withServerVariables(['HTTP_USER_AGENT' => null])
            ->post('/webinar/jak-test', ['email' => 'no-agent@example.com'])
            ->assertOk();
    }

    public function test_thank_you_screen_renders_custom_sections_and_calendly(): void
    {
        $webinar = $this->makeWebinar();
        $webinar->update([
            'settings' => [
                'content' => [
                    'thankyou_headline' => 'Jesteś zapisany!',
                    'thankyou_message' => 'Sprawdź skrzynkę.',
                ],
                'thankyou_sections' => [
                    ['type' => 'text', 'title' => 'Co dalej?', 'body' => 'Dołącz 5 minut wcześniej.'],
                ],
                'calendly' => [
                    'enabled' => true,
                    'url' => 'https://calendly.com/netsendo/konsultacja',
                    'title' => 'Umów rozmowę',
                    'description' => 'Wybierz dogodny termin.',
                ],
            ],
        ]);

        $response = $this->post('/webinar/jak-test', [
            'email' => 'sekcje@example.com',
            'first_name' => 'Anna',
        ]);

        $response->assertOk();
        $response->assertSee('Jesteś zapisany!');
        $response->assertSee('Sprawdź skrzynkę.');
        $response->assertSee('Co dalej?');
        $response->assertSee('Umów rozmowę');
        $response->assertSee('calendly.com/netsendo/konsultacja', false);
    }

    public function test_standalone_thank_you_page_renders(): void
    {
        $webinar = $this->makeWebinar();
        $webinar->update([
            'settings' => [
                'calendly' => [
                    'enabled' => true,
                    'url' => 'https://calendly.com/netsendo/konsultacja',
                ],
            ],
        ]);

        $this->get('/webinar/jak-test/thank-you')
            ->assertOk()
            ->assertSee('calendly.com/netsendo/konsultacja', false);
    }
}
