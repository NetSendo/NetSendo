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
