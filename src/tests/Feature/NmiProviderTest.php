<?php

namespace Tests\Feature;

use App\Models\DedicatedIpAddress;
use App\Models\DomainConfiguration;
use App\Models\IpPool;
use App\Models\Mailbox;
use App\Models\User;
use App\Services\Mail\MailProviderService;
use App\Services\Mail\Providers\NmiProvider;
use App\Services\Nmi\DkimKeyManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Tests\TestCase;

/**
 * NmiProvider sends through the Haraka container, logged in with the domain's NMI credentials,
 * and signs with the DKIM key of the dedicated IP. It called a method the model did not have
 * (getDecryptedNmiPassword) and could not be built for a mailbox, because the mailbox had no
 * domain to point at. A fake transport stands in for Haraka.
 */
class NmiProviderTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private DomainConfiguration $domain;
    private DedicatedIpAddress $ip;

    protected function setUp(): void
    {
        parent::setUp();

        config(['nmi.mta_host' => 'netsendo-mta', 'nmi.mta_port' => 25]);

        $this->user = User::factory()->create();
        $this->domain = DomainConfiguration::create([
            'user_id' => $this->user->id,
            'domain' => 'example.com',
            'cname_verified' => true,
            'cname_verified_at' => now(),
            'nmi_enabled' => true,
            'nmi_enabled_at' => now(),
            'nmi_smtp_username' => 'nmi-1-abcdefgh',
            'nmi_smtp_password' => 'p@ss:w/rd?#1',
        ]);

        $pool = IpPool::create([
            'user_id' => $this->user->id,
            'name' => 'Main',
            'type' => IpPool::TYPE_DEDICATED,
            'is_active' => true,
            'max_ips' => 10,
        ]);

        $keys = app(DkimKeyManager::class)->generateKeyPair('example.com', 'ns-test');
        $this->ip = DedicatedIpAddress::create([
            'ip_pool_id' => $pool->id,
            'domain_configuration_id' => $this->domain->id,
            'ip_address' => '203.0.113.10',
            'hostname' => 'mail1.example.com',
            'ip_version' => 'ipv4',
            'provider' => DedicatedIpAddress::PROVIDER_MANUAL,
            'warming_status' => DedicatedIpAddress::WARMING_WARMED,
            'reputation_score' => 100,
            'is_active' => true,
            'dkim_selector' => $keys['selector'],
            'dkim_private_key' => $keys['private_key'],
            'dkim_public_key' => $keys['public_key'],
            'dkim_generated_at' => now(),
        ]);
    }

    /** Records what would have been handed to Haraka */
    private function fakeTransport(): AbstractTransport
    {
        return new class extends AbstractTransport {
            /** @var list<SentMessage> */
            public array $sent = [];

            protected function doSend(SentMessage $message): void
            {
                $this->sent[] = $message;
            }

            public function __toString(): string
            {
                return 'fake://nmi';
            }
        };
    }

    private function provider(AbstractTransport $transport): NmiProvider
    {
        return new NmiProvider(
            domain: $this->domain,
            dedicatedIp: $this->ip,
            fromEmail: 'news@example.com',
            fromName: 'Example',
            replyTo: 'reply@example.com',
            transport: $transport,
        );
    }

    public function test_mail_is_signed_with_the_dkim_key_of_the_ip_and_handed_to_the_mta(): void
    {
        $transport = $this->fakeTransport();

        $this->assertTrue($this->provider($transport)->send('jan@example.org', 'Jan', 'Hello', '<p>Hi Jan</p>'));

        $this->assertCount(1, $transport->sent);
        $raw = $transport->sent[0]->toString();
        $this->assertMatchesRegularExpression('/^DKIM-Signature: v=1;/m', $raw);
        $this->assertStringContainsString('d=example.com', $raw);
        $this->assertStringContainsString('s=ns-test', $raw);
        $this->assertStringContainsString('X-NMI-Domain: example.com', $raw);
        $this->assertStringContainsString('X-NMI-IP: 203.0.113.10', $raw);
        $this->assertSame('news@example.com', $transport->sent[0]->getEnvelope()->getSender()->getAddress());
        $this->assertSame(1, $this->ip->fresh()->total_sent);
    }

    public function test_the_transport_logs_in_to_the_mta_with_the_domain_credentials(): void
    {
        $transport = NmiProvider::createTransport($this->domain);

        $this->assertInstanceOf(EsmtpTransport::class, $transport);
        $this->assertSame('nmi-1-abcdefgh', $transport->getUsername());
        $this->assertSame('p@ss:w/rd?#1', $transport->getPassword());
        $this->assertSame('netsendo-mta', $transport->getStream()->getHost());
        $this->assertSame(25, $transport->getStream()->getPort());
    }

    public function test_the_password_is_stored_encrypted(): void
    {
        $this->assertSame('p@ss:w/rd?#1', $this->domain->fresh()->getDecryptedNmiPassword());
        $this->assertNotSame(
            'p@ss:w/rd?#1',
            \DB::table('domain_configurations')->where('id', $this->domain->id)->value('nmi_smtp_password')
        );
    }

    public function test_the_warming_limit_of_the_ip_stops_sending(): void
    {
        $this->ip->update([
            'warming_status' => DedicatedIpAddress::WARMING_WARMING,
            'warming_day' => 1,
            'sent_today' => 50,
            'sent_today_date' => now()->toDateString(),
        ]);
        $transport = $this->fakeTransport();

        try {
            $this->provider($transport)->send('jan@example.org', 'Jan', 'Hello', '<p>Hi</p>');
            $this->fail('Sending past the warming limit should fail');
        } catch (\Exception $e) {
            $this->assertStringContainsString('warming limit', $e->getMessage());
        }

        $this->assertCount(0, $transport->sent);
    }

    public function test_the_connection_test_needs_nmi_switched_on_for_the_domain(): void
    {
        $this->domain->update(['nmi_enabled' => false]);
        $transport = $this->fakeTransport();

        $result = $this->provider($transport)->testConnection('owner@example.org');

        $this->assertFalse($result['success']);
        $this->assertSame('NetSendo MTA is switched off for example.com.', $result['message']);
        $this->assertCount(0, $transport->sent);
    }

    public function test_the_connection_test_sends_a_signed_test_message(): void
    {
        $transport = $this->fakeTransport();

        $result = $this->provider($transport)->testConnection('owner@example.org');

        $this->assertTrue($result['success']);
        $this->assertSame('203.0.113.10', $result['details']['ip']);
        $this->assertTrue($result['details']['dkim_enabled']);
        $this->assertCount(1, $transport->sent);
        $this->assertStringContainsString('s=ns-test', $transport->sent[0]->toString());
    }

    public function test_the_provider_service_builds_the_nmi_provider_of_a_mailbox(): void
    {
        $mailbox = Mailbox::create([
            'user_id' => $this->user->id,
            'name' => 'NMI',
            'provider' => 'nmi',
            'from_email' => 'news@example.com',
            'from_name' => 'Example',
            'is_default' => true,
            'is_active' => true,
            'allowed_types' => ['broadcast', 'autoresponder', 'system'],
            'credentials' => [],
            'domain_configuration_id' => $this->domain->id,
            'dedicated_ip_id' => $this->ip->id,
        ]);

        $provider = app(MailProviderService::class)->getProvider($mailbox->fresh());

        $this->assertInstanceOf(NmiProvider::class, $provider);
        $this->assertSame('example.com', $provider->getStatus()['domain']);
        $this->assertSame('203.0.113.10', $provider->getStatus()['dedicated_ip']);
        $this->assertTrue($provider->getStatus()['dkim_enabled']);
    }

    public function test_a_mailbox_without_its_nmi_domain_gets_a_clear_error(): void
    {
        $mailbox = Mailbox::create([
            'user_id' => $this->user->id,
            'name' => 'NMI',
            'provider' => 'nmi',
            'from_email' => 'news@example.com',
            'from_name' => 'Example',
            'is_default' => true,
            'is_active' => true,
            'allowed_types' => ['broadcast'],
            'credentials' => [],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        app(MailProviderService::class)->getProvider($mailbox);
    }
}
