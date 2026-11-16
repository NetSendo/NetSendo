<?php

namespace Tests\Feature;

use App\Models\Mailbox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The on/off switch of the mailbox list saves the mailbox without its custom headers in the
 * request. What a request does not mention stays as it was.
 */
class MailboxListSwitchTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Mailbox $mailbox;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->mailbox = Mailbox::create([
            'user_id' => $this->user->id,
            'name' => 'Main',
            'provider' => 'smtp',
            'from_email' => 'news@example.com',
            'from_name' => 'Sender',
            'is_active' => true,
            'allowed_types' => ['broadcast', 'autoresponder', 'system'],
            'credentials' => ['host' => 'localhost', 'port' => 1025],
            'custom_headers' => [['key' => 'X-Campaign', 'value' => 'spring']],
            'tracking_enabled' => false,
        ]);
    }

    private function payload(array $extra = []): array
    {
        return array_merge([
            'name' => 'Main',
            'provider' => 'smtp',
            'from_email' => 'news@example.com',
            'from_name' => 'Sender',
            'is_active' => false,
            'allowed_types' => ['broadcast', 'autoresponder', 'system'],
            'daily_limit' => null,
        ], $extra);
    }

    public function test_switching_a_mailbox_off_keeps_its_custom_headers_and_tracking_choice(): void
    {
        $this->actingAs($this->user)
            ->put(route('settings.mailboxes.update', $this->mailbox), $this->payload())
            ->assertSessionHasNoErrors();

        $this->mailbox->refresh();
        $this->assertFalse($this->mailbox->is_active);
        $this->assertSame([['key' => 'X-Campaign', 'value' => 'spring']], $this->mailbox->custom_headers);
        $this->assertFalse($this->mailbox->tracking_enabled);
    }

    public function test_an_empty_list_of_headers_removes_them(): void
    {
        $this->actingAs($this->user)
            ->putJson(route('settings.mailboxes.update', $this->mailbox), $this->payload(['custom_headers' => []]))
            ->assertSessionHasNoErrors();

        $this->assertEmpty($this->mailbox->refresh()->custom_headers);
    }
}
