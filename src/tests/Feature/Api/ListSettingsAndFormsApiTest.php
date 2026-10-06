<?php

namespace Tests\Feature\Api;

use App\Models\ApiKey;
use App\Models\ContactList;
use App\Models\ContactListCronSetting;
use App\Models\CronSetting;
use App\Models\SubscriptionForm;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * List configuration (settings document, sending schedule, account defaults)
 * and subscription forms over /api/v1, as used by the MCP client.
 */
class ListSettingsAndFormsApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $key;
    private ContactList $list;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->key = ApiKey::generate($this->user->id, 'Config', ['lists:read', 'lists:write'])['key'];
        $this->list = $this->makeList($this->user, 'Newsletter');
    }

    private function makeList(User $user, string $name, string $type = 'email', array $settings = []): ContactList
    {
        return ContactList::create(['user_id' => $user->id, 'name' => $name, 'type' => $type, 'settings' => $settings]);
    }

    private function api(string $method, string $uri, array $payload = [], ?string $key = null): TestResponse
    {
        return $this->withHeaders(['Authorization' => 'Bearer ' . ($key ?? $this->key)])
            ->json($method, "/api/v1/{$uri}", $payload);
    }

    // ---------------------------------------------------------------- lists

    public function test_partial_settings_update_deep_merges_and_keeps_other_keys(): void
    {
        $this->list->update(['settings' => [
            'subscription' => ['double_optin' => true, 'notification_email' => 'owner@example.com'],
            'sending' => ['from_name' => 'Shop', 'company_name' => 'Shop Ltd'],
            'pages' => ['success' => ['type' => 'custom', 'url' => 'https://example.com/thanks', 'external_page_id' => null]],
            'advanced' => ['queue_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'], 'bounce_analysis' => true],
        ]]);

        $this->api('PUT', "lists/{$this->list->id}", [
            'settings' => [
                'sending' => ['reply_to' => 'reply@example.com'],
                'pages' => ['unsubscribe' => ['type' => 'custom', 'url' => 'https://example.com/bye']],
                'advanced' => ['queue_days' => ['monday', 'friday']],
            ],
        ])->assertOk()
            ->assertJsonPath('data.settings.sending.reply_to', 'reply@example.com')
            ->assertJsonPath('data.settings.sending.from_name', 'Shop')
            ->assertJsonPath('data.double_opt_in', true);

        $settings = $this->list->fresh()->settings;

        $this->assertSame('owner@example.com', $settings['subscription']['notification_email']);
        $this->assertTrue($settings['subscription']['double_optin']);
        $this->assertSame('Shop Ltd', $settings['sending']['company_name']);
        $this->assertSame('reply@example.com', $settings['sending']['reply_to']);
        $this->assertSame('https://example.com/thanks', $settings['pages']['success']['url']);
        $this->assertSame('https://example.com/bye', $settings['pages']['unsubscribe']['url']);
        // Lists are replaced, not merged by index
        $this->assertSame(['monday', 'friday'], $settings['advanced']['queue_days']);
        $this->assertTrue($settings['advanced']['bounce_analysis']);
    }

    public function test_list_settings_are_validated_like_the_editor(): void
    {
        $this->api('PUT', "lists/{$this->list->id}", [
            'settings' => [
                'subscription' => ['delete_unconfirmed_after_days' => 999, 'notification_email' => 'nope'],
                'pages' => ['thank_you' => ['type' => 'custom'], 'success' => ['type' => 'redirect']],
                'advanced' => ['bounce_scope' => 'everywhere'],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors([
            'settings.subscription.delete_unconfirmed_after_days',
            'settings.subscription.notification_email',
            'settings.pages',
            'settings.pages.success.type',
            'settings.advanced.bounce_scope',
        ]);

        $other = User::factory()->create();
        $foreignList = $this->makeList($other, 'Foreign');
        $foreignTag = Tag::create(['user_id' => $other->id, 'name' => 'x']);

        $this->api('PUT', "lists/{$this->list->id}", [
            'parent_list_id' => $foreignList->id,
            'tags' => [$foreignTag->id],
        ])->assertStatus(422)->assertJsonValidationErrors(['parent_list_id', 'tags.0']);
    }

    public function test_list_columns_from_the_editor_can_be_set(): void
    {
        $parent = $this->makeList($this->user, 'Parent');
        $tag = Tag::create(['user_id' => $this->user->id, 'name' => 'b2b']);

        $created = $this->api('POST', 'lists', [
            'name' => 'Leads',
            'tags' => [$tag->id],
            'parent_list_id' => $parent->id,
            'sync_settings' => ['sync_on_subscribe' => true, 'sync_on_unsubscribe' => false],
            'reset_autoresponders_on_resubscription' => false,
            'signups_blocked' => true,
            'settings' => ['subscription' => ['double_optin' => true]],
        ])->assertCreated()
            ->assertJsonPath('data.parent_list_id', $parent->id)
            ->assertJsonPath('data.tags.0.id', $tag->id)
            ->assertJsonPath('data.signups_blocked', true)
            ->assertJsonPath('data.reset_autoresponders_on_resubscription', false)
            ->assertJsonPath('data.double_opt_in', true)
            ->json('data.id');

        $this->api('PATCH', "lists/{$created}", ['sync_settings' => ['sync_on_unsubscribe' => true]])
            ->assertOk()
            ->assertJsonPath('data.sync_settings.sync_on_subscribe', true)
            ->assertJsonPath('data.sync_settings.sync_on_unsubscribe', true);

        $this->api('GET', "lists/{$created}")->assertOk()->assertJsonPath('data.settings.subscription.double_optin', true);
    }

    // ----------------------------------------------------------------- cron

    public function test_cron_settings_round_trip(): void
    {
        $this->api('GET', "lists/{$this->list->id}/cron-settings")
            ->assertOk()
            ->assertJsonPath('data.use_defaults', true)
            ->assertJsonPath('data.effective_schedule.monday.end', 1440);

        $this->api('PUT', "lists/{$this->list->id}/cron-settings", [
            'volume_per_minute' => 40,
            'schedule' => [
                'monday' => ['start' => 480, 'end' => 1080],
                'sunday' => ['enabled' => false],
            ],
        ])->assertOk()
            ->assertJsonPath('data.use_defaults', false)
            ->assertJsonPath('data.volume_per_minute', 40)
            ->assertJsonPath('data.effective_volume_per_minute', 40)
            ->assertJsonPath('data.schedule.monday.start', 480)
            ->assertJsonPath('data.schedule.monday.enabled', true)
            ->assertJsonPath('data.schedule.sunday.enabled', false)
            ->assertJsonPath('data.schedule.tuesday.end', 1440);

        // Only Tuesday changes; Monday and the limit are kept
        $this->api('PUT', "lists/{$this->list->id}/cron-settings", ['schedule' => ['tuesday' => ['end' => 600]]])
            ->assertOk()
            ->assertJsonPath('data.schedule.monday.start', 480)
            ->assertJsonPath('data.schedule.tuesday.end', 600)
            ->assertJsonPath('data.volume_per_minute', 40);

        $stored = ContactListCronSetting::where('contact_list_id', $this->list->id)->first();
        $this->assertFalse($stored->use_defaults);
        $this->assertSame(480, $stored->schedule['monday']['start']);

        $this->api('PUT', "lists/{$this->list->id}/cron-settings", ['schedule' => ['friday' => ['start' => 900, 'end' => 100]]])
            ->assertStatus(422)->assertJsonValidationErrors(['schedule.friday.end']);
        $this->api('PUT', "lists/{$this->list->id}/cron-settings", ['schedule' => ['someday' => ['start' => 1]]])
            ->assertStatus(422)->assertJsonValidationErrors(['schedule']);

        $this->api('PUT', "lists/{$this->list->id}/cron-settings", ['use_defaults' => true])
            ->assertOk()
            ->assertJsonPath('data.use_defaults', true)
            ->assertJsonPath('data.volume_per_minute', null);
    }

    // ------------------------------------------------------------- defaults

    public function test_list_defaults_round_trip_and_keep_other_account_settings(): void
    {
        $this->user->update(['settings' => [
            'crm' => ['auto_convert_contacts' => false],
            'sending' => ['from_name' => 'Old', 'company_name' => 'ACME'],
        ]]);

        $this->api('GET', 'settings/list-defaults')
            ->assertOk()
            ->assertJsonPath('data.settings.sending.company_name', 'ACME')
            ->assertJsonMissingPath('data.settings.crm');

        $this->api('PUT', 'settings/list-defaults', [
            'settings' => [
                'sending' => ['from_name' => 'New'],
                'subscription' => ['double_optin' => true],
                'cron' => ['volume_per_minute' => 250, 'schedule' => ['sunday' => ['enabled' => false]]],
            ],
        ])->assertOk()
            ->assertJsonPath('data.settings.sending.from_name', 'New')
            ->assertJsonPath('data.settings.sending.company_name', 'ACME')
            ->assertJsonPath('data.settings.subscription.double_optin', true)
            ->assertJsonPath('data.cron.volume_per_minute', 250)
            ->assertJsonPath('data.cron.schedule.sunday.enabled', false)
            ->assertJsonPath('data.cron.schedule.monday.enabled', true);

        $settings = $this->user->fresh()->settings;
        $this->assertFalse($settings['crm']['auto_convert_contacts']);
        $this->assertSame(250, (int) CronSetting::getValue('volume_per_minute'));

        $this->api('PUT', 'settings/list-defaults', ['settings' => ['crm' => ['x' => 1]]])
            ->assertStatus(422)->assertJsonValidationErrors(['settings']);
    }

    // ---------------------------------------------------------------- forms

    public function test_form_crud_with_embed_code(): void
    {
        $created = $this->api('POST', 'forms', [
            'name' => 'Footer signup',
            'contact_list_id' => $this->list->id,
            'fields' => [['id' => 'email'], ['id' => 'fname', 'required' => true]],
            'design_preset' => 'modern_dark',
            'styles' => ['submit_text' => 'Join'],
            'captcha_secret_key' => 'top-secret',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.fields.0.type', 'email')
            ->assertJsonPath('data.fields.0.required', true)
            ->assertJsonPath('data.fields.1.label', 'Imię')
            ->assertJsonPath('data.styles.bgcolor', '#1F2937')
            ->assertJsonPath('data.styles.submit_text', 'Join')
            ->assertJsonPath('data.styles.padding', 24)
            ->assertJsonPath('data.captcha_secret_key_set', true)
            ->assertJsonMissingPath('data.captcha_secret_key');

        $id = $created->json('data.id');
        $slug = $created->json('data.slug');
        $this->assertNotEmpty($slug);
        $this->assertStringContainsString("netsendo-form-{$slug}", $created->json('data.embed.html'));
        $this->assertStringContainsString("/subscribe/js/{$slug}", $created->json('data.embed.js'));
        $this->assertStringContainsString("/subscribe/form/{$slug}", $created->json('data.embed.iframe'));
        $this->assertStringEndsWith("/subscribe/form/{$slug}", $created->json('data.urls.hosted'));

        $this->api('GET', "forms?list_id={$this->list->id}")->assertOk()->assertJsonPath('data.0.id', $id)->assertJsonPath('meta.total', 1);
        $this->api('GET', 'forms?list_id=' . ($this->list->id + 999))->assertOk()->assertJsonPath('meta.total', 0);

        $this->api('PATCH', "forms/{$id}", ['status' => 'active', 'styles' => ['bgcolor' => '#FFFFFF']])
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.styles.bgcolor', '#FFFFFF')
            ->assertJsonPath('data.styles.submit_text', 'Join')
            ->assertJsonPath('data.name', 'Footer signup');
        $this->assertSame('top-secret', SubscriptionForm::find($id)->captcha_secret_key);

        $this->api('PUT', "forms/{$id}", ['fields' => [['id' => 'fname']]])
            ->assertStatus(422)->assertJsonValidationErrors(['fields']);

        $copy = $this->api('POST', "forms/{$id}/duplicate")
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.name', '[KOPIA] Footer signup');
        $this->assertNotSame($slug, $copy->json('data.slug'));

        // Deleting a form with submissions needs confirmation
        DB::table('form_submissions')->insert([
            'subscription_form_id' => $id, 'status' => 'confirmed',
            'submission_data' => '{}', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->api('DELETE', "forms/{$id}")->assertStatus(409);
        $this->api('DELETE', "forms/{$id}?confirm=1")->assertOk();
        $this->assertNull(SubscriptionForm::find($id));
        $this->api('DELETE', 'forms/' . $copy->json('data.id'))->assertOk();
    }

    public function test_form_list_ownership_is_enforced(): void
    {
        $other = User::factory()->create();
        $foreignList = $this->makeList($other, 'Foreign');
        $smsList = $this->makeList($this->user, 'SMS', 'sms');

        $this->api('POST', 'forms', ['name' => 'X', 'contact_list_id' => $foreignList->id])
            ->assertStatus(422)->assertJsonValidationErrors(['contact_list_id']);
        $this->api('POST', 'forms', ['name' => 'X', 'contact_list_id' => $smsList->id])
            ->assertStatus(422)->assertJsonValidationErrors(['contact_list_id']);
        $this->api('POST', 'forms', ['name' => 'X', 'contact_list_id' => $this->list->id, 'coregister_lists' => [$foreignList->id]])
            ->assertStatus(422)->assertJsonValidationErrors(['coregister_lists.0']);
        $this->api('POST', 'forms', ['name' => 'X', 'contact_list_id' => $this->list->id, 'fields' => [['id' => 'email'], ['id' => 'custom_999']]])
            ->assertStatus(422)->assertJsonValidationErrors(['fields']);
    }

    // ------------------------------------------------------ scoping / perms

    public function test_other_accounts_records_are_not_found(): void
    {
        $other = User::factory()->create();
        $foreignList = $this->makeList($other, 'Foreign');
        $foreignForm = SubscriptionForm::create(['user_id' => $other->id, 'contact_list_id' => $foreignList->id, 'name' => 'Theirs']);

        $this->api('GET', "lists/{$foreignList->id}")->assertNotFound();
        $this->api('PUT', "lists/{$foreignList->id}", ['settings' => ['sending' => ['from_name' => 'x']]])->assertNotFound();
        $this->api('GET', "lists/{$foreignList->id}/cron-settings")->assertNotFound();
        $this->api('PUT', "lists/{$foreignList->id}/cron-settings", ['use_defaults' => true])->assertNotFound();
        $this->api('GET', "forms/{$foreignForm->id}")->assertNotFound();
        $this->api('PATCH', "forms/{$foreignForm->id}", ['name' => 'Mine'])->assertNotFound();
        $this->api('DELETE', "forms/{$foreignForm->id}")->assertNotFound();
        $this->api('POST', "forms/{$foreignForm->id}/duplicate")->assertNotFound();
        $this->api('GET', 'forms')->assertOk()->assertJsonPath('meta.total', 0);

        $this->assertSame('Theirs', $foreignForm->fresh()->name);
    }

    public function test_permissions_are_required(): void
    {
        $readOnly = ApiKey::generate($this->user->id, 'Read', ['lists:read'])['key'];
        $none = ApiKey::generate($this->user->id, 'None', ['subscribers:read'])['key'];

        $this->api('GET', "lists/{$this->list->id}/cron-settings", [], $readOnly)->assertOk();
        $this->api('GET', 'settings/list-defaults', [], $readOnly)->assertOk();
        $this->api('GET', 'forms', [], $readOnly)->assertOk();

        $this->api('PUT', "lists/{$this->list->id}/cron-settings", ['use_defaults' => true], $readOnly)->assertForbidden();
        $this->api('PUT', 'settings/list-defaults', ['settings' => ['sending' => ['from_name' => 'x']]], $readOnly)->assertForbidden();
        $this->api('POST', 'forms', ['name' => 'X', 'contact_list_id' => $this->list->id], $readOnly)->assertForbidden();

        $this->api('GET', "lists/{$this->list->id}/cron-settings", [], $none)->assertForbidden();
        $this->api('GET', 'settings/list-defaults', [], $none)->assertForbidden();
        $this->api('GET', 'forms', [], $none)->assertForbidden();
    }
}
