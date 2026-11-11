<?php

namespace Tests\Feature;

use App\Models\SmsProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Saving an SMS provider: a field sent empty is cleared, a field the request does not
 * mention stays as it was.
 */
class SmsProviderUpdateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private SmsProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->provider = new SmsProvider([
            'name' => 'Main',
            'provider' => 'smsapi',
            'from_name' => 'Shop',
            'is_active' => true,
            'is_default' => false,
            'daily_limit' => 500,
        ]);
        $this->provider->user_id = $this->user->id;
        $this->provider->setCredentials(['api_token' => 'secret']);
        $this->provider->save();
    }

    public function test_an_empty_daily_limit_removes_the_limit(): void
    {
        $this->actingAs($this->user)
            ->putJson(route('settings.sms-providers.update', $this->provider), [
                'name' => 'Main',
                'from_name' => 'Shop',
                'daily_limit' => null,
            ])
            ->assertSessionHasNoErrors();

        $this->assertNull($this->provider->refresh()->daily_limit);
    }

    public function test_what_the_request_leaves_out_stays(): void
    {
        $this->actingAs($this->user)
            ->putJson(route('settings.sms-providers.update', $this->provider), [
                'name' => 'Renamed',
                'is_active' => false,
            ])
            ->assertSessionHasNoErrors();

        $this->provider->refresh();
        $this->assertSame('Renamed', $this->provider->name);
        $this->assertFalse($this->provider->is_active);
        $this->assertSame(500, $this->provider->daily_limit);
        $this->assertSame('Shop', $this->provider->from_name);
    }
}
