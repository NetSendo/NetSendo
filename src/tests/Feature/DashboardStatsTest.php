<?php

namespace Tests\Feature;

use App\Models\ContactList;
use App\Models\Message;
use App\Models\Subscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The dashboard shows one period (7, 30 or 90 days), compares it with the period before
 * and draws one value per day.
 */
class DashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-11 12:00:00');
        $this->user = User::factory()->create(['timezone' => 'UTC']);
    }

    private function stats(array $query = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->user)->getJson(route('api.dashboard.stats', $query))->assertOk();
    }

    private function cronRun(string $at, int $sent): void
    {
        DB::table('cron_job_logs')->insert([
            'job_name' => 'send', 'started_at' => $at, 'status' => 'success', 'emails_sent' => $sent,
            'created_at' => $at, 'updated_at' => $at,
        ]);
    }

    private function message(array $attributes = []): Message
    {
        return Message::create($attributes + [
            'user_id' => $this->user->id, 'subject' => 'Hello', 'content' => '<p>Hi</p>',
            'channel' => 'email', 'type' => 'broadcast', 'status' => 'sent',
        ]);
    }

    private function subscriber(string $email, ?string $createdAt = null): Subscriber
    {
        $subscriber = Subscriber::create(['user_id' => $this->user->id, 'email' => $email]);
        if ($createdAt) {
            DB::table('subscribers')->where('id', $subscriber->id)->update(['created_at' => $createdAt]);
        }

        return $subscriber;
    }

    private function event(string $table, Message $message, Subscriber $subscriber, string $at): void
    {
        $row = ['message_id' => $message->id, 'subscriber_id' => $subscriber->id, 'created_at' => $at, 'updated_at' => $at];
        $row[$table === 'email_opens' ? 'opened_at' : 'clicked_at'] = $at;
        if ($table === 'email_clicks') {
            $row['url'] = 'https://example.com';
        }
        DB::table($table)->insert($row);
    }

    public function test_the_default_period_is_thirty_days_and_unknown_periods_fall_back_to_it(): void
    {
        $this->assertSame(30, $this->stats()->json('period.days'));
        $this->assertSame(30, $this->stats(['period' => 45])->json('period.days'));
        $this->assertSame(7, $this->stats(['period' => 7])->json('period.days'));

        $stats = $this->stats(['period' => 90]);
        $this->assertSame('2026-08-14', $stats->json('period.from'));
        $this->assertSame('2026-11-11', $stats->json('period.to'));
        $this->assertCount(90, $stats->json('activity_chart'));
    }

    public function test_sent_mail_is_compared_with_the_period_before(): void
    {
        $this->cronRun('2026-11-11 08:00:00', 30);   // today
        $this->cronRun('2026-11-05 00:00:00', 10);   // first day of the 7-day period
        $this->cronRun('2026-11-04 23:59:59', 20);   // previous period
        $this->cronRun('2026-10-28 23:00:00', 500);  // before both

        $stats = $this->stats(['period' => 7]);

        $this->assertSame(40, $stats->json('emails_sent.total'));
        $this->assertSame(20, $stats->json('emails_sent.previous'));
        $this->assertEquals(100, $stats->json('emails_sent.trend'));
    }

    public function test_rates_count_each_recipient_once_and_the_trend_is_in_percentage_points(): void
    {
        $message = $this->message();
        $reader = $this->subscriber('reader@example.com');
        $other = $this->subscriber('other@example.com');

        $this->cronRun('2026-11-10 08:00:00', 4);
        foreach (range(1, 5) as $i) {
            $this->event('email_opens', $message, $reader, '2026-11-10 09:00:00');
        }
        $this->event('email_opens', $message, $other, '2026-11-10 10:00:00');
        $this->event('email_clicks', $message, $reader, '2026-11-10 09:05:00');

        $this->cronRun('2026-11-01 08:00:00', 4);
        $this->event('email_opens', $message, $reader, '2026-11-01 09:00:00');

        $stats = $this->stats(['period' => 7]);

        $this->assertEquals(50, $stats->json('open_rate.value'));
        $this->assertEquals(25, $stats->json('open_rate.previous'));
        $this->assertEquals(25, $stats->json('open_rate.trend'));
        $this->assertEquals(25, $stats->json('click_rate.value'));
    }

    public function test_the_chart_has_one_row_per_day_in_the_users_timezone(): void
    {
        $this->user->update(['timezone' => 'Europe/Warsaw']);   // UTC+1 in November
        $message = $this->message();
        $reader = $this->subscriber('reader@example.com', '2026-11-09 23:30:00');   // 10 Nov, 00:30 in Warsaw

        $this->cronRun('2026-11-10 23:30:00', 7);   // 11 Nov, 00:30 in Warsaw
        $this->cronRun('2026-11-10 12:00:00', 3);
        $this->event('email_opens', $message, $reader, '2026-11-10 12:00:00');
        $this->event('email_opens', $message, $reader, '2026-11-10 13:00:00');
        $this->event('email_clicks', $message, $reader, '2026-11-10 13:00:00');

        $chart = collect($this->stats(['period' => 7])->json('activity_chart'))->keyBy('date');

        $this->assertSame(['2026-11-05', '2026-11-11'], [$chart->keys()->first(), $chart->keys()->last()]);
        $this->assertSame(7, $chart['2026-11-11']['emails']);
        $this->assertSame(3, $chart['2026-11-10']['emails']);
        $this->assertSame(1, $chart['2026-11-10']['opens']);
        $this->assertSame(1, $chart['2026-11-10']['clicks']);
        $this->assertSame(1, $chart['2026-11-10']['subscribers']);
        $this->assertSame(0, $chart['2026-11-09']['subscribers']);
        $this->assertSame(0, $chart['2026-11-06']['emails']);
    }

    public function test_new_subscribers_are_counted_for_the_period_and_the_one_before(): void
    {
        $this->subscriber('a@example.com', '2026-11-10 10:00:00');
        $this->subscriber('b@example.com', '2026-11-06 10:00:00');
        $this->subscriber('c@example.com', '2026-11-01 10:00:00');
        $this->subscriber('old@example.com', '2026-09-01 10:00:00');

        $stats = $this->stats(['period' => 7]);

        $this->assertSame(2, $stats->json('subscribers.new'));
        $this->assertSame(1, $stats->json('subscribers.new_previous'));
        $this->assertEquals(100, $stats->json('subscribers.trend'));
    }

    public function test_recent_campaigns_carry_their_lists_and_unique_rates(): void
    {
        $list = ContactList::factory()->create(['user_id' => $this->user->id, 'name' => 'Newsletter']);
        $message = $this->message(['subject' => 'November news', 'sent_count' => 4]);
        $message->contactLists()->attach($list->id);
        $draft = $this->message(['subject' => 'Draft', 'status' => 'draft']);
        $reader = $this->subscriber('reader@example.com');

        $this->event('email_opens', $message, $reader, '2026-11-10 09:00:00');
        $this->event('email_opens', $message, $reader, '2026-11-10 09:30:00');
        $this->event('email_clicks', $message, $reader, '2026-11-10 09:35:00');

        $campaigns = collect($this->stats()->json('recent_campaigns'))->keyBy('id');

        $sent = $campaigns[$message->id];
        $this->assertSame('November news', $sent['name']);
        $this->assertSame(['Newsletter'], $sent['lists']);
        $this->assertSame(4, $sent['sent']);
        $this->assertSame(1, $sent['opens']);
        $this->assertEquals(25, $sent['open_rate']);
        $this->assertEquals(25, $sent['click_rate']);
        $this->assertNull($campaigns[$draft->id]['open_rate']);
    }

    public function test_scheduled_lists_only_future_broadcasts_soonest_first(): void
    {
        $later = $this->message(['subject' => 'Later', 'status' => 'scheduled', 'scheduled_at' => '2026-11-20 09:00:00', 'planned_recipients_count' => 120]);
        $sooner = $this->message(['subject' => 'Sooner', 'status' => 'scheduled', 'scheduled_at' => '2026-11-12 09:00:00']);
        $this->message(['subject' => 'Overdue', 'status' => 'scheduled', 'scheduled_at' => '2026-11-10 09:00:00']);
        $this->message(['subject' => 'Sent', 'status' => 'sent', 'scheduled_at' => '2026-11-25 09:00:00']);
        $this->message(['subject' => 'Queue', 'type' => 'autoresponder', 'status' => 'scheduled', 'scheduled_at' => '2026-11-13 09:00:00']);

        $scheduled = $this->stats()->json('scheduled');

        $this->assertSame([$sooner->id, $later->id], array_column($scheduled, 'id'));
        $this->assertSame(120, $scheduled[1]['recipients']);
        $this->assertSame('email', $scheduled[0]['channel']);
    }

    public function test_guests_cannot_read_the_dashboard_numbers(): void
    {
        $this->getJson(route('api.dashboard.stats'))->assertUnauthorized();
    }
}
