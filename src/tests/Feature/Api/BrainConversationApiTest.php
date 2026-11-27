<?php

namespace Tests\Feature\Api;

use App\Models\AiActionPlan;
use App\Models\AiConversation;
use App\Models\ApiKey;
use App\Models\User;
use App\Services\Brain\AgentOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Brain conversations could only be listed, read and renamed; nobody could delete one, and
 * an agent could not start or rename one over the API/MCP. The panel and the API now delete
 * conversations, and agents start, rename and delete them and chat in them.
 */
class BrainConversationApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    private function api(array $permissions = ['brain:read', 'brain:write'])
    {
        $key = ApiKey::generate($this->user->id, 'Agent', $permissions)['key'];

        return $this->withHeaders(['Authorization' => 'Bearer ' . $key]);
    }

    private function conversation(?User $owner = null, array $attributes = []): AiConversation
    {
        $conversation = AiConversation::create(array_merge([
            'user_id' => ($owner ?? $this->user)->id,
            'channel' => 'web',
            'status' => 'active',
            'title' => 'Webinar ideas',
            'last_activity_at' => now(),
        ], $attributes));
        $conversation->addMessage('user', 'Hello Brain');
        $conversation->addMessage('assistant', 'Hello!');

        return $conversation;
    }

    public function test_the_panel_deletes_a_conversation_with_its_messages_and_keeps_its_plans(): void
    {
        $conversation = $this->conversation();
        $plan = AiActionPlan::create([
            'user_id' => $this->user->id,
            'ai_conversation_id' => $conversation->id,
            'agent_type' => 'campaign',
            'intent' => 'create_campaign',
            'plan_data' => [],
            'work_mode' => 'semi_auto',
            'title' => 'Webinar campaign',
            'status' => 'completed',
        ]);

        $this->actingAs($this->user)
            ->deleteJson(route('brain.api.conversations.destroy', $conversation->id))
            ->assertOk()
            ->assertJsonPath('deleted', true);

        $this->assertDatabaseMissing('ai_conversations', ['id' => $conversation->id]);
        $this->assertDatabaseMissing('ai_conversation_messages', ['ai_conversation_id' => $conversation->id]);
        $this->assertDatabaseHas('ai_action_plans', ['id' => $plan->id, 'ai_conversation_id' => null]);
    }

    public function test_nobody_deletes_another_users_conversation(): void
    {
        $foreign = $this->conversation(User::factory()->create());

        $this->actingAs($this->user)
            ->deleteJson(route('brain.api.conversations.destroy', $foreign->id))
            ->assertNotFound();
        $this->api()->deleteJson("/api/v1/brain/conversations/{$foreign->id}")->assertNotFound();

        $this->assertDatabaseHas('ai_conversations', ['id' => $foreign->id]);
        $this->assertSame(2, $foreign->messages()->count());
    }

    public function test_an_agent_creates_renames_lists_and_deletes_a_conversation(): void
    {
        $id = $this->api()->postJson('/api/v1/brain/conversations', ['title' => 'Q4 plan'])
            ->assertCreated()
            ->assertJsonPath('title', 'Q4 plan')
            ->assertJsonPath('channel', 'api')
            ->json('id');

        $this->api()->putJson("/api/v1/brain/conversations/{$id}", ['title' => 'Q4 newsletter plan'])
            ->assertOk()
            ->assertJsonPath('title', 'Q4 newsletter plan');

        // Listed once it has a message; filters by channel and title
        AiConversation::find($id)->addMessage('user', 'Plan Q4');
        $this->conversation();

        $this->api()->getJson('/api/v1/brain/conversations?channel=api')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $id);
        $this->api()->getJson('/api/v1/brain/conversations?search=newsletter')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $id);

        $this->api()->deleteJson("/api/v1/brain/conversations/{$id}")->assertOk();
        $this->assertDatabaseMissing('ai_conversations', ['id' => $id]);
    }

    public function test_a_read_only_key_reads_but_cannot_change_conversations(): void
    {
        $conversation = $this->conversation();
        $api = $this->api(['brain:read']);

        $api->getJson("/api/v1/brain/conversations/{$conversation->id}")
            ->assertOk()
            ->assertJsonCount(2, 'messages');
        // The list previews the latest message, not the first one
        $api->getJson('/api/v1/brain/conversations')
            ->assertOk()
            ->assertJsonPath('data.0.messages.0.content', 'Hello!');
        $api->postJson('/api/v1/brain/conversations', ['title' => 'x'])->assertForbidden();
        $api->putJson("/api/v1/brain/conversations/{$conversation->id}", ['title' => 'x'])->assertForbidden();
        $api->deleteJson("/api/v1/brain/conversations/{$conversation->id}")->assertForbidden();
        $api->postJson('/api/v1/brain/chat', ['message' => 'Hi'])->assertForbidden();

        $this->assertDatabaseHas('ai_conversations', ['id' => $conversation->id, 'title' => 'Webinar ideas']);
    }

    public function test_agent_chat_runs_on_the_api_channel_in_the_named_conversation(): void
    {
        $conversation = $this->conversation(null, ['channel' => 'api']);

        $this->mock(AgentOrchestrator::class, function ($mock) use ($conversation) {
            $mock->shouldReceive('processMessage')
                ->once()
                ->with('What next?', Mockery::on(fn ($user) => $user->id === $this->user->id), 'api', $conversation->id, false)
                ->andReturn(['type' => 'message', 'message' => 'Send the webinar reminder.', 'conversation_id' => $conversation->id]);
        });

        $this->api()->postJson('/api/v1/brain/chat', ['message' => 'What next?', 'conversation_id' => $conversation->id])
            ->assertOk()
            ->assertJsonPath('conversation_id', $conversation->id);
    }

    public function test_agent_chat_in_a_deleted_or_foreign_conversation_is_a_404_not_a_new_conversation(): void
    {
        $foreign = $this->conversation(User::factory()->create());

        $this->mock(AgentOrchestrator::class, fn ($mock) => $mock->shouldNotReceive('processMessage'));

        $this->api()->postJson('/api/v1/brain/chat', ['message' => 'Hi', 'conversation_id' => $foreign->id])
            ->assertNotFound();
        $this->api()->postJson('/api/v1/brain/chat', ['message' => 'Hi', 'conversation_id' => 999999])
            ->assertNotFound();
    }

    public function test_the_panel_chat_stays_on_the_web_channel(): void
    {
        $this->mock(AgentOrchestrator::class, function ($mock) {
            $mock->shouldReceive('processMessage')
                ->once()
                ->with('Hi', Mockery::any(), 'web', 999999, false)
                ->andReturn(['type' => 'message', 'message' => 'Hello']);
        });

        // The panel keeps its fallback for a conversation that is gone
        $this->actingAs($this->user)
            ->postJson(route('brain.api.chat'), ['message' => 'Hi', 'conversation_id' => 999999])
            ->assertOk();
    }
}
