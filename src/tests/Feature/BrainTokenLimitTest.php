<?php

namespace Tests\Feature;

use App\Jobs\ContinueBrainGoalJob;
use App\Models\AiActionPlan;
use App\Models\AiActionPlanStep;
use App\Models\AiBrainSettings;
use App\Models\AiIntegration;
use App\Models\ContactList;
use App\Models\Message;
use App\Models\Subscriber;
use App\Models\User;
use App\Services\Brain\AgentOrchestrator;
use App\Services\Brain\GoalPlanner;
use App\Services\Brain\ModeController;
use App\Services\Brain\TaskTokenBudget;
use App\Services\Brain\TaskTokenLimitExceeded;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;
use Tests\Feature\Concerns\ActivatesGold;
use Tests\TestCase;

/**
 * Brain's token limit belongs to one task — a chat message, a plan run, a goal step, a cron
 * task — not to the day. The daily limit stopped every task once it was reached (an account
 * with many lists hits it by noon) and could not be changed anywhere in the panel. A task that
 * goes over its limit makes no further AI call and says why; other tasks keep working.
 */
class BrainTokenLimitTest extends TestCase
{
    use RefreshDatabase;
    use ActivatesGold;

    private User $user;

    private ContactList $list;

    /** Prompts sent to the fake Anthropic API, in order. */
    private array $anthropicPrompts = [];

    /** Tokens the fake Anthropic API reports for every answer. */
    private int $tokensIn = 10;

    private int $tokensOut = 5;

    protected function setUp(): void
    {
        parent::setUp();

        $this->activateGold();
        $this->user = User::factory()->create();

        $anthropic = AiIntegration::create([
            'provider' => 'anthropic', 'name' => 'Anthropic', 'api_key' => 'a-test',
            'default_model' => 'claude-sonnet-4-5', 'is_active' => true,
        ]);
        $this->settings()->update([
            'preferred_integration_id' => $anthropic->id,
            'preferred_model' => 'claude-opus-4-8',
        ]);

        $this->list = ContactList::create(['user_id' => $this->user->id, 'name' => 'Brain TEST', 'type' => 'email']);
        $subscriber = Subscriber::create([
            'user_id' => $this->user->id, 'email' => 'reader@example.com', 'status' => 'active', 'is_active_global' => true,
        ]);
        $subscriber->contactLists()->attach($this->list->id, ['status' => 'active', 'subscribed_at' => now()]);

        Http::fake([
            'api.anthropic.com/*' => function (Request $request) {
                $prompt = $request['messages'][0]['content'] ?? '';
                $prompt = is_string($prompt) ? $prompt : json_encode($prompt);
                $this->anthropicPrompts[] = $prompt;

                $text = match (true) {
                    str_contains($prompt, "Classify the user's intent") => '{"requires_agent": false, "intent": "question", "task_type": "general"}',
                    str_contains($prompt, 'HIGH-LEVEL GOAL') => '{"is_goal": false}',
                    str_contains($prompt, 'marketing message content') => '{"subject": "Webinar", "preview_text": "Join", "content": "<p>Join our webinar</p>"}',
                    default => 'Brain answer',
                };

                return Http::response([
                    'content' => [['type' => 'text', 'text' => $text]],
                    'usage' => ['input_tokens' => $this->tokensIn, 'output_tokens' => $this->tokensOut],
                ]);
            },
        ]);
    }

    private function settings(): AiBrainSettings
    {
        return AiBrainSettings::getForUser($this->user->id);
    }

    private function orchestrator(): AgentOrchestrator
    {
        return app(AgentOrchestrator::class);
    }

    private function limitMessage(string $used, string $limit): string
    {
        return __('brain.task_token_limit_reached', ['used' => $used, 'limit' => $limit]);
    }

    /** @param array<int, array{0: string, 1: array}> $steps action type and config */
    private function campaignPlan(array $steps, array $attributes = []): AiActionPlan
    {
        $plan = AiActionPlan::create(array_merge([
            'user_id' => $this->user->id, 'agent_type' => 'campaign', 'intent' => 'Test campaign',
            'title' => 'Test campaign', 'plan_data' => ['steps' => []], 'work_mode' => 'autonomous',
            'status' => 'approved', 'total_steps' => count($steps),
        ], $attributes));

        foreach ($steps as $i => [$action, $config]) {
            AiActionPlanStep::create([
                'ai_action_plan_id' => $plan->id, 'step_order' => $i + 1, 'action_type' => $action,
                'title' => $action, 'config' => $config,
            ]);
        }

        return $plan;
    }

    // --- No daily limit ----------------------------------------------------------------------

    public function test_a_day_with_many_tokens_used_does_not_stop_the_chat(): void
    {
        $this->settings()->update([
            'daily_token_limit' => 100000,
            'tokens_used_today' => 500000,
            'token_reset_date' => today()->toDateString(),
        ]);

        $answer = $this->actingAs($this->user)
            ->postJson(route('brain.api.chat'), ['message' => 'What is our refund policy?', 'force_new' => true])
            ->assertOk()
            ->json();

        $this->assertSame('Brain answer', $answer['message'] ?? null);
        $this->assertNotEmpty($this->anthropicPrompts);
    }

    public function test_todays_token_count_is_kept_for_the_statistics(): void
    {
        // A count left from an earlier day is not today's
        $this->settings()->update(['tokens_used_today' => 120000, 'token_reset_date' => today()->subDays(96)->toDateString()]);
        $this->assertSame(0, $this->settings()->fresh()->tokensUsedToday());

        // Charging on a new day starts the count again
        $settings = $this->settings()->fresh();
        $settings->addTokensUsed(300);
        $this->assertSame(300, $settings->fresh()->tokens_used_today);
        $this->assertTrue($settings->fresh()->token_reset_date->isToday());

        $settings->fresh()->addTokensUsed(200);
        $this->assertSame(500, $this->settings()->fresh()->tokensUsedToday());
    }

    // --- The task budget ---------------------------------------------------------------------

    public function test_a_task_makes_no_further_ai_call_once_it_used_its_limit(): void
    {
        $this->settings()->update(['task_token_limit' => 1000]);
        $settings = $this->settings()->fresh();

        $thrown = null;
        TaskTokenBudget::run($this->user, function () use ($settings, &$thrown) {
            $settings->addTokensUsed(600);
            TaskTokenBudget::ensureAvailable($this->user->id); // 600 of 1000: fine

            $settings->addTokensUsed(600);
            try {
                TaskTokenBudget::ensureAvailable($this->user->id);
            } catch (TaskTokenLimitExceeded $e) {
                $thrown = $e;
            }

            $this->assertTrue(TaskTokenBudget::exceeded());

            // Later calls of the same task are only marked as skipped
            try {
                TaskTokenBudget::ensureAvailable($this->user->id);
                $this->fail('A task over its limit made another AI call');
            } catch (TaskTokenLimitExceeded $e) {
                $this->assertSame(__('brain.task_token_limit_skipped'), $e->getMessage());
            }
        });

        $this->assertNotNull($thrown);
        $this->assertSame($this->limitMessage('1 200', '1 000'), $thrown->getMessage());

        // Outside the task nothing is counted or blocked
        $this->assertFalse(TaskTokenBudget::exceeded());
        $this->assertSame(0, TaskTokenBudget::used());
        TaskTokenBudget::ensureAvailable($this->user->id);
    }

    public function test_every_task_starts_with_its_own_budget_and_nested_work_counts_once(): void
    {
        $this->settings()->update(['task_token_limit' => 1000]);
        $settings = $this->settings()->fresh();

        // Ten tasks of 900 tokens each: 9000 tokens in a day, none of them is stopped
        for ($i = 0; $i < 10; $i++) {
            TaskTokenBudget::run($this->user, function () use ($settings) {
                $settings->addTokensUsed(900);
                TaskTokenBudget::ensureAvailable($this->user->id);
            });
        }

        // A plan run inside a chat message is part of the message's task
        $this->expectException(TaskTokenLimitExceeded::class);
        TaskTokenBudget::run($this->user, function () use ($settings) {
            $settings->addTokensUsed(600);
            TaskTokenBudget::run($this->user, function () use ($settings) {
                $settings->addTokensUsed(600);
                TaskTokenBudget::ensureAvailable($this->user->id);
            });
        });
    }

    public function test_zero_means_no_limit(): void
    {
        $this->settings()->update(['task_token_limit' => 0]);
        $settings = $this->settings()->fresh();
        $this->assertSame(0, $settings->taskTokenLimit());

        TaskTokenBudget::run($this->user, function () use ($settings) {
            $settings->addTokensUsed(5000000);
            TaskTokenBudget::ensureAvailable($this->user->id);
            $this->assertFalse(TaskTokenBudget::exceeded());
        });

        // No value set: the default limit
        $this->settings()->update(['task_token_limit' => null]);
        $this->assertSame(AiBrainSettings::DEFAULT_TASK_TOKEN_LIMIT, $this->settings()->fresh()->taskTokenLimit());
    }

    public function test_a_chat_message_over_its_limit_stops_and_says_why_and_the_next_message_works(): void
    {
        $this->settings()->update(['task_token_limit' => 1000]);
        $this->tokensIn = 800;
        $this->tokensOut = 400;

        $stopped = $this->actingAs($this->user)
            ->postJson(route('brain.api.chat'), ['message' => 'What is our refund policy?', 'force_new' => true])
            ->assertOk()
            ->json();

        // The first AI call used 1 200 tokens; the message made no other call
        $this->assertSame($this->limitMessage('1 200', '1 000'), $stopped['message'] ?? null);
        $this->assertCount(1, $this->anthropicPrompts);

        // The next message is a new task with a fresh budget
        $this->tokensIn = 10;
        $this->tokensOut = 5;
        $answer = $this->actingAs($this->user)
            ->postJson(route('brain.api.chat'), ['message' => 'What is our refund policy?', 'force_new' => true])
            ->assertOk()
            ->json();
        $this->assertSame('Brain answer', $answer['message'] ?? null);
    }

    public function test_a_plan_run_over_its_limit_stops_its_ai_steps_and_sends_nothing(): void
    {
        $this->settings()->update(['task_token_limit' => 1000]);
        $this->tokensIn = 800;
        $this->tokensOut = 400;

        $plan = $this->campaignPlan([
            ['generate_content', ['topic' => 'Webinar']],
            ['generate_content', ['topic' => 'Webinar, second take']],
            ['generate_content', ['topic' => 'Webinar, third take']],
            ['create_message', ['subject' => 'Webinar invite']],
            ['schedule_send', ['send_at' => 'immediate', 'list_ids' => [$this->list->id]]],
        ]);

        $result = $this->orchestrator()->executePlan($plan, $this->user);

        $limitMessage = $this->limitMessage('1 200', '1 000');
        $steps = $plan->steps()->orderBy('step_order')->get();
        // The first content step used 1 200 tokens; the next AI steps made no call. The draft is
        // saved from the content that was written, and the send is skipped
        $this->assertSame(['completed', 'failed', 'failed', 'completed', 'failed'], $steps->pluck('status')->all());
        $this->assertSame($limitMessage, $steps[1]->error_message);
        $this->assertSame(__('brain.task_token_limit_skipped'), $steps[2]->error_message);
        $this->assertSame(__('brain.step_skipped_after_failure'), $steps[4]->error_message);
        $this->assertCount(1, $this->anthropicPrompts);

        $draft = Message::where('subject', 'Webinar invite')->firstOrFail();
        $this->assertSame('draft', $draft->status);
        $this->assertSame(0, $draft->queueEntries()->count());
        $this->assertSame('failed', $plan->fresh()->status);
        $this->assertTrue($result['failed']);
        $this->assertSame(1, substr_count($result['message'], $limitMessage));
    }

    public function test_a_goal_step_stopped_by_its_limit_is_not_retried(): void
    {
        Queue::fake();
        $this->settings()->update(['task_token_limit' => 1000]);
        $this->tokensIn = 800;
        $this->tokensOut = 400;

        $goal = app(GoalPlanner::class)->createGoal($this->user, 'Webinar push');
        $plan = $this->campaignPlan([
            ['generate_content', ['topic' => 'Webinar']],
            ['generate_content', ['topic' => 'Webinar, second take']],
        ], ['ai_goal_id' => $goal->id]);

        $this->orchestrator()->executePlan($plan, $this->user);

        $this->assertSame('failed', $plan->fresh()->status);
        Queue::assertNotPushed(ContinueBrainGoalJob::class);
    }

    // --- Settings ----------------------------------------------------------------------------

    public function test_the_limit_per_task_is_set_in_the_brain_settings(): void
    {
        $this->actingAs($this->user)
            ->putJson('/brain/api/settings', ['task_token_limit' => 250000])
            ->assertOk();
        $this->assertSame(250000, $this->settings()->fresh()->taskTokenLimit());

        $this->actingAs($this->user)
            ->putJson('/brain/api/settings', ['task_token_limit' => 0])
            ->assertOk();
        $this->assertSame(0, $this->settings()->fresh()->taskTokenLimit());

        $this->actingAs($this->user)
            ->putJson('/brain/api/settings', ['task_token_limit' => 500])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['task_token_limit']);
        $this->assertSame(0, $this->settings()->fresh()->taskTokenLimit());

        $this->settings()->update(['task_token_limit' => 150000, 'tokens_used_today' => 4321, 'token_reset_date' => today()->toDateString()]);
        $this->withoutVite();
        $this->actingAs($this->user)
            ->get(route('brain.settings'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Brain/Settings')
                ->where('taskTokenLimit', 150000)
                ->where('tokensUsedToday', 4321));
    }

    // --- Wording -----------------------------------------------------------------------------

    public function test_the_approval_summary_has_no_doubled_bold_markers(): void
    {
        $plan = $this->campaignPlan([['select_audience', ['list_ids' => [$this->list->id]]]], ['status' => 'pending_approval']);

        $approval = app(ModeController::class)->requestApproval($plan, $this->user, 'web');

        $this->assertStringContainsString(__('brain.steps_to_execute'), $approval->summary);
        $this->assertStringNotContainsString('****', $approval->summary);
    }

    public function test_a_dry_run_of_an_immediate_send_says_now_without_a_time_preposition(): void
    {
        $settings = $this->settings();
        $settings->update(['preferences' => array_merge($settings->preferences ?? [], ['dry_run_mode' => true])]);

        $plan = $this->campaignPlan([
            ['generate_content', ['topic' => 'Webinar']],
            ['create_message', ['subject' => 'Webinar invite']],
            ['schedule_send', ['send_at' => 'immediate', 'list_ids' => [$this->list->id]]],
        ]);

        $this->orchestrator()->executePlan($plan, $this->user);

        $send = $plan->steps()->where('action_type', 'schedule_send')->first();
        $this->assertTrue($send->result['dry_run']);
        $this->assertSame(__('brain.campaign.dry_run_notice_now', ['recipients' => 1, 'lists' => 'Brain TEST']), $send->result['message']);
    }

    public function test_every_language_has_the_task_limit_texts(): void
    {
        foreach (['pl', 'en', 'de', 'es'] as $locale) {
            foreach (['brain.task_token_limit_reached', 'brain.task_token_limit_skipped', 'brain.task_token_limit_too_low', 'brain.campaign.dry_run_notice_now'] as $key) {
                $this->assertNotSame($key, __($key, [], $locale), "{$key} missing in {$locale}");
            }

            $json = json_decode(file_get_contents(resource_path("js/locales/{$locale}.json")), true);
            foreach (['title', 'description', 'label', 'save', 'hint', 'used_today', 'save_failed'] as $key) {
                $this->assertNotEmpty($json['brain']['task_limit'][$key] ?? null, "brain.task_limit.{$key} missing in {$locale}.json");
            }
            $this->assertNotEmpty($json['brain']['monitor']['task_token_limit'] ?? null);
            $this->assertNotEmpty($json['brain']['monitor']['no_task_token_limit'] ?? null);
        }
    }
}
