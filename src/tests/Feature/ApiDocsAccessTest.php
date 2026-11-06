<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ApiDocsAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_api_docs(): void
    {
        $this->get('/docs/api')->assertForbidden();
        $this->get('/docs/api.json')->assertForbidden();
    }

    public function test_signed_in_user_may_view_api_docs(): void
    {
        $user = User::factory()->create();

        $this->assertTrue(Gate::forUser($user)->allows('viewApiDocs'));
        $this->actingAs($user)->get('/docs/api')->assertOk();
    }
}
