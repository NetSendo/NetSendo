<?php

namespace Tests\Feature;

use App\Models\Mailbox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MailboxFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_mailbox_can_be_created_using_factory_and_persisted(): void
    {
        $mailbox = Mailbox::factory()->create();

        $this->assertNotNull($mailbox);
        $this->assertModelExists($mailbox);
        $this->assertDatabaseHas('mailboxes', [
            'id' => $mailbox->id,
            'user_id' => $mailbox->user_id,
            'from_email' => $mailbox->from_email,
            'is_default' => false,
            'is_active' => true,
        ]);

        $credentials = $mailbox->getDecryptedCredentials();
        $this->assertIsArray($credentials);
        $this->assertSame('smtp.example.com', $credentials['host']);
        $this->assertSame(587, $credentials['port']);
    }

    public function test_mailbox_user_relationship_loads_correctly(): void
    {
        $mailbox = Mailbox::factory()->create();

        $this->assertInstanceOf(User::class, $mailbox->user);
        $this->assertSame($mailbox->user_id, $mailbox->user->id);
    }

    public function test_inactive_state_creates_valid_inactive_mailbox(): void
    {
        $mailbox = Mailbox::factory()->inactive()->create();

        $this->assertModelExists($mailbox);
        $this->assertFalse($mailbox->is_active);
        $this->assertDatabaseHas('mailboxes', [
            'id' => $mailbox->id,
            'is_active' => false,
        ]);
    }

    public function test_default_state_has_expected_attributes(): void
    {
        $mailbox = Mailbox::factory()->default()->create();

        $this->assertModelExists($mailbox);
        $this->assertTrue($mailbox->is_default);
        $this->assertDatabaseHas('mailboxes', [
            'id' => $mailbox->id,
            'is_default' => true,
        ]);
    }
}
