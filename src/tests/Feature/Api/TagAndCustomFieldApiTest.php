<?php

namespace Tests\Feature\Api;

use App\Models\ApiKey;
use App\Models\ContactList;
use App\Models\CustomField;
use App\Models\Subscriber;
use App\Models\SubscriberFieldValue;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Tags and custom fields used to be read-only over /api/v1, so an agent
 * could not set up the tags and fields a campaign relies on without the
 * browser. These cover the write endpoints the MCP client now uses.
 */
class TagAndCustomFieldApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $key;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->key = ApiKey::generate($this->user->id, 'Config', ['lists:write', 'tags:read'])['key'];
    }

    private function api(string $method, string $uri, array $payload = [], ?string $key = null): TestResponse
    {
        return $this->withHeaders(['Authorization' => 'Bearer ' . ($key ?? $this->key)])
            ->json($method, "/api/v1/{$uri}", $payload);
    }

    private function makeSubscriber(string $email, ?User $owner = null): Subscriber
    {
        return Subscriber::create([
            'user_id' => ($owner ?? $this->user)->id,
            'email' => $email,
            'status' => 'active',
            'is_active_global' => true,
        ]);
    }

    private function makeList(?User $owner = null): ContactList
    {
        return ContactList::create([
            'user_id' => ($owner ?? $this->user)->id,
            'name' => 'Readers',
            'type' => 'email',
            'is_public' => true,
        ]);
    }

    // ------------------------------------------------------------------
    // Tags
    // ------------------------------------------------------------------

    public function test_tag_can_be_created_updated_and_read_back(): void
    {
        $id = $this->api('POST', 'tags', ['name' => 'vip', 'description' => 'Top buyers'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'vip')
            ->assertJsonPath('data.color', '#3b82f6')
            ->json('data.id');

        $this->api('PATCH', "tags/{$id}", ['color' => '#ff0000'])
            ->assertOk()
            ->assertJsonPath('data.name', 'vip')
            ->assertJsonPath('data.color', '#ff0000')
            ->assertJsonPath('data.description', 'Top buyers');

        $this->api('PUT', "tags/{$id}", ['name' => 'vip-2026'])->assertOk();

        $this->api('GET', "tags/{$id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'vip-2026')
            ->assertJsonPath('data.color', '#ff0000');
    }

    public function test_duplicate_tag_name_is_rejected_but_allowed_in_another_account(): void
    {
        $this->api('POST', 'tags', ['name' => 'vip'])->assertCreated();
        $this->api('POST', 'tags', ['name' => 'vip'])->assertStatus(422)->assertJsonValidationErrors('name');

        $other = Tag::create(['user_id' => $this->user->id, 'name' => 'cold']);
        $this->api('PATCH', "tags/{$other->id}", ['name' => 'vip'])->assertStatus(422);
        // Renaming to its own name is not a conflict
        $this->api('PATCH', "tags/{$other->id}", ['name' => 'cold'])->assertOk();

        $stranger = User::factory()->create();
        $strangerKey = ApiKey::generate($stranger->id, 'x', ['lists:write'])['key'];
        $this->api('POST', 'tags', ['name' => 'vip'], $strangerKey)->assertCreated();
    }

    public function test_invalid_tag_color_is_rejected(): void
    {
        $this->api('POST', 'tags', ['name' => 'vip', 'color' => 'red'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('color');
    }

    public function test_other_accounts_tag_is_not_found(): void
    {
        $stranger = User::factory()->create();
        $tag = Tag::create(['user_id' => $stranger->id, 'name' => 'theirs']);

        $this->api('PATCH', "tags/{$tag->id}", ['name' => 'mine'])->assertNotFound();
        $this->api('DELETE', "tags/{$tag->id}", ['confirm' => true])->assertNotFound();

        $this->assertSame('theirs', $tag->fresh()->name);
    }

    public function test_tag_writes_need_lists_write(): void
    {
        $readOnly = ApiKey::generate($this->user->id, 'ro', ['tags:read', 'lists:read'])['key'];
        $tag = Tag::create(['user_id' => $this->user->id, 'name' => 'vip']);

        $this->api('POST', 'tags', ['name' => 'new'], $readOnly)->assertForbidden();
        $this->api('PATCH', "tags/{$tag->id}", ['name' => 'x'], $readOnly)->assertForbidden();
        $this->api('DELETE', "tags/{$tag->id}", [], $readOnly)->assertForbidden();

        // Reads keep working with the read scope
        $this->api('GET', 'tags', [], $readOnly)->assertOk()->assertJsonPath('data.0.name', 'vip');
    }

    public function test_deleting_a_tag_detaches_it_from_subscribers_and_lists(): void
    {
        $tag = Tag::create(['user_id' => $this->user->id, 'name' => 'vip']);
        $keep = Tag::create(['user_id' => $this->user->id, 'name' => 'keep']);
        $subscriber = $this->makeSubscriber('anna@example.com');
        $subscriber->tags()->attach([$tag->id, $keep->id]);
        $list = $this->makeList();
        $list->tags()->attach($tag->id);

        $this->api('DELETE', "tags/{$tag->id}")
            ->assertStatus(409)
            ->assertJsonPath('subscribers_count', 1);
        $this->assertNotNull($tag->fresh());

        $this->api('DELETE', "tags/{$tag->id}", ['confirm' => true])
            ->assertOk()
            ->assertJsonPath('subscribers_detached', 1)
            ->assertJsonPath('lists_detached', 1);

        $this->assertNull($tag->fresh());
        $this->assertDatabaseMissing('subscriber_tag', ['tag_id' => $tag->id]);
        $this->assertDatabaseMissing('taggables', ['tag_id' => $tag->id]);
        $this->assertSame(['keep'], $subscriber->fresh()->tags()->pluck('name')->all());
    }

    public function test_unused_tag_is_deleted_without_confirmation(): void
    {
        $tag = Tag::create(['user_id' => $this->user->id, 'name' => 'unused']);

        $this->api('DELETE', "tags/{$tag->id}")->assertOk();
        $this->assertNull($tag->fresh());
    }

    // ------------------------------------------------------------------
    // Custom fields
    // ------------------------------------------------------------------

    public function test_global_custom_field_can_be_created_and_updated(): void
    {
        $data = $this->api('POST', 'custom-fields', [
            'name' => 'city',
            'label' => 'City',
            'type' => 'text',
            'options' => ['ignored'],
            'is_required' => true,
        ])->assertCreated()->json('data');

        $this->assertSame('city', $data['name']);
        $this->assertSame('[[city]]', $data['placeholder']);
        $this->assertSame('global', $data['scope']);
        $this->assertNull($data['contact_list_id']);
        $this->assertNull($data['options']);
        $this->assertTrue($data['is_required']);
        $this->assertSame(1, $data['sort_order']);

        $this->api('PATCH', "custom-fields/{$data['id']}", [
            'type' => 'select',
            'options' => ['Warsaw', '', 'Krakow'],
        ])->assertOk()
            ->assertJsonPath('data.type', 'select')
            ->assertJsonPath('data.options', ['Warsaw', 'Krakow'])
            ->assertJsonPath('data.label', 'City')
            ->assertJsonPath('data.is_required', true);

        // Changing the type to one without options clears them
        $this->api('PUT', "custom-fields/{$data['id']}", ['type' => 'number'])
            ->assertOk()
            ->assertJsonPath('data.options', null);

        $this->api('GET', "custom-fields/{$data['id']}")->assertOk()->assertJsonPath('data.type', 'number');
        $this->api('GET', 'custom-fields/placeholders')->assertOk()->assertJsonPath('data.custom.0.placeholder', '[[city]]');
    }

    public function test_list_scoped_custom_field_requires_an_owned_list(): void
    {
        $list = $this->makeList();
        $this->api('POST', 'custom-fields', [
            'name' => 'company', 'label' => 'Company', 'type' => 'text', 'contact_list_id' => $list->id,
        ])->assertCreated()
            ->assertJsonPath('data.scope', 'list')
            ->assertJsonPath('data.contact_list_id', $list->id);

        // The same name may exist globally and per list (as in the web form)
        $this->api('POST', 'custom-fields', ['name' => 'company', 'label' => 'Company', 'type' => 'text'])
            ->assertCreated();

        $foreign = $this->makeList(User::factory()->create());
        $this->api('POST', 'custom-fields', [
            'name' => 'other', 'label' => 'Other', 'type' => 'text', 'contact_list_id' => $foreign->id,
        ])->assertStatus(422)->assertJsonValidationErrors('contact_list_id');
    }

    public function test_custom_field_name_rules_are_enforced(): void
    {
        CustomField::create(['user_id' => $this->user->id, 'name' => 'city', 'label' => 'City', 'type' => 'text', 'scope' => 'global']);

        $post = fn (array $payload) => $this->api('POST', 'custom-fields', $payload + ['label' => 'L', 'type' => 'text']);

        $post(['name' => 'city'])->assertStatus(422)->assertJsonValidationErrors('name');
        $post(['name' => 'email'])->assertStatus(422)->assertJsonValidationErrors('name');
        $post(['name' => 'Unsubscribe'])->assertStatus(422)->assertJsonValidationErrors('name');
        $post(['name' => '1abc'])->assertStatus(422)->assertJsonValidationErrors('name');
        $post(['name' => 'with-dash'])->assertStatus(422)->assertJsonValidationErrors('name');
        $post(['name' => 'ok_name', 'type' => 'unknown'])->assertStatus(422)->assertJsonValidationErrors('type');
        $post(['name' => 'plan', 'type' => 'select'])->assertStatus(422)->assertJsonValidationErrors('options');

        $field = CustomField::create(['user_id' => $this->user->id, 'name' => 'zip', 'label' => 'Zip', 'type' => 'text', 'scope' => 'global']);
        $this->api('PATCH', "custom-fields/{$field->id}", ['name' => 'city'])->assertStatus(422)->assertJsonValidationErrors('name');
        $this->api('PATCH', "custom-fields/{$field->id}", ['type' => 'radio'])->assertStatus(422)->assertJsonValidationErrors('options');
        $this->api('PATCH', "custom-fields/{$field->id}", ['name' => 'zip', 'label' => 'Postcode'])->assertOk();

        $this->assertSame('Postcode', $field->fresh()->label);
    }

    public function test_other_accounts_custom_field_is_not_found(): void
    {
        $field = CustomField::create([
            'user_id' => User::factory()->create()->id, 'name' => 'city', 'label' => 'City', 'type' => 'text', 'scope' => 'global',
        ]);

        $this->api('GET', "custom-fields/{$field->id}")->assertNotFound();
        $this->api('PATCH', "custom-fields/{$field->id}", ['label' => 'Mine'])->assertNotFound();
        $this->api('DELETE', "custom-fields/{$field->id}", ['confirm' => true])->assertNotFound();

        $this->assertSame('City', $field->fresh()->label);
    }

    public function test_custom_field_writes_need_lists_write(): void
    {
        $readOnly = ApiKey::generate($this->user->id, 'ro', ['lists:read'])['key'];
        $field = CustomField::create(['user_id' => $this->user->id, 'name' => 'city', 'label' => 'City', 'type' => 'text', 'scope' => 'global']);

        $this->api('POST', 'custom-fields', ['name' => 'zip', 'label' => 'Zip', 'type' => 'text'], $readOnly)->assertForbidden();
        $this->api('PATCH', "custom-fields/{$field->id}", ['label' => 'x'], $readOnly)->assertForbidden();
        $this->api('DELETE', "custom-fields/{$field->id}", [], $readOnly)->assertForbidden();

        $this->api('GET', 'custom-fields', [], $readOnly)->assertOk()->assertJsonPath('data.0.name', 'city');
    }

    public function test_deleting_a_custom_field_with_values_needs_confirmation(): void
    {
        $field = CustomField::create(['user_id' => $this->user->id, 'name' => 'city', 'label' => 'City', 'type' => 'text', 'scope' => 'global']);
        $subscriber = $this->makeSubscriber('anna@example.com');
        SubscriberFieldValue::create(['subscriber_id' => $subscriber->id, 'custom_field_id' => $field->id, 'value' => 'Warsaw']);

        $this->api('DELETE', "custom-fields/{$field->id}")
            ->assertStatus(409)
            ->assertJsonPath('values_count', 1);
        $this->assertNotNull($field->fresh());

        $this->api('DELETE', "custom-fields/{$field->id}", ['confirm' => true])
            ->assertOk()
            ->assertJsonPath('values_deleted', 1);

        $this->assertNull($field->fresh());
        $this->assertDatabaseMissing('subscriber_field_values', ['custom_field_id' => $field->id]);
        $this->assertNotNull($subscriber->fresh());
    }

    public function test_list_sorting_ignores_unknown_columns(): void
    {
        CustomField::create(['user_id' => $this->user->id, 'name' => 'b_field', 'label' => 'B', 'type' => 'text', 'scope' => 'global', 'sort_order' => 1]);
        CustomField::create(['user_id' => $this->user->id, 'name' => 'a_field', 'label' => 'A', 'type' => 'text', 'scope' => 'global', 'sort_order' => 2]);

        $this->api('GET', 'custom-fields?sort_by=nonexistent&sort_order=sideways')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'b_field');
        $this->api('GET', 'custom-fields?sort_by=name')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'a_field');
        $this->api('GET', 'tags?sort_by=nonexistent')->assertOk();
    }
}
