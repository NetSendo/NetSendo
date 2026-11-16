<?php

namespace Tests\Feature;

use App\Models\AiExecutionLog;
use App\Models\AutoWebinarChatScript;
use App\Models\AutomationRule;
use App\Models\ContactList;
use App\Models\CronJobLog;
use App\Models\CrmTask;
use App\Models\Subscriber;
use App\Models\User;
use App\Models\Webinar;
use App\Models\WebinarChatMessage;
use App\Models\WebinarSession;
use App\Services\Automation\AutomationService;
use App\Services\Brain\TaskScorer;
use App\Services\GoogleCalendarService;
use App\Services\Webinar\AutoWebinarScriptBuilderService;
use App\Services\Webinar\AutoWebinarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Carbon 3 returns signed float differences: $earlier->diffInX($later) is
 * positive, $later->diffInX($earlier) negative. Code written for Carbon 2
 * (always absolute) diffed the wrong way round in these places.
 */
class CarbonSignedDiffTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->freezeSecond();

        $this->user = User::factory()->create();
    }

    // ---- Automation: "subscribed at least N days ago" condition ----------

    private function subscribedDaysAgoMet(int $subscribedDaysAgo, int $days): bool
    {
        $list = ContactList::create([
            'user_id' => $this->user->id,
            'name' => 'Newsletter',
            'type' => 'email',
            'is_public' => true,
        ]);

        $subscriber = Subscriber::create([
            'user_id' => $this->user->id,
            'email' => "sub{$subscribedDaysAgo}-{$days}@example.com",
            'status' => 'active',
            'is_active_global' => true,
        ]);
        $subscriber->contactLists()->attach($list->id, [
            'status' => 'active',
            'subscribed_at' => now()->subDays($subscribedDaysAgo),
        ]);

        $rule = new AutomationRule([
            'user_id' => $this->user->id,
            'conditions' => [['type' => 'subscribed_days_ago', 'value' => (string) $days]],
            'condition_logic' => 'all',
        ]);

        return app(AutomationService::class)->evaluateConditions($rule, [
            'subscriber_id' => $subscriber->id,
            'user_id' => $this->user->id,
            'list_id' => $list->id,
        ]);
    }

    public function test_subscribed_days_ago_condition_matches_subscribers_older_than_n_days(): void
    {
        $this->assertTrue($this->subscribedDaysAgoMet(10, 7));
        $this->assertTrue($this->subscribedDaysAgoMet(7, 7));
    }

    public function test_subscribed_days_ago_condition_rejects_newer_subscribers(): void
    {
        $this->assertFalse($this->subscribedDaysAgoMet(3, 7));
        $this->assertFalse($this->subscribedDaysAgoMet(10, 30));
    }

    // ---- Brain: urgency of an agent that has not run for a while ---------

    public function test_task_urgency_grows_with_days_since_the_agents_last_run(): void
    {
        foreach (['campaign' => 14, 'list' => 0] as $agent => $daysAgo) {
            $log = AiExecutionLog::create([
                'user_id' => $this->user->id,
                'agent_type' => $agent,
                'action' => 'cron_task',
                'status' => 'success',
            ]);
            AiExecutionLog::whereKey($log->id)->update(['created_at' => now()->subDays($daysAgo)]);
        }

        $scored = app(TaskScorer::class)->scoreAll([
            ['title' => 'Send a broadcast', 'category' => 'send_broadcast', 'agent' => 'campaign'],
            ['title' => 'Clean the list', 'category' => 'send_broadcast', 'agent' => 'list'],
        ], $this->user, 0)->keyBy('agent');

        // 14 days idle: 5 + 14 * 1.5 = 26, capped at 25; ran today: 5
        $this->assertSame(25, $scored['campaign']['score_breakdown']['urgency']);
        $this->assertSame(5, $scored['list']['score_breakdown']['urgency']);
    }

    // ---- Cron: duration of a job run ---------------------------------------

    public function test_cron_job_duration_is_positive(): void
    {
        $log = CronJobLog::create([
            'job_name' => 'email_queue_processor',
            'started_at' => now()->subSeconds(90),
            'completed_at' => now(),
            'status' => 'success',
            'emails_sent' => 0,
            'emails_failed' => 0,
        ]);

        $this->assertSame(90, $log->fresh()->duration);
        $this->assertEquals(90, CronJobLog::getLast24HoursStats()['avg_duration']);
    }

    // ---- Google Calendar: reminder offset of a CRM task --------------------

    private function eventPayload(CrmTask $task): array
    {
        $service = app(GoogleCalendarService::class);

        return (fn () => $this->taskToEventPayload($task))->call($service);
    }

    public function test_calendar_reminder_is_set_minutes_before_the_task(): void
    {
        $task = CrmTask::create([
            'user_id' => $this->user->id,
            'title' => 'Call back',
            'type' => 'call',
            'due_date' => '2026-11-20 10:00:00',
            'reminder_at' => '2026-11-20 09:30:00',
        ]);

        $reminders = $this->eventPayload($task)['reminders'];

        $this->assertFalse($reminders['useDefault']);
        $this->assertSame(30, $reminders['overrides'][0]['minutes']);
    }

    public function test_calendar_reminder_without_due_date_uses_the_default(): void
    {
        $task = CrmTask::create([
            'user_id' => $this->user->id,
            'title' => 'Call back',
            'type' => 'call',
            'reminder_at' => '2026-11-20 09:30:00',
        ]);

        $this->assertSame(['useDefault' => true], $this->eventPayload($task)['reminders']);
    }

    // ---- Autowebinar: chat imported from a live webinar --------------------

    private function makeWebinar(string $slug, string $type, array $attributes = []): Webinar
    {
        return Webinar::create($attributes + [
            'user_id' => $this->user->id,
            'name' => 'Webinar ' . $slug,
            'slug' => $slug,
            'type' => $type,
            'status' => Webinar::STATUS_ENDED,
        ]);
    }

    private function chatMessage(Webinar $webinar, ?WebinarSession $session, int $secondsAfterStart, string $text): void
    {
        $message = WebinarChatMessage::create([
            'webinar_id' => $webinar->id,
            'webinar_session_id' => $session?->id,
            'sender_type' => WebinarChatMessage::SENDER_ATTENDEE,
            'sender_name' => 'Anna',
            'message' => $text,
            'message_type' => 'text',
            'is_visible' => true,
            'is_deleted' => false,
            'likes_count' => 0,
        ]);
        WebinarChatMessage::whereKey($message->id)->update([
            'created_at' => now()->subHour()->addSeconds($secondsAfterStart),
        ]);
    }

    public function test_chat_imported_from_a_live_session_keeps_its_offset(): void
    {
        $live = $this->makeWebinar('live-source', Webinar::TYPE_LIVE);
        $auto = $this->makeWebinar('auto-target', Webinar::TYPE_AUTO);
        $session = WebinarSession::create([
            'webinar_id' => $live->id,
            'scheduled_at' => now()->subHour(),
            'started_at' => now()->subHour(),
            'ended_at' => now(),
            'status' => 'ended',
        ]);
        $this->chatMessage($live, $session, 125, 'Hello from minute two');

        $this->assertSame(1, app(AutoWebinarService::class)->importChatFromLive($auto, $session));

        $this->assertSame(125, (int) AutoWebinarChatScript::where('webinar_id', $auto->id)->value('show_at_seconds'));
    }

    public function test_chat_imported_from_a_webinar_keeps_its_offset(): void
    {
        $source = $this->makeWebinar('live-source', Webinar::TYPE_LIVE, ['started_at' => now()->subHour()]);
        $target = $this->makeWebinar('auto-target', Webinar::TYPE_AUTO);
        $this->chatMessage($source, null, 125, 'Hello from minute two');
        $this->chatMessage($source, null, -30, 'Written before the start');

        $this->assertSame(2, app(AutoWebinarScriptBuilderService::class)->importFromWebinar($target, $source));

        $offsets = AutoWebinarChatScript::where('webinar_id', $target->id)
            ->pluck('show_at_seconds', 'message_text')
            ->map(fn ($seconds) => (int) $seconds)
            ->all();

        $this->assertSame(['Written before the start' => 0, 'Hello from minute two' => 125], $offsets);
    }
}
