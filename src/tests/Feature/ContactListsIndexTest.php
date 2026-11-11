<?php

namespace Tests\Feature;

use App\Models\ContactList;
use App\Models\Subscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * "E-mail lists" and "SMS lists": the reader chooses the page size, and the subscriber count of
 * a row is the number of active members, counted once for the page.
 */
class ContactListsIndexTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->user = User::factory()->create();
    }

    private function listOf(string $type, string $name): ContactList
    {
        return ContactList::factory()->create(['user_id' => $this->user->id, 'type' => $type, 'name' => $name]);
    }

    private function member(ContactList $list, string $email, string $status): void
    {
        $subscriber = Subscriber::create(['user_id' => $this->user->id, 'email' => $email]);
        DB::table('contact_list_subscriber')->insert([
            'contact_list_id' => $list->id, 'subscriber_id' => $subscriber->id, 'status' => $status,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public static function pages(): array
    {
        return [
            'e-mail lists' => ['email', 'mailing-lists.index', 'sms'],
            'SMS lists' => ['sms', 'sms-lists.index', 'email'],
        ];
    }

    #[DataProvider('pages')]
    public function test_a_row_counts_active_members_only(string $type, string $route, string $otherType): void
    {
        $list = $this->listOf($type, 'Customers');
        $this->listOf($otherType, 'Other kind');
        $this->member($list, 'a@example.com', 'active');
        $this->member($list, 'b@example.com', 'active');
        $this->member($list, 'c@example.com', 'unsubscribed');

        $this->actingAs($this->user)->get(route($route))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('lists.data', 1)
                ->where('lists.data.0.name', 'Customers')
                ->where('lists.data.0.subscribers_count', 2));
    }

    #[DataProvider('pages')]
    public function test_the_page_size_is_chosen_by_the_reader_within_limits(string $type, string $route): void
    {
        foreach (range(1, 14) as $i) {
            $this->listOf($type, "List {$i}");
        }

        $count = fn (array $query) => count(
            $this->actingAs($this->user)->get(route($route, $query))->assertOk()
                ->viewData('page')['props']['lists']['data']
        );

        $this->assertSame(12, $count([]));
        $this->assertSame(14, $count(['per_page' => 24]));
        $this->assertSame(14, $count(['per_page' => 9000]));
        $this->assertSame(1, $count(['per_page' => 0]));
    }

    #[DataProvider('pages')]
    public function test_lists_sort_by_their_subscriber_count(string $type, string $route): void
    {
        $small = $this->listOf($type, 'Small');
        $large = $this->listOf($type, 'Large');
        $this->member($small, 'a@example.com', 'active');
        $this->member($large, 'b@example.com', 'active');
        $this->member($large, 'c@example.com', 'active');

        $names = fn (string $direction) => array_column(
            $this->actingAs($this->user)
                ->get(route($route, ['sort_col' => 'subscribers_count', 'sort_dir' => $direction]))
                ->viewData('page')['props']['lists']['data'],
            'name'
        );

        $this->assertSame(['Large', 'Small'], $names('desc'));
        $this->assertSame(['Small', 'Large'], $names('asc'));
    }
}
