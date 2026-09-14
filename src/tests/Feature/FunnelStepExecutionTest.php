<?php

namespace Tests\Feature;

use App\Events\SubscriberUnsubscribed;
use App\Events\TagAdded;
use App\Jobs\SendEmailJob;
use App\Jobs\SendFunnelSmsJob;
use App\Jobs\SendSmsJob;
use App\Listeners\EnrollInTriggeredFunnels;
use App\Models\CustomField;
use App\Models\EmailClick;
use App\Models\EmailOpen;
use App\Models\Funnel;
use App\Models\FunnelStep;
use App\Models\FunnelStepRetry;
use App\Models\FunnelSubscriber;
use App\Models\Message;
use App\Models\MessageQueueEntry;
use App\Models\SmsProvider;
use App\Models\SubscriberFieldValue;
use App\Models\Tag;
use App\Services\PlaceholderService;
use App\Services\Sms\SmsProviderInterface;
use App\Services\Sms\SmsProviderService;
use App\Services\Sms\SmsResult;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\Feature\Concerns\BuildsFunnels;
use Tests\TestCase;

/**
 * Step types, conditions and actions the funnel engine offered but did not run:
 * conditions called methods Subscriber does not have, tag actions passed a
 * string where a Tag is required, "unsubscribe" called a missing method,
 * "notify" only logged, SMS and "wait until" steps were skipped.
 */
class FunnelStepExecutionTest extends TestCase
{
    use RefreshDatabase;
    use BuildsFunnels;

    protected function setUp(): void
    {
        parent::setUp();

        // 14 September 2026 is a Monday
        Carbon::setTestNow('2026-09-14 10:00:00');
        Queue::fake();

        $this->setUpFunnelOwner(['timezone' => 'UTC']);
    }

    /**
     * start → condition, with its YES and NO branches ending on their own end steps.
     */
    private function conditionFunnel(array $condition, array $before = []): array
    {
        $funnel = $this->makeFunnel();
        $yes = $this->makeStep($funnel, FunnelStep::TYPE_END, ['name' => 'yes']);
        $no = $this->makeStep($funnel, FunnelStep::TYPE_END, ['name' => 'no']);
        $step = $this->makeStep($funnel, FunnelStep::TYPE_CONDITION, $condition + [
            'next_step_yes_id' => $yes->id,
            'next_step_no_id' => $no->id,
        ]);

        $this->makeChain($funnel, ...[...$before, $step]);

        return [$funnel, $yes, $no];
    }

    private function runProcessor(): void
    {
        $this->artisan('funnels:process')->assertSuccessful();
    }

    // ===== Conditions =====

    public function test_opened_waits_for_an_open_of_the_last_email_the_funnel_sent(): void
    {
        $message = $this->makeEmail();
        $funnel = $this->makeFunnel();
        $email = $this->makeStep($funnel, FunnelStep::TYPE_EMAIL, ['message_id' => $message->id]);
        [$funnel, $yes] = $this->conditionFunnel(
            ['funnel_id' => $funnel->id, 'condition_type' => FunnelStep::CONDITION_EMAIL_OPENED, 'wait_for_condition' => true],
            [$email]
        );

        $subscriber = $this->makeSubscriber();
        $enrollment = $this->enroll($funnel, $subscriber);
        $this->assertSame(FunnelSubscriber::STATUS_WAITING_CONDITION, $enrollment->status);

        // An open of another email does not count
        EmailOpen::create(['message_id' => $this->makeEmail('Other')->id, 'subscriber_id' => $subscriber->id, 'opened_at' => now()]);
        $this->runProcessor();
        $this->assertSame(FunnelSubscriber::STATUS_WAITING_CONDITION, $enrollment->fresh()->status);

        EmailOpen::create(['message_id' => $message->id, 'subscriber_id' => $subscriber->id, 'opened_at' => now()]);
        $this->runProcessor();

        $this->assertSame(FunnelSubscriber::STATUS_COMPLETED, $enrollment->fresh()->status);
        $this->assertSame($yes->id, $enrollment->fresh()->current_step_id);
    }

    public function test_clicked_and_link_clicked_read_the_recorded_clicks(): void
    {
        $message = $this->makeEmail();
        [$clickedFunnel, $clickedYes, $clickedNo] = $this->conditionFunnel(['condition_type' => FunnelStep::CONDITION_EMAIL_CLICKED, 'condition_config' => ['message_id' => $message->id]]);
        [$linkFunnel, $linkYes, $linkNo] = $this->conditionFunnel(['condition_type' => FunnelStep::CONDITION_LINK_CLICKED, 'condition_config' => ['url' => 'https://example.com/offer']]);

        $clicker = $this->makeSubscriber('clicker@example.com');
        EmailClick::create(['message_id' => $message->id, 'subscriber_id' => $clicker->id, 'url' => 'https://example.com/offer', 'clicked_at' => now()]);

        $otherLink = $this->makeSubscriber('other@example.com');
        EmailClick::create(['message_id' => $message->id, 'subscriber_id' => $otherLink->id, 'url' => 'https://example.com/blog', 'clicked_at' => now()]);

        $nobody = $this->makeSubscriber('nobody@example.com');

        $this->assertSame($clickedYes->id, $this->enroll($clickedFunnel, $clicker)->current_step_id);
        $this->assertSame($clickedYes->id, $this->enroll($clickedFunnel, $otherLink)->current_step_id);
        $this->assertSame($clickedNo->id, $this->enroll($clickedFunnel, $nobody)->current_step_id);

        $this->assertSame($linkYes->id, $this->enroll($linkFunnel, $clicker)->current_step_id);
        $this->assertSame($linkNo->id, $this->enroll($linkFunnel, $otherLink)->current_step_id);
    }

    public function test_tag_exists_checks_the_accounts_tag_by_name(): void
    {
        [$funnel, $yes, $no] = $this->conditionFunnel(['condition_type' => FunnelStep::CONDITION_TAG_EXISTS, 'condition_config' => ['tag' => 'customer']]);

        $tagged = $this->makeSubscriber('tagged@example.com');
        $tagged->addTag(Tag::create(['user_id' => $this->user->id, 'name' => 'customer']));

        $untagged = $this->makeSubscriber('untagged@example.com');

        $this->assertSame($yes->id, $this->enroll($funnel, $tagged)->current_step_id);
        $this->assertSame($no->id, $this->enroll($funnel, $untagged)->current_step_id);
    }

    public function test_a_waiting_field_value_condition_is_met_once_the_field_is_set(): void
    {
        [$funnel, $yes] = $this->conditionFunnel([
            'condition_type' => FunnelStep::CONDITION_FIELD_VALUE,
            'condition_config' => ['field' => 'plan', 'operator' => 'equals', 'value' => 'pro'],
            'wait_for_condition' => true,
        ]);

        $subscriber = $this->makeSubscriber();
        $enrollment = $this->enroll($funnel, $subscriber);
        $this->assertSame(FunnelSubscriber::STATUS_WAITING_CONDITION, $enrollment->status);

        $field = CustomField::create(['user_id' => $this->user->id, 'name' => 'plan', 'label' => 'Plan', 'type' => 'text']);
        SubscriberFieldValue::create(['subscriber_id' => $subscriber->id, 'custom_field_id' => $field->id, 'value' => 'pro']);

        // The retry service had its own copy of the conditions, without field_value
        $this->runProcessor();

        $this->assertSame($yes->id, $enrollment->fresh()->current_step_id);
        $this->assertSame(FunnelSubscriber::STATUS_COMPLETED, $enrollment->fresh()->status);
    }

    // ===== Reminders =====

    public function test_a_reminder_without_its_own_email_resends_the_email_the_condition_is_about(): void
    {
        $message = $this->makeEmail();
        $funnel = $this->makeFunnel();
        $email = $this->makeStep($funnel, FunnelStep::TYPE_EMAIL, ['message_id' => $message->id]);
        [$funnel] = $this->conditionFunnel([
            'funnel_id' => $funnel->id,
            'condition_type' => FunnelStep::CONDITION_EMAIL_OPENED,
            'wait_for_condition' => true,
            'retry_enabled' => true,
            'retry_max_attempts' => 1,
            'retry_interval_value' => 1,
            'retry_interval_unit' => 'hours',
        ], [$email]);

        $this->enroll($funnel, $this->makeSubscriber());
        Queue::assertPushed(SendEmailJob::class, 1);

        $this->travel(1)->hours();
        $this->runProcessor();

        Queue::assertPushed(SendEmailJob::class, 2);
        Queue::assertPushed(SendEmailJob::class, fn (SendEmailJob $job) => $job->message->is($message));
    }

    public function test_reminders_with_no_email_at_all_are_counted_so_the_exhausted_action_applies(): void
    {
        [$funnel, $yes, $no] = $this->conditionFunnel([
            'condition_type' => FunnelStep::CONDITION_TASK_COMPLETED,
            'condition_config' => ['task_id' => 'quiz'],
            'wait_for_condition' => true,
            'retry_enabled' => true,
            'retry_max_attempts' => 2,
            'retry_interval_value' => 1,
            'retry_interval_unit' => 'hours',
        ]);

        $enrollment = $this->enroll($funnel, $this->makeSubscriber());

        foreach ([1, 1, 1] as $hours) {
            $this->travel($hours)->hours();
            $this->runProcessor();
        }

        $this->assertSame(2, FunnelStepRetry::where('funnel_subscriber_id', $enrollment->id)->count());
        $this->assertSame($no->id, $enrollment->fresh()->current_step_id);
        $this->assertSame(FunnelSubscriber::STATUS_COMPLETED, $enrollment->fresh()->status);
        Queue::assertNotPushed(SendEmailJob::class);
    }

    // ===== Actions =====

    private function actionFunnel(string $actionType, array $config, array $funnelAttributes = []): Funnel
    {
        $funnel = $this->makeFunnel($funnelAttributes);
        $this->makeChain(
            $funnel,
            $this->makeStep($funnel, FunnelStep::TYPE_ACTION, ['action_type' => $actionType, 'action_config' => $config]),
            $this->makeStep($funnel, FunnelStep::TYPE_END)
        );

        return $funnel;
    }

    public function test_tag_actions_add_a_tag_by_name_creating_it_and_remove_one(): void
    {
        $subscriber = $this->makeSubscriber();
        $old = Tag::create(['user_id' => $this->user->id, 'name' => 'lead']);
        $subscriber->addTag($old);

        $this->enroll($this->actionFunnel(FunnelStep::ACTION_ADD_TAG, ['tag' => 'customer']), $subscriber);
        $this->enroll($this->actionFunnel(FunnelStep::ACTION_REMOVE_TAG, ['tag' => 'lead']), $subscriber);

        $names = $subscriber->tags()->pluck('name')->all();
        $this->assertSame(['customer'], $names);
        $this->assertSame(1, Tag::where('user_id', $this->user->id)->where('name', 'customer')->count());

        // A real TagAdded, so a funnel triggered by that tag starts too
        Queue::assertPushed(CallQueuedListener::class, fn (CallQueuedListener $job) => $job->class === EnrollInTriggeredFunnels::class
            && $job->data[0] instanceof TagAdded
            && $job->data[0]->tag->name === 'customer');
    }

    public function test_unsubscribe_changes_only_an_active_membership_and_drops_its_planned_mail(): void
    {
        Event::fake([SubscriberUnsubscribed::class]);

        $list = $this->makeList();
        $autoresponder = Message::create(['user_id' => $this->user->id, 'channel' => 'email', 'type' => 'autoresponder', 'subject' => 'Day 3', 'content' => 'x', 'status' => 'scheduled']);
        $autoresponder->contactLists()->attach($list->id);

        $active = $this->makeSubscriber('active@example.com');
        $active->contactLists()->attach($list->id, ['status' => 'active', 'subscribed_at' => now()]);
        MessageQueueEntry::create(['message_id' => $autoresponder->id, 'subscriber_id' => $active->id, 'status' => MessageQueueEntry::STATUS_PLANNED]);

        $bounced = $this->makeSubscriber('bounced@example.com');
        $bounced->contactLists()->attach($list->id, ['status' => 'bounced', 'subscribed_at' => now()]);

        $funnel = $this->actionFunnel(FunnelStep::ACTION_UNSUBSCRIBE, ['list_id' => $list->id]);
        $activeEnrollment = $this->enroll($funnel, $active);
        $bouncedEnrollment = $this->enroll($funnel, $bounced);

        $this->assertSame('unsubscribed', $active->contactLists()->find($list->id)->pivot->status);
        $this->assertNotNull($active->contactLists()->find($list->id)->pivot->unsubscribed_at);
        $this->assertSame(0, MessageQueueEntry::where('subscriber_id', $active->id)->count());
        Event::assertDispatched(SubscriberUnsubscribed::class, fn ($event) => $event->subscriber->is($active) && $event->reason === 'funnel');

        $this->assertSame('bounced', $bounced->contactLists()->find($list->id)->pivot->status);
        $this->assertContains('unsubscribe_skipped', $this->historyActions($bouncedEnrollment));
        $this->assertSame(FunnelSubscriber::STATUS_COMPLETED, $activeEnrollment->status);
    }

    public function test_unsubscribe_without_a_list_uses_the_signup_list_also_when_reminders_run_out(): void
    {
        Event::fake([SubscriberUnsubscribed::class]);

        $list = $this->makeList();
        $subscriber = $this->makeSubscriber();
        $subscriber->contactLists()->attach($list->id, ['status' => 'active', 'subscribed_at' => now()]);

        $this->enroll($this->actionFunnel(FunnelStep::ACTION_UNSUBSCRIBE, [], ['trigger_type' => Funnel::TRIGGER_LIST_SIGNUP, 'trigger_list_id' => $list->id]), $subscriber);
        $this->assertSame('unsubscribed', $subscriber->contactLists()->find($list->id)->pivot->status);

        $other = $this->makeSubscriber('other@example.com');
        $other->contactLists()->attach($list->id, ['status' => 'active', 'subscribed_at' => now()]);

        [$funnel] = $this->conditionFunnel([
            'condition_type' => FunnelStep::CONDITION_TASK_COMPLETED,
            'condition_config' => ['task_id' => 'quiz'],
            'wait_for_condition' => true,
            'retry_enabled' => true,
            'retry_max_attempts' => 1,
            'retry_interval_value' => 1,
            'retry_interval_unit' => 'hours',
            'retry_exhausted_action' => FunnelStep::RETRY_ACTION_UNSUBSCRIBE,
        ]);
        $funnel->update(['trigger_type' => Funnel::TRIGGER_LIST_SIGNUP, 'trigger_list_id' => $list->id]);

        $enrollment = $this->enroll($funnel, $other);
        $this->travel(1)->hours();
        $this->runProcessor();
        $this->travel(1)->hours();
        $this->runProcessor();

        $this->assertSame(FunnelSubscriber::STATUS_EXITED, $enrollment->fresh()->status);
        $this->assertSame('unsubscribed', $other->contactLists()->find($list->id)->pivot->status);
    }

    public function test_notify_emails_the_funnel_owner_or_the_configured_address(): void
    {
        $subscriber = $this->makeSubscriber('jan@example.com', ['first_name' => 'Jan']);

        $this->enroll($this->actionFunnel(FunnelStep::ACTION_NOTIFY, [], ['name' => 'Onboarding']), $subscriber);
        $this->enroll($this->actionFunnel(FunnelStep::ACTION_NOTIFY, [
            'email' => 'sales@example.com',
            'subject' => 'Hot lead in {{funnel_name}}',
            'message' => '{{subscriber_name}} <{{subscriber_email}}>',
        ], ['name' => 'Sales']), $subscriber);

        $sent = collect(Mail::mailer()->getSymfonyTransport()->messages())->map(fn ($message) => $message->getOriginalMessage());

        $this->assertCount(2, $sent);
        $this->assertSame($this->user->email, $sent[0]->getTo()[0]->getAddress());
        $this->assertStringContainsString('Onboarding', $sent[0]->getSubject());
        $this->assertSame('sales@example.com', $sent[1]->getTo()[0]->getAddress());
        $this->assertSame('Hot lead in Sales', $sent[1]->getSubject());
        $this->assertSame('Jan <jan@example.com>', trim($sent[1]->getTextBody()));
    }

    // ===== SMS =====

    public function test_an_sms_step_queues_its_text_and_skips_subscribers_who_cannot_get_it(): void
    {
        $funnel = $this->makeFunnel();
        $end = $this->makeStep($funnel, FunnelStep::TYPE_END);
        $sms = $this->makeStep($funnel, FunnelStep::TYPE_SMS, ['sms_content' => 'Hi [[first_name]]', 'next_step_id' => $end->id]);
        $this->makeChain($funnel, $sms);

        $withPhone = $this->makeSubscriber('phone@example.com', ['phone' => '+48500100200']);
        $noPhone = $this->makeSubscriber('nophone@example.com');
        $inactive = $this->makeSubscriber('inactive@example.com', ['phone' => '+48500100201', 'is_active_global' => false]);

        $sent = $this->enroll($funnel, $withPhone);
        $skippedNoPhone = $this->enroll($funnel, $noPhone);
        $skippedInactive = $this->enroll($funnel, $inactive);

        Queue::assertPushed(SendFunnelSmsJob::class, 1);
        Queue::assertPushed(SendFunnelSmsJob::class, fn (SendFunnelSmsJob $job) => $job->subscriber->is($withPhone)
            && $job->content === 'Hi [[first_name]]'
            && $job->userId === $this->user->id);

        $this->assertContains('sms_queued', $this->historyActions($sent));
        $this->assertSame('Subscriber has no phone number', $this->historyEntry($skippedNoPhone, 'sms_skipped')['details']['reason']);
        $this->assertSame('Subscriber is inactive', $this->historyEntry($skippedInactive, 'sms_skipped')['details']['reason']);

        foreach ([$sent, $skippedNoPhone, $skippedInactive] as $enrollment) {
            $this->assertSame(FunnelSubscriber::STATUS_COMPLETED, $enrollment->status);
        }
    }

    /**
     * An SMS provider whose driver expects exactly this text.
     */
    private function fakeSmsProvider(string $expectedPhone, string $expectedText): void
    {
        $provider = Mockery::mock(SmsProvider::class)->makePartial();
        $provider->shouldReceive('hasReachedDailyLimit')->andReturn(false);
        $provider->shouldReceive('incrementSentCount')->once();

        $driver = Mockery::mock(SmsProviderInterface::class);
        $driver->shouldReceive('send')->once()->with($expectedPhone, $expectedText)->andReturn(SmsResult::success('sms-1'));

        $this->mock(SmsProviderService::class, function ($service) use ($provider, $driver) {
            $service->shouldReceive('getBestProvider')->andReturn($provider);
            $service->shouldReceive('getProvider')->andReturn($driver);
        });
    }

    public function test_the_funnel_sms_job_sends_the_text_with_placeholders_filled_in(): void
    {
        $subscriber = $this->makeSubscriber('jan@example.com', ['first_name' => 'Jan', 'phone' => '+48500100200']);
        $this->fakeSmsProvider('+48500100200', 'Hi Jan');

        (new SendFunnelSmsJob($subscriber, 'Hi [[first_name]]', $this->user->id))
            ->handle(app(SmsProviderService::class), app(PlaceholderService::class));
    }

    public function test_the_sms_job_no_longer_calls_a_missing_placeholder_method(): void
    {
        $subscriber = $this->makeSubscriber('jan@example.com', ['first_name' => 'Jan', 'phone' => '+48500100200']);
        $message = Message::create(['user_id' => $this->user->id, 'channel' => 'sms', 'type' => 'broadcast', 'subject' => 'SMS', 'content' => 'Hi [[first_name]]', 'status' => 'scheduled']);
        $this->fakeSmsProvider('+48500100200', 'Hi Jan');

        (new SendSmsJob($message, $subscriber))
            ->handle(app(SmsProviderService::class), app(PlaceholderService::class));
    }

    // ===== Wait until =====

    private function waitUntilFunnel(array $waitUntil): array
    {
        $funnel = $this->makeFunnel();
        $end = $this->makeStep($funnel, FunnelStep::TYPE_END);
        $wait = $this->makeStep($funnel, FunnelStep::TYPE_WAIT_UNTIL, $waitUntil + ['next_step_id' => $end->id]);
        $this->makeChain($funnel, $wait);

        return [$funnel, $end];
    }

    public function test_wait_until_a_date_holds_the_enrollment_until_that_moment_in_the_steps_time_zone(): void
    {
        [$funnel, $end] = $this->waitUntilFunnel([
            'wait_until_type' => FunnelStep::WAIT_UNTIL_SPECIFIC_DATE,
            'wait_until_date' => '2026-09-20',
            'wait_until_time' => '10:30',
            'wait_until_timezone' => 'Europe/Warsaw',
        ]);

        $enrollment = $this->enroll($funnel, $this->makeSubscriber());

        $this->assertSame(FunnelSubscriber::STATUS_WAITING, $enrollment->status);
        $this->assertSame('2026-09-20 08:30:00', $enrollment->next_action_at->format('Y-m-d H:i:s'));
        $this->assertSame($end->id, $enrollment->current_step_id);

        Carbon::setTestNow('2026-09-20 08:30:00');
        $this->runProcessor();

        $this->assertSame(FunnelSubscriber::STATUS_COMPLETED, $enrollment->fresh()->status);
    }

    public function test_wait_until_a_date_already_past_continues_at_once(): void
    {
        [$funnel] = $this->waitUntilFunnel([
            'wait_until_type' => FunnelStep::WAIT_UNTIL_SPECIFIC_DATE,
            'wait_until_date' => '2026-09-01',
        ]);

        $this->assertSame(FunnelSubscriber::STATUS_COMPLETED, $this->enroll($funnel, $this->makeSubscriber())->status);
    }

    public function test_wait_until_a_weekday_picks_its_next_occurrence(): void
    {
        [$thursday] = $this->waitUntilFunnel(['wait_until_type' => FunnelStep::WAIT_UNTIL_DAY_OF_WEEK, 'wait_until_day' => 4, 'wait_until_time' => '09:00']);
        // Monday 10:00 already past Monday 08:00: next week
        [$monday] = $this->waitUntilFunnel(['wait_until_type' => FunnelStep::WAIT_UNTIL_DAY_OF_WEEK, 'wait_until_day' => 1, 'wait_until_time' => '08:00']);
        [$unset] = $this->waitUntilFunnel(['wait_until_type' => FunnelStep::WAIT_UNTIL_DAY_OF_WEEK]);

        $this->assertSame('2026-09-17 09:00', $this->enroll($thursday, $this->makeSubscriber('a@example.com'))->next_action_at->format('Y-m-d H:i'));
        $this->assertSame('2026-09-21 08:00', $this->enroll($monday, $this->makeSubscriber('b@example.com'))->next_action_at->format('Y-m-d H:i'));
        $this->assertSame(FunnelSubscriber::STATUS_COMPLETED, $this->enroll($unset, $this->makeSubscriber('c@example.com'))->status);
    }

    public function test_wait_until_business_hours(): void
    {
        [$funnel] = $this->waitUntilFunnel(['wait_until_type' => FunnelStep::WAIT_UNTIL_BUSINESS_HOURS, 'wait_until_timezone' => 'UTC']);

        // Monday 10:00: within business hours
        $this->assertSame(FunnelSubscriber::STATUS_COMPLETED, $this->enroll($funnel, $this->makeSubscriber('now@example.com'))->status);

        Carbon::setTestNow('2026-09-18 18:00:00'); // Friday evening
        $this->assertSame('2026-09-21 09:00', $this->enroll($funnel, $this->makeSubscriber('friday@example.com'))->next_action_at->format('Y-m-d H:i'));

        Carbon::setTestNow('2026-09-15 07:00:00'); // Tuesday morning
        $this->assertSame('2026-09-15 09:00', $this->enroll($funnel, $this->makeSubscriber('tuesday@example.com'))->next_action_at->format('Y-m-d H:i'));
    }
}
