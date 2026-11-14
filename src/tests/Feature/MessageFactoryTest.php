<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_message_can_be_created_using_factory_and_persisted(): void
    {
        $message = Message::factory()->create();

        $this->assertNotNull($message);
        $this->assertModelExists($message);
        $this->assertDatabaseHas('messages', [
            'id' => $message->id,
            'user_id' => $message->user_id,
            'subject' => $message->subject,
            'channel' => 'email',
            'type' => 'broadcast',
            'status' => 'draft',
        ]);
    }

    public function test_message_user_relationship_loads_correctly(): void
    {
        $message = Message::factory()->create();

        $this->assertInstanceOf(User::class, $message->user);
        $this->assertSame($message->user_id, $message->user->id);
    }

    public function test_broadcast_state_creates_valid_record_with_expected_attributes(): void
    {
        $message = Message::factory()->broadcast()->create();

        $this->assertModelExists($message);
        $this->assertSame('broadcast', $message->type);
        $this->assertSame('email', $message->channel);
        $this->assertDatabaseHas('messages', [
            'id' => $message->id,
            'type' => 'broadcast',
        ]);
    }

    public function test_autoresponder_state_creates_valid_record_with_expected_attributes(): void
    {
        $defaultAutoresponder = Message::factory()->autoresponder()->create();

        $this->assertModelExists($defaultAutoresponder);
        $this->assertSame('autoresponder', $defaultAutoresponder->type);
        $this->assertSame(1, $defaultAutoresponder->day);
        $this->assertSame('email', $defaultAutoresponder->channel);

        $customDayAutoresponder = Message::factory()->autoresponder(3)->create();
        $this->assertSame('autoresponder', $customDayAutoresponder->type);
        $this->assertSame(3, $customDayAutoresponder->day);
    }

    public function test_sms_state_creates_valid_record_with_expected_attributes(): void
    {
        $message = Message::factory()->sms()->create();

        $this->assertModelExists($message);
        $this->assertSame('sms', $message->channel);
        $this->assertSame('broadcast', $message->type);
        $this->assertDatabaseHas('messages', [
            'id' => $message->id,
            'channel' => 'sms',
        ]);
    }
}
