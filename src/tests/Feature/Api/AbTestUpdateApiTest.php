<?php

namespace Tests\Feature\Api;

use App\Models\AbTest;
use App\Models\ApiKey;
use App\Models\ContactList;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\ActivatesGold;
use Tests\TestCase;

/**
 * PUT/PATCH /api/v1/ab-tests/{id} was registered (apiResource, with the GOLD middleware) and
 * documented in the controller, but the controller had no update() method, so every call
 * ended in a 500 (2.7.2 review, issue 23).
 */
class AbTestUpdateApiTest extends TestCase
{
    use RefreshDatabase;
    use ActivatesGold;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->activateGold();
    }

    private function api(array $permissions = ['messages:write'])
    {
        $key = ApiKey::generate($this->user->id, 'Agent', $permissions)['key'];

        return $this->withHeaders(['Authorization' => 'Bearer ' . $key]);
    }

    private function abTest(string $status = AbTest::STATUS_DRAFT, ?User $owner = null): AbTest
    {
        $owner ??= $this->user;
        $list = ContactList::create(['user_id' => $owner->id, 'name' => 'Readers', 'type' => 'email']);
        $message = Message::create([
            'user_id' => $owner->id,
            'channel' => 'email',
            'type' => 'broadcast',
            'subject' => 'Spring sale',
            'content' => '<p>Hello</p>',
            'status' => 'draft',
        ]);
        $message->contactLists()->attach($list->id);

        return AbTest::create([
            'message_id' => $message->id,
            'user_id' => $owner->id,
            'name' => 'Subject test',
            'status' => $status,
            'test_type' => 'subject',
            'winning_metric' => 'open_rate',
            'sample_percentage' => 20,
            'test_duration_hours' => 4,
            'auto_select_winner' => true,
            'confidence_threshold' => 95,
        ]);
    }

    public function test_a_draft_test_can_be_updated(): void
    {
        $test = $this->abTest();

        $this->api()->putJson("/api/v1/ab-tests/{$test->id}", [
            'name' => 'Subject test v2',
            'winning_metric' => 'click_rate',
            'sample_percentage' => 30,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Subject test v2')
            ->assertJsonPath('data.winning_metric', 'click_rate')
            ->assertJsonPath('data.sample_percentage', 30)
            ->assertJsonPath('data.test_duration_hours', 4);

        $this->api()->patchJson("/api/v1/ab-tests/{$test->id}", ['test_duration_hours' => 12])
            ->assertOk()
            ->assertJsonPath('data.test_duration_hours', 12)
            ->assertJsonPath('data.name', 'Subject test v2');
    }

    public function test_the_values_are_validated_like_on_creation(): void
    {
        $test = $this->abTest();

        $this->api()->putJson("/api/v1/ab-tests/{$test->id}", ['sample_percentage' => 80])
            ->assertStatus(422)
            ->assertJsonValidationErrors('sample_percentage');
    }

    public function test_a_running_test_cannot_be_changed(): void
    {
        $test = $this->abTest(AbTest::STATUS_RUNNING);

        $this->api()->putJson("/api/v1/ab-tests/{$test->id}", ['sample_percentage' => 30])
            ->assertStatus(409);

        $this->assertSame(20, $test->fresh()->sample_percentage);
    }

    public function test_another_accounts_test_is_not_found_and_writes_need_the_scope(): void
    {
        $foreign = $this->abTest(AbTest::STATUS_DRAFT, User::factory()->create());
        $this->api()->putJson("/api/v1/ab-tests/{$foreign->id}", ['name' => 'Mine'])->assertNotFound();

        $own = $this->abTest();
        $this->api(['messages:read'])->putJson("/api/v1/ab-tests/{$own->id}", ['name' => 'Mine'])->assertForbidden();
        $this->assertSame('Subject test', $own->fresh()->name);
    }
}
