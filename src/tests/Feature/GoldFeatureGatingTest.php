<?php

namespace Tests\Feature;

use App\Licensing\Feature;
use App\Licensing\FeatureRequiresGold;
use App\Models\AbTest;
use App\Models\AbTestVariant;
use App\Models\AiIntegration;
use App\Models\ApiKey;
use App\Models\ContactList;
use App\Models\Message;
use App\Models\Setting;
use App\Models\User;
use App\Models\Webinar;
use App\Services\AbTestService;
use App\Services\AI\AiService;
use App\Services\LicenseService;
use App\Services\Messages\MessageEditorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The GOLD plan sold on netsendo.com is enforced on the server: A/B tests, auto and hybrid
 * webinars with the scenario builder, and OpenRouter/Ollama refuse to start without an active
 * GOLD licence, through the panel, the public API (402) and background work alike. What was
 * started under GOLD keeps working after it ends, and nothing is deleted.
 */
class GoldFeatureGatingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private ContactList $list;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->list = ContactList::create([
            'user_id' => $this->user->id,
            'name' => 'Readers',
            'type' => 'email',
            'is_public' => true,
        ]);
    }

    /** An active GOLD licence (or, with a past date, an expired one). */
    private function gold(?string $expiresAt = null): void
    {
        Setting::updateOrCreate(['key' => 'license_key'], ['value' => 'Z29sZA==-test-key']);
        Setting::updateOrCreate(['key' => 'license_plan'], ['value' => 'GOLD']);
        if ($expiresAt) {
            Setting::updateOrCreate(['key' => 'license_expires_at'], ['value' => $expiresAt]);
        }
    }

    private function api(array $permissions = ['messages:write'])
    {
        $key = ApiKey::generate($this->user->id, 'Agent', $permissions)['key'];

        return $this->withHeaders(['Authorization' => 'Bearer ' . $key]);
    }

    private function message(): Message
    {
        $message = Message::create([
            'user_id' => $this->user->id,
            'channel' => 'email',
            'type' => 'broadcast',
            'subject' => 'Spring sale',
            'content' => '<p>Hello</p>',
            'status' => 'draft',
        ]);
        $message->contactLists()->attach($this->list->id);

        return $message;
    }

    private function abTest(Message $message, string $status = AbTest::STATUS_DRAFT): AbTest
    {
        $test = AbTest::create([
            'message_id' => $message->id,
            'user_id' => $this->user->id,
            'name' => 'Subject test',
            'status' => $status,
            'test_type' => 'subject',
            'winning_metric' => 'open_rate',
            'sample_percentage' => 20,
            'test_duration_hours' => 4,
            'auto_select_winner' => true,
            'confidence_threshold' => 95,
        ]);
        foreach (['A' => true, 'B' => false] as $letter => $control) {
            AbTestVariant::create([
                'ab_test_id' => $test->id,
                'variant_letter' => $letter,
                'subject' => "Subject {$letter}",
                'is_control' => $control,
            ]);
        }

        return $test;
    }

    private function abConfig(): array
    {
        return [
            'enabled' => true,
            'test_type' => 'subject',
            'variants' => [
                ['variant_letter' => 'A', 'is_control' => true],
                ['variant_letter' => 'B', 'subject' => 'Last chance'],
            ],
        ];
    }

    private function webinar(string $type): Webinar
    {
        return Webinar::create([
            'user_id' => $this->user->id,
            'name' => 'Masterclass',
            'slug' => 'masterclass-' . $type,
            'type' => $type,
            'status' => Webinar::STATUS_SCHEDULED,
            'scheduled_at' => now()->addDays(3),
            'timezone' => 'Europe/Warsaw',
            'target_list_id' => $this->list->id,
        ]);
    }

    // --- The licence in effect ---

    public function test_without_a_licence_no_gold_feature_is_allowed(): void
    {
        $licences = app(LicenseService::class);

        foreach (Feature::cases() as $feature) {
            $this->assertFalse($licences->allows($feature), $feature->value);
        }
        $this->assertSame(array_fill_keys(array_column(Feature::cases(), 'value'), false), $licences->features());
    }

    public function test_an_active_gold_licence_allows_every_gold_feature(): void
    {
        $this->gold(now()->addMonth()->toIso8601String());

        $this->assertTrue(collect(app(LicenseService::class)->features())->every(fn ($allowed) => $allowed));
    }

    public function test_an_expired_gold_licence_works_as_silver(): void
    {
        $this->gold(now()->subDay()->toIso8601String());

        $this->assertFalse(app(LicenseService::class)->allows(Feature::AbTests));
        $this->assertTrue(app(LicenseService::class)->isGoldExpired());
    }

    public function test_pages_receive_the_licensed_features(): void
    {
        $this->withoutVite();

        $this->actingAs($this->user)->get(route('license.index'))
            ->assertInertia(fn ($page) => $page
                ->where('license.plan', 'SILVER')
                ->where('license.features.ab_tests', false)
                ->where('license.features.auto_webinars', false));
    }

    // --- A/B tests ---

    public function test_the_api_refuses_to_create_an_ab_test_without_gold(): void
    {
        $message = $this->message();

        $this->api()->postJson('/api/v1/ab-tests', ['message_id' => $message->id, 'name' => 'Test'])
            ->assertStatus(402)
            ->assertJsonPath('code', 'gold_required')
            ->assertJsonPath('feature', 'ab_tests')
            ->assertJsonPath('required_plan', 'GOLD')
            ->assertJsonPath('upgrade_url', route('license.index'));

        $this->assertSame(0, AbTest::count());
    }

    public function test_the_api_lets_gold_create_ab_tests(): void
    {
        $this->gold();
        $message = $this->message();

        $response = $this->api()->postJson('/api/v1/ab-tests', ['message_id' => $message->id, 'name' => 'Test']);

        $this->assertNotSame(402, $response->status());
    }

    public function test_reading_results_stays_open_without_gold(): void
    {
        $test = $this->abTest($this->message(), AbTest::STATUS_COMPLETED);

        $this->api(['messages:read'])->getJson("/api/v1/ab-tests/{$test->id}/results")
            ->assertOk();
    }

    public function test_a_message_with_an_ab_test_is_refused_over_the_api_before_it_is_saved(): void
    {
        $this->api()->postJson('/api/v1/messages', [
            'subject' => 'Spring sale',
            'channel' => 'email',
            'type' => 'broadcast',
            'contact_list_ids' => [$this->list->id],
            'ab_test_config' => $this->abConfig(),
        ])->assertStatus(402)->assertJsonPath('feature', 'ab_tests');

        $this->assertSame(0, Message::count(), 'nothing is saved');
        $this->assertSame(0, AbTest::count());
    }

    public function test_gold_saves_a_message_with_an_ab_test_over_the_api(): void
    {
        $this->gold();

        $this->api()->postJson('/api/v1/messages', [
            'subject' => 'Spring sale',
            'channel' => 'email',
            'type' => 'broadcast',
            'contact_list_ids' => [$this->list->id],
            'ab_test_config' => $this->abConfig(),
        ])->assertCreated();

        $this->assertSame(1, AbTest::count());
    }

    public function test_the_editor_refuses_a_new_or_draft_test_but_not_a_started_one(): void
    {
        $editor = app(MessageEditorService::class);

        // Turning A/B off is always allowed
        $editor->ensureAbTestAllowed(['enabled' => false]);

        // A message whose test already ran is saved as before (the test is not changed)
        $ran = $this->message();
        $this->abTest($ran, AbTest::STATUS_COMPLETED);
        $editor->ensureAbTestAllowed($this->abConfig(), $ran->fresh());

        // A draft test needs GOLD
        $draft = $this->message();
        $this->abTest($draft);
        $this->expectException(FeatureRequiresGold::class);
        $editor->ensureAbTestAllowed($this->abConfig(), $draft->fresh());
    }

    public function test_starting_a_test_in_the_panel_without_gold_opens_the_upgrade_dialog(): void
    {
        $test = $this->abTest($this->message());

        $this->actingAs($this->user)
            ->postJson(route('ab-tests.start', $test))
            ->assertStatus(402);

        $this->actingAs($this->user)
            ->withHeaders(['X-Inertia' => 'true'])
            ->from(route('ab-tests.show', $test))
            ->post(route('ab-tests.start', $test))
            ->assertRedirect(route('ab-tests.show', $test))
            ->assertSessionHas('gold_required', 'ab_tests')
            ->assertSessionHasErrors('gold_required');

        $this->assertSame(AbTest::STATUS_DRAFT, $test->fresh()->status);
    }

    public function test_sending_cancels_a_draft_test_without_gold_and_leaves_running_tests_alone(): void
    {
        $service = app(AbTestService::class);
        $draft = $this->abTest($this->message());
        $running = $this->abTest($this->message(), AbTest::STATUS_RUNNING);

        $this->assertTrue($service->cancelDraftWithoutLicence($draft));
        $this->assertSame(AbTest::STATUS_CANCELLED, $draft->fresh()->status);

        $this->assertFalse($service->cancelDraftWithoutLicence($running));
        $this->assertSame(AbTest::STATUS_RUNNING, $running->fresh()->status);
    }

    public function test_sending_keeps_a_draft_test_with_gold(): void
    {
        $this->gold();
        $draft = $this->abTest($this->message());

        $this->assertFalse(app(AbTestService::class)->cancelDraftWithoutLicence($draft));
        $this->assertSame(AbTest::STATUS_DRAFT, $draft->fresh()->status);
    }

    // --- Webinars ---

    public function test_auto_and_hybrid_webinars_cannot_be_created_without_gold(): void
    {
        foreach (['auto' => 'auto_webinars', 'hybrid' => 'hybrid_webinars'] as $type => $feature) {
            $this->actingAs($this->user)
                ->postJson(route('webinars.store'), ['name' => 'Evergreen', 'type' => $type])
                ->assertStatus(402)
                ->assertJsonPath('feature', $feature);
        }

        $this->assertSame(0, Webinar::count());
    }

    public function test_live_webinars_stay_in_silver(): void
    {
        $this->actingAs($this->user)
            ->post(route('webinars.store'), ['name' => 'Live Q&A', 'type' => 'live'])
            ->assertRedirect();

        $this->assertSame(1, Webinar::where('type', 'live')->count());
    }

    public function test_gold_creates_auto_webinars(): void
    {
        $this->gold();

        $this->actingAs($this->user)
            ->post(route('webinars.store'), ['name' => 'Evergreen', 'type' => 'auto'])
            ->assertRedirect();

        $this->assertSame(1, Webinar::where('type', 'auto')->count());
    }

    public function test_an_auto_webinar_without_gold_takes_no_new_registrations(): void
    {
        $auto = $this->webinar(Webinar::TYPE_AUTO);
        $live = $this->webinar(Webinar::TYPE_LIVE);

        $this->assertFalse($auto->canRegister());
        $this->assertTrue($live->canRegister());

        $this->gold();
        $this->assertTrue($auto->canRegister());
    }

    public function test_changing_an_auto_webinar_schedule_needs_gold_but_reading_it_does_not(): void
    {
        $auto = $this->webinar(Webinar::TYPE_AUTO);

        $this->actingAs($this->user)
            ->postJson(route('webinars.auto.schedule', $auto), ['schedule_type' => 'daily'])
            ->assertStatus(402)
            ->assertJsonPath('feature', 'auto_webinars');

        $this->actingAs($this->user)
            ->getJson(route('webinars.scripts.index', $auto))
            ->assertOk();
    }

    public function test_copying_an_auto_webinar_needs_gold(): void
    {
        $auto = $this->webinar(Webinar::TYPE_AUTO);

        $this->actingAs($this->user)
            ->postJson(route('webinars.duplicate', $auto))
            ->assertStatus(402);

        $this->assertSame(1, Webinar::count());
    }

    // --- Advanced AI (OpenRouter, Ollama) ---

    public function test_ollama_and_openrouter_cannot_be_added_without_gold(): void
    {
        foreach (['ollama', 'openrouter'] as $provider) {
            $this->actingAs($this->user)
                ->postJson(route('settings.ai-integrations.store'), ['provider' => $provider, 'name' => $provider])
                ->assertStatus(402)
                ->assertJsonPath('feature', 'advanced_ai');
        }

        $this->actingAs($this->user)
            ->post(route('settings.ai-integrations.store'), ['provider' => 'openai', 'name' => 'OpenAI', 'api_key' => 'sk-test'])
            ->assertRedirect();

        $this->assertSame(['openai'], AiIntegration::pluck('provider')->all());
    }

    public function test_gold_adds_ollama(): void
    {
        $this->gold();

        $this->actingAs($this->user)
            ->post(route('settings.ai-integrations.store'), ['provider' => 'ollama', 'name' => 'Local'])
            ->assertRedirect();

        $this->assertSame(1, AiIntegration::where('provider', 'ollama')->count());
    }

    public function test_an_existing_ollama_integration_is_not_used_without_gold(): void
    {
        $ollama = AiIntegration::create(['provider' => 'ollama', 'name' => 'Local', 'is_active' => true]);
        $openai = AiIntegration::create(['provider' => 'openai', 'name' => 'OpenAI', 'api_key' => 'sk-test', 'is_active' => true]);
        $ai = app(AiService::class);

        $this->assertFalse($ollama->isUsable());
        $this->assertSame([$openai->id], AiIntegration::usable()->pluck('id')->all());
        $this->assertSame($openai->id, $ai->getDefaultIntegration()?->id);

        $this->expectException(FeatureRequiresGold::class);
        $ai->getProvider($ollama);
    }

    public function test_gold_uses_an_existing_ollama_integration(): void
    {
        $this->gold();
        $ollama = AiIntegration::create(['provider' => 'ollama', 'name' => 'Local', 'is_active' => true]);

        $this->assertTrue($ollama->isUsable());
        $this->assertNotNull(app(AiService::class)->getProvider($ollama));
    }

    public function test_the_default_integration_skips_inactive_ollama(): void
    {
        $this->gold();
        AiIntegration::create(['provider' => 'ollama', 'name' => 'Local', 'is_active' => false]);

        $this->assertNull(app(AiService::class)->getDefaultIntegration());
    }
}
