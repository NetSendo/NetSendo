<?php

namespace Tests\Feature;

use App\Models\ContactList;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * An SMS campaign belongs to the account that wrote it, like an e-mail message. The SMS pages
 * used to read every campaign of the installation: a team member could open, change and delete
 * the administrator's campaigns, and send to lists that were never shared with them.
 */
class SmsOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->owner = User::factory()->create();
        // A team member of the owner: the closest another account can be
        $this->other = User::factory()->create(['admin_user_id' => $this->owner->id]);
    }

    private function sms(User $user, array $attributes = []): Message
    {
        return Message::create($attributes + [
            'user_id' => $user->id, 'subject' => 'Campaign', 'content' => 'Hello',
            'channel' => 'sms', 'type' => 'broadcast', 'status' => 'draft',
        ]);
    }

    private function form(array $overrides = []): array
    {
        return $overrides + ['subject' => 'Changed', 'content' => 'New text', 'type' => 'broadcast', 'status' => 'draft'];
    }

    public function test_the_list_shows_and_counts_only_the_users_own_campaigns(): void
    {
        $this->sms($this->owner, ['subject' => 'Owner campaign']);
        $this->sms($this->other, ['subject' => 'Member campaign']);

        $this->actingAs($this->other)->get(route('sms.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('messages.data.0.subject', 'Member campaign')
                ->has('messages.data', 1)
                ->where('counts.all', 1));
    }

    public function test_another_accounts_campaign_cannot_be_opened_changed_deleted_or_switched(): void
    {
        $campaign = $this->sms($this->owner, ['type' => 'autoresponder', 'day' => 1]);

        $this->actingAs($this->other);

        $this->get(route('sms.edit', $campaign->id))->assertNotFound();
        $this->put(route('sms.update', $campaign->id), $this->form())->assertNotFound();
        $this->post(route('sms.toggle-active', $campaign->id))->assertNotFound();
        $this->delete(route('sms.destroy', $campaign->id))->assertNotFound();

        $campaign->refresh();
        $this->assertSame('Campaign', $campaign->subject);
        $this->assertTrue((bool) ($campaign->is_active ?? true));
        $this->assertNull($campaign->deleted_at);
    }

    public function test_the_author_still_edits_and_deletes_their_campaign(): void
    {
        $campaign = $this->sms($this->other);

        $this->actingAs($this->other);

        $this->get(route('sms.edit', $campaign->id))->assertOk();
        $this->put(route('sms.update', $campaign->id), $this->form())->assertRedirect();
        $this->assertSame('Changed', $campaign->refresh()->subject);

        $this->delete(route('sms.destroy', $campaign->id))->assertRedirect();
        $this->assertSoftDeleted('messages', ['id' => $campaign->id]);
    }

    public function test_a_campaign_can_only_use_a_list_its_author_may_use(): void
    {
        $private = ContactList::factory()->create(['user_id' => $this->owner->id, 'name' => 'Owner only']);
        $own = ContactList::factory()->create(['user_id' => $this->other->id, 'name' => 'Member list']);

        $this->actingAs($this->other);

        $this->post(route('sms.store'), $this->form(['subject' => 'To a foreign list', 'list_id' => $private->id]))
            ->assertSessionHasErrors('list_id');
        $this->assertDatabaseMissing('messages', ['subject' => 'To a foreign list']);

        $this->post(route('sms.store'), $this->form(['subject' => 'To my list', 'list_id' => $own->id]))
            ->assertSessionHasNoErrors();
        $campaign = Message::where('subject', 'To my list')->firstOrFail();
        $this->assertSame([$own->id], $campaign->contactLists()->pluck('contact_lists.id')->all());

        $this->put(route('sms.update', $campaign->id), $this->form(['list_id' => $private->id]))
            ->assertSessionHasErrors('list_id');
        $this->assertSame([$own->id], $campaign->contactLists()->pluck('contact_lists.id')->all());

        // The choice offered in the form is the same set
        $this->get(route('sms.create'))->assertInertia(fn (Assert $page) => $page
            ->has('lists', 1)
            ->where('lists.0.name', 'Member list'));
    }
}
