<?php

namespace Tests\Feature\Api;

use App\Jobs\SendSmsJob;
use App\Models\ApiKey;
use App\Models\SmsProvider;
use App\Models\Subscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * POST /api/v1/sms/send required `message` and `phone`, while the NetSendo MCP tool
 * send_sms (up to @netsendo/mcp-client 1.6.x) sends `content` and may send only a
 * subscriber ID, so every call from an AI assistant ended in 422.
 */
class SmsSendApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $key;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->user = User::factory()->create();
        $this->key = ApiKey::generate($this->user->id, 'SMS', ['sms:read', 'sms:write'])['key'];

        Subscriber::create(['user_id' => $this->user->id, 'email' => 'anna@example.com', 'phone' => '+48500100200']);

        SmsProvider::create([
            'user_id' => $this->user->id,
            'name' => 'SMSAPI',
            'provider' => 'smsapi',
            'credentials' => Crypt::encryptString('{"token":"test"}'),
            'is_active' => true,
            'is_default' => true,
        ]);
    }

    private function send(array $payload)
    {
        return $this->withHeaders(['Authorization' => 'Bearer ' . $this->key])->postJson('/api/v1/sms/send', $payload);
    }

    public function test_the_text_can_be_sent_as_message(): void
    {
        $this->send(['phone' => '+48500100200', 'message' => 'Hello'])->assertStatus(202);

        Queue::assertPushed(SendSmsJob::class);
    }

    public function test_the_text_can_be_sent_as_content_like_the_mcp_tool_does(): void
    {
        $this->send(['phone' => '+48500100200', 'content' => 'Hello'])
            ->assertStatus(202)
            ->assertJsonPath('data.phone', '+48500100200');

        Queue::assertPushed(SendSmsJob::class);
    }

    public function test_a_subscriber_id_alone_sends_to_the_subscribers_number(): void
    {
        $subscriber = Subscriber::create([
            'user_id' => $this->user->id,
            'email' => 'jan@example.com',
            'phone' => '+48500100300',
        ]);

        $this->send(['subscriber_id' => $subscriber->id, 'content' => 'Hello'])
            ->assertStatus(202)
            ->assertJsonPath('data.phone', '+48500100300');
    }

    public function test_a_subscriber_of_another_account_is_not_found(): void
    {
        $stranger = Subscriber::create([
            'user_id' => User::factory()->create()->id,
            'email' => 'other@example.com',
            'phone' => '+48500100400',
        ]);

        $this->send(['subscriber_id' => $stranger->id, 'content' => 'Hello'])->assertStatus(404);
        Queue::assertNothingPushed();
    }

    public function test_a_number_no_contact_has_is_rejected_instead_of_failing(): void
    {
        $this->send(['phone' => '+48999999999', 'content' => 'Hello'])->assertStatus(422);
        Queue::assertNothingPushed();
    }

    public function test_without_phone_or_subscriber_the_request_is_rejected(): void
    {
        $this->send(['content' => 'Hello'])->assertStatus(422);
        Queue::assertNothingPushed();
    }
}
