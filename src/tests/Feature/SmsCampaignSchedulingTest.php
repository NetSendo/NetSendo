<?php

namespace Tests\Feature;

use App\Models\ContactList;
use App\Models\Message;
use App\Models\SmsProvider;
use App\Models\User;
use App\Services\Sms\SmsProviderInterface;
use App\Services\Sms\SmsProviderService;
use App\Services\Sms\SmsResult;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * SMS campaigns saved in the panel never went out: "Send now" stored status `sent`, which
 * the SMS cron does not process; "Schedule" wrote the date to `send_at` while the cron reads
 * `scheduled_at`; queue SMS were stored `sent` instead of `scheduled`; and "Send test SMS"
 * passed a parameter the providers do not have, so it always failed.
 */
class SmsCampaignSchedulingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ContactList $list;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['timezone' => 'Europe/Warsaw']);
        $this->list = ContactList::create([
            'user_id' => $this->user->id,
            'name' => 'SMS customers',
            'type' => 'sms',
            'is_public' => true,
        ]);
    }

    private function save(array $fields, ?Message $existing = null): Message
    {
        $payload = $fields + [
            'subject' => 'Spring sale',
            'content' => 'Hi [[fname]], 20% off today.',
            'list_id' => $this->list->id,
            'type' => 'broadcast',
        ];

        $response = $existing
            ? $this->actingAs($this->user)->put(route('sms.update', $existing->id), $payload)
            : $this->actingAs($this->user)->post(route('sms.store'), $payload);

        $response->assertRedirect(route('sms.index'));

        return $existing ? $existing->fresh() : Message::where('channel', 'sms')->latest('id')->firstOrFail();
    }

    /** The messages the SMS cron would process now (CronScheduleService::processSmsQueue). */
    private function dueForCron(): array
    {
        return Message::where('status', 'scheduled')
            ->where('channel', 'sms')
            ->where(function ($query) {
                $query->where('type', 'broadcast')
                    ->orWhere(fn ($q) => $q->where('type', 'autoresponder')->where('is_active', true));
            })
            ->where(fn ($q) => $q->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', now()))
            ->pluck('id')
            ->all();
    }

    public function test_send_now_queues_the_campaign_for_the_cron(): void
    {
        $message = $this->save(['status' => 'sent']);

        $this->assertSame('scheduled', $message->status);
        $this->assertNotNull($message->scheduled_at);
        $this->assertTrue($message->scheduled_at->lte(now()));
        $this->assertSame([$message->id], $this->dueForCron());
    }

    public function test_schedule_sends_at_the_chosen_time_in_the_users_timezone(): void
    {
        $message = $this->save(['status' => 'sent', 'schedule_date' => '2030-01-15T09:00']);

        $this->assertSame('scheduled', $message->status);
        // 09:00 in Warsaw (UTC+1 in January) is 08:00 UTC
        $this->assertSame('2030-01-15 08:00:00', $message->scheduled_at->copy()->setTimezone('UTC')->format('Y-m-d H:i:s'));
        $this->assertSame([], $this->dueForCron(), 'A campaign scheduled for later is not sent now');

        Carbon::setTestNow('2030-01-15 08:00:30');
        $this->assertSame([$message->id], $this->dueForCron());
        Carbon::setTestNow();
    }

    public function test_the_edit_form_shows_the_scheduled_time_in_the_users_timezone(): void
    {
        $message = $this->save(['status' => 'sent', 'schedule_date' => '2030-01-15T09:00']);

        $this->actingAs($this->user)
            ->get(route('sms.edit', $message->id))
            ->assertInertia(fn ($page) => $page->where('sms.scheduled_at', '2030-01-15T09:00'));
    }

    public function test_an_activated_queue_sms_is_processed_by_the_cron(): void
    {
        $message = $this->save(['status' => 'sent', 'type' => 'autoresponder', 'day' => 1]);

        $this->assertSame('scheduled', $message->status);
        $this->assertTrue((bool) $message->is_active);
        $this->assertNull($message->scheduled_at);
        $this->assertSame([$message->id], $this->dueForCron());
    }

    public function test_a_draft_is_not_sent(): void
    {
        $message = $this->save(['status' => 'draft']);

        $this->assertSame('draft', $message->status);
        $this->assertSame([], $this->dueForCron());
    }

    public function test_sending_a_saved_draft_queues_it(): void
    {
        $draft = $this->save(['status' => 'draft']);
        $message = $this->save(['status' => 'sent'], $draft);

        $this->assertSame('scheduled', $message->status);
        $this->assertSame([$message->id], $this->dueForCron());
    }

    public function test_a_test_sms_reaches_the_provider_with_its_content(): void
    {
        SmsProvider::create([
            'user_id' => $this->user->id,
            'name' => 'SMSAPI',
            'provider' => 'smsapi',
            'credentials' => \Illuminate\Support\Facades\Crypt::encryptString('{"token":"test"}'),
            'is_active' => true,
            'is_default' => true,
        ]);

        $sent = [];
        $driver = Mockery::mock(SmsProviderInterface::class);
        $driver->shouldReceive('send')->once()->andReturnUsing(function (string $to, string $content) use (&$sent) {
            $sent = compact('to', 'content');

            return SmsResult::success('sms-1');
        });
        $this->mock(SmsProviderService::class, fn ($service) => $service->shouldReceive('getProvider')->andReturn($driver));

        $this->actingAs($this->user)
            ->postJson(route('sms.test'), ['phone' => '+48500100200', 'content' => 'Hello'])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame(['to' => '+48500100200', 'content' => '[TEST] Hello'], $sent);
    }

    public function test_a_test_sms_the_provider_rejects_is_reported_as_an_error(): void
    {
        SmsProvider::create([
            'user_id' => $this->user->id,
            'name' => 'SMSAPI',
            'provider' => 'smsapi',
            'credentials' => \Illuminate\Support\Facades\Crypt::encryptString('{"token":"test"}'),
            'is_active' => true,
            'is_default' => true,
        ]);

        $driver = Mockery::mock(SmsProviderInterface::class);
        $driver->shouldReceive('send')->once()->andReturn(new SmsResult(success: false, errorMessage: 'Invalid number'));
        $this->mock(SmsProviderService::class, fn ($service) => $service->shouldReceive('getProvider')->andReturn($driver));

        $this->actingAs($this->user)
            ->postJson(route('sms.test'), ['phone' => '+48500100200', 'content' => 'Hello'])
            ->assertStatus(422)
            ->assertJson(['error' => 'Invalid number']);
    }
}
