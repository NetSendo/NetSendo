<?php

namespace Tests\Feature;

use App\Events\SubscriberSignedUp;
use App\Models\ContactList;
use App\Models\CustomField;
use App\Models\Subscriber;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The promise of this pair is that a file the export produces is a file the
 * import reads back with no surprises: same contacts, same lists, same tags,
 * and no autoresponder sequence quietly restarted on the way.
 */
class SubscriberExportImportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private ContactList $list;
    private ContactList $secondList;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->list = $this->makeList('Newsletter');
        $this->secondList = $this->makeList('Klienci');
    }

    private function makeList(string $name): ContactList
    {
        return ContactList::create([
            'user_id' => $this->user->id,
            'name' => $name,
            'type' => 'email',
            'is_public' => true,
            'settings' => [],
            'webhook_events' => [],
            'sync_settings' => [],
            'required_fields' => [],
        ]);
    }

    private function makeSubscriber(string $email, array $attributes = []): Subscriber
    {
        $subscriber = Subscriber::create(array_merge([
            'user_id' => $this->user->id,
            'email' => $email,
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
            'is_active_global' => true,
        ], $attributes));

        $subscriber->contactLists()->attach($this->list->id, [
            'status' => 'active',
            'subscribed_at' => now()->subDays(30),
        ]);

        return $subscriber;
    }

    /**
     * Run the export endpoint and return the file body.
     */
    private function export(array $payload = []): string
    {
        $response = $this->actingAs($this->user)->post(route('subscribers.export'), array_merge([
            'preset' => 'netsendo',
            'format' => 'csv',
            'scope' => 'filtered',
            'list_id' => $this->list->id,
        ], $payload));

        $response->assertOk();

        return $response->streamedContent();
    }

    /**
     * Feed a body back through the import endpoint.
     */
    private function import(string $body, array $payload = [], string $filename = 'export.csv')
    {
        $path = tempnam(sys_get_temp_dir(), 'imp');
        File::put($path, $body);

        $response = $this->actingAs($this->user)->post(route('subscribers.import.store'), array_merge([
            'file' => new UploadedFile($path, $filename, 'text/csv', null, true),
            'contact_list_id' => $this->list->id,
        ], $payload));

        $response->assertSessionHasNoErrors();

        return $response;
    }

    public function test_export_returns_round_trip_columns(): void
    {
        $this->makeSubscriber('jan@example.com');

        $csv = $this->export();
        $header = str_getcsv(strtok($csv, "\n"), ',', '"', '');

        $this->assertContains('netsendo_id', $header);
        $this->assertContains('email', $header);
        $this->assertContains('lists', $header);
        $this->assertContains('tags', $header);
        $this->assertStringContainsString('jan@example.com', $csv);
    }

    public function test_export_covers_tags_lists_and_custom_fields(): void
    {
        $subscriber = $this->makeSubscriber('jan@example.com');

        $tag = Tag::create(['user_id' => $this->user->id, 'name' => 'vip']);
        $subscriber->tags()->attach($tag->id);

        $subscriber->contactLists()->attach($this->secondList->id, [
            'status' => 'unsubscribed',
            'subscribed_at' => now()->subDays(10),
        ]);

        $field = CustomField::create([
            'user_id' => $this->user->id,
            'name' => 'miasto',
            'label' => 'Miasto',
            'type' => 'text',
        ]);
        $subscriber->fieldValues()->create(['custom_field_id' => $field->id, 'value' => 'Kraków']);

        $csv = $this->export();

        $this->assertStringContainsString('cf:miasto', $csv);
        $this->assertStringContainsString('Kraków', $csv);
        $this->assertStringContainsString('vip', $csv);
        // Per-list status travels as a suffix on the list name.
        $this->assertStringContainsString('Newsletter:active', $csv);
        $this->assertStringContainsString('Klienci:unsubscribed', $csv);
    }

    public function test_reimporting_an_untouched_export_changes_nothing(): void
    {
        $subscriber = $this->makeSubscriber('jan@example.com');
        $subscribedAt = $subscriber->contactLists()->first()->pivot->subscribed_at;

        $csv = $this->export();

        Event::fake([SubscriberSignedUp::class]);

        $this->import($csv, ['update_mode' => 'overwrite'])->assertRedirect();

        // One contact, still one contact.
        $this->assertSame(1, Subscriber::where('user_id', $this->user->id)->count());

        $subscriber->refresh();
        $this->assertSame('jan@example.com', $subscriber->email);
        $this->assertSame('Jan', $subscriber->first_name);

        // The signup date must not move, or every autoresponder offset shifts.
        $this->assertSame(
            (string) $subscribedAt,
            (string) $subscriber->contactLists()->first()->pivot->subscribed_at
        );

        // And nothing may look like a fresh signup, or the sequences restart.
        Event::assertNotDispatched(SubscriberSignedUp::class);
    }

    public function test_corrected_email_updates_the_same_contact(): void
    {
        $subscriber = $this->makeSubscriber('literowka@example.com');

        $csv = $this->export();
        $fixed = str_replace('literowka@example.com', 'poprawny@example.com', $csv);

        $this->import($fixed, ['update_mode' => 'overwrite'])->assertRedirect();

        // Identity came from netsendo_id, so this is an update, not a second
        // contact with the old one orphaned.
        $this->assertSame(1, Subscriber::where('user_id', $this->user->id)->count());
        $this->assertSame('poprawny@example.com', $subscriber->fresh()->email);
    }

    public function test_edited_names_and_tags_land_back_on_the_contact(): void
    {
        $subscriber = $this->makeSubscriber('jan@example.com');

        $csv = $this->export();
        $edited = str_replace('Kowalski', 'Nowak', $csv);
        // The tags cell is empty on export; fill it in as a person would.
        $edited = rtrim($edited, "\n");
        $lines = explode("\n", $edited);
        $header = str_getcsv($lines[0], ',', '"', '');
        $row = str_getcsv($lines[1], ',', '"', '');
        $row[array_search('tags', $header, true)] = 'vip|klient';
        $lines[1] = implode(',', array_map(fn ($v) => '"' . str_replace('"', '""', $v) . '"', $row));

        $this->import(implode("\n", $lines), ['update_mode' => 'overwrite'])->assertRedirect();

        $subscriber->refresh();
        $this->assertSame('Nowak', $subscriber->last_name);
        $this->assertEqualsCanonicalizing(
            ['vip', 'klient'],
            $subscriber->tags()->pluck('name')->all()
        );
    }

    public function test_memberships_are_restored_from_the_lists_column(): void
    {
        $subscriber = $this->makeSubscriber('jan@example.com');
        $subscriber->contactLists()->attach($this->secondList->id, [
            'status' => 'active',
            'subscribed_at' => now()->subDays(5),
        ]);

        $csv = $this->export();

        // Wipe the second membership, then let the file put it back.
        $subscriber->contactLists()->detach($this->secondList->id);
        $this->assertSame(1, $subscriber->contactLists()->count());

        $this->import($csv, [
            'update_mode' => 'overwrite',
            'restore_memberships' => '1',
        ])->assertRedirect();

        $this->assertSame(2, $subscriber->fresh()->contactLists()->count());
    }

    public function test_json_export_imports_back(): void
    {
        $this->makeSubscriber('jan@example.com');

        $json = $this->export(['format' => 'json']);
        $decoded = json_decode($json, true);

        $this->assertIsArray($decoded);
        $this->assertSame(1, $decoded['netsendo_export']['format_version']);
        $this->assertCount(1, $decoded['subscribers']);

        Subscriber::where('user_id', $this->user->id)->forceDelete();

        $this->import($json, ['update_mode' => 'overwrite'], 'export.json')->assertRedirect();

        $this->assertSame(1, Subscriber::where('user_id', $this->user->id)->count());
        $this->assertDatabaseHas('subscribers', ['email' => 'jan@example.com']);
    }

    public function test_a_plain_csv_without_identity_still_imports(): void
    {
        $csv = "email,first_name,last_name\nanna@example.com,Anna,Nowak\n";

        $this->import($csv)->assertRedirect();

        $subscriber = Subscriber::where('email', 'anna@example.com')->first();
        $this->assertNotNull($subscriber);
        $this->assertSame('Anna', $subscriber->first_name);
        $this->assertSame(1, $subscriber->contactLists()->count());
    }

    public function test_quoted_newlines_no_longer_split_a_contact_in_two(): void
    {
        // The previous importer split on "\n" before parsing, so this row
        // produced two broken records instead of one contact.
        $csv = "email,first_name,last_name\n\"anna@example.com\",\"Anna\nMaria\",\"Nowak\"\n";

        $this->import($csv)->assertRedirect();

        $this->assertSame(1, Subscriber::where('user_id', $this->user->id)->count());
        $this->assertSame('anna@example.com', Subscriber::first()->email);
    }

    public function test_a_new_signup_still_triggers_automations(): void
    {
        Event::fake([SubscriberSignedUp::class]);

        $this->import("email,first_name\nnowy@example.com,Nowy\n")->assertRedirect();

        Event::assertDispatched(SubscriberSignedUp::class);
    }

    public function test_preview_writes_nothing(): void
    {
        $response = $this->actingAs($this->user)->post(route('subscribers.import.preview'), [
            'file' => UploadedFile::fake()->createWithContent(
                'lista.csv',
                "email,first_name\nanna@example.com,Anna\nzepsuty,Brak\n"
            ),
            'contact_list_id' => $this->list->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('summary.create', 1);
        $response->assertJsonPath('summary.invalid', 1);

        $this->assertSame(0, Subscriber::where('user_id', $this->user->id)->count());
    }

    public function test_export_refuses_to_leak_another_accounts_contacts(): void
    {
        $this->makeSubscriber('moj@example.com');

        $stranger = User::factory()->create();
        $strangerList = ContactList::create([
            'user_id' => $stranger->id,
            'name' => 'Obca lista',
            'type' => 'email',
            'is_public' => true,
            'settings' => [],
            'webhook_events' => [],
            'sync_settings' => [],
            'required_fields' => [],
        ]);
        $strangerSubscriber = Subscriber::create([
            'user_id' => $stranger->id,
            'email' => 'obcy@example.com',
            'is_active_global' => true,
        ]);
        $strangerSubscriber->contactLists()->attach($strangerList->id, [
            'status' => 'active',
            'subscribed_at' => now(),
        ]);

        // Even when the ids are named outright, only the caller's own contacts
        // come back.
        $csv = $this->export([
            'scope' => 'selected',
            'ids' => [$strangerSubscriber->id],
            'list_id' => null,
        ]);

        $this->assertStringNotContainsString('obcy@example.com', $csv);
    }

    public function test_a_list_id_the_caller_cannot_reach_exports_nothing(): void
    {
        $this->makeSubscriber('moj@example.com');

        $stranger = User::factory()->create();
        $strangerList = ContactList::create([
            'user_id' => $stranger->id,
            'name' => 'Obca lista',
            'type' => 'email',
            'is_public' => true,
            'settings' => [],
            'webhook_events' => [],
            'sync_settings' => [],
            'required_fields' => [],
        ]);

        // Naming a foreign list must narrow the export to nothing, never widen
        // it back to every list the caller can reach.
        $csv = $this->export(['list_id' => $strangerList->id]);

        $this->assertStringNotContainsString('moj@example.com', $csv);
    }

    public function test_selected_scope_exports_only_the_chosen_contacts(): void
    {
        $first = $this->makeSubscriber('pierwszy@example.com');
        $this->makeSubscriber('drugi@example.com');

        $csv = $this->export(['scope' => 'selected', 'ids' => [$first->id]]);

        $this->assertStringContainsString('pierwszy@example.com', $csv);
        $this->assertStringNotContainsString('drugi@example.com', $csv);
    }

    public function test_excel_variant_starts_with_a_bom_and_uses_semicolons(): void
    {
        $this->makeSubscriber('jan@example.com');

        $csv = $this->export(['format' => 'csv_excel']);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('netsendo_id;email', $csv);
    }

    public function test_unsubscribed_contacts_are_excluded_unless_asked_for(): void
    {
        $subscriber = $this->makeSubscriber('wypisany@example.com');
        $subscriber->contactLists()->updateExistingPivot($this->list->id, ['status' => 'unsubscribed']);

        $this->assertStringNotContainsString('wypisany@example.com', $this->export());
        $this->assertStringContainsString(
            'wypisany@example.com',
            $this->export(['membership' => 'all'])
        );
    }
}
