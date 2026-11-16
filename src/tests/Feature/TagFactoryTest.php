<?php

namespace Tests\Feature;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_tag_can_be_created_using_factory_and_persisted(): void
    {
        $tag = Tag::factory()->create();

        $this->assertNotNull($tag);
        $this->assertModelExists($tag);
        $this->assertDatabaseHas('tags', [
            'id' => $tag->id,
            'name' => $tag->name,
            'user_id' => $tag->user_id,
        ]);
    }

    public function test_tag_user_relationship_loads_correctly(): void
    {
        $tag = Tag::factory()->create();

        $this->assertInstanceOf(User::class, $tag->user);
        $this->assertSame($tag->user_id, $tag->user->id);
    }
}
