<?php

namespace Tests\Feature;

use App\Jobs\ContinueBrainGoalJob;
use App\Jobs\ExecuteBrainPlanJob;
use App\Models\AiActionPlan;
use App\Models\AiActionPlanStep;
use App\Models\AiBrainSettings;
use App\Models\AiConversation;
use App\Models\AiGoal;
use App\Models\AiIntegration;
use App\Models\AiPendingApproval;
use App\Models\ContactList;
use App\Models\Message;
use App\Models\Subscriber;
use App\Models\User;
use App\Services\Brain\AgentOrchestrator;
use App\Services\Brain\GoalPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Concerns\ActivatesGold;
use Tests\TestCase;

/**
 * Fixes from the second live review of Brain (all three modes on a GOLD instance): a send step
 * mailed the message whose database ID matched a step number, a failed content step left a
 * placeholder message to send, failed steps counted as done, goals from the chat stopped after
 * their first step and the chat could not approve its own plans.
 */
class BrainPlanRunFixesTest extends TestCase
{
    use RefreshDatabase;
    use ActivatesGold;

    private User $user;

    private AiIntegration $anthropic;

    private ContactList $list;

    /** Prompts sent to the fake Anthropic API, in order. */
    private array $anthropicPrompts = [];

    private string $classification = '{"requires_agent": false, "intent": "question", "task_type": "general"}';

    private string $goalDetection = '{"is_goal": false}';

    private string $decomposition = '{"plans": []}';

    private string $analyticsPlan = '{"title": "Stats", "steps": [{"action_type": "fetch_campaign_stats", "title": "Fetch stats", "config": {"days": 30}}]}';

    private string $content = '{"subject": "Webinar", "preview_text": "Join", "content": "<p>Join our webinar</p>"}';

    private string $answer = 'Brain answer';

    /** Answers the fake Gemini API (null: Gemini must not be called). */
    private ?\Closure $geminiResponder = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->activateGold();
        $this->user = User::factory()->create();

        $this->anthropic = AiIntegration::create([
            'provider' => 'anthropic', 'name' => 'Anthropic', 'api_key' => 'a-test',
            'default_model' => 'claude-sonnet-4-5', 'is_active' => true,
        ]);

        AiBrainSettings::getForUser($this->user->id)->update([
            'preferred_integration_id' => $this->anthropic->id,
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
                $this->anthropicPrompts[] = ['model' => $request['model'] ?? null, 'prompt' => $prompt];

                $text = match (true) {
                    str_contains($prompt, "Classify the user's intent") => $this->classification,
                    str_contains($prompt, 'HIGH-LEVEL GOAL') => $this->goalDetection,
                    str_contains($prompt, 'Decompose this goal') => $this->decomposition,
                    str_contains($prompt, 'You are a marketing analytics expert. The user wants') => $this->analyticsPlan,
                    str_contains($prompt, 'marketing message content') => $this->content,
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

    private function settings(): AiBrainSettings
    {
        return AiBrainSettings::getForUser($this->user->id);
    }

    private function dryRun(bool $on = true): void
    {
        $settings = $this->settings();
        $settings->update(['preferences' => array_merge($settings->preferences ?? [], ['dry_run_mode' => $on])]);
    }

    private function conversation(): AiConversation
    {
        return AiConversation::create(['user_id' => $this->user->id, 'channel' => 'web', 'status' => 'active']);
    }

    /** @param array<int, array{0: string, 1: array}> $steps action type and config */
    private function campaignPlan(array $steps): AiActionPlan
    {
        $plan = AiActionPlan::create([
            'user_id' => $this->user->id, 'agent_type' => 'campaign', 'intent' => 'Test campaign',
            'title' => 'Test campaign', 'plan_data' => ['steps' => []], 'work_mode' => 'autonomous',
            'status' => 'approved', 'total_steps' => count($steps),
        ]);

        foreach ($steps as $i => [$action, $config]) {
            AiActionPlanStep::create([
                'ai_action_plan_id' => $plan->id, 'step_order' => $i + 1, 'action_type' => $action,
                'title' => $action, 'config' => $config,
            ]);
        }

        return $plan;
    }

    private function otherMessage(string $status = 'sent'): Message
    {
        $other = ContactList::create(['user_id' => $this->user->id, 'name' => 'Customers', 'type' => 'email']);
        $message = Message::create([
            'user_id' => $this->user->id, 'subject' => 'Old newsletter', 'content' => '<p>Old</p>',
            'channel' => 'email', 'type' => 'broadcast', 'status' => $status,
        ]);
        $message->contactLists()->sync([$other->id]);

        return $message;
    }

    private function promptsContaining(string $needle): array
    {
        return array_values(array_filter($this->anthropicPrompts, fn ($p) => str_contains($p['prompt'], $needle)));
    }

    // --- Sending --------------------------------------------------------------------------

    public function test_a_send_uses_the_message_its_plan_created_not_the_message_with_the_step_number(): void
    {
        $this->dryRun();
        $older = $this->otherMessage();

        // The planner wrote the number of the create_message step into message_id, and an older
        // message happens to have that ID
        $plan = $this->campaignPlan([
            ['select_audience', ['list_ids' => [$this->list->id]]],
            ['generate_content', ['topic' => 'Webinar']],
            ['create_message', ['subject' => 'Webinar invite']],
            ['schedule_send', ['send_at' => 'immediate', 'list_ids' => [$this->list->id], 'message_id' => $older->id]],
        ]);

        $result = $this->orchestrator()->executePlan($plan, $this->user);

        $created = Message::where('subject', 'Webinar invite')->firstOrFail();
        $send = $plan->steps()->where('action_type', 'schedule_send')->first();
        $this->assertSame('completed', $send->status);
        $this->assertSame($created->id, $send->result['message_id']);
        $this->assertTrue($send->result['dry_run']);
        $this->assertSame(1, $send->result['recipients']);
        $this->assertArrayNotHasKey('failed', $result);

        // The older message keeps its audience, type and status
        $older->refresh();
        $this->assertSame('sent', $older->status);
        $this->assertSame(['Customers'], $older->contactLists()->pluck('name')->all());
        $this->assertSame('completed', $plan->fresh()->status);
    }

    public function test_a_real_send_queues_the_plans_own_message_for_its_list(): void
    {
        $older = $this->otherMessage();

        $plan = $this->campaignPlan([
            ['generate_content', ['topic' => 'Webinar']],
            ['create_message', ['subject' => 'Webinar invite']],
            ['schedule_send', ['send_at' => 'immediate', 'list_ids' => [$this->list->id], 'message_id' => $older->id]],
        ]);

        $this->orchestrator()->executePlan($plan, $this->user);

        $created = Message::where('subject', 'Webinar invite')->firstOrFail();
        $this->assertSame('completed', $plan->steps()->where('step_order', 3)->first()->status);
        $this->assertSame('scheduled', $created->status);
        $this->assertSame([$this->list->id], $created->contactLists()->pluck('contact_lists.id')->all());
        // Sent now or moved into the allowed send window, it is this message that goes out
        $this->assertSame($created->id, $plan->steps()->where('step_order', 3)->first()->result['message_id']);
        $this->assertSame(1, $plan->steps()->where('step_order', 3)->first()->result['recipients']);

        $older->refresh();
        $this->assertSame('sent', $older->status);
        $this->assertSame(['Customers'], $older->contactLists()->pluck('name')->all());
        $this->assertSame(0, $older->queueEntries()->count());
    }

    public function test_a_dry_run_changes_nothing_on_a_message_it_was_given(): void
    {
        $this->dryRun();
        $draft = $this->otherMessage('draft');
        $draft->update(['type' => 'autoresponder_copy']);

        $plan = $this->campaignPlan([
            ['schedule_send', ['send_at' => 'immediate', 'list_ids' => [$this->list->id], 'message_id' => $draft->id]],
        ]);

        $this->orchestrator()->executePlan($plan, $this->user);

        $send = $plan->steps()->first();
        $this->assertSame('completed', $send->status);
        $this->assertTrue($send->result['dry_run']);
        $draft->refresh();
        $this->assertSame('autoresponder_copy', $draft->type);
        $this->assertSame('draft', $draft->status);
        $this->assertSame(['Customers'], $draft->contactLists()->pluck('name')->all());
    }

    public function test_a_failed_content_step_saves_no_placeholder_and_sends_nothing(): void
    {
        $this->content = 'Sorry, I cannot help with that.';
        $messagesBefore = Message::count();

        $plan = $this->campaignPlan([
            ['select_audience', ['list_ids' => [$this->list->id]]],
            ['generate_content', ['topic' => 'Webinar']],
            ['create_message', ['subject' => 'Webinar invite']],
            ['schedule_send', ['send_at' => 'immediate', 'list_ids' => [$this->list->id], 'message_id' => 3]],
        ]);

        $result = $this->orchestrator()->executePlan($plan, $this->user);

        $this->assertSame($messagesBefore, Message::count());
        $this->assertSame(0, Message::where('content', '<p>Message content</p>')->count());
        $statuses = $plan->steps()->orderBy('step_order')->pluck('status')->all();
        $this->assertSame(['completed', 'failed', 'failed', 'failed'], $statuses);
        $this->assertSame(__('brain.step_skipped_after_failure'), $plan->steps()->where('step_order', 4)->first()->error_message);
        $this->assertSame('failed', $plan->fresh()->status);
        $this->assertTrue($result['failed']);
        $this->assertStringContainsString(__('brain.campaign.steps_done', ['done' => 1, 'total' => 4]), $result['message']);
    }

    public function test_a_step_that_reports_failure_fails_and_the_plan_fails(): void
    {
        // A send with no message in the plan and none given: "no message to send"
        $plan = $this->campaignPlan([
            ['schedule_send', ['send_at' => 'immediate', 'list_ids' => [$this->list->id]]],
        ]);

        $result = $this->orchestrator()->executePlan($plan, $this->user);

        $step = $plan->steps()->first();
        $this->assertSame('failed', $step->status);
        $this->assertSame(__('brain.campaign.ab_no_message'), $step->error_message);
        $this->assertSame('failed', $plan->fresh()->status);
        $this->assertSame(1, $plan->fresh()->failed_steps);
        $this->assertSame(0, $plan->fresh()->completed_steps);
        $this->assertTrue($result['failed']);
        $this->assertDatabaseHas('ai_execution_logs', ['action' => 'execute_step:schedule_send', 'status' => 'error']);
    }

    public function test_a_task_model_that_fails_is_retried_on_the_brain_model(): void
    {
        $gemini = AiIntegration::create([
            'provider' => 'gemini', 'name' => 'Google Gemini', 'api_key' => 'g-test',
            'default_model' => 'gemini-2.5-flash', 'is_active' => true,
        ]);
        $this->settings()->update(['model_routing' => [
            'content_generation' => ['integration_id' => $gemini->id, 'model' => 'gemini-2.5-pro'],
        ]]);
        $this->geminiResponder = fn () => Http::response(['error' => ['message' => 'You exceeded your current quota', 'code' => 429]], 429);

        $plan = $this->campaignPlan([
            ['generate_content', ['topic' => 'Webinar']],
            ['create_message', ['subject' => 'Webinar invite']],
        ]);

        $this->orchestrator()->executePlan($plan, $this->user);

        $this->assertSame(['completed', 'completed'], $plan->steps()->orderBy('step_order')->pluck('status')->all());
        $calls = $this->promptsContaining('marketing message content');
        $this->assertCount(1, $calls);
        $this->assertSame('claude-opus-4-8', $calls[0]['model']);
        $this->assertSame('<p>Join our webinar</p>', Message::where('subject', 'Webinar invite')->value('content'));
    }

    // --- Modes and goals ------------------------------------------------------------------

    public function test_advisory_mode_opens_no_goal_and_records_the_advice_tokens_once(): void
    {
        $this->settings()->setWorkMode('manual');
        $this->classification = '{"requires_agent": true, "agent": "campaign", "intent": "Campaign", "task_type": "campaign", "parameters": {"subject": "Webinar"}}';

        $result = $this->orchestrator()->processMessage('Prepare a webinar campaign for the Brain TEST list and send it tomorrow at 10', $this->user, 'web', null, true);

        $this->assertSame([], $this->promptsContaining('HIGH-LEVEL GOAL'));
        $this->assertSame(0, AiGoal::count());
        $this->assertSame(0, AiActionPlan::count());
        $this->assertSame('Brain answer', $result['message']);

        $answer = AiConversation::find($result['conversation_id'])->messages()->where('role', 'assistant')->first();
        $this->assertSame(10, $answer->tokens_input);
        $this->assertSame(5, $answer->tokens_output);
        // Every call is charged once (15 tokens each), the advice is not charged a second time
        $this->assertLessThanOrEqual(15 * count($this->anthropicPrompts), $this->settings()->fresh()->tokensUsedToday());
    }

    private function semiAutoGoal(): array
    {
        $this->settings()->setWorkMode('semi_auto');
        $this->goalDetection = '{"is_goal": true, "title": "Webinar push", "description": "Fill the webinar", "priority": "high", "confidence": 0.9}';
        $this->decomposition = json_encode(['plans' => [
            ['order' => 1, 'agent' => 'analytics', 'title' => 'Collect stats', 'intent' => 'Collect campaign stats', 'depends_on' => null],
            ['order' => 2, 'agent' => 'analytics', 'title' => 'Write report', 'intent' => 'Write the webinar report', 'depends_on' => [1]],
        ]]);

        $result = $this->orchestrator()->processMessage('Fill our webinar over the next two weeks', $this->user, 'web', null, true);

        return [$result, AiGoal::firstOrFail()];
    }

    public function test_a_goal_from_the_chat_starts_its_first_step_for_approval(): void
    {
        [$result, $goal] = $this->semiAutoGoal();

        $plan = AiActionPlan::firstOrFail();
        $this->assertSame('pending_approval', $plan->status);
        $this->assertSame($goal->id, $plan->ai_goal_id);
        $this->assertSame(1, $plan->plan_data['goal_step']);
        $this->assertSame($result['conversation_id'], $plan->ai_conversation_id);
        $this->assertSame(1, AiPendingApproval::where('ai_action_plan_id', $plan->id)->count());

        // The chat gets the plan with its buttons, and a mode name instead of a translation key
        $this->assertSame('approval_request', $result['type']);
        $this->assertSame($plan->id, $result['plan']['id']);
        $this->assertSame('fetch_campaign_stats', $result['plan']['steps'][0]['action']);
        $this->assertStringNotContainsString('brain.mode.', $result['message']);

        // Two planned steps, none done: the goal is open at 0%
        $goal->refresh();
        $this->assertSame('active', $goal->status);
        $this->assertSame(2, $goal->total_plans);
        $this->assertSame(0, $goal->progress_percent);
    }

    public function test_approving_a_goal_step_runs_it_answers_in_the_chat_and_queues_the_next_step(): void
    {
        [$result, $goal] = $this->semiAutoGoal();
        $plan = AiActionPlan::firstOrFail();
        Queue::fake();

        $this->actingAs($this->user)
            ->postJson(route('brain.api.plans.approve', $plan->id), ['approved' => true])
            ->assertOk();
        Queue::assertPushed(ExecuteBrainPlanJob::class);

        (new ExecuteBrainPlanJob($plan->id, $this->user->id))->handle($this->orchestrator());

        $this->assertSame('completed', $plan->fresh()->status);
        $goal->refresh();
        $this->assertSame('active', $goal->status);
        $this->assertSame(50, $goal->progress_percent);
        Queue::assertPushed(ContinueBrainGoalJob::class, fn ($job) => $job->goalId === $goal->id);

        $posted = AiConversation::find($result['conversation_id'])->messages()->where('role', 'assistant')->latest('id')->first();
        $this->assertSame($plan->id, $posted->metadata['plan_id']);
    }

    public function test_the_next_goal_step_waits_for_approval_and_the_chat_shows_its_buttons(): void
    {
        [$result, $goal] = $this->semiAutoGoal();
        $first = AiActionPlan::firstOrFail();
        Queue::fake();
        app(\App\Services\Brain\ModeController::class)->processApproval($first->pendingApproval->id, true);
        (new ExecuteBrainPlanJob($first->id, $this->user->id))->handle($this->orchestrator());

        (new ContinueBrainGoalJob($goal->id, $this->user->id))->handle($this->orchestrator(), app(GoalPlanner::class));

        $second = AiActionPlan::where('id', '!=', $first->id)->firstOrFail();
        $this->assertSame('pending_approval', $second->status);
        $this->assertSame(2, $second->plan_data['goal_step']);

        // Running the job again opens nothing new while step 2 waits
        (new ContinueBrainGoalJob($goal->id, $this->user->id))->handle($this->orchestrator(), app(GoalPlanner::class));
        $this->assertSame(2, AiActionPlan::count());

        $messages = $this->actingAs($this->user)
            ->getJson(route('brain.api.conversations.show', $result['conversation_id']))
            ->assertOk()
            ->json('messages');
        $withButtons = array_values(array_filter($messages, fn ($m) => !empty($m['plan'])));
        $this->assertCount(1, $withButtons);
        $this->assertSame($second->id, $withButtons[0]['plan']['id']);
    }

    public function test_goal_progress_counts_planned_steps_and_dependencies_are_step_numbers(): void
    {
        $goal = AiGoal::create([
            'user_id' => $this->user->id, 'title' => 'Three steps', 'status' => 'active', 'priority' => 'high',
            'context' => ['decomposition' => [
                ['order' => 1, 'agent' => 'analytics', 'title' => 'One', 'intent' => 'First intent'],
                ['order' => 2, 'agent' => 'analytics', 'title' => 'Two', 'intent' => 'Second intent', 'depends_on' => [1]],
                ['order' => 3, 'agent' => 'analytics', 'title' => 'Three', 'intent' => 'Third intent', 'depends_on' => 2],
            ]],
        ]);
        $planner = app(GoalPlanner::class);

        $this->assertSame(1, $planner->getNextAction($goal)['order']);

        $plan = AiActionPlan::create([
            'user_id' => $this->user->id, 'ai_goal_id' => $goal->id, 'agent_type' => 'analytics',
            'intent' => 'Something worded differently', 'title' => 'One', 'plan_data' => ['steps' => [], 'goal_step' => 1],
            'work_mode' => 'semi_auto', 'status' => 'pending_approval', 'total_steps' => 1,
        ]);
        $this->assertNull($planner->getNextAction($goal->fresh()), 'nothing new while a step waits');

        $plan->markCompleted();
        $goal->refresh();
        $this->assertSame('active', $goal->status);
        $this->assertSame(3, $goal->total_plans);
        $this->assertSame(1, $goal->completed_plans);
        $this->assertSame(33, $goal->progress_percent);
        $this->assertSame(2, $planner->getNextAction($goal)['order']);
    }

    public function test_old_untouched_goals_are_left_out_of_the_answers(): void
    {
        AiGoal::create(['user_id' => $this->user->id, 'title' => 'Fresh goal', 'status' => 'active', 'priority' => 'high']);
        $old = AiGoal::create(['user_id' => $this->user->id, 'title' => 'July hot leads', 'status' => 'active', 'priority' => 'high']);
        AiGoal::where('id', $old->id)->update(['updated_at' => now()->subDays(60)]);

        $context = app(GoalPlanner::class)->getActiveGoalsContext($this->user);

        $this->assertStringContainsString('Fresh goal', $context);
        $this->assertStringNotContainsString('July hot leads', $context);
    }

    // --- Chat routing and questions ------------------------------------------------------

    public function test_a_campaign_request_with_a_subject_and_a_list_is_not_asked_again(): void
    {
        $agent = $this->orchestrator()->getAgents()['campaign'];

        $this->assertFalse($agent->needsMoreInfo([
            'intent' => 'Create a campaign',
            'parameters' => ['list_name' => 'Brain TEST', 'subject' => 'Test Brain'],
        ], $this->user));
        $this->assertTrue($agent->needsMoreInfo(['intent' => 'Create a campaign', 'parameters' => []], $this->user));

        $questions = $agent->getInfoQuestions(['intent' => 'Create a campaign', 'parameters' => ['list_name' => 'Brain TEST']], $this->user);
        $this->assertStringNotContainsString('Brain TEST (', $questions);
    }

    public function test_a_question_about_the_own_offer_is_answered_in_the_conversation(): void
    {
        $this->classification = '{"requires_agent": true, "agent": "research", "intent": "Plans and prices", "task_type": "research"}';

        $question = $this->orchestrator()->classifyIntent('What plans do we have and what does the cheapest cost?', $this->conversation(), $this->user);
        $research = $this->orchestrator()->classifyIntent('Research what our competitors charge for dropshipping plans', $this->conversation(), $this->user);

        $this->assertFalse($question['requires_agent']);
        $this->assertTrue($research['requires_agent']);
        $this->assertSame('research', $research['agent']);
    }

    // --- Monitor and reports ---------------------------------------------------------------

    public function test_the_plans_today_card_counts_today_only(): void
    {
        $make = fn (string $status) => AiActionPlan::create([
            'user_id' => $this->user->id, 'agent_type' => 'analytics', 'intent' => 'x', 'title' => 'x',
            'plan_data' => ['steps' => []], 'work_mode' => 'semi_auto', 'status' => $status, 'total_steps' => 0,
        ]);
        $old = $make('completed');
        AiActionPlan::where('id', $old->id)->update(['created_at' => now()->subDays(3)]);
        $make('failed');

        $stats = $this->actingAs($this->user)->getJson(route('brain.api.monitor'))->assertOk()->json('plan_stats');

        $this->assertSame(1, $stats['today']);
        $this->assertSame(0, $stats['completed_today']);
        $this->assertSame(1, $stats['failed_today']);
        $this->assertSame(1, $stats['completed']);
    }

    public function test_the_campaign_comparison_stays_inside_the_report_period(): void
    {
        $sent = fn (string $subject, int $daysAgo) => Message::create([
            'user_id' => $this->user->id, 'subject' => $subject, 'content' => '<p>x</p>', 'channel' => 'email',
            'type' => 'broadcast', 'status' => 'sent', 'scheduled_at' => now()->subDays($daysAgo),
        ]);
        $sent('August campaign', 70);
        $sent('November campaign', 3);

        $plan = AiActionPlan::create([
            'user_id' => $this->user->id, 'agent_type' => 'analytics', 'intent' => 'Report', 'title' => 'Report',
            'plan_data' => ['steps' => []], 'work_mode' => 'autonomous', 'status' => 'approved', 'total_steps' => 1,
        ]);
        AiActionPlanStep::create([
            'ai_action_plan_id' => $plan->id, 'step_order' => 1, 'action_type' => 'compare_performance',
            'title' => 'Compare', 'config' => ['days' => 30],
        ]);

        $this->orchestrator()->executePlan($plan, $this->user);

        $report = $plan->steps()->first()->result['message'];
        $this->assertStringContainsString('November campaign', $report);
        $this->assertStringNotContainsString('August campaign', $report);
    }

    public function test_every_language_names_the_research_model_task(): void
    {
        foreach (['pl', 'en', 'de', 'es'] as $locale) {
            $strings = json_decode(file_get_contents(resource_path("js/locales/{$locale}.json")), true);
            $this->assertNotEmpty($strings['brain']['task']['research'] ?? null, $locale);
            $this->assertNotEmpty($strings['brain']['monitor']['events']['plan_execution_finished'] ?? null, $locale);
        }
    }
}
