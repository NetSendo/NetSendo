<?php

namespace Tests\Feature;

use App\Models\AiActionPlan;
use App\Models\AiActionPlanStep;
use App\Models\AiBrainSettings;
use App\Models\AiConversation;
use App\Models\AiExecutionLog;
use App\Models\AiGoal;
use App\Models\AiIntegration;
use App\Models\AiPendingApproval;
use App\Models\KnowledgeEntry;
use App\Models\Message;
use App\Models\Subscriber;
use App\Models\User;
use App\Services\AI\AiService;
use App\Services\Brain\AgentOrchestrator;
use App\Services\Brain\Agents\ResearchAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Concerns\ActivatesGold;
use Tests\TestCase;

/**
 * Fixes from a review of Brain on a live GOLD instance: every chat command was misread because
 * intent classification went to whichever AI integration came first (and failed there), modes
 * shown and modes applied differed, and the Monitor showed stalled work and wrong numbers.
 */
class BrainReviewFixesTest extends TestCase
{
    use RefreshDatabase;
    use ActivatesGold;

    private User $user;

    private AiIntegration $gemini;

    private AiIntegration $anthropic;

    /** Prompts sent to the fake Anthropic API, in order. */
    private array $anthropicPrompts = [];

    /** What the fake Anthropic API answers to the intent classification prompt. */
    private string $classification = '{"requires_agent": false, "intent": "question", "task_type": "general"}';

    /** Answer to every other Anthropic prompt. */
    private string $answer = 'Brain answer';

    /** Answers the fake Gemini API (null: Gemini must not be called). */
    private ?\Closure $geminiResponder = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->activateGold();
        $this->user = User::factory()->create();

        // Gemini comes first in the database, like on the instance where the bug was found
        $this->gemini = AiIntegration::create([
            'provider' => 'gemini', 'name' => 'Google Gemini', 'api_key' => 'g-test',
            'default_model' => 'gemini-2.5-flash', 'is_active' => true,
        ]);
        $this->anthropic = AiIntegration::create([
            'provider' => 'anthropic', 'name' => 'Anthropic', 'api_key' => 'a-test',
            'default_model' => 'claude-sonnet-4-5', 'is_active' => true,
        ]);

        AiBrainSettings::getForUser($this->user->id)->update([
            'preferred_integration_id' => $this->anthropic->id,
            'preferred_model' => 'claude-opus-4-8',
        ]);

        Http::fake([
            'api.anthropic.com/*' => function (Request $request) {
                $prompt = $request['messages'][0]['content'] ?? '';
                $this->anthropicPrompts[] = ['model' => $request['model'] ?? null, 'prompt' => $prompt];

                $text = match (true) {
                    str_contains($prompt, "Classify the user's intent") => $this->classification,
                    str_contains($prompt, 'HIGH-LEVEL GOAL') => '{"is_goal": false}',
                    default => $this->answer,
                };

                return Http::response([
                    'content' => [['type' => 'text', 'text' => $text]],
                    'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
                ]);
            },
            'generativelanguage.googleapis.com/*' => fn (Request $request) => $this->geminiResponder
                ? ($this->geminiResponder)($request)
                : Http::response(['error' => ['message' => 'should not be called']], 500),
        ]);
    }

    private function orchestrator(): AgentOrchestrator
    {
        return app(AgentOrchestrator::class);
    }

    private function conversation(): AiConversation
    {
        return AiConversation::create(['user_id' => $this->user->id, 'channel' => 'web', 'status' => 'active']);
    }

    private function promptsContaining(string $needle): array
    {
        return array_values(array_filter($this->anthropicPrompts, fn ($p) => str_contains($p['prompt'], $needle)));
    }

    // --- Intent classification -------------------------------------------------------------

    public function test_intent_classification_uses_the_orchestration_model_not_the_first_integration(): void
    {
        AiBrainSettings::getForUser($this->user->id)->update(['model_routing' => [
            'orchestration' => ['integration_id' => $this->anthropic->id, 'model' => 'claude-haiku-4-5'],
        ]]);

        $intent = $this->orchestrator()->classifyIntent('How do we handle returns?', $this->conversation(), $this->user);

        $this->assertSame('question', $intent['intent']);
        $calls = $this->promptsContaining("Classify the user's intent");
        $this->assertCount(1, $calls);
        $this->assertSame('claude-haiku-4-5', $calls[0]['model']);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'generativelanguage'));
        $this->assertDatabaseHas('ai_execution_logs', ['action' => 'classify_intent', 'model_used' => 'claude-haiku-4-5']);
    }

    public function test_without_a_routed_model_the_brain_model_classifies(): void
    {
        $this->orchestrator()->classifyIntent('Hello', $this->conversation(), $this->user);

        $this->assertSame('claude-opus-4-8', $this->promptsContaining("Classify the user's intent")[0]['model']);
    }

    public function test_every_registered_agent_can_be_chosen(): void
    {
        $this->orchestrator()->classifyIntent('Hello', $this->conversation(), $this->user);

        $prompt = $this->promptsContaining("Classify the user's intent")[0]['prompt'];
        $this->assertStringContainsString('revenue|funnel|deliverability|null', $prompt);
    }

    public function test_the_same_message_is_classified_once_per_request(): void
    {
        // The web chat asks once while deciding whether to stream and again in processMessage(),
        // on the orchestrator the controller holds for the request
        $conversation = $this->conversation();
        $orchestrator = $this->orchestrator();
        $orchestrator->classifyIntent('Hello', $conversation, $this->user);
        $orchestrator->classifyIntent('Hello', $conversation, $this->user);

        $this->assertCount(1, $this->promptsContaining("Classify the user's intent"));
    }

    public function test_an_unknown_agent_from_the_classifier_goes_to_the_conversation(): void
    {
        $this->classification = '{"requires_agent": true, "agent": "billing", "intent": "x", "task_type": "general"}';

        $intent = $this->orchestrator()->classifyIntent('Refund me', $this->conversation(), $this->user);

        $this->assertFalse($intent['requires_agent']);
    }

    public function test_json_wrapped_in_a_sentence_is_still_read(): void
    {
        $this->classification = 'Here you go: {"requires_agent": false, "intent": "greeting", "task_type": "general"} Hope it helps.';

        $intent = $this->orchestrator()->classifyIntent('Hi', $this->conversation(), $this->user);

        $this->assertSame('greeting', $intent['intent']);
    }

    public function test_a_failed_classification_keeps_questions_in_the_conversation(): void
    {
        $this->classification = 'not json at all';

        $intent = $this->orchestrator()->classifyIntent(
            'Kolejne pytanie: jak GAPLI obsługuje zwroty i reklamacje od klientów końcowych?',
            $this->conversation(),
            $this->user,
        );

        $this->assertFalse($intent['requires_agent']);
        $this->assertSame('general', $intent['task_type']);
    }

    public function test_a_failed_classification_gives_the_agent_the_users_own_words(): void
    {
        $this->classification = 'not json at all';
        $message = 'Przygotuj krótką kampanię e-mail tylko na listę "Brain TEST" (ID 47).';

        $intent = $this->orchestrator()->classifyIntent($message, $this->conversation(), $this->user);

        $this->assertTrue($intent['requires_agent']);
        $this->assertSame('campaign', $intent['agent']);
        $this->assertSame($message, $intent['intent']);
        $this->assertNotSame('keyword_match', $intent['intent']);
    }

    public function test_listopad_is_no_list(): void
    {
        $this->classification = 'not json at all';

        $intent = $this->orchestrator()->classifyIntent('Zaplanuj coś na listopad', $this->conversation(), $this->user);

        $this->assertNotSame('list', $intent['agent'] ?? null);
    }

    // --- Modes ---------------------------------------------------------------------------------

    public function test_an_advisory_agent_advises_with_the_users_request_even_in_semi_auto(): void
    {
        $settings = AiBrainSettings::getForUser($this->user->id);
        $settings->update(['work_mode' => 'semi_auto']);
        $settings->setAgentMode('crm', 'manual');
        $this->classification = '{"requires_agent": true, "agent": "crm", "intent": "follow-up plan", "task_type": "crm"}';
        $this->answer = 'Do it like this';

        $result = $this->orchestrator()->processMessage('Zaplanuj follow-up z leadem Jan Test', $this->user, 'web', null, true);

        $this->assertSame('advice', $result['type']);
        $this->assertSame(0, AiActionPlan::count());
        $advice = $this->promptsContaining('manual mode')[0]['prompt'];
        $this->assertStringContainsString('Zaplanuj follow-up z leadem Jan Test', $advice);
    }

    public function test_choosing_a_global_mode_applies_it_to_every_agent(): void
    {
        $settings = AiBrainSettings::getForUser($this->user->id);
        $settings->update(['work_mode' => 'semi_auto', 'agent_modes' => ['campaign' => 'autonomous', 'list' => 'autonomous']]);

        $this->actingAs($this->user)
            ->putJson(route('brain.api.settings.update'), ['work_mode' => 'manual'])
            ->assertOk();

        $settings->refresh();
        $this->assertSame('manual', $settings->work_mode);
        $this->assertNull($settings->agent_modes);
        $this->assertSame('manual', $settings->effectiveMode());
    }

    public function test_an_agent_mode_equal_to_the_global_one_is_no_override(): void
    {
        $settings = AiBrainSettings::getForUser($this->user->id);
        $settings->update(['work_mode' => 'semi_auto']);

        $this->actingAs($this->user)
            ->putJson(route('brain.api.settings.update'), ['agent_modes' => ['campaign' => 'semi_auto', 'list' => 'autonomous']])
            ->assertOk();

        $settings->refresh();
        $this->assertSame(['list' => 'autonomous'], $settings->agent_modes);
        $this->assertSame('mixed', $settings->effectiveMode());
    }

    public function test_the_chat_header_shows_the_mode_the_agents_work_in(): void
    {
        AiBrainSettings::getForUser($this->user->id)->update([
            'work_mode' => 'semi_auto',
            'agent_modes' => array_fill_keys(array_keys(AiBrainSettings::AGENT_TYPES), 'autonomous'),
        ]);

        $this->actingAs($this->user)->withoutVite()->get(route('brain.index'))
            ->assertInertia(fn ($page) => $page->where('settings.effective_mode', 'autonomous'));
    }

    public function test_saved_agent_modes_are_normalised(): void
    {
        $manual = User::factory()->create();
        $semi = User::factory()->create();
        AiBrainSettings::getForUser($manual->id)->update(['work_mode' => 'manual', 'agent_modes' => ['campaign' => 'autonomous']]);
        AiBrainSettings::getForUser($semi->id)->update(['work_mode' => 'semi_auto', 'agent_modes' => ['campaign' => 'semi_auto', 'list' => 'autonomous', 'crm' => 'bogus']]);

        (require database_path('migrations/2026_11_08_200000_normalize_brain_agent_modes.php'))->up();

        $this->assertNull(AiBrainSettings::getForUser($manual->id)->fresh()->agent_modes);
        $this->assertSame(['list' => 'autonomous'], AiBrainSettings::getForUser($semi->id)->fresh()->agent_modes);
    }

    // --- Plans, goals and logs ---------------------------------------------------------------

    public function test_a_request_the_agent_cannot_plan_is_logged_as_a_failure(): void
    {
        AiBrainSettings::getForUser($this->user->id)->update(['work_mode' => 'semi_auto']);
        $this->classification = '{"requires_agent": true, "agent": "crm", "intent": "plan", "task_type": "crm"}';
        $this->answer = 'no plan here';

        $result = $this->orchestrator()->processMessage('Utwórz zadanie w CRM', $this->user, 'web', null, true);

        $this->assertTrue($result['failed']);
        $this->assertDatabaseHas('ai_execution_logs', ['action' => 'process_message', 'agent_type' => 'crm', 'status' => 'error']);
        $this->assertDatabaseMissing('ai_execution_logs', ['action' => 'process_message', 'agent_type' => 'crm', 'status' => 'success']);
    }

    public function test_a_chat_request_is_not_counted_towards_an_unrelated_goal(): void
    {
        AiBrainSettings::getForUser($this->user->id)->update(['work_mode' => 'semi_auto']);
        $goal = AiGoal::create(['user_id' => $this->user->id, 'title' => 'Segmentation', 'status' => 'active', 'priority' => 'high']);
        $this->classification = '{"requires_agent": true, "agent": "crm", "intent": "task", "task_type": "crm"}';
        $this->answer = '{"title": "Task", "description": "d", "steps": [{"action_type": "create_task", "title": "t", "config": {}}]}';

        $this->orchestrator()->processMessage('Utwórz zadanie w CRM', $this->user, 'web', null, true);

        $plan = AiActionPlan::firstOrFail();
        $this->assertNull($plan->ai_goal_id);
        $this->assertSame(0, $goal->fresh()->plans()->count());
    }

    public function test_stalled_and_forgotten_plans_are_closed(): void
    {
        $plan = fn (array $attributes) => AiActionPlan::create(array_merge([
            'user_id' => $this->user->id, 'agent_type' => 'research', 'intent' => 'x', 'title' => 'x', 'plan_data' => [], 'work_mode' => 'semi_auto',
        ], $attributes));

        $stalled = $plan(['status' => 'executing', 'started_at' => now()->subHours(3)]);
        $running = $plan(['status' => 'executing', 'started_at' => now()->subMinutes(10)]);
        $approved = $plan(['status' => 'approved']);
        $draft = $plan(['status' => 'draft']);
        $waiting = $plan(['status' => 'pending_approval']);
        AiPendingApproval::create([
            'ai_action_plan_id' => $waiting->id, 'user_id' => $this->user->id, 'channel' => 'web',
            'status' => 'pending', 'summary' => 's', 'expires_at' => now()->addDay(),
        ]);
        AiActionPlan::whereIn('id', [$approved->id, $draft->id, $waiting->id])->update(['updated_at' => now()->subDays(8)]);

        // The Monitor no longer shows the stalled run as current work
        $monitor = $this->actingAs($this->user)->getJson(route('brain.api.monitor'))->assertOk();
        $this->assertSame($running->id, $monitor->json('current_task.plan_id'));

        $this->artisan('brain:close-stale-plans')->assertSuccessful();

        $this->assertSame('failed', $stalled->fresh()->status);
        $this->assertSame('stalled', $stalled->fresh()->execution_summary['error']);
        $this->assertSame('executing', $running->fresh()->status);
        $this->assertSame('failed', $approved->fresh()->status);
        $this->assertSame('cancelled', $draft->fresh()->status);
        $this->assertSame('pending_approval', $waiting->fresh()->status);
    }

    public function test_the_monitor_shows_the_latest_activity(): void
    {
        AiBrainSettings::getForUser($this->user->id)->update(['last_activity_at' => now()->subDays(96)]);
        AiExecutionLog::logSuccess($this->user->id, 'brain', 'conversation_title');

        $monitor = $this->actingAs($this->user)->getJson(route('brain.api.monitor'))->assertOk();

        $this->assertTrue(now()->parse($monitor->json('brain.last_activity_at'))->gt(now()->subMinute()));
    }

    // --- Research, conversations, dashboard --------------------------------------------------

    public function test_research_that_found_nothing_is_not_saved_to_the_knowledge_base(): void
    {
        $this->answer = 'NO_FINDINGS';
        $step = new AiActionPlanStep(['config' => ['_research_context' => 'Search returned no results.', 'title' => 'Trends']]);

        $result = (fn () => $this->executeSaveToKnowledge($step, func_get_arg(0)))->call(app(ResearchAgent::class), $this->user);

        $this->assertSame('completed', $result['status']);
        $this->assertSame(0, KnowledgeEntry::count());
    }

    public function test_an_agent_request_from_the_web_chat_opens_one_conversation(): void
    {
        AiBrainSettings::getForUser($this->user->id)->update(['work_mode' => 'manual']);
        $this->classification = '{"requires_agent": true, "agent": "crm", "intent": "x", "task_type": "crm"}';

        // The stream closes one output buffer before writing: give it one of its own
        ob_start();
        $this->actingAs($this->user)
            ->post(route('brain.api.chat.stream'), ['message' => 'Utwórz zadanie w CRM', 'force_new' => true])
            ->streamedContent();

        $this->assertSame(1, AiConversation::count());
        $this->assertSame(2, AiConversation::first()->messages()->count());
    }

    public function test_conversations_without_messages_are_not_listed(): void
    {
        $this->conversation();

        $this->actingAs($this->user)->getJson(route('brain.api.conversations'))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_the_dashboard_open_rate_counts_each_recipient_once(): void
    {
        $message = Message::create(['user_id' => $this->user->id, 'subject' => 'Hi', 'content' => '<p>Hi</p>', 'channel' => 'email', 'type' => 'broadcast', 'status' => 'sent']);
        $subscriber = Subscriber::create(['user_id' => $this->user->id, 'email' => 'reader@example.com']);
        DB::table('cron_job_logs')->insert(['job_name' => 'send', 'started_at' => now(), 'status' => 'success', 'emails_sent' => 2, 'created_at' => now(), 'updated_at' => now()]);
        foreach (range(1, 5) as $i) {
            DB::table('email_opens')->insert(['message_id' => $message->id, 'subscriber_id' => $subscriber->id, 'opened_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        }

        $stats = $this->actingAs($this->user)->getJson(route('api.dashboard.stats'))->assertOk();

        $this->assertEquals(50, $stats->json('open_rate.value'));
    }

    // --- Providers -----------------------------------------------------------------------------

    public function test_openai_reasoning_models_get_the_parameters_they_accept(): void
    {
        $openai = AiIntegration::create(['provider' => 'openai', 'name' => 'OpenAI', 'api_key' => 'sk-test', 'default_model' => 'gpt-5-mini', 'is_active' => true]);
        $sent = [];
        Http::fake(['api.openai.com/*' => function (Request $request) use (&$sent) {
            $sent[] = $request->data();

            return Http::response(['choices' => [['message' => ['content' => 'ok']]], 'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1]]);
        }]);
        $provider = app(AiService::class)->getProvider($openai);

        $provider->generateTextWithUsage('hi', 'gpt-5-mini', ['max_tokens' => 500, 'temperature' => 0.1]);
        $provider->generateTextWithUsage('hi', 'gpt-4o-mini', ['max_tokens' => 500, 'temperature' => 0.1]);
        $provider->generateTextWithUsage('hi', 'gpt-4o-mini');

        $this->assertSame(4096, $sent[0]['max_completion_tokens']);
        $this->assertArrayNotHasKey('max_tokens', $sent[0]);
        $this->assertArrayNotHasKey('temperature', $sent[0]);
        $this->assertSame(500, $sent[1]['max_tokens']);
        $this->assertSame(0.1, $sent[1]['temperature']);
        $this->assertArrayNotHasKey('max_tokens', $sent[2]);
    }

    public function test_gemini_thinking_does_not_eat_a_short_answer(): void
    {
        $sent = [];
        $this->geminiResponder = function (Request $request) use (&$sent) {
            $sent[] = $request->data()['generationConfig'];

            return Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'ok']]]]],
                'usageMetadata' => ['promptTokenCount' => 10, 'candidatesTokenCount' => 2, 'thoughtsTokenCount' => 30],
            ]);
        };
        $provider = app(AiService::class)->getProvider($this->gemini);

        $result = $provider->generateTextWithUsage('hi', 'gemini-2.5-flash', ['max_tokens' => 500]);
        $provider->generateTextWithUsage('hi', 'gemini-2.5-pro', ['max_tokens' => 500]);

        $this->assertSame(['thinkingBudget' => 0], $sent[0]['thinkingConfig']);
        $this->assertSame(500 + 8192, $sent[1]['maxOutputTokens']);
        $this->assertSame(32, $result['tokens_output']);
    }
}
