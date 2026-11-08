<?php

namespace Tests\Feature;

use App\Models\ContactList;
use App\Models\Message;
use App\Models\User;
use App\Services\Messages\MessageEditorService;
use App\Services\Sms\SmsProviderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Polish texts were hard-coded in the English (and German, Spanish) panel (2.7.2 review,
 * issue 20): message save messages, SMS provider names and credential labels, the form
 * builder, the "[KOPIA]" prefix of copies and the "no mailbox" errors of SendEmailJob.
 */
class PanelTranslationsTest extends TestCase
{
    use RefreshDatabase;

    private const LOCALES = ['en', 'pl', 'de', 'es'];

    /** Files whose user-facing texts moved to translations */
    private const PHP_FILES = [
        'app/Http/Controllers/MessageController.php',
        'app/Http/Controllers/MailingListController.php',
        'app/Http/Controllers/SmsProviderController.php',
        'app/Services/Sms/SmsProviderService.php',
        'app/Jobs/SendEmailJob.php',
        'app/Services/Messages/MessageEditorService.php',
    ];

    /** Pages changed in this fix: every key they use must exist in all four languages */
    private const VUE_FILES = [
        'js/Pages/Forms/Builder.vue',
        'js/Pages/Deliverability/Index.vue',
        'js/Pages/Deliverability/DomainStatus.vue',
        'js/Pages/Deliverability/SimulationHistory.vue',
        'js/Pages/Deliverability/SimulationResult.vue',
        'js/Pages/Deliverability/InboxPassport.vue',
        'js/Components/Deliverability/DnsRecordGenerator.vue',
        'js/Pages/MailingList/Create.vue',
        // NetSendo MTA setup (NmiSetupFlowTest)
        'js/Pages/Nmi/IpDetail.vue',
        'js/Pages/Nmi/Index.vue',
        'js/Pages/Settings/Mailboxes/Index.vue',
    ];

    public function test_the_listed_php_files_have_no_polish_texts_in_string_literals(): void
    {
        foreach (self::PHP_FILES as $path) {
            $tokens = token_get_all(File::get(base_path($path)));
            foreach ($tokens as $token) {
                if (is_array($token) && in_array($token[0], [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true)) {
                    $this->assertDoesNotMatchRegularExpression(
                        '/[ąćęłńóśźżĄĆĘŁŃÓŚŹŻ]/u',
                        $token[1],
                        "{$path}:{$token[2]} has a Polish text: {$token[1]}"
                    );
                }
            }
        }
    }

    public function test_the_form_builder_has_no_hard_coded_polish_text(): void
    {
        $source = File::get(resource_path('js/Pages/Forms/Builder.vue'));

        $this->assertDoesNotMatchRegularExpression('/[ąćęłńóśźżĄĆĘŁŃÓŚŹŻ]/u', $source);
        foreach (['Kontener', 'Przycisk', 'Efekty', 'Kierunek', 'Animacja'] as $word) {
            $this->assertStringNotContainsString($word, $source);
        }
    }

    public function test_every_key_the_changed_pages_use_exists_in_all_four_languages(): void
    {
        $locales = [];
        foreach (self::LOCALES as $locale) {
            $locales[$locale] = json_decode(File::get(resource_path("js/locales/{$locale}.json")), true);
        }

        foreach (self::VUE_FILES as $path) {
            // Complete keys only: "forms.builder.tab_" + tab is built at run time
            preg_match_all('/\$?\bt\(\s*["\']([a-z_]+\.[a-z0-9_.]+)["\']\s*[,)]/', File::get(resource_path($path)), $matches);
            foreach (array_unique($matches[1]) as $key) {
                foreach ($locales as $locale => $messages) {
                    $this->assertIsString(data_get($messages, $key), "{$path}: {$key} is missing in {$locale}.json");
                }
            }
        }
    }

    public function test_saving_a_message_answers_in_the_users_language(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();
        $list = ContactList::create(['user_id' => $user->id, 'name' => 'Readers', 'type' => 'email']);

        $this->actingAs($user)->post(route('messages.store'), [
            'subject' => 'Draft',
            'type' => 'broadcast',
            'status' => 'draft',
            'content' => '<p>Hi</p>',
            'contact_list_ids' => [$list->id],
        ])->assertSessionHas('success', 'The message has been saved as a draft.');

        $this->actingAs($user)->post(route('mailing-lists.store'), ['name' => 'Second'])
            ->assertSessionHas('success', 'The list has been created.');
    }

    public function test_sms_provider_labels_and_the_copy_prefix_follow_the_locale(): void
    {
        App::setLocale('en');
        $labels = array_column(SmsProviderService::getProviderFields('twilio'), 'label');
        $this->assertContains('Sender number', $labels);
        $this->assertSame('Global SMS provider (USA, Europe, Asia)', SmsProviderService::getAvailableProviders()[0]['description']);

        App::setLocale('pl');
        $labels = array_column(SmsProviderService::getProviderFields('twilio'), 'label');
        $this->assertContains('Numer nadawcy', $labels);

        App::setLocale('en');
        $user = User::factory()->create();
        $message = Message::create([
            'user_id' => $user->id,
            'channel' => 'email',
            'type' => 'broadcast',
            'subject' => 'Spring sale',
            'content' => '<p>Hi</p>',
            'status' => 'draft',
        ]);
        $this->assertSame('[COPY] Spring sale', app(MessageEditorService::class)->duplicate($message)->subject);
    }

    public function test_every_new_backend_key_exists_in_all_four_languages(): void
    {
        $keys = [
            'messages.campaign.saved_draft', 'messages.campaign.resend_scheduled', 'messages.campaign.skipped_scheduled',
            'mailing_lists.flash.created', 'mailing_lists.flash.copy_success', 'mailing_lists.flash.no_edit_permission',
            'sms_providers.notifications.created', 'sms_providers.notifications.test_failed',
            'sms_providers.fields.from_number', 'sms_providers.providers.plivo',
            'mail.errors.no_mailbox', 'mail.errors.no_mailbox_for_type', 'common.copy.prefix',
        ];

        foreach (self::LOCALES as $locale) {
            foreach ($keys as $key) {
                $this->assertNotSame($key, trans($key, [], $locale), "{$locale}: {$key} is missing");
            }
        }
    }
}
