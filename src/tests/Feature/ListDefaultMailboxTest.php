<?php

namespace Tests\Feature;

use App\Models\ContactList;
use App\Models\Mailbox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The mailbox chosen in Sending when a list is created was never saved: only update() copied
 * settings.sending.mailbox_id to default_mailbox_id (2.7.2 review, issue 13). Sending loads
 * the list's default mailbox by its ID alone, so the ID must belong to the account.
 */
class ListDefaultMailboxTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->user = User::factory()->create();
    }

    private function mailbox(User $user, string $name = 'SMTP'): Mailbox
    {
        return Mailbox::create([
            'user_id' => $user->id,
            'name' => $name,
            'provider' => 'smtp',
            'from_email' => "news{$user->id}@example.com",
            'from_name' => 'Sender',
            'is_active' => true,
            'allowed_types' => ['broadcast', 'autoresponder', 'system'],
            'credentials' => ['host' => 'localhost', 'port' => 1025],
        ]);
    }

    public function test_the_mailbox_chosen_when_creating_a_list_becomes_its_default_mailbox(): void
    {
        $mailbox = $this->mailbox($this->user);

        $this->actingAs($this->user)
            ->post(route('mailing-lists.store'), [
                'name' => 'Newsletter',
                'settings' => ['sending' => ['mailbox_id' => $mailbox->id]],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('mailing-lists.index'));

        $list = ContactList::where('name', 'Newsletter')->firstOrFail();
        $this->assertSame($mailbox->id, $list->default_mailbox_id);
    }

    public function test_a_list_cannot_use_another_accounts_mailbox(): void
    {
        $foreign = $this->mailbox(User::factory()->create(), 'Someone else');

        $this->actingAs($this->user)
            ->post(route('mailing-lists.store'), [
                'name' => 'Newsletter',
                'settings' => ['sending' => ['mailbox_id' => $foreign->id]],
            ])
            ->assertSessionHasErrors('settings.sending.mailbox_id');
        $this->assertDatabaseMissing('contact_lists', ['name' => 'Newsletter']);

        $list = ContactList::create(['user_id' => $this->user->id, 'name' => 'Existing', 'type' => 'email']);
        $this->actingAs($this->user)
            ->put(route('mailing-lists.update', $list), [
                'name' => 'Existing',
                'settings' => ['sending' => ['mailbox_id' => $foreign->id]],
            ])
            ->assertSessionHasErrors('settings.sending.mailbox_id');
        $this->assertNull($list->fresh()->default_mailbox_id);
    }

    public function test_choosing_no_mailbox_on_the_edit_page_clears_the_default_mailbox(): void
    {
        $mailbox = $this->mailbox($this->user);
        $list = ContactList::create([
            'user_id' => $this->user->id,
            'name' => 'Existing',
            'type' => 'email',
            'default_mailbox_id' => $mailbox->id,
        ]);

        $this->actingAs($this->user)
            ->put(route('mailing-lists.update', $list), [
                'name' => 'Existing',
                'settings' => ['sending' => ['mailbox_id' => null]],
            ])
            ->assertSessionHasNoErrors();

        $this->assertNull($list->fresh()->default_mailbox_id);
    }

    public function test_the_create_page_uses_translated_mailbox_labels(): void
    {
        $source = File::get(resource_path('js/Pages/MailingList/Create.vue'));
        $en = json_decode(File::get(resource_path('js/locales/en.json')), true);

        preg_match_all("/\\\$t\('(mailing_lists\.[a-z_.]*mailbox[a-z_.]*)'\)/", $source, $matches);
        $this->assertNotEmpty($matches[1]);
        foreach ($matches[1] as $key) {
            $this->assertIsString(data_get($en, $key), "{$key} is missing in en.json");
        }
    }
}
