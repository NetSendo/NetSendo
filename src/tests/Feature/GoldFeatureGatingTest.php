<?php

namespace Tests\Feature;

use App\Licensing\Feature;
use App\Licensing\FeatureRequiresGold;
use App\Models\AbTest;
use App\Models\AbTestVariant;
use App\Models\AiBrainSettings;
use App\Models\AiIntegration;
use App\Models\ApiKey;
use App\Models\ContactList;
use App\Models\Funnel;
use App\Models\FunnelStep;
use App\Models\Message;
use App\Models\Setting;
use App\Models\Tag;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Models\Webinar;
use App\Services\AbTestService;
use App\Services\AI\AiService;
use App\Services\Brain\WebResearchService;
use App\Services\LicenseService;
use App\Services\Messages\MessageEditorService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Concerns\ActivatesGold;
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
    use ActivatesGold;

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

    private function gold(?string $expiresAt = null): void
    {
        $this->activateGold($expiresAt);
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

    // --- The plan list shown on the licence page ---

    public function test_every_gold_feature_is_listed_in_the_gold_plan_and_none_in_silver(): void
    {
        $listed = fn (string $plan) => collect(config("netsendo.plans.{$plan}.sections"))->flatten()->all();

        foreach (Feature::cases() as $feature) {
            $this->assertContains($feature->value, $listed('GOLD'), "{$feature->value} is not on the licence page");
            $this->assertNotContains($feature->value, $listed('SILVER'));
        }
    }

    // --- Deliverability Shield, NMI, segmentation, Campaign Auditor ---

    public function test_deliverability_shield_tools_need_gold_but_its_page_shows_the_offer(): void
    {
        $this->withoutVite();

        $this->actingAs($this->user)
            ->postJson(route('deliverability.domains.store'), ['domain' => 'example.com'])
            ->assertStatus(402)
            ->assertJsonPath('feature', 'deliverability_shield');

        $this->actingAs($this->user)
            ->postJson(route('deliverability.quick-simulate'), ['subject' => 'Hi', 'content' => '<p>Hi</p>'])
            ->assertStatus(402);

        $this->actingAs($this->user)->get(route('deliverability.index'))->assertOk();
    }

    public function test_mail_infrastructure_changes_need_gold_but_reading_does_not(): void
    {
        $this->actingAs($this->user)
            ->postJson(route('settings.nmi.pools.store'), ['name' => 'Pool'])
            ->assertStatus(402)
            ->assertJsonPath('feature', 'mail_infrastructure');

        $this->assertNotSame(402, $this->actingAs($this->user)->getJson(route('settings.nmi.pools.index'))->status());
    }

    public function test_the_segmentation_dashboard_needs_gold(): void
    {
        $this->withoutVite();

        $this->actingAs($this->user)->getJson(route('segmentation.index'))
            ->assertStatus(402)
            ->assertJsonPath('feature', 'segmentation_dashboard');

        // A plain page load lands on the licence page
        $this->actingAs($this->user)->get(route('segmentation.index'))
            ->assertRedirect(route('license.index'));

        $this->gold();
        $this->actingAs($this->user)->get(route('segmentation.index'))->assertOk();
    }

    public function test_running_audits_and_ai_analysis_of_stats_need_gold(): void
    {
        $this->actingAs($this->user)
            ->postJson(route('campaign-auditor.run'), ['type' => 'full'])
            ->assertStatus(402)
            ->assertJsonPath('feature', 'campaign_auditor');

        $tag = Tag::create(['user_id' => $this->user->id, 'name' => 'spring']);
        $this->actingAs($this->user)
            ->postJson(route('campaign-stats.ai-analysis', $tag))
            ->assertStatus(402);
    }

    public function test_scheduled_gold_work_runs_only_with_gold(): void
    {
        $events = collect(app(Schedule::class)->events())->filter(fn ($event) => collect([
            'audit:run', 'deliverability:check-domains', 'deliverability:upgrade-dmarc',
            'nmi:process-warming', 'nmi:check-blacklists', 'nmi:rotate-dkim',
        ])->contains(fn ($command) => str_contains($event->command, $command)));

        $this->assertCount(6, $events);
        $events->each(fn ($event) => $this->assertFalse($event->filtersPass($this->app), $event->command));

        $this->gold();
        $events->each(fn ($event) => $this->assertTrue($event->filtersPass($this->app), $event->command));
    }

    // --- Team members ---

    public function test_silver_has_room_for_one_team_member_counting_invitations(): void
    {
        Mail::fake();

        $this->actingAs($this->user)
            ->post(route('settings.users.store'), ['email' => 'anna@example.com', 'name' => 'Anna'])
            ->assertSessionHasNoErrors();

        // The pending invitation takes the one place
        $this->actingAs($this->user)
            ->postJson(route('settings.users.create-user'), [
                'email' => 'bob@example.com', 'name' => 'Bob',
                'password' => 'Secret-pass-123', 'password_confirmation' => 'Secret-pass-123',
            ])
            ->assertStatus(402)
            ->assertJsonPath('feature', 'unlimited_team_members');

        $this->assertSame(0, $this->user->teamMembers()->count());
    }

    public function test_gold_adds_any_number_of_team_members(): void
    {
        $this->gold();

        foreach (['bob', 'carol', 'dave'] as $name) {
            $this->actingAs($this->user)->post(route('settings.users.create-user'), [
                'email' => "{$name}@example.com", 'name' => $name,
                'password' => 'Secret-pass-123', 'password_confirmation' => 'Secret-pass-123',
            ])->assertSessionHasNoErrors();
        }

        $this->assertSame(3, $this->user->teamMembers()->count());
    }

    public function test_an_invitation_cannot_take_a_silver_team_past_its_limit(): void
    {
        User::factory()->create(['admin_user_id' => $this->user->id]);
        $invitation = $this->user->invitations()->create([
            'email' => 'late@example.com', 'name' => 'Late', 'token' => TeamInvitation::generateToken(),
        ]);

        $this->post(route('invitation.complete', $invitation->token), [
            'password' => 'Secret-pass-123', 'password_confirmation' => 'Secret-pass-123',
        ])->assertSessionHasErrors('password');

        $this->assertNull(User::where('email', 'late@example.com')->first());
    }

    // --- NetSendo Brain: manual chat is SILVER, autonomy is GOLD ---

    public function test_without_gold_brain_works_in_manual_mode_whatever_is_stored(): void
    {
        $settings = AiBrainSettings::getForUser($this->user->id);
        $settings->update(['work_mode' => 'autonomous', 'agent_modes' => ['campaign' => 'autonomous']]);
        $settings = $settings->fresh();

        $this->assertSame('manual', $settings->work_mode);
        $this->assertSame('manual', $settings->getAgentMode('campaign'));
        $this->assertSame('autonomous', $settings->getRawOriginal('work_mode'), 'the choice is kept');

        $this->gold();
        $settings = $settings->fresh();
        $this->assertSame('autonomous', $settings->work_mode);
        $this->assertSame('autonomous', $settings->getAgentMode('campaign'));
    }

    public function test_automatic_modes_telegram_and_research_keys_need_gold_but_manual_settings_do_not(): void
    {
        $url = route('brain.api.settings.update');

        foreach ([['work_mode' => 'semi_auto'], ['agent_modes' => ['campaign' => 'autonomous']], ['telegram_bot_token' => '123:abc'], ['perplexity_api_key' => 'pplx-1']] as $payload) {
            $this->actingAs($this->user)->putJson($url, $payload)
                ->assertStatus(402)
                ->assertJsonPath('feature', 'brain_autonomy');
        }

        $this->actingAs($this->user)->putJson($url, ['work_mode' => 'manual', 'preferred_language' => 'pl'])
            ->assertOk();
        $this->assertSame('pl', AiBrainSettings::getForUser($this->user->id)->preferred_language);
    }

    public function test_turning_the_brain_cron_on_needs_gold_turning_it_off_does_not(): void
    {
        $url = route('brain.api.monitor.cron');

        $this->actingAs($this->user)->putJson($url, ['cron_enabled' => true, 'cron_interval_minutes' => 60])
            ->assertStatus(402);
        $this->actingAs($this->user)->putJson($url, ['cron_enabled' => false, 'cron_interval_minutes' => 60])
            ->assertOk();
    }

    public function test_the_brain_cron_does_nothing_without_gold(): void
    {
        AiBrainSettings::getForUser($this->user->id)->update(['cron_enabled' => true, 'cron_interval_minutes' => 5]);

        $this->artisan('brain:run-cron')
            ->expectsOutputToContain('needs the GOLD licence')
            ->assertSuccessful();

        $this->assertNull(AiBrainSettings::getForUser($this->user->id)->last_cron_run_at);
    }

    public function test_voice_goals_digest_and_telegram_linking_need_gold(): void
    {
        foreach ([
            ['post', route('brain.api.chat.voice')],
            ['post', route('brain.api.goals.store')],
            ['get', route('brain.api.digest')],
            ['post', route('brain.api.telegram.link-code')],
            ['post', route('brain.api.research.test')],
        ] as [$method, $url]) {
            $this->actingAs($this->user)->json($method, $url)
                ->assertStatus(402)
                ->assertJsonPath('feature', 'brain_autonomy');
        }

        // Reading goals and unlinking Telegram stay open
        $this->assertNotSame(402, $this->actingAs($this->user)->getJson(route('brain.api.goals.index'))->status());
        $this->assertNotSame(402, $this->actingAs($this->user)->postJson(route('brain.api.telegram.disconnect'))->status());
    }

    public function test_web_research_is_unavailable_without_gold_even_with_keys(): void
    {
        AiBrainSettings::getForUser($this->user->id)->update(['perplexity_api_key' => 'pplx-1', 'serpapi_api_key' => 'serp-1']);
        $research = app(WebResearchService::class);

        $this->assertFalse($research->isAvailable($this->user)['any']);
        $this->assertSame('Web research requires the GOLD licence', $research->deepResearch('trends', $this->user)['error']);

        $this->gold();
        $this->assertTrue($research->isAvailable($this->user)['any']);
    }

    public function test_the_telegram_webhook_answers_without_gold_and_runs_nothing(): void
    {
        $this->postJson('/api/telegram/webhook', [
            'update_id' => 1,
            'message' => ['message_id' => 1, 'chat' => ['id' => 4242], 'text' => 'Plan a campaign'],
        ])->assertOk();

        $this->assertSame(0, \App\Models\AiConversation::count());
    }

    // --- Split A/B steps in funnels ---

    private function splitNodes(string $splitId = 'new-2'): array
    {
        return [
            ['id' => 'new-1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
            ['id' => $splitId, 'type' => 'split', 'position' => ['x' => 0, 'y' => 100], 'data' => ['split_variants' => [
                ['key' => 'a', 'name' => 'A', 'weight' => 50],
                ['key' => 'b', 'name' => 'B', 'weight' => 50],
            ]]],
        ];
    }

    public function test_a_funnel_with_a_new_split_step_is_refused_without_gold(): void
    {
        $this->actingAs($this->user)
            ->postJson(route('funnels.store'), [
                'name' => 'Welcome', 'trigger_type' => 'manual', 'nodes' => $this->splitNodes(), 'edges' => [],
            ])
            ->assertStatus(402)
            ->assertJsonPath('feature', 'funnel_ab_split');

        $this->assertSame(0, Funnel::count(), 'nothing is saved');
    }

    public function test_a_split_step_the_funnel_already_has_can_still_be_saved(): void
    {
        $funnel = Funnel::create(['user_id' => $this->user->id, 'name' => 'Built under GOLD', 'trigger_type' => 'manual']);
        $split = $funnel->steps()->create(['type' => FunnelStep::TYPE_SPLIT, 'order' => 1, 'split_variants' => [
            ['key' => 'a', 'name' => 'A', 'weight' => 50],
            ['key' => 'b', 'name' => 'B', 'weight' => 50],
        ]]);

        app(\App\Services\Funnels\FunnelService::class)->ensureSplitStepsAllowed($funnel, $this->splitNodes((string) $split->id));

        $this->expectException(FeatureRequiresGold::class);
        app(\App\Services\Funnels\FunnelService::class)->ensureSplitStepsAllowed($funnel, [
            ...$this->splitNodes((string) $split->id),
            ['id' => 'new-9', 'type' => 'split', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
        ]);
    }

    public function test_the_api_refuses_a_new_split_step_without_gold(): void
    {
        $funnel = Funnel::create(['user_id' => $this->user->id, 'name' => 'Welcome', 'trigger_type' => 'manual']);
        $funnel->steps()->create(['type' => FunnelStep::TYPE_START, 'order' => 0]);

        $this->api(['funnels:read', 'funnels:write'])
            ->postJson("/api/v1/funnels/{$funnel->id}/steps", [
                'type' => 'split',
                'name' => 'Subject test',
                'split_variants' => [['key' => 'a', 'name' => 'A', 'weight' => 50], ['key' => 'b', 'name' => 'B', 'weight' => 50]],
            ])
            ->assertStatus(402)
            ->assertJsonPath('feature', 'funnel_ab_split');
    }
}
