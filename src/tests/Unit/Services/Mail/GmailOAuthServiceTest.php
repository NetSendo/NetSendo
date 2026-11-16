<?php

namespace Tests\Unit\Services\Mail;

use App\Models\GoogleIntegration;
use App\Models\Mailbox;
use App\Models\User;
use App\Services\Mail\GmailOAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * OAuth client credentials live on a per-user GoogleIntegration, and the
 * tokens in the mailbox's encrypted credentials (token_expires_at as ISO 8601).
 */
class GmailOAuthServiceTest extends TestCase
{
    use RefreshDatabase;

    private GmailOAuthService $service;
    private GoogleIntegration $integration;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->integration = GoogleIntegration::create([
            'user_id' => $user->id,
            'name' => 'Test Google app',
            'client_id' => 'test-client-id',
            'client_secret' => 'test-client-secret',
        ]);

        $this->service = new GmailOAuthService();
    }

    private function gmailMailbox(array $credentials): Mailbox
    {
        return Mailbox::create([
            'user_id' => $this->integration->user_id,
            'google_integration_id' => $this->integration->id,
            'name' => 'Gmail',
            'provider' => 'gmail',
            'from_email' => 'sender@example.com',
            'from_name' => 'Sender',
            'credentials' => $credentials,
        ]);
    }

    public function test_get_authorization_url_contains_correct_params()
    {
        $url = $this->service->getAuthorizationUrl($this->integration, 'state-123');

        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $url);
        $this->assertStringContainsString('client_id=test-client-id', $url);
        $this->assertStringContainsString('redirect_uri=' . urlencode(route('settings.mailboxes.gmail.callback')), $url);
        $this->assertStringContainsString('response_type=code', $url);
        $this->assertStringContainsString(urlencode('https://www.googleapis.com/auth/gmail.send'), $url);
        $this->assertStringContainsString('access_type=offline', $url);
        $this->assertStringContainsString('prompt=consent', $url);
        $this->assertStringContainsString('state=state-123', $url);
    }

    public function test_get_valid_access_token_returns_existing_token_if_not_expired()
    {
        Http::fake();

        $mailbox = $this->gmailMailbox([
            'access_token' => 'valid-token',
            'refresh_token' => 'refresh-token',
            'token_expires_at' => now()->addHour()->toIso8601String(),
        ]);

        $token = $this->service->getValidAccessToken($mailbox);

        $this->assertEquals('valid-token', $token);
        Http::assertNothingSent();
    }

    public function test_get_valid_access_token_refreshes_if_expired()
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'new-access-token',
                'expires_in' => 3599,
                'scope' => 'https://www.googleapis.com/auth/gmail.send',
                'token_type' => 'Bearer',
            ], 200),
        ]);

        $mailbox = $this->gmailMailbox([
            'access_token' => 'expired-token',
            'refresh_token' => 'refresh-token',
            'token_expires_at' => now()->subHour()->toIso8601String(),
        ]);

        $token = $this->service->getValidAccessToken($mailbox);

        $this->assertEquals('new-access-token', $token);

        $credentials = $mailbox->fresh()->getDecryptedCredentials();
        $this->assertSame('new-access-token', $credentials['access_token']);
        $this->assertSame('refresh-token', $credentials['refresh_token']);

        Http::assertSent(fn ($request) => $request['grant_type'] === 'refresh_token'
            && $request['client_id'] === 'test-client-id'
            && $request['refresh_token'] === 'refresh-token');
    }
}
