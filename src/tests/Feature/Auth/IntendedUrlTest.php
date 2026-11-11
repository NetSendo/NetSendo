<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Signing in returns the user to the page they were refused, never to an endpoint that a
 * background poll of another open tab happened to request while signed out.
 */
class IntendedUrlTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(User $user): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);
    }

    public function test_a_refused_page_is_where_sign_in_returns_to(): void
    {
        $user = User::factory()->create();

        $this->get(route('messages.index'))->assertRedirect(route('login'));

        $this->signIn($user)->assertRedirect(route('messages.index'));
    }

    public function test_a_background_poll_does_not_change_where_sign_in_returns_to(): void
    {
        $user = User::factory()->create();

        $this->get(route('messages.index'), ['Sec-Fetch-Dest' => 'document'])->assertRedirect(route('login'));
        // fetch() from a tab that is still open: no Accept header, so Laravel takes it for a page
        $this->get(route('brain.api.monitor'), ['Sec-Fetch-Dest' => 'empty'])->assertRedirect(route('login'));

        $this->signIn($user)->assertRedirect(route('messages.index'));
    }

    public function test_a_poll_alone_leaves_sign_in_going_to_the_dashboard(): void
    {
        $user = User::factory()->create();

        $this->get(route('brain.api.monitor'), ['Sec-Fetch-Dest' => 'empty'])->assertRedirect(route('login'));

        $this->signIn($user)->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_an_inertia_visit_counts_as_a_page(): void
    {
        $user = User::factory()->create();

        $this->get(route('subscribers.index'), ['X-Inertia' => 'true', 'Sec-Fetch-Dest' => 'empty']);

        $this->signIn($user)->assertRedirect(route('subscribers.index'));
    }
}
