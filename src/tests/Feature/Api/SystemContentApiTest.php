<?php

namespace Tests\Feature\Api;

use App\Models\ApiKey;
use App\Models\ContactList;
use App\Models\SystemEmail;
use App\Models\SystemPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * System emails and system pages over /api/v1 (what the MCP client uses):
 * global defaults plus copy-on-write list overrides, addressed by slug.
 */
class SystemContentApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private ContactList $list;
    private string $key;
    private SystemEmail $globalEmail;
    private SystemPage $globalPage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->list = $this->makeList($this->user, 'Readers');
        $this->key = ApiKey::generate($this->user->id, 'System content', ['lists:read', 'lists:write'])['key'];

        // The migrations seed the globals; make the fixtures explicit anyway.
        $this->globalEmail = SystemEmail::firstOrCreate(
            ['slug' => 'signup_confirmation', 'contact_list_id' => null],
            ['name' => 'Signup Confirmation', 'subject' => 'Please Confirm', 'content' => '<p><a href="[[activation-link]]">Confirm</a></p>', 'is_active' => true]
        );
        $this->globalPage = SystemPage::firstOrCreate(
            ['slug' => 'signup_success', 'contact_list_id' => null],
            ['name' => 'Signup Success', 'title' => 'Thanks!', 'content' => '<h1>Thanks!</h1>', 'access' => 'public']
        );
    }

    private function makeList(User $owner, string $name): ContactList
    {
        return ContactList::create([
            'user_id' => $owner->id,
            'name' => $name,
            'type' => 'email',
            'is_public' => true,
        ]);
    }

    private function api(string $method, string $uri, array $payload = [], ?string $key = null): TestResponse
    {
        return $this->withHeaders(['Authorization' => 'Bearer ' . ($key ?? $this->key)])
            ->json($method, "/api/v1/{$uri}", $payload);
    }

    private function item(TestResponse $response, string $slug): array
    {
        return collect($response->json('data'))->firstWhere('slug', $slug);
    }

    public function test_list_view_resolves_overrides_over_global_defaults(): void
    {
        $global = $this->api('GET', 'system-emails')->assertOk();
        $this->assertSame('global', $global->json('meta.scope'));
        $this->assertSame('global', $this->item($global, 'signup_confirmation')['source']);
        $this->assertArrayNotHasKey('content', $this->item($global, 'signup_confirmation'));
        $this->assertNotEmpty($this->item($global, 'signup_confirmation')['description']);

        $override = SystemEmail::create([
            'slug' => 'signup_confirmation',
            'name' => 'Signup Confirmation',
            'subject' => 'Readers: confirm',
            'content' => '<p>Readers [[activation-link]]</p>',
            'is_active' => true,
            'contact_list_id' => $this->list->id,
        ]);

        $forList = $this->api('GET', "system-emails?list_id={$this->list->id}&include_content=1")->assertOk();
        $this->assertSame('list', $forList->json('meta.scope'));
        $this->assertSame($this->list->id, $forList->json('meta.list.id'));

        $resolved = $this->item($forList, 'signup_confirmation');
        $this->assertSame('list', $resolved['source']);
        $this->assertTrue($resolved['is_custom']);
        $this->assertSame($override->id, $resolved['id']);
        $this->assertSame($this->globalEmail->id, $resolved['global_id']);
        $this->assertSame('Readers: confirm', $resolved['subject']);
        $this->assertSame('<p>Readers [[activation-link]]</p>', $resolved['content']);

        // Other slugs still come from the globals, and the slug list matches the globals.
        $this->assertSame(
            SystemEmail::whereNull('contact_list_id')->count(),
            count($forList->json('data'))
        );
        foreach ($forList->json('data') as $row) {
            if ($row['slug'] !== 'signup_confirmation') {
                $this->assertSame('global', $row['source'], $row['slug']);
            }
        }

        // Single read with placeholders.
        $show = $this->api('GET', "system-emails/signup_confirmation?list_id={$this->list->id}")->assertOk();
        $this->assertSame($override->id, $show->json('data.id'));
        $this->assertContains('[[activation-link]]', array_column($show->json('data.placeholders.for_this_slug'), 'placeholder'));
        $this->assertContains('[[list-name]]', array_column($show->json('data.placeholders.built_in'), 'placeholder'));
        $this->assertIsArray($show->json('data.placeholders.available.standard'));

        $this->api('GET', 'system-emails/signup_confirmation')
            ->assertOk()
            ->assertJsonPath('data.source', 'global')
            ->assertJsonPath('data.id', $this->globalEmail->id);
    }

    public function test_update_for_a_list_creates_the_override_then_edits_it(): void
    {
        $created = $this->api('PUT', 'system-emails/signup_confirmation', [
            'list_id' => $this->list->id,
            'subject' => 'Confirm your Readers subscription',
        ])->assertCreated();

        $created->assertJsonPath('meta.created_override', true)
            ->assertJsonPath('data.source', 'list')
            ->assertJsonPath('data.contact_list_id', $this->list->id)
            ->assertJsonPath('data.subject', 'Confirm your Readers subscription')
            ->assertJsonPath('data.is_active', true);

        // Missing fields are copied from the global; the global is untouched.
        $this->assertSame($this->globalEmail->content, $created->json('data.content'));
        $this->assertSame($this->globalEmail->subject, $this->globalEmail->fresh()->subject);

        $overrideId = $created->json('data.id');
        $this->assertNotSame($this->globalEmail->id, $overrideId);

        $edited = $this->api('PUT', 'system-emails/signup_confirmation', [
            'list_id' => $this->list->id,
            'content' => '<p>New body [[activation-link]]</p>',
        ])->assertOk();

        $edited->assertJsonPath('meta.created_override', false)
            ->assertJsonPath('data.id', $overrideId)
            ->assertJsonPath('data.subject', 'Confirm your Readers subscription')
            ->assertJsonPath('data.content', '<p>New body [[activation-link]]</p>');

        $this->assertSame(1, SystemEmail::where('slug', 'signup_confirmation')->where('contact_list_id', $this->list->id)->count());

        $this->api('PUT', 'system-emails/signup_confirmation', ['list_id' => $this->list->id])
            ->assertStatus(422);
    }

    public function test_reset_deletes_the_override_and_globals_cannot_be_deleted(): void
    {
        $override = SystemPage::create([
            'slug' => 'signup_success',
            'name' => 'Signup Success',
            'title' => 'Readers thanks',
            'content' => '<h1>Readers</h1>',
            'access' => 'public',
            'contact_list_id' => $this->list->id,
        ]);

        $this->api('DELETE', "system-pages/signup_success?list_id={$this->list->id}")
            ->assertOk()
            ->assertJsonPath('meta.deleted', true)
            ->assertJsonPath('data.id', $this->globalPage->id)
            ->assertJsonPath('data.source', 'global');

        $this->assertNull(SystemPage::find($override->id));

        $this->api('GET', "system-pages/signup_success?list_id={$this->list->id}")
            ->assertOk()
            ->assertJsonPath('data.source', 'global');

        // Idempotent second reset.
        $this->api('DELETE', "system-pages/signup_success?list_id={$this->list->id}")
            ->assertOk()
            ->assertJsonPath('meta.deleted', false);

        // Globals cannot be deleted.
        $this->api('DELETE', 'system-pages/signup_success')->assertStatus(422);
        $this->api('DELETE', 'system-emails/signup_confirmation')->assertStatus(422);
        $this->assertNotNull($this->globalPage->fresh());
        $this->assertNotNull($this->globalEmail->fresh());
    }

    public function test_global_defaults_can_only_be_edited_by_the_account_admin(): void
    {
        $this->api('PUT', 'system-emails/signup_confirmation', ['subject' => 'Global subject'])
            ->assertOk()
            ->assertJsonPath('data.id', $this->globalEmail->id)
            ->assertJsonPath('data.source', 'global')
            ->assertJsonPath('meta.created_override', false);
        $this->assertSame('Global subject', $this->globalEmail->fresh()->subject);

        $member = User::factory()->create(['admin_user_id' => $this->user->id]);
        $this->assertFalse($member->fresh()->isAdmin());
        $memberKey = ApiKey::generate($member->id, 'Member', ['lists:read', 'lists:write'])['key'];

        $this->api('PUT', 'system-emails/signup_confirmation', ['subject' => 'Hijacked'], $memberKey)->assertForbidden();
        $this->api('PUT', 'system-pages/signup_success', ['title' => 'Hijacked'], $memberKey)->assertForbidden();
        $this->assertSame('Global subject', $this->globalEmail->fresh()->subject);
        $this->assertSame($this->globalPage->title, $this->globalPage->fresh()->title);

        // A team member may still customise their own lists.
        $memberList = $this->makeList($member, 'Member list');
        $this->api('PUT', 'system-pages/signup_success', ['list_id' => $memberList->id, 'title' => 'Member thanks'], $memberKey)
            ->assertCreated()
            ->assertJsonPath('data.contact_list_id', $memberList->id);

        // Reading globals is fine for anyone with lists:read.
        $this->api('GET', 'system-pages', [], $memberKey)->assertOk();
    }

    public function test_another_accounts_list_is_not_found(): void
    {
        $stranger = User::factory()->create();
        $foreignList = $this->makeList($stranger, 'Not yours');

        $this->api('GET', "system-emails?list_id={$foreignList->id}")->assertNotFound();
        $this->api('GET', "system-pages/signup_success?list_id={$foreignList->id}")->assertNotFound();
        $this->api('PUT', 'system-emails/signup_confirmation', ['list_id' => $foreignList->id, 'subject' => 'x'])->assertNotFound();
        $this->api('PUT', 'system-emails/signup_confirmation/active', ['list_id' => $foreignList->id, 'is_active' => false])->assertNotFound();
        $this->api('DELETE', "system-pages/signup_success?list_id={$foreignList->id}")->assertNotFound();

        $this->assertSame(0, SystemEmail::where('contact_list_id', $foreignList->id)->count());
    }

    public function test_unknown_slug_is_not_found(): void
    {
        $this->api('GET', 'system-emails/does_not_exist')->assertNotFound();
        $this->api('PUT', 'system-pages/does_not_exist', ['list_id' => $this->list->id, 'title' => 'x'])->assertNotFound();
        $this->api('DELETE', "system-emails/does_not_exist?list_id={$this->list->id}")->assertNotFound();
        $this->assertSame(0, SystemPage::where('slug', 'does_not_exist')->count());
    }

    public function test_permissions_are_enforced(): void
    {
        $readOnly = ApiKey::generate($this->user->id, 'Read', ['lists:read'])['key'];
        $this->api('GET', 'system-emails', [], $readOnly)->assertOk();
        $this->api('PUT', 'system-emails/signup_confirmation', ['list_id' => $this->list->id, 'subject' => 'x'], $readOnly)->assertForbidden();
        $this->api('PUT', 'system-emails/signup_confirmation/active', ['list_id' => $this->list->id, 'is_active' => false], $readOnly)->assertForbidden();
        $this->api('DELETE', "system-pages/signup_success?list_id={$this->list->id}", [], $readOnly)->assertForbidden();

        $unrelated = ApiKey::generate($this->user->id, 'Subscribers', ['subscribers:read'])['key'];
        $this->api('GET', 'system-emails', [], $unrelated)->assertForbidden();
        $this->api('GET', 'system-pages/signup_success', [], $unrelated)->assertForbidden();

        $this->assertSame(0, SystemEmail::where('contact_list_id', $this->list->id)->count());
    }

    public function test_system_email_can_be_switched_off_per_list_but_globals_stay_active(): void
    {
        $this->api('PUT', 'system-emails/signup_confirmation/active', ['list_id' => $this->list->id, 'is_active' => false])
            ->assertCreated()
            ->assertJsonPath('meta.created_override', true)
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.source', 'list')
            ->assertJsonPath('data.subject', $this->globalEmail->subject);

        // The switched-off override disables the email for this list instead
        // of falling back to the global default.
        $this->assertNull(SystemEmail::getBySlug('signup_confirmation', $this->list->id));
        $this->assertSame($this->globalEmail->id, SystemEmail::getBySlug('signup_confirmation', null)->id);

        $this->api('PUT', 'system-emails/signup_confirmation/active', ['list_id' => $this->list->id, 'is_active' => true])
            ->assertOk()
            ->assertJsonPath('meta.created_override', false)
            ->assertJsonPath('data.is_active', true);
        $this->assertSame($this->list->id, SystemEmail::getBySlug('signup_confirmation', $this->list->id)->contact_list_id);

        // Globals must stay active.
        $this->api('PUT', 'system-emails/signup_confirmation/active', ['is_active' => false])->assertStatus(422);
        $this->api('PUT', 'system-emails/signup_confirmation', ['is_active' => false])->assertStatus(422);
        $this->api('PUT', 'system-emails/signup_confirmation/active', ['list_id' => $this->list->id])->assertStatus(422);
        $this->assertTrue($this->globalEmail->fresh()->is_active);

        // is_active also works through the regular update for a list.
        $this->api('PUT', 'system-emails/signup_confirmation', ['list_id' => $this->list->id, 'is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    public function test_system_page_slug_and_access_rules(): void
    {
        // Global slugs are fixed.
        $this->api('PUT', 'system-pages/signup_success', ['slug' => 'thanks'])->assertStatus(422);
        $this->assertSame('signup_success', $this->globalPage->fresh()->slug);

        $this->api('PUT', 'system-pages/signup_success', ['access' => 'bogus', 'list_id' => $this->list->id])->assertStatus(422);
        $this->api('PUT', 'system-pages/signup_success', ['slug' => 'not a slug!', 'list_id' => $this->list->id])->assertStatus(422);

        // Copy-on-write keeps the standard slug and copies the global access.
        $created = $this->api('PUT', 'system-pages/signup_success', [
            'list_id' => $this->list->id,
            'title' => 'Readers thanks',
            'access' => 'private',
        ])->assertCreated();
        $created->assertJsonPath('data.slug', 'signup_success')
            ->assertJsonPath('data.access', 'private')
            ->assertJsonPath('data.content', $this->globalPage->content)
            ->assertJsonPath('meta.warnings', []);
        $this->assertStringEndsWith('/p/signup_success', $created->json('data.url'));
        $this->assertSame('public', $this->globalPage->fresh()->access);

        // A list override may rename its slug (with a warning), and stays addressable.
        $renamed = $this->api('PUT', 'system-pages/signup_success', [
            'list_id' => $this->list->id,
            'slug' => 'readers-thanks',
        ])->assertOk();
        $renamed->assertJsonPath('data.slug', 'readers-thanks')
            ->assertJsonPath('data.id', $created->json('data.id'))
            ->assertJsonPath('data.global_id', null);
        $this->assertNotEmpty($renamed->json('meta.warnings'));
        $this->assertStringEndsWith('/p/readers-thanks', $renamed->json('data.url'));

        $this->api('GET', "system-pages/readers-thanks?list_id={$this->list->id}")
            ->assertOk()
            ->assertJsonPath('data.source', 'list');
        $this->api('GET', "system-pages/signup_success?list_id={$this->list->id}")
            ->assertOk()
            ->assertJsonPath('data.source', 'global');
        $listing = $this->api('GET', "system-pages?list_id={$this->list->id}")->assertOk();
        $this->assertSame('list', $this->item($listing, 'readers-thanks')['source']);
        $this->assertSame('global', $this->item($listing, 'signup_success')['source']);

        // Slugs are unique within a list.
        SystemPage::create([
            'slug' => 'signup_error',
            'name' => 'Signup Error',
            'title' => 'Oops',
            'content' => '<h1>Oops</h1>',
            'access' => 'public',
            'contact_list_id' => $this->list->id,
        ]);
        $this->api('PUT', 'system-pages/readers-thanks', ['list_id' => $this->list->id, 'slug' => 'signup_error'])
            ->assertStatus(422);
    }
}
