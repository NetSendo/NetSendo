<?php

namespace Tests\Feature;

use App\Models\ContactList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The lists a team member may open are set as a whole; an empty set takes every list away.
 */
class TeamMemberPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_list_can_be_taken_away_from_a_team_member(): void
    {
        $admin = User::factory()->create();
        $member = User::factory()->create(['admin_user_id' => $admin->id]);
        $list = ContactList::factory()->create(['user_id' => $admin->id]);
        $member->sharedLists()->attach($list->id, ['permission' => 'edit', 'granted_by' => $admin->id]);

        $this->actingAs($admin)
            ->putJson(route('settings.users.permissions', $member), ['list_permissions' => []])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $member->sharedLists()->count());
    }

    public function test_the_set_of_lists_is_replaced_by_the_one_sent(): void
    {
        $admin = User::factory()->create();
        $member = User::factory()->create(['admin_user_id' => $admin->id]);
        $old = ContactList::factory()->create(['user_id' => $admin->id]);
        $new = ContactList::factory()->create(['user_id' => $admin->id]);
        $member->sharedLists()->attach($old->id, ['permission' => 'view', 'granted_by' => $admin->id]);

        $this->actingAs($admin)
            ->putJson(route('settings.users.permissions', $member), [
                'list_permissions' => [['list_id' => $new->id, 'permission' => 'edit']],
            ])
            ->assertSessionHasNoErrors();

        $shared = $member->sharedLists()->get();
        $this->assertCount(1, $shared);
        $this->assertSame($new->id, $shared[0]->id);
        $this->assertSame('edit', $shared[0]->pivot->permission);
    }
}
