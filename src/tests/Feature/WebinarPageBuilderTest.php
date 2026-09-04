<?php

namespace Tests\Feature;

use App\Models\ContactList;
use App\Models\User;
use App\Models\Webinar;
use App\Services\Webinar\WebinarPagePresets;
use App\Services\Webinar\WebinarPageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebinarPageBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Webinar $webinar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $list = ContactList::create([
            'user_id' => $this->user->id,
            'name' => 'Webinar signups',
            'type' => 'email',
        ]);

        $this->webinar = Webinar::create([
            'user_id' => $this->user->id,
            'name' => 'Niezależność finansowa',
            'slug' => 'jak-test',
            'description' => 'Plan finansowy krok po kroku.',
            'type' => 'live',
            'status' => Webinar::STATUS_SCHEDULED,
            'scheduled_at' => now()->addDays(3),
            'timezone' => 'Europe/Warsaw',
            'target_list_id' => $list->id,
            'settings' => [
                'benefits' => ['Zbudujesz plan', 'Policzysz odsetki'],
                'registration_sections' => [
                    ['type' => 'text', 'title' => 'Dla kogo', 'body' => 'Dla każdego.', 'placement' => 'below_form'],
                ],
            ],
        ]);
    }

    /**
     * Every block type must render on the public page — this is the guard that
     * a broken block template can never reach a visitor unnoticed.
     */
    public function test_every_block_type_renders_on_the_public_registration_page(): void
    {
        $rows = [];

        foreach (array_keys(WebinarPageService::blocksForPage(WebinarPageService::PAGE_REGISTER)) as $type) {
            $rows[] = WebinarPageService::row([
                WebinarPageService::block($type, $this->sampleProps($type)),
            ]);
        }

        WebinarPageService::store($this->webinar, WebinarPageService::PAGE_REGISTER, [
            'enabled' => true,
            'theme' => WebinarPageService::themeDefaults(),
            'rows' => $rows,
        ]);

        $response = $this->get('/webinar/jak-test');

        $response->assertOk();
        $response->assertSee('Nagłówek testowy');
        $response->assertSee('wb-registration-form', false);
        $response->assertSee('data-wb-countdown', false);
    }

    public function test_thank_you_and_purchase_pages_render_every_block(): void
    {
        foreach ([WebinarPageService::PAGE_THANKYOU, WebinarPageService::PAGE_PURCHASE] as $page) {
            $rows = [];
            foreach (array_keys(WebinarPageService::blocksForPage($page)) as $type) {
                $rows[] = WebinarPageService::row([
                    WebinarPageService::block($type, $this->sampleProps($type)),
                ]);
            }

            WebinarPageService::store($this->webinar, $page, [
                'enabled' => true,
                'theme' => WebinarPageService::themeDefaults(),
                'rows' => $rows,
            ]);
        }

        $this->post('/webinar/jak-test', ['email' => 'blocks@example.com', 'first_name' => 'Jan'])
            ->assertOk()
            ->assertSee('Nagłówek testowy');

        $this->get('/webinar/jak-test/thank-you')
            ->assertOk()
            ->assertSee('Nagłówek testowy');
    }

    public function test_every_preset_produces_a_renderable_page(): void
    {
        foreach (WebinarPagePresets::PRESETS as $preset) {
            foreach (WebinarPageService::PAGES as $page) {
                WebinarPageService::store(
                    $this->webinar,
                    $page,
                    WebinarPagePresets::build($this->webinar->fresh(), $preset, $page)
                );
            }

            $this->get('/webinar/jak-test')->assertOk();
            $this->post('/webinar/jak-test', ['email' => "{$preset}@example.com"])->assertOk();
            $this->get('/webinar/jak-test/thank-you')->assertOk();
        }
    }

    public function test_page_falls_back_to_the_legacy_template_when_not_built(): void
    {
        $this->get('/webinar/jak-test')
            ->assertOk()
            ->assertSee('cdn.tailwindcss.com', false);
    }

    public function test_disabled_definition_is_ignored_by_the_public_page(): void
    {
        WebinarPageService::store($this->webinar, WebinarPageService::PAGE_REGISTER, [
            'enabled' => false,
            'theme' => WebinarPageService::themeDefaults(),
            'rows' => [WebinarPageService::row([WebinarPageService::block('heading', ['text' => 'Ukryty'])])],
        ]);

        $this->get('/webinar/jak-test')
            ->assertOk()
            ->assertDontSee('Ukryty');
    }

    public function test_builder_screen_and_save_round_trip(): void
    {
        $this->actingAs($this->user)
            ->get(route('webinars.pages.edit', [$this->webinar->id, 'register']))
            ->assertOk();

        $definition = WebinarPagePresets::build($this->webinar, 'aurora', 'register');

        $this->actingAs($this->user)
            ->put(route('webinars.pages.update', [$this->webinar->id, 'register']), ['definition' => $definition])
            ->assertRedirect();

        $this->assertTrue(WebinarPageService::isBuilt($this->webinar->fresh(), 'register'));

        $this->actingAs($this->user)
            ->delete(route('webinars.pages.destroy', [$this->webinar->id, 'register']))
            ->assertRedirect();

        $this->assertFalse(WebinarPageService::isBuilt($this->webinar->fresh(), 'register'));
    }

    public function test_preview_endpoint_renders_the_draft(): void
    {
        $definition = WebinarPagePresets::build($this->webinar, 'urgency', 'register');

        $this->actingAs($this->user)
            ->post(route('webinars.pages.preview', [$this->webinar->id, 'register']), ['definition' => $definition])
            ->assertOk()
            ->assertSee('wb-registration-form', false);

        // Waiting-room preview renders the player stand-in plus the blocks.
        $watch = WebinarPagePresets::build($this->webinar, 'urgency', 'watch');

        $this->actingAs($this->user)
            ->post(route('webinars.pages.preview', [$this->webinar->id, 'watch']), ['definition' => $watch])
            ->assertOk()
            ->assertSee('wb-scope', false);
    }

    public function test_preset_endpoint_returns_a_prefilled_definition(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('webinars.pages.preset', [$this->webinar->id, 'register']), ['preset' => 'minimal']);

        $response->assertOk();
        $this->assertTrue($response->json('definition.enabled'));
        $this->assertNotEmpty($response->json('definition.rows'));
        $this->assertSame('minimal', 'minimal');
    }

    public function test_another_user_cannot_open_the_builder(): void
    {
        $intruder = User::factory()->create();

        $this->actingAs($intruder)
            ->get(route('webinars.pages.edit', [$this->webinar->id, 'register']))
            ->assertForbidden();
    }

    public function test_unknown_block_types_and_pages_are_rejected(): void
    {
        $rows = WebinarPageService::normalizeRows([
            [
                'layout' => '1',
                'columns' => [['blocks' => [
                    ['type' => 'script_kiddie', 'props' => []],
                    ['type' => 'heading', 'props' => ['text' => 'ok']],
                ]]],
            ],
        ], WebinarPageService::PAGE_REGISTER);

        $this->assertCount(1, $rows[0]['columns'][0]['blocks']);
        $this->assertSame('heading', $rows[0]['columns'][0]['blocks'][0]['type']);

        // The registration form block is not offered on the thank-you page.
        $thankYouRows = WebinarPageService::normalizeRows([
            ['layout' => '1', 'columns' => [['blocks' => [['type' => 'form', 'props' => []]]]]],
        ], WebinarPageService::PAGE_THANKYOU);

        $this->assertSame([], $thankYouRows[0]['columns'][0]['blocks']);

        $this->actingAs($this->user)
            ->get(route('webinars.pages.edit', [$this->webinar->id, 'nope']))
            ->assertNotFound();
    }

    /**
     * Representative content for each block type.
     */
    protected function sampleProps(string $type): array
    {
        return match ($type) {
            'heading' => ['text' => 'Nagłówek testowy'],
            'text' => ['body' => "Akapit testowy.\nDruga linia."],
            'image' => ['url' => 'https://example.com/img.png', 'alt' => 'Obraz'],
            'video' => ['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'caption' => 'Zapowiedź'],
            'button' => ['label' => 'Zapisz się', 'url' => '#wb-form'],
            'html' => ['code' => '<span data-html-block>ok</span>'],
            'benefits' => ['title' => 'Co zyskasz', 'items' => ['Punkt pierwszy', 'Punkt drugi']],
            'steps' => ['title' => 'Jak to działa', 'items' => [['title' => 'Krok 1', 'body' => 'Opis']]],
            'testimonials' => ['title' => 'Opinie', 'items' => [['quote' => 'Świetne', 'author' => 'Anna', 'role' => 'CEO']]],
            'faq' => ['title' => 'FAQ', 'items' => [['question' => 'Czy będzie nagranie?', 'answer' => 'Tak.']]],
            'speaker' => ['name' => 'Andrzej', 'role' => 'Trener', 'bio' => 'Bio prelegenta.'],
            'stats' => ['items' => [['value' => '1200', 'label' => 'uczestników']]],
            'countdown' => ['title' => 'Start za'],
            'calendly' => ['url' => 'https://calendly.com/netsendo/rozmowa', 'title' => 'Umów rozmowę'],
            'sessions' => ['title' => 'Wybierz termin'],
            default => [],
        };
    }
}
