<?php

namespace Tests\Feature;

use App\Models\Mailbox;
use App\Models\SmsProvider;
use App\Models\User;
use App\Services\Mail\MailProviderService;
use App\Services\Sms\SmsProviderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * The fallback "any active mailbox / SMS provider under its daily limit" used
 * whereRaw('daily_limit IS NULL OR sent_today < daily_limit') without parentheses, so the
 * OR escaped the user condition and the query could return another user's mailbox or SMS
 * provider (with its credentials) on an instance with several users.
 */
class ProviderFallbackOwnerScopeTest extends TestCase
{
    use RefreshDatabase;

    private function mailbox(User $user, array $attributes = []): Mailbox
    {
        return Mailbox::create($attributes + [
            'user_id' => $user->id,
            'name' => 'SMTP',
            'provider' => 'smtp',
            'from_email' => "user{$user->id}@example.com",
            'from_name' => 'Sender',
            'is_default' => false,
            'is_active' => true,
            'allowed_types' => ['broadcast', 'autoresponder', 'system'],
            'credentials' => ['host' => 'localhost', 'port' => 1025],
        ]);
    }

    private function smsProvider(User $user, array $attributes = []): SmsProvider
    {
        return SmsProvider::create($attributes + [
            'user_id' => $user->id,
            'name' => 'SMSAPI',
            'provider' => 'smsapi',
            'credentials' => Crypt::encryptString('{"token":"test"}'),
            'is_active' => true,
            'is_default' => false,
        ]);
    }

    public function test_the_mailbox_fallback_never_returns_another_users_mailbox(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        // The other user's mailbox has a daily limit it has not reached
        $this->mailbox($other, ['daily_limit' => 1000, 'sent_today' => 5]);

        $this->assertNull(app(MailProviderService::class)->getBestMailbox($owner->id, 'broadcast'));
    }

    public function test_the_mailbox_fallback_returns_the_users_own_mailbox_under_its_limit(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->mailbox($other, ['daily_limit' => 1000, 'sent_today' => 5]);
        $own = $this->mailbox($owner, ['daily_limit' => 100, 'sent_today' => 10]);

        $this->assertSame($own->id, app(MailProviderService::class)->getBestMailbox($owner->id, 'broadcast')?->id);
    }

    public function test_the_sms_provider_fallback_never_returns_another_users_provider(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->smsProvider($other, ['daily_limit' => 1000, 'sent_today' => 5]);

        $this->assertNull(app(SmsProviderService::class)->getBestProvider($owner->id));
    }
}
