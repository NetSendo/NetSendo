<?php

namespace Tests\Feature;

use App\Events\CrmDealCreated;
use App\Events\CrmDealStageChanged;
use App\Events\CrmTaskCompleted;
use App\Events\CrmContactCreated;
use App\Events\CrmContactStatusChanged;
use App\Events\CrmScoreThresholdReached;
use App\Models\AutomationRule;
use App\Models\CrmContact;
use App\Models\CrmDeal;
use App\Models\CrmPipeline;
use App\Models\CrmStage;
use App\Models\CrmTask;
use App\Models\Subscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CrmAutomationTriggerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected CrmPipeline $pipeline;
    protected CrmStage $stage1;
    protected CrmStage $stage2;
    protected CrmStage $wonStage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        // Create pipeline with stages
        $this->pipeline = CrmPipeline::create([
            'user_id' => $this->user->id,
            'name' => 'Test Pipeline',
        ]);

        $this->stage1 = CrmStage::create([
            'crm_pipeline_id' => $this->pipeline->id,
            'name' => 'Lead',
            'order' => 1,
        ]);

        $this->stage2 = CrmStage::create([
            'crm_pipeline_id' => $this->pipeline->id,
            'name' => 'Negotiation',
            'order' => 2,
        ]);

        $this->wonStage = CrmStage::create([
            'crm_pipeline_id' => $this->pipeline->id,
            'name' => 'Won',
            'order' => 3,
            'is_won' => true,
        ]);
    }

    /** @test */
    public function it_triggers_automation_on_deal_stage_changed()
    {
        Event::fake([CrmDealStageChanged::class]);

        // Create automation rule
        $rule = AutomationRule::create([
            'user_id' => $this->user->id,
            'name' => 'Test Deal Stage Changed',
            'trigger_event' => 'crm_deal_stage_changed',
            'trigger_config' => [
                'pipeline_id' => $this->pipeline->id,
            ],
            'actions' => [
                ['type' => 'notify_admin', 'config' => ['email' => 'test@example.com', 'subject' => 'Deal moved', 'message' => 'Test']],
            ],
            'is_active' => true,
        ]);

        // Create deal and move it
        $deal = CrmDeal::create([
            'user_id' => $this->user->id,
            'crm_pipeline_id' => $this->pipeline->id,
            'crm_stage_id' => $this->stage1->id,
            'name' => 'Test Deal',
            'value' => 1000,
            'status' => 'open',
        ]);

        $deal->moveToStage($this->stage2);

        Event::assertDispatched(CrmDealStageChanged::class, function ($event) use ($deal) {
            return $event->deal->id === $deal->id
                && $event->oldStage->id === $this->stage1->id
                && $event->newStage->id === $this->stage2->id;
        });
    }

    /** @test */
    public function it_triggers_automation_on_deal_won()
    {
        Event::fake([CrmDealStageChanged::class]);

        $rule = AutomationRule::create([
            'user_id' => $this->user->id,
            'name' => 'Test Deal Won',
            'trigger_event' => 'crm_deal_won',
            'trigger_config' => [],
            'actions' => [
                ['type' => 'crm_create_task', 'config' => ['title' => 'Onboarding', 'task_type' => 'follow_up']],
            ],
            'is_active' => true,
        ]);

        $deal = CrmDeal::create([
            'user_id' => $this->user->id,
            'crm_pipeline_id' => $this->pipeline->id,
            'crm_stage_id' => $this->stage1->id,
            'name' => 'Winning Deal',
            'value' => 5000,
            'status' => 'open',
        ]);

        $deal->moveToStage($this->wonStage);

        Event::assertDispatched(CrmDealStageChanged::class, function ($event) {
            return $event->newStage->is_won === true;
        });

        $this->assertEquals('won', $deal->fresh()->status);
    }

    /** @test */
    public function it_triggers_automation_on_deal_created()
    {
        Event::fake([CrmDealCreated::class]);

        $rule = AutomationRule::create([
            'user_id' => $this->user->id,
            'name' => 'Test Deal Created',
            'trigger_event' => 'crm_deal_created',
            'trigger_config' => [
                'pipeline_id' => $this->pipeline->id,
                'deal_value_min' => 1000,
            ],
            'actions' => [
                ['type' => 'crm_assign_owner', 'config' => ['owner_id' => $this->user->id]],
            ],
            'is_active' => true,
        ]);

        $deal = CrmDeal::create([
            'user_id' => $this->user->id,
            'crm_pipeline_id' => $this->pipeline->id,
            'crm_stage_id' => $this->stage1->id,
            'name' => 'New Big Deal',
            'value' => 2000,
            'status' => 'open',
        ]);

        Event::assertDispatched(CrmDealCreated::class, function ($event) use ($deal) {
            return $event->deal->id === $deal->id
                && $event->deal->value == 2000;
        });
    }

    /** @test */
    public function it_triggers_automation_on_task_completed()
    {
        Event::fake([CrmTaskCompleted::class]);

        $rule = AutomationRule::create([
            'user_id' => $this->user->id,
            'name' => 'Test Task Completed',
            'trigger_event' => 'crm_task_completed',
            'trigger_config' => [
                'task_type' => 'follow_up',
            ],
            'actions' => [
                ['type' => 'crm_update_score', 'config' => ['score_delta' => 10]],
            ],
            'is_active' => true,
        ]);

        $task = CrmTask::create([
            'user_id' => $this->user->id,
            'owner_id' => $this->user->id,
            'title' => 'Follow up call',
            'type' => 'follow_up',
            'priority' => 'high',
            'status' => 'pending',
        ]);

        $this->actingAs($this->user);
        $task->complete();

        Event::assertDispatched(CrmTaskCompleted::class, function ($event) use ($task) {
            return $event->task->id === $task->id
                && $event->task->status === 'completed';
        });
    }

    /** @test */
    public function it_triggers_automation_on_contact_created()
    {
        Event::fake([CrmContactCreated::class]);

        $rule = AutomationRule::create([
            'user_id' => $this->user->id,
            'name' => 'Test Contact Created',
            'trigger_event' => 'crm_contact_created',
            'trigger_config' => [],
            'actions' => [
                ['type' => 'crm_update_score', 'config' => ['score_delta' => 5]],
            ],
            'is_active' => true,
        ]);

        $subscriber = Subscriber::factory()->create(['user_id' => $this->user->id]);

        $contact = CrmContact::create([
            'user_id' => $this->user->id,
            'subscriber_id' => $subscriber->id,
            'status' => 'lead',
            'source' => 'form',
            'score' => 0,
        ]);

        Event::assertDispatched(CrmContactCreated::class, function ($event) use ($contact) {
            return $event->contact->id === $contact->id;
        });
    }

    /** @test */
    public function it_filters_triggers_by_pipeline()
    {
        $otherPipeline = CrmPipeline::create([
            'user_id' => $this->user->id,
            'name' => 'Other Pipeline',
        ]);

        $otherStage = CrmStage::create([
            'crm_pipeline_id' => $otherPipeline->id,
            'name' => 'Other Stage',
            'order' => 1,
        ]);

        // Rule only for main pipeline
        $rule = AutomationRule::create([
            'user_id' => $this->user->id,
            'name' => 'Test Pipeline Filter',
            'trigger_event' => 'crm_deal_created',
            'trigger_config' => [
                'pipeline_id' => $this->pipeline->id,
            ],
            'actions' => [
                ['type' => 'notify_admin', 'config' => ['email' => 'test@example.com']],
            ],
            'is_active' => true,
        ]);

        // Deal in different pipeline should NOT trigger
        $deal = CrmDeal::create([
            'user_id' => $this->user->id,
            'crm_pipeline_id' => $otherPipeline->id,
            'crm_stage_id' => $otherStage->id,
            'name' => 'Other Pipeline Deal',
            'value' => 1000,
            'status' => 'open',
        ]);

        $context = [
            'user_id' => $this->user->id,
            'pipeline_id' => $otherPipeline->id,
        ];

        // Verify that trigger config matching would fail
        $service = app(\App\Services\Automation\AutomationService::class);
        $matchingRules = $service->findActiveRulesForTrigger('crm_deal_created', $context);

        $this->assertCount(0, $matchingRules);
    }

    /** @test */
    public function it_filters_triggers_by_deal_value()
    {
        $rule = AutomationRule::create([
            'user_id' => $this->user->id,
            'name' => 'Test Value Filter',
            'trigger_event' => 'crm_deal_created',
            'trigger_config' => [
                'deal_value_min' => 5000,
            ],
            'actions' => [
                ['type' => 'notify_admin', 'config' => ['email' => 'test@example.com']],
            ],
            'is_active' => true,
        ]);

        // Low value context should NOT match
        $lowValueContext = [
            'user_id' => $this->user->id,
            'deal_value' => 1000,
        ];

        $service = app(\App\Services\Automation\AutomationService::class);
        $matchingRules = $service->findActiveRulesForTrigger('crm_deal_created', $lowValueContext);

        $this->assertCount(0, $matchingRules);

        // High value context SHOULD match
        $highValueContext = [
            'user_id' => $this->user->id,
            'deal_value' => 10000,
        ];

        $matchingRules = $service->findActiveRulesForTrigger('crm_deal_created', $highValueContext);

        $this->assertCount(1, $matchingRules);
    }

    /** @test */
    public function crm_actions_execute_correctly()
    {
        $subscriber = Subscriber::factory()->create(['user_id' => $this->user->id]);

        $contact = CrmContact::create([
            'user_id' => $this->user->id,
            'subscriber_id' => $subscriber->id,
            'status' => 'lead',
            'score' => 10,
        ]);

        $executor = app(\App\Services\Automation\AutomationActionExecutor::class);

        // Test crm_update_score action
        $result = $executor->execute(
            ['type' => 'crm_update_score', 'config' => ['score_delta' => 15]],
            $subscriber,
            ['user_id' => $this->user->id, 'contact_id' => $contact->id]
        );

        $this->assertEquals(10, $result['old_score']);
        $this->assertEquals(25, $result['new_score']);

        // Test crm_create_task action
        $result = $executor->execute(
            ['type' => 'crm_create_task', 'config' => ['title' => 'Test Task', 'task_type' => 'call', 'priority' => 'high']],
            $subscriber,
            ['user_id' => $this->user->id, 'contact_id' => $contact->id]
        );

        $this->assertTrue($result['created']);
        $this->assertDatabaseHas('crm_tasks', [
            'title' => 'Test Task',
            'type' => 'call',
            'priority' => 'high',
        ]);
    }
}
