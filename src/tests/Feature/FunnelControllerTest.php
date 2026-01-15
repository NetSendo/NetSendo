<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Funnel;
use App\Models\FunnelStep;
use App\Models\ContactList;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

    /** @test */
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

    /** @test */
    public function test_user_can_create_funnel()
    {
        $response = $this->actingAs($this->user)->get(route('funnels.create'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Funnels/Builder')
        );
    }

    /** @test */
    public function test_user_can_store_funnel()
    {
        $funnelData = [
            'name' => 'New Test Funnel',
            'description' => 'Test description',
            'trigger_list_id' => $this->list->id,
            'steps' => [
                [
                    'type' => FunnelStep::TYPE_START,
                    'name' => 'Start',
                    'position' => ['x' => 100, 'y' => 100],
                ],
                [
                    'type' => FunnelStep::TYPE_END,
                    'name' => 'End',
                    'position' => ['x' => 100, 'y' => 300],
                ],
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

    /** @test */
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

    /** @test */
    public function test_user_can_delete_funnel()
    {
        $funnel = Funnel::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->delete(route('funnels.destroy', $funnel));

        $response->assertRedirect();
        $this->assertDatabaseMissing('funnels', ['id' => $funnel->id]);
    }

    /** @test */
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

    /** @test */
    public function test_user_can_toggle_funnel_status()
    {
        $funnel = Funnel::factory()->create([
            'user_id' => $this->user->id,
            'status' => Funnel::STATUS_DRAFT,
        ]);

        $response = $this->actingAs($this->user)->post(route('funnels.toggle-status', $funnel));

        $response->assertRedirect();

        $funnel->refresh();
        $this->assertEquals(Funnel::STATUS_ACTIVE, $funnel->status);

        // Toggle again
        $this->actingAs($this->user)->post(route('funnels.toggle-status', $funnel));

        $funnel->refresh();
        $this->assertEquals(Funnel::STATUS_PAUSED, $funnel->status);
    }

    /** @test */
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

    /** @test */
    public function test_user_cannot_access_other_user_funnel()
    {
        $otherUser = User::factory()->create();
        $funnel = Funnel::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('funnels.edit', $funnel));

        $response->assertStatus(403);
    }

    /** @test */
    public function test_funnel_validation_requires_name()
    {
        $response = $this->actingAs($this->user)->post(route('funnels.store'), [
            'description' => 'Test',
            'trigger_list_id' => $this->list->id,
        ]);

        $response->assertSessionHasErrors('name');
    }

    /** @test */
    public function test_funnel_steps_are_properly_connected()
    {
        $funnelData = [
            'name' => 'Connected Funnel',
            'trigger_list_id' => $this->list->id,
            'steps' => [
                [
                    'id' => 'temp-1',
                    'type' => FunnelStep::TYPE_START,
                    'name' => 'Start',
                    'position' => ['x' => 100, 'y' => 100],
                    'connections' => ['temp-2'],
                ],
                [
                    'id' => 'temp-2',
                    'type' => FunnelStep::TYPE_DELAY,
                    'name' => 'Wait 1 day',
                    'delay_value' => 1,
                    'delay_unit' => 'days',
                    'position' => ['x' => 100, 'y' => 200],
                    'connections' => ['temp-3'],
                ],
                [
                    'id' => 'temp-3',
                    'type' => FunnelStep::TYPE_END,
                    'name' => 'End',
                    'position' => ['x' => 100, 'y' => 300],
                ],
            ],
        ];

        $this->actingAs($this->user)->post(route('funnels.store'), $funnelData);

        $funnel = Funnel::where('name', 'Connected Funnel')->first();
        $startStep = $funnel->steps()->where('type', FunnelStep::TYPE_START)->first();

        $this->assertNotNull($startStep->next_step_id);
    }
}
