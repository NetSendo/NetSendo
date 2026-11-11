<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The SMS campaign list: the same tabs and page size as the e-mail list, SMS only, and a sort
 * direction that never reaches SQL as the request wrote it.
 */
class SmsIndexTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->user = User::factory()->create();
    }

    private function sms(array $attributes = []): Message
    {
        return Message::create($attributes + [
            'user_id' => $this->user->id, 'subject' => 'Campaign', 'content' => 'Hello',
            'channel' => 'sms', 'type' => 'broadcast', 'status' => 'draft',
        ]);
    }

    private function index(array $query = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->user)->get(route('sms.index', $query))->assertOk();
    }

    private function subjects(array $query = []): array
    {
        $subjects = [];
        $this->index($query)->assertInertia(function (Assert $page) use (&$subjects) {
            $subjects = array_column($page->toArray()['props']['messages']['data'], 'subject');
        });

        return $subjects;
    }

    public function test_the_tabs_filter_by_type_and_status_and_carry_counts(): void
    {
        $this->sms(['subject' => 'Draft']);
        $this->sms(['subject' => 'Scheduled', 'status' => 'scheduled', 'scheduled_at' => now()->addDay()]);
        $this->sms(['subject' => 'Sent', 'status' => 'sent']);
        $this->sms(['subject' => 'Queue', 'type' => 'autoresponder', 'status' => 'sent', 'day' => 1]);
        $this->sms(['subject' => 'E-mail', 'channel' => 'email']);

        $this->assertSame(['Draft'], $this->subjects(['type' => 'broadcast', 'status' => 'draft']));
        $this->assertSame(['Scheduled'], $this->subjects(['type' => 'broadcast', 'status' => 'scheduled']));
        $this->assertSame(['Sent'], $this->subjects(['type' => 'broadcast', 'status' => 'sent']));
        $this->assertSame(['Queue'], $this->subjects(['type' => 'autoresponder']));
        $this->assertCount(4, $this->subjects());

        $this->index(['type' => 'broadcast', 'status' => 'sent'])->assertInertia(fn (Assert $page) => $page
            ->where('counts', ['all' => 4, 'draft' => 1, 'scheduled' => 1, 'sent' => 1, 'autoresponder' => 1])
            ->where('filters.status', 'sent'));
    }

    public function test_the_page_size_is_chosen_by_the_reader_within_limits(): void
    {
        foreach (range(1, 12) as $i) {
            $this->sms(['subject' => "Campaign {$i}"]);
        }

        $this->assertCount(12, $this->subjects());
        $this->assertCount(10, $this->subjects(['per_page' => 10]));
        $this->assertCount(12, $this->subjects(['per_page' => 5000]));
        $this->assertCount(1, $this->subjects(['per_page' => 0]));
    }

    public function test_the_sort_direction_is_never_taken_from_the_request_as_written(): void
    {
        $older = $this->sms(['subject' => 'Older']);
        $newer = $this->sms(['subject' => 'Newer']);
        DB::table('messages')->where('id', $older->id)->update(['created_at' => now()->subDays(2)]);
        DB::table('messages')->where('id', $newer->id)->update(['created_at' => now()->subDay()]);

        $this->assertSame(['Older', 'Newer'], $this->subjects(['sort' => 'effective_date', 'direction' => 'asc']));
        $this->assertSame(['Newer', 'Older'], $this->subjects(['sort' => 'effective_date', 'direction' => 'desc']));

        // Anything that is not "asc" sorts descending: it used to be pasted into ORDER BY
        $injected = 'asc, (SELECT password FROM users LIMIT 1)';
        $this->assertSame(['Newer', 'Older'], $this->subjects(['sort' => 'effective_date', 'direction' => $injected]));
        $this->assertSame(['Newer', 'Older'], $this->subjects(['sort' => 'created_at', 'direction' => $injected]));
    }
}
