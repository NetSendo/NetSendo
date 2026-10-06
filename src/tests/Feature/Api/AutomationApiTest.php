<?php

namespace Tests\Feature\Api;

use App\Events\SubscriberSignedUp;
use App\Models\ApiKey;
use App\Models\AutomationRule;
use App\Models\AutomationRuleLog;
use App\Models\ContactList;
use App\Models\CrmPipeline;
use App\Models\CrmStage;
use App\Models\CustomField;
use App\Models\Message;
use App\Models\Subscriber;
use App\Models\Tag;
use App\Models\User;
use App\Services\Automation\AutomationActionExecutor;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Automation rules over /api/v1/automations (what the MCP client uses): the
 * catalogue an agent builds rules from, validation that mirrors the builder
 * (including ids of the account only), and rules that actually run.
 */
class AutomationApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $other;
    private string $key;
    private ContactList $list;
    private Tag $tag;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Mail::fake();

        $this->user = User::factory()->create();
        $this->other = User::factory()->create();
        $this->key = ApiKey::generate($this->user->id, 'Automations', ['funnels:read', 'funnels:write'])['key'];

        $this->list = $this->makeList($this->user, 'Newsletter');
        $this->tag = Tag::create(['user_id' => $this->user->id, 'name' => 'welcomed']);
    }

    private function api(string $method, string $uri, array $payload = [], ?string $key = null): TestResponse
    {
        return $this->withHeaders(['Authorization' => 'Bearer ' . ($key ?? $this->key)])
            ->json($method, "/api/v1/{$uri}", $payload);
    }

    private function makeList(User $user, string $name): ContactList
    {
        return ContactList::create(['user_id' => $user->id, 'name' => $name, 'type' => 'email']);
    }

    private function makeSubscriber(User $user, ContactList $list, string $email): Subscriber
    {
        $subscriber = Subscriber::create([
            'user_id' => $user->id,
            'email' => $email,
            'status' => 'active',
            'is_active_global' => true,
        ]);
        $subscriber->contactLists()->attach($list->id, ['status' => 'active', 'subscribed_at' => now()]);

        return $subscriber;
    }

    private function makeRule(User $user, array $attributes = []): AutomationRule
    {
        return AutomationRule::create($attributes + [
            'user_id' => $user->id,
            'name' => 'Rule',
            'trigger_event' => 'subscriber_signup',
            'trigger_config' => [],
            'conditions' => [],
            'condition_logic' => 'all',
            'actions' => [['type' => 'add_tag', 'config' => ['tag_name' => 'x']]],
            'is_active' => true,
        ]);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Tag new readers',
            'trigger_event' => 'subscriber_signup',
            'trigger_config' => ['list_id' => (string) $this->list->id],
            'actions' => [['type' => 'add_tag', 'config' => ['tag_id' => $this->tag->id]]],
        ], $overrides);
    }

    public function test_options_describe_triggers_conditions_and_actions(): void
    {
        $data = $this->api('GET', 'automations/options')->assertOk()->json('data');

        $triggers = collect($data['triggers'])->keyBy('key');
        $this->assertSame(array_keys(AutomationRule::TRIGGER_EVENTS), $triggers->keys()->all());
        $this->assertSame('list', $triggers['subscriber_signup']['config']['list_id']['ref']);
        $this->assertTrue($triggers['subscriber_signup']['available']);
        $this->assertFalse($triggers['subscriber_activated']['available']);
        $this->assertTrue($triggers['date_reached']['config']['date']['required']);

        $actions = collect($data['actions'])->keyBy('key');
        $this->assertSame(array_keys(AutomationRule::ACTION_TYPES), $actions->keys()->all());
        $this->assertSame(['tag_id', 'tag_name'], $actions['add_tag']['one_of_required']);
        $this->assertSame('message', $actions['send_email']['config']['message_id']['ref']);
        $this->assertSame(['high', 'medium', 'low'], $actions['crm_create_task']['config']['priority']['values']);

        $conditions = collect($data['conditions'])->keyBy('key');
        $this->assertSame(array_keys(AutomationRule::CONDITION_TYPES), $conditions->keys()->all());
        $this->assertSame('field', $conditions['field_equals']['field_spec']['ref']);
        $this->assertNull($conditions['field_is_empty']['value_spec']);
        $this->assertFalse($conditions['crm_score_above']['available']);

        $this->assertSame('subscriber_signup', $data['example']['trigger_event']);
        $this->assertContains('ever', $data['limit_periods']);
    }

    public function test_a_rule_is_created_and_shown(): void
    {
        $created = $this->api('POST', 'automations', $this->validPayload([
            'conditions' => [['type' => 'field_contains', 'field' => 'email', 'value' => '@acme.com']],
            'limit_per_subscriber' => true,
            'limit_count' => 1,
            'limit_period' => 'ever',
        ]))->assertCreated()->json('data');

        $this->assertSame($this->list->id, $created['trigger_config']['list_id']);
        $this->assertSame('all', $created['condition_logic']);
        $this->assertTrue($created['is_active']);
        $this->assertFalse($created['read_only']);

        $rule = AutomationRule::findOrFail($created['id']);
        $this->assertSame($this->user->id, $rule->user_id);
        $this->assertSame([['type' => 'add_tag', 'config' => ['tag_id' => $this->tag->id]]], $rule->actions);

        $this->api('GET', "automations/{$rule->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Tag new readers')
            ->assertJsonPath('data.stats_7d.total_executions', 0);

        $this->api('GET', 'automations?trigger_event=subscriber_signup&is_active=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $rule->id);
        $this->api('GET', 'automations?is_active=0')->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_validation_mirrors_the_builder_and_the_engine(): void
    {
        $this->api('POST', 'automations', ['trigger_event' => 'subscriber_signup'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'actions']);

        $this->api('POST', 'automations', $this->validPayload(['trigger_event' => 'nope']))
            ->assertStatus(422)->assertJsonValidationErrors(['trigger_event']);

        $this->api('POST', 'automations', $this->validPayload(['trigger_event' => 'subscriber_activated']))
            ->assertStatus(422)->assertJsonValidationErrors(['trigger_event']);

        $this->api('POST', 'automations', $this->validPayload(['trigger_config' => ['tag_id' => $this->tag->id]]))
            ->assertStatus(422)->assertJsonValidationErrors(['trigger_config.tag_id']);

        $this->api('POST', 'automations', $this->validPayload(['actions' => [['type' => 'explode', 'config' => []]]]))
            ->assertStatus(422)->assertJsonValidationErrors(['actions.0.type']);

        $this->api('POST', 'automations', $this->validPayload(['actions' => [['type' => 'add_tag', 'config' => []]]]))
            ->assertStatus(422)->assertJsonValidationErrors(['actions.0.config']);

        $this->api('POST', 'automations', $this->validPayload(['actions' => [['type' => 'send_email', 'config' => []]]]))
            ->assertStatus(422)->assertJsonValidationErrors(['actions.0.config.message_id']);

        $this->api('POST', 'automations', $this->validPayload(['actions' => [['type' => 'call_webhook', 'config' => ['url' => 'ftp://x']]]]))
            ->assertStatus(422)->assertJsonValidationErrors(['actions.0.config.url']);

        $this->api('POST', 'automations', $this->validPayload(['conditions' => [['type' => 'field_equals', 'field' => 'shoe_size', 'value' => '42']]]))
            ->assertStatus(422)->assertJsonValidationErrors(['conditions.0.field']);

        $this->api('POST', 'automations', $this->validPayload(['conditions' => [['type' => 'crm_score_above', 'value' => 5]]]))
            ->assertStatus(422)->assertJsonValidationErrors(['conditions.0.type']);

        $this->api('POST', 'automations', $this->validPayload(['limit_period' => 'year']))
            ->assertStatus(422)->assertJsonValidationErrors(['limit_period']);

        $this->assertSame(0, AutomationRule::count());
    }

    public function test_referenced_ids_must_belong_to_the_account(): void
    {
        $foreignList = $this->makeList($this->other, 'Theirs');
        $foreignTag = Tag::create(['user_id' => $this->other->id, 'name' => 'theirs']);
        $draft = Message::create([
            'user_id' => $this->user->id, 'channel' => 'email', 'type' => 'broadcast',
            'subject' => 'Draft', 'content' => '<p>x</p>', 'status' => 'draft',
        ]);

        $this->api('POST', 'automations', $this->validPayload(['trigger_config' => ['list_id' => $foreignList->id]]))
            ->assertStatus(422)->assertJsonValidationErrors(['trigger_config.list_id']);

        $this->api('POST', 'automations', $this->validPayload(['actions' => [['type' => 'add_tag', 'config' => ['tag_id' => $foreignTag->id]]]]))
            ->assertStatus(422)->assertJsonValidationErrors(['actions.0.config.tag_id']);

        $this->api('POST', 'automations', $this->validPayload(['actions' => [['type' => 'copy_to_list', 'config' => ['list_id' => $foreignList->id]]]]))
            ->assertStatus(422)->assertJsonValidationErrors(['actions.0.config.list_id']);

        $this->api('POST', 'automations', $this->validPayload(['conditions' => [['type' => 'tag_exists', 'value' => $foreignTag->id]]]))
            ->assertStatus(422)->assertJsonValidationErrors(['conditions.0.value']);

        $response = $this->api('POST', 'automations', $this->validPayload(['actions' => [['type' => 'send_email', 'config' => ['message_id' => $draft->id]]]]))
            ->assertStatus(422);
        $this->assertStringContainsString('draft', $response->json('errors')['actions.0.config.message_id'][0]);

        $this->api('POST', 'automations', $this->validPayload(['actions' => [['type' => 'crm_assign_owner', 'config' => ['owner_id' => $this->other->id]]]]))
            ->assertStatus(422)->assertJsonValidationErrors(['actions.0.config.owner_id']);

        $this->assertSame(0, AutomationRule::count());
    }

    public function test_crm_triggers_and_actions_check_pipelines_and_stages(): void
    {
        // trigger_event is an enum widened by MySQL-only migrations
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('automation_rules', fn (Blueprint $table) => $table->string('trigger_event')->change());
        }

        $mine = CrmPipeline::create(['user_id' => $this->user->id, 'name' => 'Sales']);
        $stage = CrmStage::create(['crm_pipeline_id' => $mine->id, 'name' => 'Won', 'order' => 1]);
        $theirs = CrmPipeline::create(['user_id' => $this->other->id, 'name' => 'Theirs']);
        $theirStage = CrmStage::create(['crm_pipeline_id' => $theirs->id, 'name' => 'Lead', 'order' => 1]);

        $payload = fn (int $pipelineId, int $stageId) => [
            'name' => 'Big deals',
            'trigger_event' => 'crm_deal_created',
            'trigger_config' => ['pipeline_id' => $pipelineId, 'deal_value_min' => 5000],
            'actions' => [
                ['type' => 'crm_move_deal', 'config' => ['stage_id' => $stageId]],
                ['type' => 'crm_create_task', 'config' => ['title' => 'Call {{first_name}}', 'priority' => 'high', 'due_days' => 1]],
            ],
        ];

        $this->api('POST', 'automations', $payload($theirs->id, $theirStage->id))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['trigger_config.pipeline_id', 'actions.0.config.stage_id']);

        $this->api('POST', 'automations', $payload($mine->id, $stage->id))
            ->assertCreated()
            ->assertJsonPath('data.trigger_config.pipeline_id', $mine->id)
            ->assertJsonPath('data.actions.1.config.priority', 'high');
    }

    public function test_partial_update_keeps_unsent_fields(): void
    {
        $id = $this->api('POST', 'automations', $this->validPayload())->assertCreated()->json('data.id');

        $this->api('PATCH', "automations/{$id}", ['name' => 'Renamed', 'condition_logic' => 'any'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed')
            ->assertJsonPath('data.condition_logic', 'any')
            ->assertJsonPath('data.trigger_config.list_id', $this->list->id)
            ->assertJsonPath('data.actions.0.config.tag_id', $this->tag->id);

        // A new trigger_event is checked against the stored trigger_config
        $this->api('PUT', "automations/{$id}", ['trigger_event' => 'tag_added'])
            ->assertStatus(422)->assertJsonValidationErrors(['trigger_config.list_id']);

        $this->api('PUT', "automations/{$id}", ['trigger_event' => 'tag_added', 'trigger_config' => ['tag_id' => $this->tag->id]])
            ->assertOk()
            ->assertJsonPath('data.trigger_event', 'tag_added')
            ->assertJsonPath('data.trigger_config', ['tag_id' => $this->tag->id]);
    }

    public function test_other_accounts_rules_are_not_found(): void
    {
        $theirs = $this->makeRule($this->other);

        $this->api('GET', "automations/{$theirs->id}")->assertNotFound();
        $this->api('PUT', "automations/{$theirs->id}", ['name' => 'Mine now'])->assertNotFound();
        $this->api('POST', "automations/{$theirs->id}/toggle")->assertNotFound();
        $this->api('POST', "automations/{$theirs->id}/duplicate")->assertNotFound();
        $this->api('GET', "automations/{$theirs->id}/logs")->assertNotFound();
        $this->api('DELETE', "automations/{$theirs->id}")->assertNotFound();
        $this->api('GET', 'automations')->assertOk()->assertJsonPath('meta.total', 0);

        $this->assertSame('Rule', $theirs->fresh()->name);
        $this->assertTrue($theirs->fresh()->is_active);
    }

    public function test_toggle_duplicate_and_delete(): void
    {
        $rule = $this->makeRule($this->user);

        $this->api('POST', "automations/{$rule->id}/toggle")->assertOk()->assertJsonPath('data.is_active', false);
        $this->api('POST', "automations/{$rule->id}/toggle")->assertOk()->assertJsonPath('data.is_active', true);
        $this->api('POST', "automations/{$rule->id}/toggle", ['is_active' => true])->assertOk()->assertJsonPath('data.is_active', true);
        $this->api('POST', "automations/{$rule->id}/toggle", ['is_active' => false])->assertOk()->assertJsonPath('data.is_active', false);

        $copy = $this->api('POST', "automations/{$rule->id}/duplicate")->assertCreated()->json('data');
        $this->assertNotSame($rule->id, $copy['id']);
        $this->assertSame('[KOPIA] Rule', $copy['name']);
        $this->assertFalse($copy['is_active']);

        $this->api('DELETE', "automations/{$rule->id}")->assertOk()->assertJsonPath('data.deleted', true);
        $this->assertNull($rule->fresh());
    }

    public function test_message_managed_rules_are_read_only_and_system_rules_need_confirmation(): void
    {
        $managed = $this->makeRule($this->user, ['trigger_source' => 'message', 'trigger_source_id' => 99, 'name' => 'Auto: Welcome']);

        $this->api('GET', "automations/{$managed->id}")
            ->assertOk()
            ->assertJsonPath('data.read_only', true)
            ->assertJsonPath('data.managed_by.type', 'message')
            ->assertJsonPath('data.managed_by.id', 99);

        $this->api('PUT', "automations/{$managed->id}", ['name' => 'Changed'])->assertStatus(409);
        $this->api('POST', "automations/{$managed->id}/toggle")->assertStatus(409);
        $this->api('DELETE', "automations/{$managed->id}")->assertStatus(409);
        $this->assertSame('Auto: Welcome', $managed->fresh()->name);

        // The copy is an ordinary, editable rule
        $copy = $this->api('POST', "automations/{$managed->id}/duplicate")->assertCreated()->json('data');
        $this->assertFalse($copy['read_only']);
        $this->assertNull(AutomationRule::find($copy['id'])->trigger_source);

        $system = $this->makeRule($this->user, ['is_system' => true, 'system_key' => 'welcome_sequence']);
        $this->api('POST', "automations/{$system->id}/duplicate")->assertCreated()->assertJsonPath('data.is_system', false);
        $this->api('DELETE', "automations/{$system->id}")->assertStatus(409);
        $this->api('DELETE', "automations/{$system->id}", ['confirm' => true])->assertOk();
        $this->assertNull($system->fresh());
    }

    public function test_logs_are_listed_newest_first(): void
    {
        $rule = $this->makeRule($this->user);
        $subscriber = $this->makeSubscriber($this->user, $this->list, 'reader@example.com');

        AutomationRuleLog::create([
            'automation_rule_id' => $rule->id, 'subscriber_id' => $subscriber->id, 'trigger_event' => 'subscriber_signup',
            'trigger_data' => ['list_id' => $this->list->id], 'actions_executed' => [['type' => 'add_tag', 'status' => 'success']],
            'status' => 'success', 'execution_time_ms' => 5, 'executed_at' => now()->subHour(),
        ]);
        AutomationRuleLog::logFailure($rule, $subscriber, 'subscriber_signup', [], [], 'Boom', 3);

        $response = $this->api('GET', "automations/{$rule->id}/logs")->assertOk();
        $response->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.totals_by_status.success', 1)
            ->assertJsonPath('meta.totals_by_status.failed', 1)
            ->assertJsonPath('data.0.status', 'failed')
            ->assertJsonPath('data.0.error_message', 'Boom')
            ->assertJsonPath('data.1.subscriber.email', 'reader@example.com')
            ->assertJsonPath('data.1.actions_executed.0.type', 'add_tag');

        $this->api('GET', "automations/{$rule->id}/logs?status=success")->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_permissions(): void
    {
        $rule = $this->makeRule($this->user);
        $readOnly = ApiKey::generate($this->user->id, 'Read', ['funnels:read'])['key'];
        $noFunnels = ApiKey::generate($this->user->id, 'Lists', ['lists:read'])['key'];

        $this->api('GET', 'automations', [], $readOnly)->assertOk();
        $this->api('GET', 'automations/options', [], $readOnly)->assertOk();
        $this->api('GET', "automations/{$rule->id}/logs", [], $readOnly)->assertOk();
        $this->api('POST', 'automations', $this->validPayload(), $readOnly)->assertForbidden();
        $this->api('PUT', "automations/{$rule->id}", ['name' => 'x'], $readOnly)->assertForbidden();
        $this->api('POST', "automations/{$rule->id}/toggle", [], $readOnly)->assertForbidden();
        $this->api('POST', "automations/{$rule->id}/duplicate", [], $readOnly)->assertForbidden();
        $this->api('DELETE', "automations/{$rule->id}", [], $readOnly)->assertForbidden();

        $this->api('GET', 'automations', [], $noFunnels)->assertForbidden();
        $this->api('GET', 'automations/options', [], $noFunnels)->assertForbidden();
        $this->api('GET', "automations/{$rule->id}", [], $noFunnels)->assertForbidden();

        $this->assertTrue($rule->fresh()->is_active);
    }

    public function test_a_rule_created_over_the_api_runs_on_signup(): void
    {
        $this->api('POST', 'automations', $this->validPayload())->assertCreated();

        $subscriber = $this->makeSubscriber($this->user, $this->list, 'new@example.com');
        event(new SubscriberSignedUp($subscriber, $this->list, null, 'api'));

        $this->assertSame(['welcomed'], $subscriber->fresh()->tags()->pluck('name')->all());
        $this->assertDatabaseHas('automation_rule_logs', ['subscriber_id' => $subscriber->id, 'status' => 'success']);

        // Another list of the account does not match the rule's list_id
        $other = $this->makeList($this->user, 'Other');
        $second = $this->makeSubscriber($this->user, $other, 'second@example.com');
        event(new SubscriberSignedUp($second, $other, null, 'api'));
        $this->assertSame([], $second->fresh()->tags()->pluck('name')->all());
    }

    /**
     * A rule without trigger_config used to match every account's events (the
     * ownership check came after "no config means match all").
     */
    public function test_a_rule_without_trigger_config_only_runs_for_its_own_account(): void
    {
        $theirTag = Tag::create(['user_id' => $this->other->id, 'name' => 'theirs']);
        $signupRule = $this->makeRule($this->other, ['actions' => [['type' => 'add_tag', 'config' => ['tag_id' => $theirTag->id]]]]);
        $tagRule = $this->makeRule($this->other, ['trigger_event' => 'tag_added', 'actions' => [['type' => 'add_tag', 'config' => ['tag_id' => $theirTag->id]]]]);
        $mine = $this->makeRule($this->user, ['actions' => [['type' => 'add_tag', 'config' => ['tag_id' => $this->tag->id]]]]);

        $subscriber = $this->makeSubscriber($this->user, $this->list, 'mine@example.com');
        event(new SubscriberSignedUp($subscriber, $this->list, null, 'form'));

        // My rule ran, which also fired tag_added (a context without user_id)
        $this->assertSame(['welcomed'], $subscriber->fresh()->tags()->pluck('name')->all());
        $this->assertSame(1, $mine->logs()->count());
        $this->assertSame(0, $signupRule->logs()->count());
        $this->assertSame(0, $tagRule->logs()->count());
    }

    public function test_update_field_action_writes_a_custom_field_of_the_account(): void
    {
        CustomField::create(['user_id' => $this->other->id, 'name' => 'company', 'label' => 'Company', 'type' => 'text', 'scope' => 'global']);
        CustomField::create(['user_id' => $this->user->id, 'name' => 'company', 'label' => 'Company', 'type' => 'text', 'scope' => 'global']);
        $subscriber = $this->makeSubscriber($this->user, $this->list, 'field@example.com');

        app(AutomationActionExecutor::class)->execute(['type' => 'update_field', 'config' => ['field' => 'company', 'value' => 'ACME']], $subscriber, []);

        $this->assertSame('ACME', $subscriber->fresh()->getCustomFieldValue('company'));
        $this->assertSame(1, $subscriber->fieldValues()->count());
    }

    public function test_the_web_builder_still_saves_rules_with_the_shared_rules(): void
    {
        $this->withoutVite();

        $this->actingAs($this->user)
            ->post(route('automations.store'), $this->validPayload(['name' => 'From web']))
            ->assertRedirect(route('automations.index'));
        $this->assertDatabaseHas('automation_rules', ['user_id' => $this->user->id, 'name' => 'From web']);

        $this->actingAs($this->user)
            ->post(route('automations.store'), ['name' => 'Broken'])
            ->assertSessionHasErrors(['trigger_event', 'actions']);
    }
}
