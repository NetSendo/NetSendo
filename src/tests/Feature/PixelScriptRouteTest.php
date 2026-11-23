<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /t/pixel/{userId} serves the pixel script. A non-numeric id (scanners,
 * "/t/pixel/abc", "/t/pixel/1.js") reached PixelController::script(int) as a
 * string and ended in a TypeError and a 500 instead of a 404.
 */
class PixelScriptRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_serves_the_script_for_an_existing_user(): void
    {
        $user = User::factory()->create();

        $response = $this->get('/t/pixel/'.$user->id);

        $response->assertOk();
        $this->assertStringContainsString('application/javascript', $response->headers->get('Content-Type'));
    }

    public function test_unknown_numeric_user_gets_404(): void
    {
        $this->get('/t/pixel/999999')->assertNotFound();
    }

    public function test_non_numeric_user_id_gets_404_not_500(): void
    {
        foreach (['abc', '1.js', 'pixel.js', '1abc'] as $id) {
            $this->get('/t/pixel/'.$id)->assertNotFound();
        }
    }
}
