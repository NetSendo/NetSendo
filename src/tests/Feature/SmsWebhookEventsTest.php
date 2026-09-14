<?php

namespace Tests\Feature;

use App\Jobs\DispatchWebhookJob;
use App\Jobs\SendFunnelSmsJob;
use App\Jobs\SendSmsJob;
use App\Models\Message;
use App\Models\MessageQueueEntry;
use App\Models\SmsProvider;
use App\Models\Subscriber;
use App\Models\Webhook;
use App\Services\PlaceholderService;
use App\Services\Sms\SmsProviderInterface;
use App\Services\Sms\SmsProviderService;
use App\Services\Sms\SmsResult;
use App\Services\WebhookDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Concerns\BuildsFunnels;
use Tests\TestCase;

/**
 * `sms.sent` and `sms.failed` were in the webhook catalogue and offered by the
 * n8n trigger, but nothing dispatched them, so webhooks and workflows on them
 * never fired.
 */
class SmsWebhookEventsTest extends TestCase
{
    use RefreshDatabase;
    use BuildsFunnels;

    /** @var array<int, array{user_id: int, event: string, data: array}> */
    private array $webhooks = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpFunnelOwner();
    }

    /**
     * Records what the jobs hand to the webhook dispatcher.
     */
    private function recordWebhooks(): void
    {
        $this->mock(WebhookDispatcher::class, function ($dispatcher) {
            $dispatcher->shouldReceive('dispatch')->andReturnUsing(function (int $userId, string $event, array $data) {
                $this->webhooks[] = ['user_id' => $userId, 'event' => $event, 'data' => $data];
            });
        });
    }

    private function events(): array
    {
        return array_column($this->webhooks, 'event');
    }

    /**
     * The account's SMS provider "SMSAPI" whose driver answers each send in turn
     * with the given results; an exception is thrown instead of returned.
     */
    private function fakeSmsProvider(SmsResult|\Throwable ...$answers): void
    {
        $provider = Mockery::mock(SmsProvider::class)->makePartial();
        $provider->name = 'SMSAPI';
        $provider->shouldReceive('hasReachedDailyLimit')->andReturn(false);
        $provider->shouldReceive('incrementSentCount');

        $driver = Mockery::mock(SmsProviderInterface::class);
        $driver->shouldReceive('send')->times(count($answers))->andReturnUsing(function () use (&$answers) {
            $answer = array_shift($answers);

            return $answer instanceof \Throwable ? throw $answer : $answer;
        });

        $this->mock(SmsProviderService::class, function ($service) use ($provider, $driver) {
            $service->shouldReceive('getBestProvider')->andReturn($provider);
            $service->shouldReceive('getProvider')->andReturn($driver);
        });
    }

    private function subscriber(array $attributes = []): Subscriber
    {
        return $this->makeSubscriber('jan@example.com', $attributes + ['first_name' => 'Jan', 'phone' => '+48500100200']);
    }

    private function smsMessage(): Message
    {
        return Message::create([
            'user_id' => $this->user->id,
            'channel' => 'sms',
            'type' => 'broadcast',
            'subject' => 'SMS',
            'content' => 'Hi [[first_name]]',
            'status' => 'scheduled',
        ]);
    }

    private function runHandle(SendSmsJob|SendFunnelSmsJob $job): void
    {
        $job->handle(app(SmsProviderService::class), app(PlaceholderService::class));
    }

    // ===== sms.sent =====

    public function test_a_campaign_sms_the_provider_accepted_dispatches_sms_sent_once(): void
    {
        $this->recordWebhooks();
        $this->fakeSmsProvider(SmsResult::success('sms-1', credits: 0.16, parts: 1));
        $subscriber = $this->subscriber();
        $message = $this->smsMessage();

        $this->runHandle(new SendSmsJob($message, $subscriber));

        $this->assertSame([[
            'user_id' => $this->user->id,
            'event' => 'sms.sent',
            'data' => [
                'message_id' => $message->id,
                'funnel_step_id' => null,
                'subscriber_id' => $subscriber->id,
                'phone' => '+48500100200',
                'provider' => 'SMSAPI',
                'content' => 'Hi Jan',
                'provider_message_id' => 'sms-1',
                'credits' => 0.16,
                'parts' => 1,
            ],
        ]], $this->webhooks);
    }

    public function test_a_funnel_sms_the_provider_accepted_dispatches_sms_sent_with_its_step(): void
    {
        $this->recordWebhooks();
        $this->fakeSmsProvider(SmsResult::success('sms-2'));
        $subscriber = $this->subscriber();

        $this->runHandle(new SendFunnelSmsJob($subscriber, 'Hi [[first_name]]', $this->user->id, 42));

        $this->assertSame([[
            'user_id' => $this->user->id,
            'event' => 'sms.sent',
            'data' => [
                'message_id' => null,
                'funnel_step_id' => 42,
                'subscriber_id' => $subscriber->id,
                'phone' => '+48500100200',
                'provider' => 'SMSAPI',
                'content' => 'Hi Jan',
                'provider_message_id' => 'sms-2',
                'credits' => null,
                'parts' => null,
            ],
        ]], $this->webhooks);
    }

    public function test_sms_sent_reaches_a_webhook_registered_for_it(): void
    {
        Queue::fake();
        $this->fakeSmsProvider(SmsResult::success('sms-3'));
        $webhook = Webhook::create([
            'user_id' => $this->user->id,
            'name' => 'n8n Workflow: SMS',
            'url' => 'https://n8n.example.com/webhook/sms',
            'events' => ['sms.sent', 'sms.failed'],
            'is_active' => true,
        ]);

        $this->runHandle(new SendFunnelSmsJob($this->subscriber(), 'Hi', $this->user->id, 7));

        Queue::assertPushed(DispatchWebhookJob::class, 1);
        Queue::assertPushed(DispatchWebhookJob::class, fn (DispatchWebhookJob $job) => $job->webhookId === $webhook->id
            && $job->event === 'sms.sent'
            && $job->data['provider_message_id'] === 'sms-3'
            && $job->data['funnel_step_id'] === 7);
    }

    public function test_a_webhook_that_cannot_be_dispatched_does_not_fail_the_sent_sms(): void
    {
        $this->mock(WebhookDispatcher::class, function ($dispatcher) {
            $dispatcher->shouldReceive('dispatch')->once()->andThrow(new \RuntimeException('Queue is down'));
        });
        $this->fakeSmsProvider(SmsResult::success('sms-4'));
        $message = $this->smsMessage();
        $entry = MessageQueueEntry::create([
            'message_id' => $message->id,
            'subscriber_id' => $this->subscriber()->id,
            'status' => MessageQueueEntry::STATUS_QUEUED,
        ]);

        $this->runHandle(new SendSmsJob($message, $entry->subscriber, null, $entry->id));

        $this->assertSame('sent', $entry->fresh()->status);
    }

    // ===== sms.failed without a retry =====

    public function test_a_campaign_sms_the_provider_rejected_dispatches_sms_failed_at_once(): void
    {
        $this->recordWebhooks();
        $this->fakeSmsProvider(SmsResult::failure('Invalid phone number', 'INVALID_NUMBER'));
        $subscriber = $this->subscriber();
        $message = $this->smsMessage();

        $this->runHandle(new SendSmsJob($message, $subscriber));

        $this->assertSame([[
            'user_id' => $this->user->id,
            'event' => 'sms.failed',
            'data' => [
                'message_id' => $message->id,
                'funnel_step_id' => null,
                'subscriber_id' => $subscriber->id,
                'phone' => '+48500100200',
                'provider' => 'SMSAPI',
                'error' => 'Invalid phone number',
                'error_code' => 'INVALID_NUMBER',
            ],
        ]], $this->webhooks);
    }

    public function test_a_funnel_sms_the_provider_rejected_dispatches_sms_failed_at_once(): void
    {
        $this->recordWebhooks();
        $this->fakeSmsProvider(SmsResult::failure('Invalid phone number', 'INVALID_NUMBER'));
        $subscriber = $this->subscriber();

        $this->runHandle(new SendFunnelSmsJob($subscriber, 'Hi', $this->user->id, 42));

        $this->assertSame([[
            'user_id' => $this->user->id,
            'event' => 'sms.failed',
            'data' => [
                'message_id' => null,
                'funnel_step_id' => 42,
                'subscriber_id' => $subscriber->id,
                'phone' => '+48500100200',
                'provider' => 'SMSAPI',
                'error' => 'Invalid phone number',
                'error_code' => 'INVALID_NUMBER',
            ],
        ]], $this->webhooks);
    }

    public function test_an_sms_that_cannot_go_out_dispatches_sms_failed_with_the_reason(): void
    {
        $this->recordWebhooks();
        $this->mock(SmsProviderService::class, fn ($service) => $service->shouldReceive('getBestProvider')->andReturn(null));
        $noPhone = $this->makeSubscriber('nophone@example.com');
        $withPhone = $this->subscriber();

        $this->runHandle(new SendSmsJob($this->smsMessage(), $noPhone));
        $this->runHandle(new SendSmsJob($this->smsMessage(), $withPhone));
        $this->runHandle(new SendFunnelSmsJob($noPhone, 'Hi', $this->user->id, 42));
        $this->runHandle(new SendFunnelSmsJob($withPhone, 'Hi', $this->user->id, 42));

        $this->assertSame(['sms.failed', 'sms.failed', 'sms.failed', 'sms.failed'], $this->events());
        $this->assertSame(
            ['NO_PHONE', 'NO_PROVIDER', 'NO_PHONE', 'NO_PROVIDER'],
            array_map(fn (array $webhook) => $webhook['data']['error_code'], $this->webhooks)
        );
        $this->assertSame([null, null, null, null], array_map(fn (array $webhook) => $webhook['data']['provider'], $this->webhooks));
    }

    // ===== Exceptions are retried =====

    /**
     * Push the job onto the database queue and run a worker until the queue is
     * empty, moving the clock past each retry's backoff.
     */
    private function workThroughRetries(SendSmsJob|SendFunnelSmsJob $job): int
    {
        config(['queue.default' => 'database']);
        dispatch($job);

        $attempts = 0;
        while (Queue::connection('database')->size() > 0) {
            $this->artisan('queue:work', ['connection' => 'database', '--once' => true, '--sleep' => 0])->assertSuccessful();
            $attempts++;
            $this->travel(16)->minutes();
        }

        return $attempts;
    }

    private function jobOfKind(string $kind): SendSmsJob|SendFunnelSmsJob
    {
        return $kind === 'campaign'
            ? new SendSmsJob($this->smsMessage(), $this->subscriber())
            : new SendFunnelSmsJob($this->subscriber(), 'Hi [[first_name]]', $this->user->id, 42);
    }

    public static function jobKinds(): array
    {
        return ['campaign or API SMS' => ['campaign'], 'funnel SMS' => ['funnel']];
    }

    #[DataProvider('jobKinds')]
    public function test_an_exception_dispatches_sms_failed_once_only_after_the_last_attempt(string $kind): void
    {
        $this->recordWebhooks();
        $this->fakeSmsProvider(
            new \RuntimeException('Gateway timeout'),
            new \RuntimeException('Gateway timeout'),
            new \RuntimeException('Gateway timeout'),
        );

        $this->assertSame(3, $this->workThroughRetries($this->jobOfKind($kind)));

        $this->assertSame(['sms.failed'], $this->events());
        $this->assertSame('Gateway timeout', $this->webhooks[0]['data']['error']);
        $this->assertSame('EXCEPTION', $this->webhooks[0]['data']['error_code']);
        $this->assertSame('SMSAPI', $this->webhooks[0]['data']['provider']);
        $this->assertDatabaseCount('failed_jobs', 1);
    }

    #[DataProvider('jobKinds')]
    public function test_a_retry_that_goes_through_dispatches_only_sms_sent(string $kind): void
    {
        $this->recordWebhooks();
        $this->fakeSmsProvider(new \RuntimeException('Gateway timeout'), SmsResult::success('sms-5'));

        $this->assertSame(2, $this->workThroughRetries($this->jobOfKind($kind)));

        $this->assertSame(['sms.sent'], $this->events());
        $this->assertSame('sms-5', $this->webhooks[0]['data']['provider_message_id']);
        $this->assertDatabaseCount('failed_jobs', 0);
    }
}
