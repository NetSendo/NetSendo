<?php

namespace Tests\Feature;

use App\Jobs\SendEmailJob;
use App\Models\ApiKey;
use App\Models\Funnel;
use App\Models\FunnelStep;
use App\Models\FunnelSubscriber;
use App\Models\Message;
use App\Services\Funnels\FunnelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Concerns\BuildsFunnels;
use Tests\TestCase;

/**
 * What the builder, the enrollment list and the API save and do with a funnel:
 * settings the engine reads but nothing stored, connections that could not be
 * removed, a resume that left the enrollment stuck, API steps never connected.
 */
class FunnelBuilderAndManagementTest extends TestCase
{
    use RefreshDatabase;
    use BuildsFunnels;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-14 10:00:00');
        Queue::fake();

        $this->setUpFunnelOwner();
    }

    private function node(string $id, string $type, array $data = []): array
    {
        return ['id' => $id, 'type' => $type, 'position' => ['x' => 0, 'y' => 0], 'data' => $data];
    }

    // ===== Builder =====

    public function test_the_builder_saves_wait_and_retry_settings_and_the_wait_until_day(): void
    {
        $service = app(FunnelService::class);
        $funnel = $service->create(['user_id' => $this->user->id, 'name' => 'Built']);
        $reminder = $this->makeEmail('Reminder');

        $service->updateSteps($funnel, [
            $this->node('new-1', 'start'),
            $this->node('new-2', 'condition', [
                'condition_type' => 'task_completed',
                'condition_config' => ['task_id' => 'quiz'],
                'wait_for_condition' => true,
                'retry_enabled' => true,
                'retry_max_attempts' => 5,
                'retry_interval_value' => 2,
                'retry_interval_unit' => 'days',
                'retry_message_id' => (string) $reminder->id,
                'retry_exhausted_action' => 'exit',
            ]),
            $this->node('new-3', 'wait_until', ['wait_until_type' => 'day_of_week', 'wait_until_day' => 3, 'wait_until_time' => '08:15']),
        ], []);

        $condition = $funnel->steps()->where('type', 'condition')->first();
        $this->assertTrue($condition->wait_for_condition);
        $this->assertTrue($condition->retry_enabled);
        $this->assertSame(5, $condition->retry_max_attempts);
        $this->assertSame(2, $condition->retry_interval_value);
        $this->assertSame('days', $condition->retry_interval_unit);
        $this->assertSame($reminder->id, (int) $condition->retry_message_id);
        $this->assertSame('exit', $condition->retry_exhausted_action);
        $this->assertSame(3, $funnel->steps()->where('type', 'wait_until')->first()->wait_until_day);

        $data = collect($service->prepareForBuilder($funnel->fresh('steps'))['nodes'])->firstWhere('type', 'condition')['data'];
        $this->assertTrue($data['wait_for_condition']);
        $this->assertSame('exit', $data['retry_exhausted_action']);
    }

    public function test_a_connection_removed_in_the_builder_is_removed_on_save(): void
    {
        $service = app(FunnelService::class);
        $funnel = $service->create(['user_id' => $this->user->id, 'name' => 'Built']);

        $nodes = [$this->node('new-1', 'start'), $this->node('new-2', 'condition', ['condition_type' => 'tag_exists']), $this->node('new-3', 'end'), $this->node('new-4', 'end')];
        $service->updateSteps($funnel, $nodes, [
            ['source' => 'new-1', 'target' => 'new-2', 'sourceHandle' => 'default'],
            ['source' => 'new-2', 'target' => 'new-3', 'sourceHandle' => 'yes'],
            ['source' => 'new-2', 'target' => 'new-4', 'sourceHandle' => 'no'],
        ]);

        $steps = $funnel->steps()->orderBy('order')->get();
        $nodes = $steps->map(fn (FunnelStep $step) => $this->node((string) $step->id, $step->type, ['condition_type' => $step->condition_type]))->all();

        // Saved again without the "no" branch
        $service->updateSteps($funnel, $nodes, [
            ['source' => (string) $steps[0]->id, 'target' => (string) $steps[1]->id, 'sourceHandle' => 'default'],
            ['source' => (string) $steps[1]->id, 'target' => (string) $steps[2]->id, 'sourceHandle' => 'yes'],
        ]);

        $condition = $steps[1]->fresh();
        $this->assertSame($steps[2]->id, $condition->next_step_yes_id);
        $this->assertNull($condition->next_step_no_id);
        $this->assertSame($steps[1]->id, $steps[0]->fresh()->next_step_id);
    }

    public function test_the_builder_offers_the_accounts_emails_not_only_brains(): void
    {
        $draft = $this->makeEmail('Written by hand');
        $ready = Message::create(['user_id' => $this->user->id, 'channel' => 'email', 'type' => 'broadcast', 'subject' => 'Written by Brain', 'content' => 'x', 'status' => 'ready']);
        Message::create(['user_id' => $this->user->id, 'channel' => 'sms', 'type' => 'broadcast', 'subject' => 'An SMS', 'content' => 'x', 'status' => 'draft']);

        $offered = app(FunnelService::class)->getAvailableMessages($this->user->id)->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$draft->id, $ready->id], $offered);
    }

    // ===== Pause / resume =====

    public function test_resuming_puts_an_enrollment_back_where_it_was_paused(): void
    {
        $message = $this->makeEmail();
        $funnel = $this->makeFunnel();
        $end = $this->makeStep($funnel, FunnelStep::TYPE_END);
        $email = $this->makeStep($funnel, FunnelStep::TYPE_EMAIL, ['message_id' => $message->id, 'next_step_id' => $end->id]);
        $delay = $this->makeStep($funnel, FunnelStep::TYPE_DELAY, ['delay_value' => 1, 'delay_unit' => 'hours', 'next_step_id' => $email->id]);
        $this->makeChain($funnel, $delay);

        $waiting = $this->enroll($funnel, $this->makeSubscriber('waiting@example.com'));

        // An enrollment left active on a step that never ran
        $stuck = FunnelSubscriber::create([
            'funnel_id' => $funnel->id,
            'subscriber_id' => $this->makeSubscriber('stuck@example.com')->id,
            'current_step_id' => $email->id,
            'status' => FunnelSubscriber::STATUS_ACTIVE,
            'entered_at' => now(),
            'data' => [],
        ]);

        $this->actingAs($this->user);

        foreach ([$waiting, $stuck] as $enrollment) {
            $this->postJson(route('funnels.subscribers.pause', [$funnel, $enrollment]))->assertOk();
            $this->assertSame(FunnelSubscriber::STATUS_PAUSED, $enrollment->fresh()->status);
            $this->postJson(route('funnels.subscribers.resume', [$funnel, $enrollment]))->assertOk();
        }

        $this->assertSame(FunnelSubscriber::STATUS_WAITING, $waiting->fresh()->status);
        $this->assertSame('2026-09-14 11:00:00', $waiting->fresh()->next_action_at->format('Y-m-d H:i:s'));

        Queue::assertPushed(SendEmailJob::class, 1);
        $this->assertSame(FunnelSubscriber::STATUS_COMPLETED, $stuck->fresh()->status);
    }

    public function test_an_enrollment_waiting_for_its_condition_can_be_paused_and_resumed(): void
    {
        $funnel = $this->makeFunnel();
        $this->makeChain($funnel, $this->makeStep($funnel, FunnelStep::TYPE_CONDITION, [
            'condition_type' => FunnelStep::CONDITION_TASK_COMPLETED,
            'condition_config' => ['task_id' => 'quiz'],
            'wait_for_condition' => true,
        ]));

        $enrollment = $this->enroll($funnel, $this->makeSubscriber());
        $this->actingAs($this->user);

        $this->postJson(route('funnels.subscribers.pause', [$funnel, $enrollment]))->assertOk();
        $this->postJson(route('funnels.subscribers.resume', [$funnel, $enrollment]))->assertOk();

        $this->assertSame(FunnelSubscriber::STATUS_WAITING_CONDITION, $enrollment->fresh()->status);
    }

    // ===== API =====

    public function test_steps_added_over_the_api_are_connected_and_run(): void
    {
        $key = ApiKey::generate($this->user->id, 'Funnels', ['funnels:read', 'funnels:write'])['key'];
        $funnel = app(FunnelService::class)->create(['user_id' => $this->user->id, 'name' => 'API funnel']);
        $start = $funnel->steps()->first();
        $message = $this->makeEmail();

        $add = fn (array $payload) => $this->withHeaders(['Authorization' => "Bearer {$key}"])
            ->postJson("/api/v1/funnels/{$funnel->id}/steps", $payload)
            ->assertCreated()
            ->json('data.id');

        $conditionId = $add(['type' => 'condition', 'name' => 'Has tag', 'condition_type' => 'tag_exists', 'condition_config' => ['tag' => 'vip']]);
        $vipEmailId = $add(['type' => 'email', 'name' => 'VIP email', 'message_id' => $message->id]);
        $tagId = $add(['type' => 'action', 'name' => 'Tag', 'after_step_id' => $conditionId, 'branch' => 'no', 'action_type' => 'add_tag', 'action_config' => ['tag' => 'not-vip']]);
        // Inserted between the start and the condition
        $smsId = $add(['type' => 'sms', 'name' => 'Welcome SMS', 'after_step_id' => $start->id, 'sms_content' => 'Hi']);

        $this->assertSame($smsId, $start->fresh()->next_step_id);
        $this->assertSame($conditionId, FunnelStep::find($smsId)->next_step_id);
        $this->assertSame($vipEmailId, FunnelStep::find($conditionId)->next_step_yes_id);
        $this->assertSame($tagId, FunnelStep::find($conditionId)->next_step_no_id);
        $this->assertSame('add_tag', FunnelStep::find($tagId)->action_type);

        $funnel->update(['status' => Funnel::STATUS_ACTIVE]);
        $subscriber = $this->makeSubscriber();
        $enrollment = $this->enroll($funnel, $subscriber);

        $this->assertSame(FunnelSubscriber::STATUS_COMPLETED, $enrollment->status);
        $this->assertSame(['not-vip'], $subscriber->tags()->pluck('name')->all());
        Queue::assertNotPushed(SendEmailJob::class);
    }
}
