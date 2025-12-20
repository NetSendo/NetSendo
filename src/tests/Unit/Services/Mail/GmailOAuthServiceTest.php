<?php

namespace Tests\Unit\Services\Mail;

use App\Models\Mailbox;
use App\Services\Mail\GmailOAuthService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;
use Mockery;

class GmailOAuthServiceTest extends TestCase
{
    private GmailOAuthService $service;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock config credentials
        Config::set('services.google.client_id', 'test-client-id');
        Config::set('services.google.client_secret', 'test-client-secret');
        Config::set('services.google.redirect_uri', 'http://localhost/callback');

        $this->service = new GmailOAuthService();
    }

    public function test_get_authorization_url_contains_correct_params()
    {
        $url = $this->service->getAuthorizationUrl();

        $this->assertStringContainsString('client_id=test-client-id', $url);
        $this->assertStringContainsString('redirect_uri=' . urlencode('http://localhost/callback'), $url);
        $this->assertStringContainsString('response_type=code', $url);
        $this->assertStringContainsString('scope=' . urlencode('https://www.googleapis.com/auth/gmail.send https://www.googleapis.com/auth/userinfo.email'), $url);
        $this->assertStringContainsString('access_type=offline', $url);
        $this->assertStringContainsString('prompt=consent', $url);
    }

    public function test_get_valid_access_token_returns_existing_token_if_not_expired()
    {
        $mailbox = Mockery::mock(Mailbox::class);
        $mailbox->shouldReceive('getDecryptedCredentials')->andReturn([
            'access_token' => 'valid-token',
            'refresh_token' => 'refresh-token',
            'expires_at' => time() + 3600 // Valid for 1 hour
        ]);

        $token = $this->service->getValidAccessToken($mailbox);

        $this->assertEquals('valid-token', $token);
    }

    public function test_get_valid_access_token_refreshes_if_expired()
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'new-access-token',
                'expires_in' => 3599,
                'scope' => 'https://www.googleapis.com/auth/gmail.send',
                'token_type' => 'Bearer'
            ], 200)
        ]);

        $mailbox = Mockery::mock(Mailbox::class);
        $mailbox->shouldReceive('getDecryptedCredentials')->andReturn([
            'access_token' => 'expired-token',
            'refresh_token' => 'refresh-token',
            'expires_at' => time() - 3600 // Expired 1 hour ago
        ]);

        // Expect mailbox update
        $mailbox->shouldReceive('setCredentialsAttribute')->with(Mockery::on(function ($creds) {
            return $creds['access_token'] === 'new-access-token' 
                && $creds['expires_at'] > time();
        }))->once();
        
        $mailbox->shouldReceive('save')->once();

        $token = $this->service->getValidAccessToken($mailbox);

        $this->assertEquals('new-access-token', $token);
    }
}
