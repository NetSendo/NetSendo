<?php

namespace Tests\Feature\Api;

use App\Models\ApiKey;
use App\Models\Message;
use App\Models\Subscriber;
use App\Models\Template;
use App\Models\TemplateBlock;
use App\Models\TemplateCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Email templates over /api/v1/templates (what the MCP client uses): agents
 * could reference a template_id on a campaign but could not list, read,
 * create or edit templates.
 */
class TemplateApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $key;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->key = ApiKey::generate($this->user->id, 'Templates', ['messages:read', 'messages:write'])['key'];
    }

    private function api(string $method, string $uri, array $payload = [], ?string $key = null): TestResponse
    {
        return $this->withHeaders(['Authorization' => 'Bearer ' . ($key ?? $this->key)])
            ->json($method, "/api/v1/{$uri}", $payload);
    }

    private function systemTemplate(array $attributes = []): Template
    {
        return Template::create(array_merge([
            'user_id' => null,
            'name' => 'Starter newsletter',
            'is_public' => true,
            'category' => 'newsletter',
            'json_structure' => ['blocks' => [
                ['id' => 'b1', 'type' => 'text', 'content' => ['html' => '<p>Hello [[first_name]]</p>'], 'settings' => []],
            ]],
            'mjml_content' => '<mjml><mj-body><mj-text>Hello [[first_name]]</mj-text></mj-body></mjml>',
        ], $attributes));
    }

    public function test_html_template_crud_round_trip(): void
    {
        $id = $this->api('POST', 'templates', [
            'name' => 'Welcome',
            'preheader' => 'Hi [[first_name]]',
            'content' => '<html><body><p>Hello [[first_name]]</p><a href="[[unsubscribe_link]]">Unsubscribe</a></body></html>',
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Welcome')
            ->assertJsonPath('data.type', 'email')
            ->assertJsonPath('data.editor', 'html')
            ->assertJsonPath('data.is_system', false)
            ->json('data.id');

        $this->api('GET', "templates/{$id}")
            ->assertOk()
            ->assertJsonPath('data.html', '<html><body><p>Hello [[first_name]]</p><a href="[[unsubscribe_link]]">Unsubscribe</a></body></html>')
            ->assertJsonPath('data.mjml', null)
            ->assertJsonPath('data.settings.width', 600);

        $this->api('PATCH', "templates/{$id}", ['description' => 'First mail'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Welcome')
            ->assertJsonPath('data.description', 'First mail');

        $this->api('PUT', "templates/{$id}", ['name' => 'Welcome v2', 'content' => '<p>v2</p>'])
            ->assertOk()
            ->assertJsonPath('data.html', '<p>v2</p>');

        // A campaign references the template; deleting it is a soft delete as in the panel.
        Message::create(['user_id' => $this->user->id, 'channel' => 'email', 'type' => 'broadcast', 'subject' => 'x', 'content' => '<p>v2</p>', 'status' => 'draft', 'template_id' => $id]);

        $this->api('DELETE', "templates/{$id}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true)
            ->assertJsonPath('data.used_by_messages', 1);

        $this->assertSoftDeleted('templates', ['id' => $id]);
        $this->api('GET', "templates/{$id}")->assertNotFound();
    }

    public function test_builder_template_gets_block_ids_and_mjml(): void
    {
        $category = TemplateCategory::create(['name' => 'Promo', 'slug' => 'promo-x', 'is_system' => true]);

        $response = $this->api('POST', 'templates', [
            'name' => 'Builder',
            'category_id' => $category->id,
            'json_structure' => ['blocks' => [
                ['type' => 'text', 'content' => ['html' => '<p>First</p>']],
                ['type' => 'button', 'content' => ['text' => 'Buy', 'href' => 'https://example.com'], 'settings' => []],
            ]],
        ])->assertCreated()
            ->assertJsonPath('data.editor', 'builder')
            ->assertJsonPath('data.category', 'promo-x')
            ->assertJsonPath('data.category_data.id', $category->id);

        $blocks = $response->json('data.json_structure.blocks');
        $this->assertSame(['text', 'button'], array_column($blocks, 'type'));
        $this->assertNotEmpty($blocks[0]['id']);
        $this->assertNotEmpty($blocks[1]['id']);
        $this->assertStringContainsString('<mjml>', $response->json('data.mjml'));
        $this->assertStringContainsString('First', $response->json('data.mjml'));

        $this->api('POST', 'templates', [
            'name' => 'Bad',
            'json_structure' => ['blocks' => [['type' => 'nope']]],
        ])->assertUnprocessable()->assertJsonValidationErrors('json_structure.blocks.0.type');
    }

    public function test_listing_shows_own_and_system_templates_with_filters(): void
    {
        $system = $this->systemTemplate();
        $own = Template::create(['user_id' => $this->user->id, 'name' => 'Own promo', 'content' => '<p>x</p>', 'category' => 'promotional']);
        Template::create(['user_id' => $this->user->id, 'name' => 'Signature', 'type' => 'signature', 'content' => '--']);
        $foreign = Template::create(['user_id' => User::factory()->create()->id, 'name' => 'Foreign', 'content' => '<p>x</p>', 'is_public' => true]);

        $ids = $this->api('GET', 'templates')->assertOk()->json('data.*.id');
        sort($ids);
        $this->assertSame([$system->id, $own->id], $ids);

        $this->assertSame([$own->id], $this->api('GET', 'templates?source=own')->json('data.*.id'));
        $this->assertSame([$system->id], $this->api('GET', 'templates?source=system')->json('data.*.id'));
        $this->assertSame([$own->id], $this->api('GET', 'templates?search=promo')->json('data.*.id'));
        $this->assertSame([$own->id], $this->api('GET', 'templates?category=promotional')->json('data.*.id'));
        $this->assertSame(['Signature'], $this->api('GET', 'templates?type=signature')->json('data.*.name'));
        $this->api('GET', 'templates')->assertJsonPath('meta.total', 2);

        $this->api('GET', "templates/{$foreign->id}")->assertNotFound();
        $this->api('GET', "templates/{$system->id}")
            ->assertOk()
            ->assertJsonPath('data.is_system', true)
            ->assertJsonPath('data.editable', false);
    }

    public function test_system_and_foreign_templates_are_not_writable(): void
    {
        $system = $this->systemTemplate();
        $foreign = Template::create(['user_id' => User::factory()->create()->id, 'name' => 'Foreign', 'content' => '<p>x</p>']);

        $this->api('PUT', "templates/{$system->id}", ['name' => 'Hacked'])->assertForbidden();
        $this->api('DELETE', "templates/{$system->id}")->assertForbidden();
        $this->api('PUT', "templates/{$foreign->id}", ['name' => 'Hacked'])->assertNotFound();
        $this->api('DELETE', "templates/{$foreign->id}")->assertNotFound();
        $this->api('POST', "templates/{$foreign->id}/duplicate")->assertNotFound();

        $this->assertSame('Starter newsletter', $system->fresh()->name);
        $this->assertSame('Foreign', $foreign->fresh()->name);
        $this->assertNull($system->fresh()->deleted_at);
    }

    public function test_duplicating_a_system_template_gives_an_editable_copy(): void
    {
        $system = $this->systemTemplate();

        $copy = $this->api('POST', "templates/{$system->id}/duplicate", ['name' => 'My newsletter'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'My newsletter')
            ->assertJsonPath('data.is_system', false)
            ->assertJsonPath('data.editable', true)
            ->assertJsonPath('data.json_structure.blocks.0.id', 'b1')
            ->json('data');

        $this->assertSame($this->user->id, Template::find($copy['id'])->user_id);
        $this->assertFalse(Template::find($copy['id'])->is_public);

        $this->api('PATCH', "templates/{$copy['id']}", ['preheader' => 'Mine'])->assertOk();

        $this->api('POST', "templates/{$copy['id']}/duplicate")
            ->assertCreated()
            ->assertJsonPath('data.name', 'My newsletter (kopia)');
    }

    public function test_preview_renders_placeholders_for_sample_and_real_subscriber(): void
    {
        $id = $this->api('POST', 'templates', [
            'name' => 'Preview',
            'preheader' => 'For [[first_name]]',
            'content' => '<p>Hi [[first_name]] [[last_name]]</p><a href="[[unsubscribe_link]]">u</a>[[made_up]]',
        ])->json('data.id');

        $this->api('POST', "templates/{$id}/preview")
            ->assertOk()
            ->assertJsonPath('data.source', 'html')
            ->assertJsonPath('data.html', '<p>Hi Jan Kowalski</p><a href="#preview-unsubscribe">u</a>')
            ->assertJsonPath('data.preheader', 'For Jan')
            ->assertJsonPath('data.subscriber.sample', true)
            ->assertJsonPath('data.unknown_placeholders', ['made_up']);

        $this->api('POST', "templates/{$id}/preview", ['sample' => ['first_name' => 'Anna']])
            ->assertJsonPath('data.preheader', 'For Anna');

        $subscriber = Subscriber::create(['user_id' => $this->user->id, 'email' => 'ewa@example.com', 'first_name' => 'Ewa', 'last_name' => 'Nowak']);
        $html = $this->api('POST', "templates/{$id}/preview", ['subscriber_id' => $subscriber->id])
            ->assertOk()
            ->assertJsonPath('data.subscriber.id', $subscriber->id)
            ->json('data.html');
        $this->assertStringContainsString('Hi Ewa Nowak', $html);
        $this->assertStringNotContainsString('[[unsubscribe_link]]', $html);

        $foreign = Subscriber::create(['user_id' => User::factory()->create()->id, 'email' => 'x@example.com']);
        $this->api('POST', "templates/{$id}/preview", ['subscriber_id' => $foreign->id])->assertNotFound();

        // Builder-only templates come back as MJML with placeholders rendered.
        $system = $this->systemTemplate();
        $this->api('POST', "templates/{$system->id}/preview")
            ->assertOk()
            ->assertJsonPath('data.source', 'mjml')
            ->assertJsonPath('data.html', null)
            ->assertJsonPath('data.mjml', '<mjml><mj-body><mj-text>Hello Jan</mj-text></mj-body></mjml>');
    }

    public function test_categories_list_seeds_system_categories(): void
    {
        $own = TemplateCategory::create(['user_id' => $this->user->id, 'name' => 'Mine', 'slug' => 'mine-1']);
        TemplateCategory::create(['user_id' => User::factory()->create()->id, 'name' => 'Theirs', 'slug' => 'theirs-1']);

        $slugs = $this->api('GET', 'template-categories')->assertOk()->json('data.*.slug');

        $this->assertContains('newsletter', $slugs);
        $this->assertContains('mine-1', $slugs);
        $this->assertNotContains('theirs-1', $slugs);

        // Category ids must belong to the account or the system.
        $theirs = TemplateCategory::where('slug', 'theirs-1')->first();
        $this->api('POST', 'templates', ['name' => 'x', 'category_id' => $theirs->id])
            ->assertUnprocessable()->assertJsonValidationErrors('category_id');
        $this->api('POST', 'templates', ['name' => 'x', 'category_id' => $own->id])
            ->assertCreated()->assertJsonPath('data.category', 'mine-1');
    }

    public function test_saved_blocks_crud_and_types(): void
    {
        $this->api('GET', 'template-blocks/types')
            ->assertOk()
            ->assertJsonFragment(['type' => 'button']);

        $id = $this->api('POST', 'template-blocks', [
            'name' => 'CTA',
            'type' => 'button',
            'content' => ['text' => 'Buy', 'href' => 'https://example.com'],
        ])->assertCreated()
            ->assertJsonPath('data.editable', true)
            ->assertJsonPath('data.settings.padding', '0')
            ->json('data.id');

        $this->api('PUT', "template-blocks/{$id}", ['name' => 'CTA 2'])
            ->assertOk()->assertJsonPath('data.name', 'CTA 2')->assertJsonPath('data.content.text', 'Buy');

        $foreign = TemplateBlock::create(['user_id' => User::factory()->create()->id, 'name' => 'F', 'type' => 'text', 'content' => ['html' => 'x']]);
        $global = TemplateBlock::create(['user_id' => User::factory()->create()->id, 'name' => 'G', 'type' => 'text', 'content' => ['html' => 'x'], 'is_global' => true]);

        $names = $this->api('GET', 'template-blocks')->json('data.*.name');
        sort($names);
        $this->assertSame(['CTA 2', 'G'], $names);

        $this->api('PUT', "template-blocks/{$foreign->id}", ['name' => 'x'])->assertNotFound();
        $this->api('DELETE', "template-blocks/{$global->id}")->assertForbidden();

        $this->api('DELETE', "template-blocks/{$id}")->assertOk();
        $this->assertDatabaseMissing('template_blocks', ['id' => $id]);
    }

    public function test_permissions_are_enforced(): void
    {
        $readOnly = ApiKey::generate($this->user->id, 'RO', ['messages:read'])['key'];
        $none = ApiKey::generate($this->user->id, 'None', ['lists:read'])['key'];
        $own = Template::create(['user_id' => $this->user->id, 'name' => 'Own', 'content' => '<p>x</p>']);

        $this->api('GET', 'templates', [], $readOnly)->assertOk();
        $this->api('GET', "templates/{$own->id}", [], $readOnly)->assertOk();
        $this->api('POST', "templates/{$own->id}/preview", [], $readOnly)->assertOk();
        $this->api('POST', 'templates', ['name' => 'x'], $readOnly)->assertForbidden();
        $this->api('PUT', "templates/{$own->id}", ['name' => 'x'], $readOnly)->assertForbidden();
        $this->api('DELETE', "templates/{$own->id}", [], $readOnly)->assertForbidden();
        $this->api('POST', "templates/{$own->id}/duplicate", [], $readOnly)->assertForbidden();
        $this->api('POST', 'template-blocks', ['name' => 'x', 'type' => 'text', 'content' => ['html' => 'x']], $readOnly)->assertForbidden();

        $this->api('GET', 'templates', [], $none)->assertForbidden();
        $this->api('GET', 'template-categories', [], $none)->assertForbidden();
        $this->api('GET', 'template-blocks', [], $none)->assertForbidden();
    }
}
