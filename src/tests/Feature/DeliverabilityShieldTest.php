<?php

namespace Tests\Feature;

use App\Models\DomainConfiguration;
use App\Models\Mailbox;
use App\Models\User;
use App\Services\Deliverability\DnsLookupService;
use App\Services\Deliverability\DomainVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Concerns\ActivatesGold;
use Tests\TestCase;

/**
 * Deliverability Shield (2.7.2 review, issues 11 and 12):
 * - the pages compared the record statuses with 'pass', a value the check never stores
 *   (it stores valid/warning/critical/pending), so no badge ever turned green;
 * - the texts of the status cards had no translations (raw keys such as deliverability.spf.valid);
 * - DKIM of SMTP mailboxes was looked up only under default/mail/dkim, so Amazon SES (token
 *   selectors) was never found, and records without "v=DKIM1" were ignored;
 * - the SPF generator replaced the record with include:_spf.<NetSendo host> -all for SMTP mailboxes;
 * - the DMARC generator started at p=quarantine with strict alignment, and the published
 *   policy was never stored.
 */
class DeliverabilityShieldTest extends TestCase
{
    use RefreshDatabase;
    use ActivatesGold;

    private const SES_TOKEN = 'abcdefghijklmnopqrstuvwxyz234567';

    private User $user;

    /** @var array<string, array<int, string>> TXT records the fake resolver answers */
    private array $txt = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://panel.example.net']);
        $this->user = User::factory()->create();
        $this->activateGold();

        $txt = &$this->txt;
        $this->app->instance(DnsLookupService::class, new class($txt) extends DnsLookupService {
            public function __construct(private array &$records) {}

            public function lookupTxt(string $domain): array
            {
                return $this->records[strtolower($domain)] ?? [];
            }

            public function lookupCname(string $subdomain): ?string
            {
                return null;
            }

            public function clearCache(string $domain): void {}
        });
    }

    private function sesMailbox(): Mailbox
    {
        return Mailbox::create([
            'user_id' => $this->user->id,
            'name' => 'Amazon SES',
            'provider' => 'smtp',
            'from_email' => 'news@example.com',
            'from_name' => 'Example',
            'is_default' => true,
            'is_active' => true,
            'allowed_types' => ['broadcast', 'autoresponder', 'system'],
            'credentials' => ['host' => 'email-smtp.eu-central-1.amazonaws.com', 'port' => 587],
        ]);
    }

    private function domain(array $attributes = []): DomainConfiguration
    {
        return DomainConfiguration::create($attributes + [
            'user_id' => $this->user->id,
            'domain' => 'example.com',
            'cname_verified' => true,
            'cname_verified_at' => now(),
        ]);
    }

    private function publishSesDomain(): void
    {
        $this->txt = [
            'example.com' => ['v=spf1 ip4:203.0.113.10 include:_spf.google.com ~all'],
            // SES Easy DKIM: the token CNAME leads to a key published without "v=DKIM1"
            self::SES_TOKEN . '._domainkey.example.com' => ['p=MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQC'],
            '_dmarc.example.com' => ['v=DMARC1; p=reject; rua=mailto:reports@example.com'],
        ];
    }

    public function test_the_pages_compare_record_statuses_with_values_the_check_stores(): void
    {
        $stored = [
            DomainConfiguration::STATUS_PENDING, DomainConfiguration::STATUS_VALID,
            DomainConfiguration::STATUS_WARNING, DomainConfiguration::STATUS_CRITICAL,
        ];

        $files = array_merge(
            File::glob(resource_path('js/Pages/Deliverability/*.vue')),
            File::glob(resource_path('js/Components/Deliverability/*.vue')),
        );
        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            preg_match_all("/(?:spf|dkim|dmarc)_status\s*[!=]==\s*'([a-z_]+)'/", File::get($file), $matches);
            foreach ($matches[1] as $value) {
                $this->assertContains($value, $stored, basename($file) . " compares a record status with '{$value}'");
            }
        }
    }

    public function test_every_status_text_and_message_key_is_translated_in_all_languages(): void
    {
        $keys = [];
        foreach (['status' => ['pending', 'safe', 'warning', 'critical'], 'cname' => ['verified', 'pending']] as $group => $values) {
            foreach ($values as $value) {
                $keys[] = "deliverability.{$group}.{$value}";
            }
        }
        foreach (['spf', 'dkim', 'dmarc'] as $record) {
            foreach (['pending', 'valid', 'warning', 'critical'] as $status) {
                $keys[] = "deliverability.{$record}.{$status}";
            }
        }
        foreach (['none', 'quarantine', 'reject'] as $policy) {
            $keys[] = "deliverability.dmarc_policy.{$policy}";
        }

        // Keys the backend hands to __() or to the pages as message_key / label_key / action_key
        foreach (File::allFiles(app_path()) as $file) {
            preg_match_all("/'(?:message_key|label_key|action_key|explanation_key)'\s*=>\s*'(deliverability\.[a-z_.]+)'/", $file->getContents(), $m);
            $keys = array_merge($keys, $m[1]);
            preg_match_all("/__\('(deliverability\.[a-z_.]+)'/", $file->getContents(), $m);
            $keys = array_merge($keys, $m[1]);
        }
        // A key ending in "." is completed at run time (dmarc_policy.<policy>), listed above
        $keys = array_values(array_unique(array_filter($keys, fn ($key) => !str_ends_with($key, '.'))));

        foreach (['en', 'pl', 'de', 'es'] as $locale) {
            foreach ($keys as $key) {
                $text = trans($key, [], $locale);
                $this->assertIsString($text, "{$locale}: {$key} is a group, not a text");
                $this->assertNotSame($key, $text, "{$locale}: {$key} is missing");
            }
        }
    }

    public function test_ses_dkim_is_found_once_the_user_enters_a_token_selector(): void
    {
        $this->sesMailbox();
        $domain = $this->domain();
        $this->publishSesDomain();

        $service = app(DomainVerificationService::class);
        $result = $service->checkDnsRecords($domain);

        // The token cannot be guessed: the check asks for it instead of reporting "no DKIM"
        $this->assertSame(DomainConfiguration::STATUS_PENDING, $domain->fresh()->dkim_status);
        $this->assertSame('dkim_selector_needed', $result->dkim->issues[0]['code']);
        $this->assertSame('Amazon SES', $result->dkim->issues[0]['context']['provider']);

        // Pasted the way the SES console shows the CNAME name
        $this->actingAs($this->user)
            ->put(route('deliverability.domains.dkim-selector', $domain), [
                'dkim_selector' => self::SES_TOKEN . '._domainkey.example.com',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $domain->refresh();
        $this->assertSame(self::SES_TOKEN, $domain->dkim_selector);
        $this->assertSame(DomainConfiguration::STATUS_VALID, $domain->spf_status);
        $this->assertSame(DomainConfiguration::STATUS_VALID, $domain->dkim_status);
        $this->assertSame(DomainConfiguration::STATUS_VALID, $domain->dmarc_status);
        $this->assertSame(DomainConfiguration::OVERALL_SAFE, $domain->overall_status);
        // The policy actually published is stored
        $this->assertSame('reject', $domain->dmarc_policy);
        $this->assertSame(self::SES_TOKEN, $domain->dns_records['dkim_selector_found']);

        $this->actingAs($this->user)->get(route('deliverability.domains.show', $domain))
            ->assertInertia(fn (Assert $page) => $page
                ->where('domain.dkim_status', 'valid')
                ->where('domain.dkim_selector', self::SES_TOKEN)
                ->where('humanStatus.spf', 'SPF record is valid')
                ->where('humanStatus.dkim', 'DKIM record found')
                ->where('humanStatus.summary', 'Protected'));
    }

    public function test_another_user_cannot_set_the_dkim_selector(): void
    {
        $domain = $this->domain();

        $this->actingAs(User::factory()->create())
            ->put(route('deliverability.domains.dkim-selector', $domain), ['dkim_selector' => 'evil'])
            ->assertForbidden();

        $this->assertNull($domain->fresh()->dkim_selector);
    }

    public function test_generic_smtp_tries_common_selectors_and_records_without_version_tag(): void
    {
        Mailbox::create([
            'user_id' => $this->user->id,
            'name' => 'Hosting SMTP',
            'provider' => 'smtp',
            'from_email' => 'news@example.com',
            'from_name' => 'Example',
            'is_active' => true,
            'allowed_types' => ['broadcast'],
            'credentials' => ['host' => 'mail.example-hosting.net', 'port' => 587],
        ]);
        $domain = $this->domain();
        $this->txt = ['selector1._domainkey.example.com' => ['k=rsa; p=MIGfMA0GCSqGSIb3DQEB']];

        app(DomainVerificationService::class)->checkDnsRecords($domain);

        $this->assertSame(DomainConfiguration::STATUS_VALID, $domain->fresh()->dkim_status);
    }

    public function test_the_spf_generator_keeps_the_record_and_adds_no_netsendo_include_for_smtp(): void
    {
        $this->sesMailbox();
        $domain = $this->domain();
        $this->publishSesDomain();
        $service = app(DomainVerificationService::class);
        $service->checkDnsRecords($domain);

        $spf = $service->generateOptimalSpfRecord($domain->fresh());

        $this->assertSame('v=spf1 ip4:203.0.113.10 include:_spf.google.com -all', $spf['optimal']['value']);
        $this->assertSame('v=spf1 ip4:203.0.113.10 include:_spf.google.com ~all', $spf['soft_fail']['value']);
        $this->assertStringNotContainsString('_spf.panel.example.net', $spf['optimal']['value']);
        $this->assertSame(['Amazon SES'], $spf['providers']);
    }

    public function test_the_spf_generator_does_not_guess_an_include_for_an_unknown_smtp_server(): void
    {
        Mailbox::create([
            'user_id' => $this->user->id,
            'name' => 'Hosting SMTP',
            'provider' => 'smtp',
            'from_email' => 'news@example.com',
            'from_name' => 'Example',
            'is_active' => true,
            'allowed_types' => ['broadcast'],
            'credentials' => ['host' => 'mail.example-hosting.net'],
        ]);
        $domain = $this->domain();
        app(DomainVerificationService::class)->checkDnsRecords($domain);

        $spf = app(DomainVerificationService::class)->generateOptimalSpfRecord($domain->fresh());

        $this->assertNull($spf['optimal']);
        $this->assertTrue($spf['needs_provider_include']);
    }

    public function test_the_spf_generator_adds_the_missing_include_of_a_known_provider(): void
    {
        Mailbox::create([
            'user_id' => $this->user->id,
            'name' => 'SendGrid',
            'provider' => 'sendgrid',
            'from_email' => 'news@example.com',
            'from_name' => 'Example',
            'is_active' => true,
            'allowed_types' => ['broadcast'],
            'credentials' => ['api_key' => 'SG.test'],
        ]);
        $domain = $this->domain();
        $this->txt = ['example.com' => ['v=spf1 mx include:_spf.google.com ~all']];
        app(DomainVerificationService::class)->checkDnsRecords($domain);

        $spf = app(DomainVerificationService::class)->generateOptimalSpfRecord($domain->fresh());

        $this->assertSame('v=spf1 mx include:_spf.google.com include:sendgrid.net -all', $spf['optimal']['value']);
        $this->assertSame(['sendgrid.net'], $spf['optimal']['added_includes']);
    }

    public function test_the_dmarc_generator_starts_with_monitoring_and_relaxed_alignment(): void
    {
        $domain = $this->domain();
        app(DomainVerificationService::class)->checkDnsRecords($domain);

        $dmarc = app(DomainVerificationService::class)->generateOptimalDmarcRecord($domain->fresh());

        $this->assertSame('v=DMARC1; p=none; rua=mailto:dmarc-reports@example.com', $dmarc['initial']['value']);
        $this->assertSame('v=DMARC1; p=quarantine; rua=mailto:dmarc-reports@example.com', $dmarc['quarantine']['value']);
        $this->assertSame('v=DMARC1; p=reject; rua=mailto:dmarc-reports@example.com', $dmarc['recommended']['value']);
        foreach (['initial', 'quarantine', 'recommended', 'minimal'] as $level) {
            $this->assertStringNotContainsString('adkim=s', $dmarc[$level]['value']);
            $this->assertStringNotContainsString('aspf=s', $dmarc[$level]['value']);
            $this->assertStringNotContainsString('ruf=', $dmarc[$level]['value']);
        }
        $this->assertTrue($dmarc['should_configure']);
        $this->assertNull($dmarc['current']['policy']);
    }

    public function test_the_dmarc_generator_keeps_the_published_report_address(): void
    {
        $domain = $this->domain();
        $this->txt = ['_dmarc.example.com' => ['v=DMARC1; p=none; rua=mailto:dmarc@reports.example.org']];
        app(DomainVerificationService::class)->checkDnsRecords($domain);

        $dmarc = app(DomainVerificationService::class)->generateOptimalDmarcRecord($domain->fresh());

        $this->assertSame('none', $dmarc['current']['policy']);
        $this->assertTrue($dmarc['can_upgrade']);
        $this->assertFalse($dmarc['should_configure']);
        $this->assertSame('v=DMARC1; p=quarantine; rua=mailto:dmarc@reports.example.org', $dmarc['quarantine']['value']);
    }
}
