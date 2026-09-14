<?php

namespace Tests\Feature;

use App\Jobs\SendEmailJob;
use App\Models\AutomationRule;
use App\Models\AutomationRuleLog;
use App\Models\Funnel;
use App\Models\FunnelStep;
use App\Models\FunnelStepRetry;
use App\Models\FunnelSubscriber;
use App\Models\Message;
use App\Models\Subscriber;
use App\Models\Tag;
use App\Models\User;
use App\Services\Automation\AutomationService;
use App\Services\Funnels\FunnelExecutionService;
use App\Services\Funnels\FunnelRetryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Automation and funnel emails dispatch SendEmailJob directly, outside the
 * CRON queue gate. They used to reach bounced, unsubscribed and inactive
 * subscribers too.
 *
 * The funnel tests run whole enrollments, so they also cover the stale
 * `currentStep` relation that made every funnel re-run its start step forever.
 */
class NonDeliverableSubscriberSendTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Message $message;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->user = User::factory()->create(['timezone' => 'UTC']);

        $this->message = Message::create([
            'user_id' => $this->user->id,
            'channel' => 'email',
            'type' => 'autoresponder',
            'subject' => 'Hello',
            'content' => '<p>Body</p>',
            'status' => 'draft',
        ]);
    }

    public static function nonDeliverableStates(): array
    {
        return [
            'bounced' => ['bounced', true],
            'unsubscribed' => ['unsubscribed', true],
            'inactive' => ['active', false],
        ];
    }

    private function subscriber(string $status = 'active', bool $activeGlobal = true): Subscriber
    {
        return Subscriber::create([
            'user_id' => $this->user->id,
            'email' => 'reader' . random_int(1000, 999999) . '@example.com',
            'status' => $status,
            'is_active_global' => $activeGlobal,
        ]);
    }

    private function sendEmailRule(array $extraActions = []): AutomationRule
    {
        return AutomationRule::create([
            'user_id' => $this->user->id,
            'name' => 'Welcome email',
            'trigger_event' => 'tag_removed',
            'trigger_config' => [],
            'conditions' => [],
            'actions' => array_merge([
                ['type' => 'send_email', 'config' => ['message_id' => $this->message->id]],
            ], $extraActions),
            'is_active' => true,
        ]);
    }

    /**
     * start → email → (delay) → email → end, with the delay optional.
     */
    private function funnel(bool $withDelay = false): Funnel
    {
        $funnel = Funnel::create([
            'user_id' => $this->user->id,
            'name' => 'Onboarding',
            'status' => Funnel::STATUS_ACTIVE,
            'trigger_type' => Funnel::TRIGGER_MANUAL,
        ]);

        $step = fn (array $attributes) => FunnelStep::create(array_merge(['funnel_id' => $funnel->id], $attributes));

        $end = $step(['type' => FunnelStep::TYPE_END, 'order' => 5]);
        $second = $step(['type' => FunnelStep::TYPE_EMAIL, 'message_id' => $this->message->id, 'next_step_id' => $end->id, 'order' => 4]);
        $next = $withDelay
            ? $step(['type' => FunnelStep::TYPE_DELAY, 'delay_value' => 2, 'delay_unit' => FunnelStep::DELAY_DAYS, 'next_step_id' => $second->id, 'order' => 3])
            : $second;
        $first = $step(['type' => FunnelStep::TYPE_EMAIL, 'message_id' => $this->message->id, 'next_step_id' => $next->id, 'order' => 2]);
        $step(['type' => FunnelStep::TYPE_START, 'next_step_id' => $first->id, 'order' => 1]);

        return $funnel;
    }

    private function historyActions(FunnelSubscriber $enrollment): array
    {
        return array_column($enrollment->fresh()->getHistory(), 'action');
    }

    // ---- Automations -------------------------------------------------------

    #[DataProvider('nonDeliverableStates')]
    public function test_automation_send_email_skips_a_non_deliverable_subscriber_but_runs_other_actions(string $status, bool $activeGlobal): void
    {
        $subscriber = $this->subscriber($status, $activeGlobal);
        $tag = Tag::create(['user_id' => $this->user->id, 'name' => 'Onboarded']);
        $rule = $this->sendEmailRule([['type' => 'add_tag', 'config' => ['tag_id' => $tag->id]]]);

        app(AutomationService::class)->executeRule($rule, $subscriber, ['subscriber_id' => $subscriber->id], 'tag_removed');

        Queue::assertNotPushed(SendEmailJob::class);
        $this->assertTrue($subscriber->tags()->whereKey($tag->id)->exists(), 'Only the send is skipped');

        $log = AutomationRuleLog::where('automation_rule_id', $rule->id)->sole();
        $this->assertSame(AutomationRuleLog::STATUS_SUCCESS, $log->status, 'A skipped send is not a failed rule');
        $this->assertFalse($log->actions_executed[0]['result']['queued']);
        $this->assertSame(
            'Subscriber is ' . Subscriber::displayStatusFor($status, $activeGlobal),
            $log->actions_executed[0]['result']['skipped']
        );
    }

    public function test_automation_send_email_still_reaches_an_active_subscriber(): void
    {
        $subscriber = $this->subscriber();
        $rule = $this->sendEmailRule();

        app(AutomationService::class)->executeRule($rule, $subscriber, ['subscriber_id' => $subscriber->id], 'tag_removed');

        Queue::assertPushed(SendEmailJob::class, fn ($job) => $job->subscriber->is($subscriber));
        $log = AutomationRuleLog::where('automation_rule_id', $rule->id)->sole();
        $this->assertTrue($log->actions_executed[0]['result']['queued']);
    }

    // ---- Funnels -----------------------------------------------------------

    #[DataProvider('nonDeliverableStates')]
    public function test_funnel_email_steps_skip_a_non_deliverable_subscriber_and_the_funnel_moves_on(string $status, bool $activeGlobal): void
    {
        $subscriber = $this->subscriber($status, $activeGlobal);

        $enrollment = app(FunnelExecutionService::class)->enrollSubscriber($this->funnel(), $subscriber);

        Queue::assertNotPushed(SendEmailJob::class);
        $this->assertSame(FunnelSubscriber::STATUS_COMPLETED, $enrollment->fresh()->status);
        $this->assertSame(['started', 'email_skipped', 'email_skipped', 'completed'], $this->historyActions($enrollment));
        $this->assertSame(
            'Subscriber is ' . Subscriber::displayStatusFor($status, $activeGlobal),
            $enrollment->fresh()->getHistory()[1]['details']['reason']
        );
    }

    public function test_funnel_email_steps_still_reach_an_active_subscriber(): void
    {
        $subscriber = $this->subscriber();

        $enrollment = app(FunnelExecutionService::class)->enrollSubscriber($this->funnel(), $subscriber);

        Queue::assertPushed(SendEmailJob::class, 2);
        $this->assertSame(['started', 'email_queued', 'email_queued', 'completed'], $this->historyActions($enrollment));
    }

    public function test_funnel_subscriber_reactivated_during_a_delay_gets_the_later_emails(): void
    {
        $subscriber = $this->subscriber('active', false);

        $enrollment = app(FunnelExecutionService::class)->enrollSubscriber($this->funnel(withDelay: true), $subscriber);

        Queue::assertNotPushed(SendEmailJob::class);
        $this->assertSame(FunnelSubscriber::STATUS_WAITING, $enrollment->fresh()->status);

        $subscriber->update(Subscriber::adminStatusAttributes(Subscriber::STATUS_ACTIVE));
        $this->travel(3)->days();

        app(FunnelExecutionService::class)->processReadyEnrollments();

        Queue::assertPushed(SendEmailJob::class, 1);
        $this->assertSame(FunnelSubscriber::STATUS_COMPLETED, $enrollment->fresh()->status);
    }

    #[DataProvider('nonDeliverableStates')]
    public function test_funnel_retry_reminder_skips_a_non_deliverable_subscriber_but_counts_the_attempt(string $status, bool $activeGlobal): void
    {
        $subscriber = $this->subscriber($status, $activeGlobal);
        $funnel = $this->funnel();
        $step = FunnelStep::create([
            'funnel_id' => $funnel->id,
            'type' => FunnelStep::TYPE_CONDITION,
            'condition_type' => FunnelStep::CONDITION_EMAIL_OPENED,
            'condition_config' => ['message_id' => $this->message->id],
            'retry_enabled' => true,
            'retry_max_attempts' => 2,
            'retry_interval_value' => 1,
            'retry_interval_unit' => FunnelStep::RETRY_UNIT_DAYS,
            'retry_message_id' => $this->message->id,
        ]);
        $enrollment = FunnelSubscriber::create([
            'funnel_id' => $funnel->id,
            'subscriber_id' => $subscriber->id,
            'current_step_id' => $step->id,
            'status' => FunnelSubscriber::STATUS_ACTIVE,
            'entered_at' => now(),
            'data' => [],
        ]);

        $sent = app(FunnelRetryService::class)->sendRetry($enrollment, $step);

        $this->assertFalse($sent);
        Queue::assertNotPushed(SendEmailJob::class);
        // Counted, so the retry interval and the exhausted action still apply
        // instead of retrying on every run
        $this->assertSame(1, FunnelStepRetry::getAttemptCount($enrollment->id, $step->id));
        $this->assertSame(['retry_skipped'], $this->historyActions($enrollment));
    }
}
