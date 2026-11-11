<?php

namespace Tests\Feature;

use App\Models\Mailbox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Settings → Mailboxes, "Add mailbox" (client report 2026-11-11): choosing "NetSendo MTA"
 * could never be saved. The form offered the NMI provider, the server accepts only SMTP,
 * SendGrid and Gmail, and its errors (provider, credentials) had no place in the modal, so
 * all the person saw was a toast with the raw key "common.notifications.error".
 */
class MailboxFormTest extends TestCase
{
    use RefreshDatabase;

    private const LOCALES = ['en', 'pl', 'de', 'es'];

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    private function smtpPayload(array $extra = []): array
    {
        return array_merge([
            'name' => 'Newsletter',
            'provider' => 'smtp',
            'from_email' => 'news@example.com',
            'from_name' => 'News',
            'reply_to' => null,
            'is_active' => true,
            'allowed_types' => ['broadcast', 'autoresponder', 'system'],
            'credentials' => ['host' => 'smtp.example.com', 'port' => 587, 'encryption' => 'tls', 'username' => 'u', 'password' => 'p'],
            'daily_limit' => null,
            'time_restriction' => 2,
            'custom_headers' => [],
            'tracking_enabled' => true,
        ], $extra);
    }

    public function test_the_form_offers_only_providers_the_server_accepts(): void
    {
        $this->withoutVite();

        $this->actingAs($this->user)
            ->get(route('settings.mailboxes.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Settings/Mailboxes/Index')
                ->where('providers', fn ($providers) => array_keys(collect($providers)->all()) === Mailbox::PANEL_PROVIDERS)
            );

        $this->assertNotContains(Mailbox::PROVIDER_NMI, Mailbox::PANEL_PROVIDERS);
    }

    public function test_an_smtp_mailbox_is_created(): void
    {
        $this->actingAs($this->user)
            ->from(route('settings.mailboxes.index'))
            ->post(route('settings.mailboxes.store'), $this->smtpPayload())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('settings.mailboxes.index'));

        $mailbox = Mailbox::where('user_id', $this->user->id)->sole();
        $this->assertSame('smtp', $mailbox->provider);
        $this->assertSame('smtp.example.com', $mailbox->getDecryptedCredentials()['host']);
    }

    public function test_an_nmi_mailbox_is_refused_with_a_message(): void
    {
        $this->actingAs($this->user)
            ->post(route('settings.mailboxes.store'), $this->smtpPayload(['provider' => 'nmi', 'credentials' => []]))
            ->assertSessionHasErrors(['provider', 'credentials']);

        $this->assertSame(0, Mailbox::count());
    }

    public function test_messages_name_the_field_in_the_users_language(): void
    {
        $this->user->forceFill(['locale' => 'pl'])->save();

        $this->actingAs($this->user)
            ->post(route('settings.mailboxes.store'), $this->smtpPayload([
                'credentials' => [],
                'custom_headers' => [['key' => '', 'value' => 'x']],
            ]));

        $errors = session('errors')->getBag('default');
        $this->assertStringContainsString('dane logowania do serwera', $errors->first('credentials'));
        $this->assertStringContainsString('nazwa nagłówka', $errors->first('custom_headers.0.key'));
    }

    public function test_every_key_the_mailbox_page_uses_exists_in_all_four_languages(): void
    {
        $locales = [];
        foreach (self::LOCALES as $locale) {
            $locales[$locale] = json_decode(File::get(resource_path("js/locales/{$locale}.json")), true);
        }

        preg_match_all(
            '/\$?\bt\(\s*["\']([a-z_]+\.[a-z0-9_.]+)["\']\s*[,)]/',
            File::get(resource_path('js/Pages/Settings/Mailboxes/Index.vue')),
            $matches
        );
        $this->assertNotEmpty($matches[1]);

        foreach (array_unique($matches[1]) as $key) {
            foreach ($locales as $locale => $messages) {
                $this->assertIsString(data_get($messages, $key), "{$key} is missing in {$locale}.json");
            }
        }
    }

    public function test_no_page_uses_the_missing_generic_error_key(): void
    {
        foreach (File::allFiles(resource_path('js')) as $file) {
            if (! in_array($file->getExtension(), ['vue', 'js'], true)) {
                continue;
            }
            $this->assertDoesNotMatchRegularExpression(
                '/["\']common\.notifications\.error["\']/',
                $file->getContents(),
                "{$file->getRelativePathname()} uses common.notifications.error, which has no text"
            );
        }
    }
}
