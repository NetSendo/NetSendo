<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The message list sorts by a CASE expression written as raw SQL. The direction next to it used
 * to be the request's own text: ?direction=asc, (SELECT ...) ran as part of ORDER BY.
 */
class MessageListSortDirectionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->user = User::factory()->create();
    }

    private function message(array $attributes = []): Message
    {
        return Message::create($attributes + [
            'user_id' => $this->user->id, 'subject' => 'Hello', 'content' => '<p>Hi</p>',
            'channel' => 'email', 'type' => 'broadcast', 'status' => 'draft',
        ]);
    }

    private function subjects(array $query = []): array
    {
        $subjects = [];
        $this->actingAs($this->user)->get(route('messages.index', $query))->assertOk()
            ->assertInertia(function (Assert $page) use (&$subjects) {
                $subjects = array_column($page->toArray()['props']['messages']['data'], 'subject');
            });

        return $subjects;
    }

    public function test_the_sort_direction_is_never_taken_from_the_request_as_written(): void
    {
        // created_at is not fillable, so the two ages are written past the model
        $older = $this->message(['subject' => 'Older']);
        $newer = $this->message(['subject' => 'Newer']);
        DB::table('messages')->where('id', $older->id)->update(['created_at' => now()->subDays(2)]);
        DB::table('messages')->where('id', $newer->id)->update(['created_at' => now()->subDay()]);

        $this->assertSame(['Older', 'Newer'], $this->subjects(['sort' => 'effective_date', 'direction' => 'asc']));
        $this->assertSame(['Older', 'Newer'], $this->subjects(['sort' => 'effective_date', 'direction' => 'ASC']));
        $this->assertSame(['Newer', 'Older'], $this->subjects(['sort' => 'effective_date', 'direction' => 'desc']));

        // Anything that is not "asc" sorts descending: it used to be pasted into ORDER BY
        $injected = 'asc, (SELECT password FROM users LIMIT 1)';
        $this->assertSame(['Newer', 'Older'], $this->subjects(['sort' => 'effective_date', 'direction' => $injected]));
        $this->assertSame(['Newer', 'Older'], $this->subjects(['sort' => 'created_at', 'direction' => $injected]));
    }
}
