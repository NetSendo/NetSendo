<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Funnel;
use App\Models\FunnelStep;
use App\Models\ContactList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

class FunnelControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected ContactList $list;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->list = ContactList::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Test List',
            'type' => 'email',
        ]);
    }

    #[Test]
    public function test_user_can_view_funnels_index()
    {
        Funnel::factory()->count(3)->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('funnels.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Funnels/Index')
            ->has('funnels.data', 3)
        );
    }

    #[Test]
    public function test_user_can_create_funnel()
    {
        $response = $this->actingAs($this->user)->get(route('funnels.create'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Funnels/Builder')
        );
    }

    #[Test]
    public function test_user_can_store_funnel()
    {
        // The builder posts its graph as Vue Flow nodes and edges.
        $funnelData = [
            'name' => 'New Test Funnel',
            'trigger_type' => Funnel::TRIGGER_LIST_SIGNUP,
            'trigger_list_id' => $this->list->id,
            'nodes' => [
                ['id' => 'temp-1', 'type' => FunnelStep::TYPE_START, 'data' => ['name' => 'Start'], 'position' => ['x' => 100, 'y' => 100]],
                ['id' => 'temp-2', 'type' => FunnelStep::TYPE_END, 'data' => ['name' => 'End'], 'position' => ['x' => 100, 'y' => 300]],
            ],
            'edges' => [
                ['source' => 'temp-1', 'target' => 'temp-2'],
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('funnels.store'), $funnelData);

        $response->assertRedirect();

        $this->assertDatabaseHas('funnels', [
            'user_id' => $this->user->id,
            'name' => 'New Test Funnel',
        ]);

        $funnel = Funnel::where('name', 'New Test Funnel')->first();
        $this->assertEquals(2, $funnel->steps()->count());
    }

    #[Test]
    public function test_user_can_update_funnel()
    {
        $funnel = Funnel::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Original Name',
        ]);

        $response = $this->actingAs($this->user)->put(route('funnels.update', $funnel), [
            'name' => 'Updated Name',
            'description' => 'Updated description',
            'trigger_list_id' => $this->list->id,
            'steps' => [],
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('funnels', [
            'id' => $funnel->id,
            'name' => 'Updated Name',
        ]);
    }

    #[Test]
    public function test_user_can_delete_funnel()
    {
        $funnel = Funnel::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->delete(route('funnels.destroy', $funnel));

        $response->assertRedirect();
        $this->assertDatabaseMissing('funnels', ['id' => $funnel->id]);
    }

    #[Test]
    public function test_user_can_duplicate_funnel()
    {
        $funnel = Funnel::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Original Funnel',
        ]);

        FunnelStep::factory()->count(3)->create([
            'funnel_id' => $funnel->id,
        ]);

        $response = $this->actingAs($this->user)->post(route('funnels.duplicate', $funnel));

        $response->assertRedirect();

        $this->assertDatabaseHas('funnels', [
            'user_id' => $this->user->id,
            'name' => 'Original Funnel (kopia)',
        ]);

        // Verify steps were copied
        $duplicate = Funnel::where('name', 'Original Funnel (kopia)')->first();
        $this->assertEquals(3, $duplicate->steps()->count());
    }

    #[Test]
    public function test_user_can_toggle_funnel_status()
    {
        $funnel = Funnel::factory()->create([
            'user_id' => $this->user->id,
            'status' => Funnel::STATUS_DRAFT,
        ]);

        // Activation is refused unless the funnel has a start step and at least
        // one email or action step (FunnelService::validate).
        FunnelStep::factory()->create(['funnel_id' => $funnel->id, 'type' => FunnelStep::TYPE_START]);
        FunnelStep::factory()->create([
            'funnel_id' => $funnel->id,
            'type' => FunnelStep::TYPE_ACTION,
            'action_type' => FunnelStep::ACTION_ADD_TAG,
            'action_config' => ['tag' => 'Lead'],
        ]);

        $response = $this->actingAs($this->user)->post(route('funnels.toggle-status', $funnel));

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $funnel->refresh();
        $this->assertEquals(Funnel::STATUS_ACTIVE, $funnel->status);

        // Toggle again
        $this->actingAs($this->user)->post(route('funnels.toggle-status', $funnel));

        $funnel->refresh();
        $this->assertEquals(Funnel::STATUS_PAUSED, $funnel->status);
    }

    #[Test]
    public function test_user_can_view_funnel_stats()
    {
        $funnel = Funnel::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('funnels.stats', $funnel));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Funnels/Stats')
            ->has('funnel')
            ->has('stats')
        );
    }

    #[Test]
    public function test_user_cannot_access_other_user_funnel()
    {
        $otherUser = User::factory()->create();
        $funnel = Funnel::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('funnels.edit', $funnel));

        $response->assertStatus(403);
    }

    #[Test]
    public function test_funnel_validation_requires_name()
    {
        $response = $this->actingAs($this->user)->post(route('funnels.store'), [
            'description' => 'Test',
            'trigger_list_id' => $this->list->id,
        ]);

        $response->assertSessionHasErrors('name');
    }

    #[Test]
    public function test_funnel_steps_are_properly_connected()
    {
        $funnelData = [
            'name' => 'Connected Funnel',
            'trigger_type' => Funnel::TRIGGER_LIST_SIGNUP,
            'trigger_list_id' => $this->list->id,
            'nodes' => [
                ['id' => 'temp-1', 'type' => FunnelStep::TYPE_START, 'data' => ['name' => 'Start'], 'position' => ['x' => 100, 'y' => 100]],
                ['id' => 'temp-2', 'type' => FunnelStep::TYPE_DELAY, 'data' => ['name' => 'Wait 1 day', 'delay_value' => 1, 'delay_unit' => 'days'], 'position' => ['x' => 100, 'y' => 200]],
                ['id' => 'temp-3', 'type' => FunnelStep::TYPE_END, 'data' => ['name' => 'End'], 'position' => ['x' => 100, 'y' => 300]],
            ],
            'edges' => [
                ['source' => 'temp-1', 'target' => 'temp-2'],
                ['source' => 'temp-2', 'target' => 'temp-3'],
            ],
        ];

        $this->actingAs($this->user)->post(route('funnels.store'), $funnelData);

        $funnel = Funnel::where('name', 'Connected Funnel')->first();
        $startStep = $funnel->steps()->where('type', FunnelStep::TYPE_START)->first();
        $delayStep = $funnel->steps()->where('type', FunnelStep::TYPE_DELAY)->first();

        $this->assertSame($delayStep->id, $startStep->next_step_id);
    }
}
