<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

// Registration creates only the first account (the administrator). After that the sign-in
// page no longer offers it, and "Forgot password" asks the relay for a reset email that
// links to this instance.
class RegistrationClosedTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_fresh_installation_offers_registration(): void
    {
        $this->get('/login')->assertInertia(fn ($page) => $page->component('Auth/Login')->where('isFirstUser', true));
        $this->get('/register')->assertOk();
    }

    public function test_once_an_account_exists_registration_is_closed_with_a_translated_message(): void
    {
        User::factory()->create();

        $this->get('/login')->assertInertia(fn ($page) => $page->where('isFirstUser', false));
        $this->get('/register')
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', __('auth.registration_closed'));
        $this->assertNotSame('auth.registration_closed', __('auth.registration_closed'));
    }

    public function test_forgot_password_asks_for_a_reset_email_linking_to_this_instance(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        Http::fake(['*' => Http::response(['newPassword' => 'N3w-pass!word'])]);

        app(\App\Services\PasswordRelayService::class)->sendNewPassword($user);

        Http::assertSent(fn (Request $r) => $r['email'] === 'owner@example.com' && $r['reason'] === 'reset'
            && $r['login_url'] === rtrim((string) config('app.url'), '/') . '/login'
            && is_string($r['locale']));
    }
}
