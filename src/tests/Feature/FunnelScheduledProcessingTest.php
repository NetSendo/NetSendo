<?php

namespace Tests\Feature;

use App\Jobs\SendEmailJob;
use App\Models\Funnel;
use App\Models\FunnelStep;
use App\Models\FunnelSubscriber;
use App\Models\FunnelTask;
use App\Models\Message;
use App\Models\Subscriber;
use App\Models\User;
use App\Services\Funnels\FunnelExecutionService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Nothing ran the funnel engine after enrollment: an enrollment reached a
 * delay or a condition wait and stayed there. `funnels:process` runs both
 * halves every minute.
 */
class FunnelScheduledProcessingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Message $message;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Carbon::setTestNow('2026-09-14 10:00:00');

        $this->user = User::factory()->create();

        $this->message = Message::create([
            'user_id' => $this->user->id,
            'channel' => 'email',
            'type' => 'broadcast',
            'subject' => 'Hello',
            'content' => '<p>Body</p>',
            'status' => 'draft',
        ]);
    }

    private function funnel(string $status = Funnel::STATUS_ACTIVE): Funnel
    {
        return Funnel::create([
            'user_id' => $this->user->id,
            'name' => 'Funnel',
            'status' => $status,
            'trigger_type' => 'manual',
        ]);
    }

    private function step(Funnel $funnel, string $type, array $attributes = []): FunnelStep
    {
        return FunnelStep::create(array_merge([
            'funnel_id' => $funnel->id,
            'type' => $type,
        ], $attributes));
    }

    private function subscriber(string $email = 'jan@example.com'): Subscriber
    {
        return Subscriber::create([
            'user_id' => $this->user->id,
            'email' => $email,
            'status' => 'active',
            'is_active_global' => true,
        ]);
    }

    private function enroll(Funnel $funnel, Subscriber $subscriber): FunnelSubscriber
    {
        return app(FunnelExecutionService::class)->enrollSubscriber($funnel, $subscriber)->fresh();
    }

    /**
     * Enrollments inserted directly, so that there are enough of them to fill
     * the batch the processors used to take.
     */
    private function insertEnrollments(Funnel $funnel, FunnelStep $step, int $count, array $attributes): void
    {
        $now = now();

        DB::table('subscribers')->insert(collect(range(1, $count))->map(fn ($i) => [
            'user_id' => $this->user->id,
            'email' => "filler{$funnel->id}-{$i}@example.com",
            'status' => 'active',
            'is_active_global' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());

        $subscriberIds = DB::table('subscribers')
            ->where('email', 'like', "filler{$funnel->id}-%")
            ->pluck('id');

        DB::table('funnel_subscribers')->insert($subscriberIds->map(fn ($id) => array_merge([
            'funnel_id' => $funnel->id,
            'subscriber_id' => $id,
            'current_step_id' => $step->id,
            'entered_at' => $now,
            'steps_completed' => 0,
            'data' => json_encode([]),
            'created_at' => $now,
            'updated_at' => $now,
        ], $attributes))->all());
    }

    private function runProcessor(): void
    {
        $this->artisan('funnels:process')->assertSuccessful();
    }

    private function historyActions(FunnelSubscriber $enrollment): array
    {
        return array_column($enrollment->fresh()->getHistory(), 'action');
    }

    public function test_the_processor_is_scheduled_every_minute_without_overlapping(): void
    {
        $this->app->make(Kernel::class)->bootstrap();

        $events = collect($this->app->make(Schedule::class)->events())
            ->filter(fn ($event) => str_contains((string) $event->command, 'funnels:process'));

        $this->assertCount(1, $events);
        $this->assertSame('* * * * *', $events->first()->expression);
        $this->assertTrue($events->first()->withoutOverlapping);
    }

    public function test_the_former_command_name_still_runs_it(): void
    {
        $this->artisan('funnels:process-retries')->assertSuccessful();
    }

    public function test_an_enrollment_resumes_once_its_delay_has_passed(): void
    {
        $funnel = $this->funnel();
        $end = $this->step($funnel, FunnelStep::TYPE_END);
        $email = $this->step($funnel, FunnelStep::TYPE_EMAIL, ['message_id' => $this->message->id, 'next_step_id' => $end->id]);
        $delay = $this->step($funnel, FunnelStep::TYPE_DELAY, ['delay_value' => 2, 'delay_unit' => 'hours', 'next_step_id' => $email->id]);
        $this->step($funnel, FunnelStep::TYPE_START, ['next_step_id' => $delay->id]);

        $enrollment = $this->enroll($funnel, $this->subscriber());

        $this->assertSame(FunnelSubscriber::STATUS_WAITING, $enrollment->status);
        $this->assertSame($email->id, $enrollment->current_step_id);

        $this->travel(119)->minutes();
        $this->runProcessor();

        $this->assertSame(FunnelSubscriber::STATUS_WAITING, $enrollment->fresh()->status);
        Queue::assertNotPushed(SendEmailJob::class);

        $this->travel(1)->minutes();
        $this->runProcessor();

        Queue::assertPushed(SendEmailJob::class, 1);
        $this->assertSame(FunnelSubscriber::STATUS_COMPLETED, $enrollment->fresh()->status);
        $this->assertSame($end->id, $enrollment->fresh()->current_step_id);
    }

    public function test_a_delay_that_ends_the_funnel_completes_the_enrollment(): void
    {
        $funnel = $this->funnel();
        $delay = $this->step($funnel, FunnelStep::TYPE_DELAY, ['delay_value' => 1, 'delay_unit' => 'hours']);
        $this->step($funnel, FunnelStep::TYPE_START, ['next_step_id' => $delay->id]);

        $enrollment = $this->enroll($funnel, $this->subscriber());
        $this->assertSame(FunnelSubscriber::STATUS_WAITING, $enrollment->status);

        // Kept on the delay, each run used to start the same delay again
        $this->travel(1)->hours();
        $this->runProcessor();
        $this->travel(1)->hours();
        $this->runProcessor();

        $this->assertSame(FunnelSubscriber::STATUS_COMPLETED, $enrollment->fresh()->status);
        $this->assertSame(1, array_count_values($this->historyActions($enrollment))['delay_started']);
    }

    public function test_enrollments_of_paused_funnels_do_not_hold_up_the_rest(): void
    {
        $paused = $this->funnel(Funnel::STATUS_PAUSED);
        $pausedStep = $this->step($paused, FunnelStep::TYPE_END);
        $this->insertEnrollments($paused, $pausedStep, 100, [
            'status' => FunnelSubscriber::STATUS_WAITING,
            'next_action_at' => now()->subHour(),
        ]);

        $funnel = $this->funnel();
        $end = $this->step($funnel, FunnelStep::TYPE_END);
        $enrollment = FunnelSubscriber::create([
            'funnel_id' => $funnel->id,
            'subscriber_id' => $this->subscriber()->id,
            'current_step_id' => $end->id,
            'status' => FunnelSubscriber::STATUS_WAITING,
            'next_action_at' => now()->subMinute(),
            'entered_at' => now(),
        ]);

        $this->runProcessor();

        $this->assertSame(FunnelSubscriber::STATUS_COMPLETED, $enrollment->fresh()->status);
        $this->assertSame(100, FunnelSubscriber::where('funnel_id', $paused->id)
            ->where('status', FunnelSubscriber::STATUS_WAITING)->count());
    }

    public function test_a_condition_step_waits_until_the_task_is_completed(): void
    {
        $funnel = $this->funnel();
        $end = $this->step($funnel, FunnelStep::TYPE_END);
        $email = $this->step($funnel, FunnelStep::TYPE_EMAIL, ['message_id' => $this->message->id, 'next_step_id' => $end->id]);
        $condition = $this->step($funnel, FunnelStep::TYPE_CONDITION, [
            'condition_type' => FunnelStep::CONDITION_TASK_COMPLETED,
            'condition_config' => ['task_id' => 'quiz'],
            'wait_for_condition' => true,
            'next_step_yes_id' => $email->id,
        ]);
        $this->step($funnel, FunnelStep::TYPE_START, ['next_step_id' => $condition->id]);

        $subscriber = $this->subscriber();
        $enrollment = $this->enroll($funnel, $subscriber);

        $this->assertSame(FunnelSubscriber::STATUS_WAITING_CONDITION, $enrollment->status);

        $this->travel(1)->hours();
        $this->runProcessor();

        $this->assertSame(FunnelSubscriber::STATUS_WAITING_CONDITION, $enrollment->fresh()->status);
        Queue::assertNotPushed(SendEmailJob::class);

        FunnelTask::markCompleted($funnel->id, $subscriber->id, 'quiz');
        $this->runProcessor();

        // The YES branch runs straight away, not just becomes the current step
        Queue::assertPushed(SendEmailJob::class, 1);
        $this->assertSame(FunnelSubscriber::STATUS_COMPLETED, $enrollment->fresh()->status);
        $this->assertSame($end->id, $enrollment->fresh()->current_step_id);
    }

    public function test_reminders_are_sent_and_the_exhausted_action_waits_for_the_last_interval(): void
    {
        $funnel = $this->funnel();
        $no = $this->step($funnel, FunnelStep::TYPE_END);
        $yes = $this->step($funnel, FunnelStep::TYPE_END);
        $condition = $this->step($funnel, FunnelStep::TYPE_CONDITION, [
            'condition_type' => FunnelStep::CONDITION_TASK_COMPLETED,
            'condition_config' => ['task_id' => 'quiz'],
            'wait_for_condition' => true,
            'retry_enabled' => true,
            'retry_max_attempts' => 2,
            'retry_interval_value' => 1,
            'retry_interval_unit' => 'hours',
            'retry_message_id' => $this->message->id,
            'retry_exhausted_action' => FunnelStep::RETRY_ACTION_CONTINUE,
            'next_step_yes_id' => $yes->id,
            'next_step_no_id' => $no->id,
        ]);
        $this->step($funnel, FunnelStep::TYPE_START, ['next_step_id' => $condition->id]);

        $enrollment = $this->enroll($funnel, $this->subscriber());

        $this->travel(59)->minutes();
        $this->runProcessor();
        Queue::assertNotPushed(SendEmailJob::class);

        $this->travel(1)->minutes();
        $this->runProcessor();
        Queue::assertPushed(SendEmailJob::class, 1);

        $this->travel(1)->minutes();
        $this->runProcessor();
        Queue::assertPushed(SendEmailJob::class, 1);

        $this->travel(59)->minutes();
        $this->runProcessor();
        Queue::assertPushed(SendEmailJob::class, 2);

        // The last reminder is given its interval before the NO branch
        $this->travel(59)->minutes();
        $this->runProcessor();
        $this->assertSame(FunnelSubscriber::STATUS_WAITING_CONDITION, $enrollment->fresh()->status);

        $this->travel(1)->minutes();
        $this->runProcessor();

        Queue::assertPushed(SendEmailJob::class, 2);
        $this->assertSame(FunnelSubscriber::STATUS_COMPLETED, $enrollment->fresh()->status);
        $this->assertSame($no->id, $enrollment->fresh()->current_step_id);
        $this->assertContains('retry_exhausted', $this->historyActions($enrollment));
    }

    public function test_enrollments_waiting_beyond_the_first_hundred_are_checked(): void
    {
        $funnel = $this->funnel();
        $end = $this->step($funnel, FunnelStep::TYPE_END);
        $condition = $this->step($funnel, FunnelStep::TYPE_CONDITION, [
            'condition_type' => FunnelStep::CONDITION_TASK_COMPLETED,
            'condition_config' => ['task_id' => 'quiz'],
            'wait_for_condition' => true,
            'next_step_yes_id' => $end->id,
        ]);

        $this->insertEnrollments($funnel, $condition, 100, [
            'status' => FunnelSubscriber::STATUS_WAITING_CONDITION,
        ]);

        $subscriber = $this->subscriber();
        $enrollment = FunnelSubscriber::create([
            'funnel_id' => $funnel->id,
            'subscriber_id' => $subscriber->id,
            'current_step_id' => $condition->id,
            'status' => FunnelSubscriber::STATUS_WAITING_CONDITION,
            'entered_at' => now(),
        ]);
        FunnelTask::markCompleted($funnel->id, $subscriber->id, 'quiz');

        $this->runProcessor();

        $this->assertSame(FunnelSubscriber::STATUS_COMPLETED, $enrollment->fresh()->status);
    }
}
