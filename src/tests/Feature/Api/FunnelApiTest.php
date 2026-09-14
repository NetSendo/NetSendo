<?php

namespace Tests\Feature\Api;

use App\Jobs\SendEmailJob;
use App\Models\ApiKey;
use App\Models\Funnel;
use App\Models\FunnelStep;
use App\Models\FunnelSubscriber;
use App\Models\User;
use App\Services\Funnels\FunnelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Concerns\BuildsFunnels;
use Tests\TestCase;

/**
 * Building and running a funnel over /api/v1/funnels (what the MCP client
 * uses): steps added without connections were never reached, most step
 * settings could not be sent, steps could not be changed or removed, and a
 * `manual` funnel could not be started at all.
 */
class FunnelApiTest extends TestCase
{
    use RefreshDatabase;
    use BuildsFunnels;

    private string $key;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-14 10:00:00');
        Queue::fake();

        $this->setUpFunnelOwner();
        $this->key = ApiKey::generate($this->user->id, 'Funnels', ['funnels:read', 'funnels:write'])['key'];
    }

    private function api(string $method, string $uri, array $payload = [], ?string $key = null): TestResponse
    {
        return $this->withHeaders(['Authorization' => 'Bearer ' . ($key ?? $this->key)])
            ->json($method, "/api/v1/{$uri}", $payload);
    }

    private function apiFunnel(): Funnel
    {
        return app(FunnelService::class)->create(['user_id' => $this->user->id, 'name' => 'API funnel', 'trigger_type' => Funnel::TRIGGER_MANUAL]);
    }

    public function test_steps_added_over_the_api_are_connected_and_run(): void
    {
        $funnel = $this->apiFunnel();
        $start = $funnel->steps()->first();
        $message = $this->makeEmail();

        $add = fn (array $payload) => $this->api('POST', "funnels/{$funnel->id}/steps", $payload)->assertCreated()->json('data.id');

        $conditionId = $add(['type' => 'condition', 'name' => 'Has tag', 'condition_type' => 'tag_exists', 'condition_config' => ['tag' => 'vip']]);
        $vipEmailId = $add(['type' => 'email', 'name' => 'VIP email', 'message_id' => $message->id]);
        $tagId = $add(['type' => 'action', 'name' => 'Tag', 'after_step_id' => $conditionId, 'branch' => 'no', 'action_type' => 'add_tag', 'action_config' => ['tag' => 'not-vip']]);
        // Inserted between the start and the condition
        $smsId = $add(['type' => 'sms', 'name' => 'Welcome SMS', 'after_step_id' => $start->id, 'sms_content' => 'Hi']);

        $this->assertSame($smsId, $start->fresh()->next_step_id);
        $this->assertSame($conditionId, FunnelStep::find($smsId)->next_step_id);
        $this->assertSame($vipEmailId, FunnelStep::find($conditionId)->next_step_yes_id);
        $this->assertSame($tagId, FunnelStep::find($conditionId)->next_step_no_id);

        $funnel->update(['status' => Funnel::STATUS_ACTIVE]);
        $subscriber = $this->makeSubscriber();

        $this->api('POST', "funnels/{$funnel->id}/subscribers", ['email' => $subscriber->email])
            ->assertCreated()
            ->assertJsonPath('data.status', FunnelSubscriber::STATUS_COMPLETED);

        $this->assertSame(['not-vip'], $subscriber->tags()->pluck('name')->all());
        Queue::assertNotPushed(SendEmailJob::class);
    }

    public function test_every_step_setting_can_be_sent(): void
    {
        $funnel = $this->apiFunnel();
        $reminder = $this->makeEmail('Reminder');

        $waitId = $this->api('POST', "funnels/{$funnel->id}/steps", [
            'type' => 'wait_until', 'name' => 'Wednesday',
            'wait_until_type' => 'day_of_week', 'wait_until_day' => 3, 'wait_until_time' => '09:30', 'wait_until_timezone' => 'Europe/Warsaw',
        ])->assertCreated()->json('data.id');

        $conditionId = $this->api('POST', "funnels/{$funnel->id}/steps", [
            'type' => 'condition', 'name' => 'Quiz done',
            'condition_type' => 'task_completed', 'condition_config' => ['task_id' => 'quiz'],
            'wait_for_condition' => true, 'retry_enabled' => true, 'retry_max_attempts' => 3,
            'retry_interval_value' => 2, 'retry_interval_unit' => 'days',
            'retry_message_id' => $reminder->id, 'retry_exhausted_action' => 'unsubscribe',
        ])->assertCreated()->json('data.id');

        $goalId = $this->api('POST', "funnels/{$funnel->id}/steps", [
            'type' => 'goal', 'name' => 'Bought', 'goal_name' => 'Course', 'goal_type' => 'purchase', 'goal_value' => 199.5,
        ])->assertCreated()->json('data.id');

        $delayId = $this->api('POST', "funnels/{$funnel->id}/steps", [
            'type' => 'delay', 'name' => 'Two weeks', 'delay_value' => 2, 'delay_unit' => 'weeks',
        ])->assertCreated()->json('data.id');

        $wait = FunnelStep::find($waitId);
        $this->assertSame([3, '09:30', 'Europe/Warsaw'], [$wait->wait_until_day, substr($wait->wait_until_time, 0, 5), $wait->wait_until_timezone]);

        $condition = FunnelStep::find($conditionId);
        $this->assertTrue($condition->wait_for_condition && $condition->retry_enabled);
        $this->assertSame([3, 2, 'days', 'unsubscribe'], [$condition->retry_max_attempts, $condition->retry_interval_value, $condition->retry_interval_unit, $condition->retry_exhausted_action]);
        $this->assertSame($reminder->id, (int) $condition->retry_message_id);

        $this->assertSame(['purchase', '199.50'], [FunnelStep::find($goalId)->goal_type, FunnelStep::find($goalId)->goal_value]);
        $this->assertSame(1209600, FunnelStep::find($delayId)->delay_in_seconds);
    }

    public function test_unknown_values_and_another_accounts_email_are_rejected(): void
    {
        $funnel = $this->apiFunnel();
        $foreignEmail = \App\Models\Message::create(['user_id' => User::factory()->create()->id, 'channel' => 'email', 'type' => 'broadcast', 'subject' => 'x', 'content' => 'x', 'status' => 'draft']);

        $this->api('POST', "funnels/{$funnel->id}/steps", ['type' => 'condition', 'name' => 'x', 'condition_type' => 'opened_yesterday'])
            ->assertUnprocessable()->assertJsonValidationErrors('condition_type');
        $this->api('POST', "funnels/{$funnel->id}/steps", ['type' => 'email', 'name' => 'x', 'message_id' => $foreignEmail->id])
            ->assertUnprocessable()->assertJsonValidationErrors('message_id');
        $this->api('POST', "funnels/{$funnel->id}/steps", ['type' => 'wait_until', 'name' => 'x', 'wait_until_day' => 8])
            ->assertUnprocessable()->assertJsonValidationErrors('wait_until_day');

        $this->assertSame(1, $funnel->steps()->count());
    }

    public function test_a_step_can_be_updated_and_reconnected_within_its_funnel(): void
    {
        $funnel = $this->apiFunnel();
        $condition = $this->makeStep($funnel, FunnelStep::TYPE_CONDITION, ['condition_type' => 'tag_exists', 'condition_config' => ['tag' => 'a']]);
        $end = $this->makeStep($funnel, FunnelStep::TYPE_END);
        $other = $this->apiFunnel();
        $foreignStep = $this->makeStep($other, FunnelStep::TYPE_END);

        $this->api('PUT', "funnels/{$funnel->id}/steps/{$condition->id}", [
            'name' => 'Renamed',
            'condition_config' => ['tag' => 'b'],
            'wait_for_condition' => true,
            'next_step_yes_id' => $end->id,
        ])->assertOk()->assertJsonPath('data.name', 'Renamed');

        $condition->refresh();
        $this->assertSame(['tag' => 'b'], $condition->condition_config);
        $this->assertTrue($condition->wait_for_condition);
        $this->assertSame($end->id, $condition->next_step_yes_id);
        $this->assertSame('tag_exists', $condition->condition_type);

        $this->api('PUT', "funnels/{$funnel->id}/steps/{$condition->id}", ['next_step_no_id' => $foreignStep->id])
            ->assertUnprocessable()->assertJsonValidationErrors('next_step_no_id');
        $this->api('PUT', "funnels/{$funnel->id}/steps/{$condition->id}", ['next_step_id' => $condition->id])
            ->assertUnprocessable()->assertJsonValidationErrors('next_step_id');
        $this->api('PUT', "funnels/{$funnel->id}/steps/{$foreignStep->id}", ['name' => 'x'])->assertNotFound();

        $this->api('PUT', "funnels/{$funnel->id}/steps/{$condition->id}", ['next_step_yes_id' => null])->assertOk();
        $this->assertNull($condition->fresh()->next_step_yes_id);
    }

    public function test_deleting_a_step_reconnects_the_chain_and_moves_its_enrollments_on(): void
    {
        $message = $this->makeEmail();
        $funnel = $this->apiFunnel();
        $start = $funnel->steps()->first();
        $end = $this->makeStep($funnel, FunnelStep::TYPE_END);
        $email = $this->makeStep($funnel, FunnelStep::TYPE_EMAIL, ['message_id' => $message->id, 'next_step_id' => $end->id]);
        $condition = $this->makeStep($funnel, FunnelStep::TYPE_CONDITION, [
            'condition_type' => 'task_completed', 'condition_config' => ['task_id' => 'quiz'], 'wait_for_condition' => true,
            'next_step_id' => $email->id, 'next_step_yes_id' => $email->id,
        ]);
        $start->update(['next_step_id' => $condition->id]);
        $funnel->update(['status' => Funnel::STATUS_ACTIVE]);

        $enrollment = $this->enroll($funnel, $this->makeSubscriber());
        $this->assertSame(FunnelSubscriber::STATUS_WAITING_CONDITION, $enrollment->status);

        $this->api('DELETE', "funnels/{$funnel->id}/steps/{$start->id}")->assertUnprocessable();
        $this->api('DELETE', "funnels/{$funnel->id}/steps/{$condition->id}")->assertOk();

        $this->assertNull(FunnelStep::find($condition->id));
        $this->assertSame($email->id, $start->fresh()->next_step_id);
        $this->assertSame(FunnelSubscriber::STATUS_WAITING, $enrollment->fresh()->status);
        $this->assertSame($email->id, $enrollment->fresh()->current_step_id);

        // The scheduled processor runs the step it was moved to
        $this->artisan('funnels:process')->assertSuccessful();
        Queue::assertPushed(SendEmailJob::class, 1);
        $this->assertSame(FunnelSubscriber::STATUS_COMPLETED, $enrollment->fresh()->status);
    }

    public function test_enrolling_needs_an_active_funnel_the_accounts_subscriber_and_happens_once(): void
    {
        $funnel = $this->apiFunnel();
        $this->makeChain($funnel, $this->makeStep($funnel, FunnelStep::TYPE_END));
        $subscriber = $this->makeSubscriber();

        $this->api('POST', "funnels/{$funnel->id}/subscribers", ['subscriber_id' => $subscriber->id])->assertStatus(409);

        $funnel->update(['status' => Funnel::STATUS_ACTIVE]);
        $this->api('POST', "funnels/{$funnel->id}/subscribers", ['subscriber_id' => $subscriber->id])->assertCreated();
        $this->api('POST', "funnels/{$funnel->id}/subscribers", ['email' => $subscriber->email])
            ->assertStatus(409)
            ->assertJsonPath('data.subscriber_id', $subscriber->id);

        $stranger = User::factory()->create();
        $foreign = \App\Models\Subscriber::create(['user_id' => $stranger->id, 'email' => 'foreign@example.com', 'status' => 'active', 'is_active_global' => true]);
        $this->api('POST', "funnels/{$funnel->id}/subscribers", ['subscriber_id' => $foreign->id])->assertUnprocessable();
        $this->api('POST', "funnels/{$funnel->id}/subscribers", ['email' => 'foreign@example.com'])->assertNotFound();

        $this->assertSame(1, FunnelSubscriber::where('funnel_id', $funnel->id)->count());
    }

    public function test_a_read_only_key_and_another_accounts_funnel_are_refused(): void
    {
        $funnel = $this->apiFunnel();
        $step = $this->makeStep($funnel, FunnelStep::TYPE_END);
        $readOnly = ApiKey::generate($this->user->id, 'Read', ['funnels:read'])['key'];

        $this->api('POST', "funnels/{$funnel->id}/steps", ['type' => 'end', 'name' => 'x'], $readOnly)->assertForbidden();
        $this->api('PUT', "funnels/{$funnel->id}/steps/{$step->id}", ['name' => 'x'], $readOnly)->assertForbidden();
        $this->api('DELETE', "funnels/{$funnel->id}/steps/{$step->id}", [], $readOnly)->assertForbidden();
        $this->api('POST', "funnels/{$funnel->id}/subscribers", ['email' => 'a@example.com'], $readOnly)->assertForbidden();

        $strangerKey = ApiKey::generate(User::factory()->create()->id, 'Other', ['funnels:read', 'funnels:write'])['key'];
        $this->api('PUT', "funnels/{$funnel->id}/steps/{$step->id}", ['name' => 'x'], $strangerKey)->assertNotFound();
        $this->api('DELETE', "funnels/{$funnel->id}/steps/{$step->id}", [], $strangerKey)->assertNotFound();
    }
}
