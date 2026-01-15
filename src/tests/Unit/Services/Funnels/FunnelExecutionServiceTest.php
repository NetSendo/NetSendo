<?php

namespace Tests\Unit\Services\Funnels;

use Tests\TestCase;
use App\Models\User;
use App\Models\Funnel;
use App\Models\FunnelStep;
use App\Models\FunnelSubscriber;
use App\Models\Subscriber;
use App\Models\ContactList;
use App\Services\Funnels\FunnelExecutionService;
use App\Services\Funnels\ABTestService;
use App\Services\Funnels\WebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

class FunnelExecutionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected FunnelExecutionService $service;
    protected User $user;
    protected ContactList $list;
    protected Funnel $funnel;
    protected Subscriber $subscriber;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(FunnelExecutionService::class);

        $this->user = User::factory()->create();
        $this->list = ContactList::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'email',
        ]);

        $this->funnel = Funnel::factory()->create([
            'user_id' => $this->user->id,
            'trigger_list_id' => $this->list->id,
            'status' => Funnel::STATUS_ACTIVE,
        ]);

        $this->subscriber = Subscriber::factory()->create([
            'user_id' => $this->user->id,
            'email' => 'test@example.com',
        ]);
    }

    /** @test */
    public function test_can_enroll_subscriber_in_active_funnel()
    {
        // Create start step
        $startStep = FunnelStep::factory()->create([
            'funnel_id' => $this->funnel->id,
            'type' => FunnelStep::TYPE_START,
            'name' => 'Start',
        ]);

        $this->funnel->update(['start_step_id' => $startStep->id]);

        $enrollment = $this->service->enrollSubscriber($this->funnel, $this->subscriber);

        $this->assertNotNull($enrollment);
        $this->assertEquals(FunnelSubscriber::STATUS_ACTIVE, $enrollment->status);
        $this->assertEquals($this->funnel->id, $enrollment->funnel_id);
        $this->assertEquals($this->subscriber->id, $enrollment->subscriber_id);
    }

    /** @test */
    public function test_cannot_enroll_subscriber_in_inactive_funnel()
    {
        $this->funnel->update(['status' => Funnel::STATUS_DRAFT]);

        $enrollment = $this->service->enrollSubscriber($this->funnel, $this->subscriber);

        $this->assertNull($enrollment);
    }

    /** @test */
    public function test_delay_step_schedules_next_action()
    {
        // Create steps: Start -> Delay -> End
        $startStep = FunnelStep::factory()->create([
            'funnel_id' => $this->funnel->id,
            'type' => FunnelStep::TYPE_START,
            'name' => 'Start',
        ]);

        $delayStep = FunnelStep::factory()->create([
            'funnel_id' => $this->funnel->id,
            'type' => FunnelStep::TYPE_DELAY,
            'name' => 'Wait 1 hour',
            'delay_value' => 1,
            'delay_unit' => 'hours',
        ]);

        $endStep = FunnelStep::factory()->create([
            'funnel_id' => $this->funnel->id,
            'type' => FunnelStep::TYPE_END,
            'name' => 'End',
        ]);

        // Connect steps
        $startStep->update(['next_step_id' => $delayStep->id]);
        $delayStep->update(['next_step_id' => $endStep->id]);
        $this->funnel->update(['start_step_id' => $startStep->id]);

        // Enroll subscriber
        $enrollment = $this->service->enrollSubscriber($this->funnel, $this->subscriber);

        // Refresh enrollment
        $enrollment->refresh();

        // Should be waiting and have next_action_at scheduled
        $this->assertEquals(FunnelSubscriber::STATUS_WAITING, $enrollment->status);
        $this->assertNotNull($enrollment->next_action_at);
        $this->assertTrue($enrollment->next_action_at->isFuture());
    }

    /** @test */
    public function test_end_step_marks_enrollment_as_completed()
    {
        // Create simple funnel: Start -> End
        $startStep = FunnelStep::factory()->create([
            'funnel_id' => $this->funnel->id,
            'type' => FunnelStep::TYPE_START,
            'name' => 'Start',
        ]);

        $endStep = FunnelStep::factory()->create([
            'funnel_id' => $this->funnel->id,
            'type' => FunnelStep::TYPE_END,
            'name' => 'End',
        ]);

        $startStep->update(['next_step_id' => $endStep->id]);
        $this->funnel->update(['start_step_id' => $startStep->id]);

        // Enroll subscriber
        $enrollment = $this->service->enrollSubscriber($this->funnel, $this->subscriber);

        // Refresh enrollment
        $enrollment->refresh();

        // Should be completed
        $this->assertEquals(FunnelSubscriber::STATUS_COMPLETED, $enrollment->status);
        $this->assertNotNull($enrollment->completed_at);
    }

    /** @test */
    public function test_condition_step_branches_correctly()
    {
        // Create condition step with yes/no branches
        $startStep = FunnelStep::factory()->create([
            'funnel_id' => $this->funnel->id,
            'type' => FunnelStep::TYPE_START,
        ]);

        $conditionStep = FunnelStep::factory()->create([
            'funnel_id' => $this->funnel->id,
            'type' => FunnelStep::TYPE_CONDITION,
            'condition_type' => FunnelStep::CONDITION_TAG_EXISTS,
            'condition_config' => ['tag' => 'VIP'],
        ]);

        $yesEndStep = FunnelStep::factory()->create([
            'funnel_id' => $this->funnel->id,
            'type' => FunnelStep::TYPE_END,
            'name' => 'VIP End',
        ]);

        $noEndStep = FunnelStep::factory()->create([
            'funnel_id' => $this->funnel->id,
            'type' => FunnelStep::TYPE_END,
            'name' => 'Regular End',
        ]);

        $startStep->update(['next_step_id' => $conditionStep->id]);
        $conditionStep->update([
            'next_step_id_yes' => $yesEndStep->id,
            'next_step_id_no' => $noEndStep->id,
        ]);
        $this->funnel->update(['start_step_id' => $startStep->id]);

        // Subscriber without tag - should go to NO branch
        $enrollment = $this->service->enrollSubscriber($this->funnel, $this->subscriber);
        $enrollment->refresh();

        $this->assertEquals(FunnelSubscriber::STATUS_COMPLETED, $enrollment->status);

        // Check history - condition was evaluated as false
        $history = $enrollment->history;
        $conditionEntry = collect($history)->firstWhere('event', 'condition_evaluated');
        $this->assertNotNull($conditionEntry);
        $this->assertFalse($conditionEntry['data']['result']);
    }

    /** @test */
    public function test_goal_step_records_conversion()
    {
        // Create funnel with goal step
        $startStep = FunnelStep::factory()->create([
            'funnel_id' => $this->funnel->id,
            'type' => FunnelStep::TYPE_START,
        ]);

        $goalStep = FunnelStep::factory()->create([
            'funnel_id' => $this->funnel->id,
            'type' => FunnelStep::TYPE_GOAL,
            'goal_name' => 'Purchase',
            'goal_type' => FunnelStep::GOAL_PURCHASE,
            'goal_value' => 99.99,
        ]);

        $endStep = FunnelStep::factory()->create([
            'funnel_id' => $this->funnel->id,
            'type' => FunnelStep::TYPE_END,
        ]);

        $startStep->update(['next_step_id' => $goalStep->id]);
        $goalStep->update(['next_step_id' => $endStep->id]);
        $this->funnel->update(['start_step_id' => $startStep->id]);

        // Enroll subscriber
        $this->service->enrollSubscriber($this->funnel, $this->subscriber);

        // Check goal conversion was recorded
        $this->assertDatabaseHas('funnel_goal_conversions', [
            'funnel_id' => $this->funnel->id,
            'subscriber_id' => $this->subscriber->id,
            'goal_type' => FunnelStep::GOAL_PURCHASE,
        ]);
    }

    /** @test */
    public function test_action_step_executes_add_tag()
    {
        // Create tag
        $tag = \App\Models\Tag::create([
            'user_id' => $this->user->id,
            'name' => 'Funnel Tag',
        ]);

        // Create funnel with action step
        $startStep = FunnelStep::factory()->create([
            'funnel_id' => $this->funnel->id,
            'type' => FunnelStep::TYPE_START,
        ]);

        $actionStep = FunnelStep::factory()->create([
            'funnel_id' => $this->funnel->id,
            'type' => FunnelStep::TYPE_ACTION,
            'action_type' => FunnelStep::ACTION_ADD_TAG,
            'action_config' => ['tag' => $tag->name],
        ]);

        $endStep = FunnelStep::factory()->create([
            'funnel_id' => $this->funnel->id,
            'type' => FunnelStep::TYPE_END,
        ]);

        $startStep->update(['next_step_id' => $actionStep->id]);
        $actionStep->update(['next_step_id' => $endStep->id]);
        $this->funnel->update(['start_step_id' => $startStep->id]);

        // Enroll subscriber
        $this->service->enrollSubscriber($this->funnel, $this->subscriber);

        // Verify tag was added
        $this->subscriber->refresh();
        $this->assertTrue($this->subscriber->tags->contains('name', 'Funnel Tag'));
    }

    /** @test */
    public function test_process_ready_enrollments()
    {
        // Create simple funnel
        $startStep = FunnelStep::factory()->create([
            'funnel_id' => $this->funnel->id,
            'type' => FunnelStep::TYPE_START,
        ]);

        $endStep = FunnelStep::factory()->create([
            'funnel_id' => $this->funnel->id,
            'type' => FunnelStep::TYPE_END,
        ]);

        $startStep->update(['next_step_id' => $endStep->id]);
        $this->funnel->update(['start_step_id' => $startStep->id]);

        // Create waiting enrollment with past next_action_at
        $enrollment = FunnelSubscriber::create([
            'funnel_id' => $this->funnel->id,
            'subscriber_id' => $this->subscriber->id,
            'current_step_id' => $endStep->id,
            'status' => FunnelSubscriber::STATUS_WAITING,
            'next_action_at' => now()->subMinute(),
            'entered_at' => now(),
        ]);

        // Process ready enrollments
        $processed = $this->service->processReadyEnrollments();

        $this->assertEquals(1, $processed);

        $enrollment->refresh();
        $this->assertEquals(FunnelSubscriber::STATUS_COMPLETED, $enrollment->status);
    }
}
