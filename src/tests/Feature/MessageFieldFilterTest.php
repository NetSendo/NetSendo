<?php

namespace Tests\Feature;

use App\Models\ContactList;
use App\Models\CustomField;
use App\Models\Message;
use App\Models\MessageFieldFilter;
use App\Models\Subscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audience narrowing by subscriber custom fields, both on the include side
 * ("send only to Oświęcim") and on the exclude side ("drop from that list only
 * the Kraków people").
 */
class MessageFieldFilterTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected ContactList $list;
    protected CustomField $cityField;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->list = ContactList::create([
            'user_id' => $this->user->id,
            'name' => 'Main list',
        ]);

        $this->cityField = CustomField::create([
            'user_id' => $this->user->id,
            'name' => 'city',
            'label' => 'Miejscowość',
            'type' => 'text',
            'scope' => 'global',
        ]);
    }

    /**
     * Create a subscriber on the given lists, optionally with a city value.
     */
    protected function subscriber(string $email, ?string $city, array $lists = []): Subscriber
    {
        $subscriber = Subscriber::create([
            'user_id' => $this->user->id,
            'email' => $email,
            'is_active_global' => true,
        ]);

        foreach ($lists ?: [$this->list] as $list) {
            $subscriber->contactLists()->attach($list->id, [
                'status' => 'active',
                'subscribed_at' => now(),
            ]);
        }

        if ($city !== null) {
            $subscriber->fieldValues()->create([
                'custom_field_id' => $this->cityField->id,
                'value' => $city,
            ]);
        }

        return $subscriber;
    }

    protected function message(array $attributes = []): Message
    {
        $message = Message::create(array_merge([
            'user_id' => $this->user->id,
            'channel' => 'email',
            'type' => 'broadcast',
            'subject' => 'Hello',
            'content' => 'Body',
            'status' => 'draft',
        ], $attributes));

        $message->contactLists()->attach($this->list->id);

        return $message;
    }

    public function test_include_filter_keeps_only_matching_subscribers(): void
    {
        $this->subscriber('oswiecim@example.com', 'Oświęcim');
        $this->subscriber('krakow@example.com', 'Kraków');
        $this->subscriber('nocity@example.com', null);

        $message = $this->message();
        $message->fieldFilters()->create([
            'custom_field_id' => $this->cityField->id,
            'mode' => MessageFieldFilter::MODE_INCLUDE,
            'operator' => MessageFieldFilter::OP_ANY_OF,
            'values' => ['Oświęcim'],
        ]);

        $emails = $message->fresh()->getUniqueRecipients()->pluck('email')->all();

        $this->assertSame(['oswiecim@example.com'], $emails);
    }

    public function test_include_filter_accepts_several_values(): void
    {
        $this->subscriber('oswiecim@example.com', 'Oświęcim');
        $this->subscriber('krakow@example.com', 'Kraków');
        $this->subscriber('gdansk@example.com', 'Gdańsk');

        $message = $this->message();
        $message->fieldFilters()->create([
            'custom_field_id' => $this->cityField->id,
            'mode' => MessageFieldFilter::MODE_INCLUDE,
            'operator' => MessageFieldFilter::OP_ANY_OF,
            'values' => ['Oświęcim', 'Kraków'],
        ]);

        $emails = $message->fresh()->getUniqueRecipients()->pluck('email')->sort()->values()->all();

        $this->assertSame(['krakow@example.com', 'oswiecim@example.com'], $emails);
    }

    public function test_empty_values_do_not_widen_the_audience(): void
    {
        $this->subscriber('oswiecim@example.com', 'Oświęcim');
        $this->subscriber('krakow@example.com', 'Kraków');

        $message = $this->message();
        // A half-filled row: operator set, no value yet
        $message->fieldFilters()->create([
            'custom_field_id' => $this->cityField->id,
            'mode' => MessageFieldFilter::MODE_INCLUDE,
            'operator' => MessageFieldFilter::OP_ANY_OF,
            'values' => [],
        ]);

        $this->assertCount(2, $message->fresh()->getUniqueRecipients());
    }

    public function test_is_empty_matches_subscribers_without_the_field(): void
    {
        $this->subscriber('oswiecim@example.com', 'Oświęcim');
        $this->subscriber('nocity@example.com', null);
        $this->subscriber('blank@example.com', '');

        $message = $this->message();
        $message->fieldFilters()->create([
            'custom_field_id' => $this->cityField->id,
            'mode' => MessageFieldFilter::MODE_INCLUDE,
            'operator' => MessageFieldFilter::OP_IS_EMPTY,
            'values' => [],
        ]);

        $emails = $message->fresh()->getUniqueRecipients()->pluck('email')->sort()->values()->all();

        $this->assertSame(['blank@example.com', 'nocity@example.com'], $emails);
    }

    public function test_exclusion_list_narrowed_by_a_field_drops_only_matching_members(): void
    {
        $blocked = ContactList::create(['user_id' => $this->user->id, 'name' => 'Blocked']);

        // Both are on the exclusion list; only the Kraków one should be dropped
        $this->subscriber('krakow@example.com', 'Kraków', [$this->list, $blocked]);
        $this->subscriber('oswiecim@example.com', 'Oświęcim', [$this->list, $blocked]);
        $this->subscriber('plain@example.com', 'Gdańsk');

        $message = $this->message();
        $message->excludedLists()->attach($blocked->id);
        $message->fieldFilters()->create([
            'custom_field_id' => $this->cityField->id,
            'mode' => MessageFieldFilter::MODE_EXCLUDE,
            'operator' => MessageFieldFilter::OP_ANY_OF,
            'values' => ['Kraków'],
        ]);

        $emails = $message->fresh()->getUniqueRecipients()->pluck('email')->sort()->values()->all();

        $this->assertSame(['oswiecim@example.com', 'plain@example.com'], $emails);
    }

    public function test_exclusion_list_without_field_filters_still_drops_everyone(): void
    {
        $blocked = ContactList::create(['user_id' => $this->user->id, 'name' => 'Blocked']);

        $this->subscriber('krakow@example.com', 'Kraków', [$this->list, $blocked]);
        $this->subscriber('plain@example.com', 'Gdańsk');

        $message = $this->message();
        $message->excludedLists()->attach($blocked->id);

        $emails = $message->fresh()->getUniqueRecipients()->pluck('email')->all();

        $this->assertSame(['plain@example.com'], $emails);
    }

    public function test_exclude_filter_without_an_excluded_list_applies_to_the_audience(): void
    {
        $this->subscriber('krakow@example.com', 'Kraków');
        $this->subscriber('oswiecim@example.com', 'Oświęcim');

        $message = $this->message();
        $message->fieldFilters()->create([
            'custom_field_id' => $this->cityField->id,
            'mode' => MessageFieldFilter::MODE_EXCLUDE,
            'operator' => MessageFieldFilter::OP_ANY_OF,
            'values' => ['Kraków'],
        ]);

        $emails = $message->fresh()->getUniqueRecipients()->pluck('email')->all();

        $this->assertSame(['oswiecim@example.com'], $emails);
    }

    public function test_several_include_filters_combine_with_and_or_or(): void
    {
        $ageField = CustomField::create([
            'user_id' => $this->user->id,
            'name' => 'age',
            'label' => 'Wiek',
            'type' => 'number',
            'scope' => 'global',
        ]);

        $young = $this->subscriber('young@example.com', 'Kraków');
        $young->fieldValues()->create(['custom_field_id' => $ageField->id, 'value' => '20']);

        $old = $this->subscriber('old@example.com', 'Kraków');
        $old->fieldValues()->create(['custom_field_id' => $ageField->id, 'value' => '60']);

        $this->subscriber('elsewhere@example.com', 'Gdańsk');

        $message = $this->message(['include_field_filter_match' => MessageFieldFilter::MATCH_ALL]);
        $message->fieldFilters()->create([
            'custom_field_id' => $this->cityField->id,
            'mode' => MessageFieldFilter::MODE_INCLUDE,
            'operator' => MessageFieldFilter::OP_ANY_OF,
            'values' => ['Kraków'],
        ]);
        $message->fieldFilters()->create([
            'custom_field_id' => $ageField->id,
            'mode' => MessageFieldFilter::MODE_INCLUDE,
            'operator' => MessageFieldFilter::OP_GTE,
            'values' => ['50'],
        ]);

        $this->assertSame(
            ['old@example.com'],
            $message->fresh()->getUniqueRecipients()->pluck('email')->all()
        );

        $message->update(['include_field_filter_match' => MessageFieldFilter::MATCH_ANY]);

        $emails = $message->fresh()->getUniqueRecipients()->pluck('email')->sort()->values()->all();
        $this->assertSame(['old@example.com', 'young@example.com'], $emails);
    }

    public function test_field_values_endpoint_returns_stored_values_with_counts(): void
    {
        $this->subscriber('a@example.com', 'Oświęcim');
        $this->subscriber('b@example.com', 'Oświęcim');
        $this->subscriber('c@example.com', 'Kraków');

        $response = $this->actingAs($this->user)->getJson(route('messages.audience.field-values', [
            'field_id' => $this->cityField->id,
            'list_ids' => [$this->list->id],
        ]));

        $response->assertOk();
        $this->assertSame(
            [['value' => 'Oświęcim', 'count' => 2], ['value' => 'Kraków', 'count' => 1]],
            $response->json('values')
        );
    }

    public function test_field_values_endpoint_can_search(): void
    {
        $this->subscriber('a@example.com', 'Oświęcim');
        $this->subscriber('c@example.com', 'Kraków');

        $response = $this->actingAs($this->user)->getJson(route('messages.audience.field-values', [
            'field_id' => $this->cityField->id,
            'list_ids' => [$this->list->id],
            'search' => 'kra',
        ]));

        $response->assertOk();
        $this->assertSame([['value' => 'Kraków', 'count' => 1]], $response->json('values'));
    }

    public function test_field_values_endpoint_refuses_another_users_field(): void
    {
        $stranger = User::factory()->create();
        $foreignField = CustomField::create([
            'user_id' => $stranger->id,
            'name' => 'secret',
            'label' => 'Secret',
            'type' => 'text',
            'scope' => 'global',
        ]);

        $response = $this->actingAs($this->user)->getJson(route('messages.audience.field-values', [
            'field_id' => $foreignField->id,
        ]));

        $response->assertOk();
        $this->assertSame([], $response->json('values'));
    }

    public function test_estimate_endpoint_reports_the_filtered_audience(): void
    {
        $this->subscriber('a@example.com', 'Oświęcim');
        $this->subscriber('b@example.com', 'Kraków');
        $this->subscriber('c@example.com', 'Kraków');

        $response = $this->actingAs($this->user)->postJson(route('messages.audience.estimate'), [
            'contact_list_ids' => [$this->list->id],
            'include_field_filters' => [[
                'custom_field_id' => $this->cityField->id,
                'operator' => MessageFieldFilter::OP_ANY_OF,
                'values' => ['Kraków'],
            ]],
        ]);

        $response->assertOk();
        $response->assertJson(['base' => 3, 'total' => 2, 'excluded' => 1]);
    }

    public function test_available_fields_endpoint_lists_global_and_list_fields(): void
    {
        $otherList = ContactList::create(['user_id' => $this->user->id, 'name' => 'Other']);

        $listField = CustomField::create([
            'user_id' => $this->user->id,
            'name' => 'nip',
            'label' => 'NIP',
            'type' => 'text',
            'scope' => 'list',
            'contact_list_id' => $this->list->id,
        ]);

        CustomField::create([
            'user_id' => $this->user->id,
            'name' => 'other_only',
            'label' => 'Other only',
            'type' => 'text',
            'scope' => 'list',
            'contact_list_id' => $otherList->id,
        ]);

        $response = $this->actingAs($this->user)->getJson(route('messages.audience.fields', [
            'list_ids' => [$this->list->id],
        ]));

        $response->assertOk();
        $ids = collect($response->json('fields'))->pluck('id')->all();

        $this->assertContains($this->cityField->id, $ids);
        $this->assertContains($listField->id, $ids);
        $this->assertCount(2, $ids);
    }

    public function test_saving_a_message_persists_and_reloads_its_filters(): void
    {
        $this->subscriber('a@example.com', 'Oświęcim');

        $response = $this->actingAs($this->user)->post(route('messages.store'), [
            'subject' => 'Filtered',
            'type' => 'broadcast',
            'status' => 'draft',
            'content' => 'Body',
            'contact_list_ids' => [$this->list->id],
            'include_field_filters' => [[
                'custom_field_id' => $this->cityField->id,
                'operator' => MessageFieldFilter::OP_ANY_OF,
                'values' => ['Oświęcim', ''],
            ]],
            'exclude_field_filters' => [[
                'custom_field_id' => $this->cityField->id,
                'operator' => MessageFieldFilter::OP_IS_EMPTY,
                'values' => [],
            ]],
        ]);

        $response->assertRedirect();

        $message = Message::where('user_id', $this->user->id)->latest('id')->first();

        $this->assertCount(1, $message->getIncludeFieldFilters());
        // The blank value was dropped on the way in
        $this->assertSame(['Oświęcim'], $message->getIncludeFieldFilters()->first()->values);
        $this->assertCount(1, $message->getExcludeFieldFilters());
    }

    public function test_autoresponder_signup_respects_include_filters(): void
    {
        $message = $this->message([
            'type' => 'autoresponder',
            'status' => 'scheduled',
            'is_active' => true,
            'day' => 0,
        ]);
        $message->fieldFilters()->create([
            'custom_field_id' => $this->cityField->id,
            'mode' => MessageFieldFilter::MODE_INCLUDE,
            'operator' => MessageFieldFilter::OP_ANY_OF,
            'values' => ['Oświęcim'],
        ]);

        $matching = $this->subscriber('oswiecim@example.com', 'Oświęcim');
        $other = $this->subscriber('krakow@example.com', 'Kraków');

        $listener = new \App\Listeners\CreateAutoresponderQueueEntries();
        $listener->handle(new \App\Events\SubscriberSignedUp($matching, $this->list));
        $listener->handle(new \App\Events\SubscriberSignedUp($other, $this->list));

        $queued = $message->queueEntries()->pluck('subscriber_id')->all();

        $this->assertSame([$matching->id], $queued);
    }

    public function test_autoresponder_signup_narrows_the_exclusion_by_field(): void
    {
        $blocked = ContactList::create(['user_id' => $this->user->id, 'name' => 'Blocked']);

        $message = $this->message([
            'type' => 'autoresponder',
            'status' => 'scheduled',
            'is_active' => true,
            'day' => 0,
        ]);
        $message->excludedLists()->attach($blocked->id);
        $message->fieldFilters()->create([
            'custom_field_id' => $this->cityField->id,
            'mode' => MessageFieldFilter::MODE_EXCLUDE,
            'operator' => MessageFieldFilter::OP_ANY_OF,
            'values' => ['Kraków'],
        ]);

        // Both sit on the exclusion list; only the Kraków one is dropped
        $dropped = $this->subscriber('krakow@example.com', 'Kraków', [$this->list, $blocked]);
        $kept = $this->subscriber('oswiecim@example.com', 'Oświęcim', [$this->list, $blocked]);

        $listener = new \App\Listeners\CreateAutoresponderQueueEntries();
        $listener->handle(new \App\Events\SubscriberSignedUp($dropped, $this->list));
        $listener->handle(new \App\Events\SubscriberSignedUp($kept, $this->list));

        $this->assertSame([$kept->id], $message->queueEntries()->pluck('subscriber_id')->all());
    }

    public function test_a_filter_on_another_users_field_is_not_stored(): void
    {
        $stranger = User::factory()->create();
        $foreignField = CustomField::create([
            'user_id' => $stranger->id,
            'name' => 'secret',
            'label' => 'Secret',
            'type' => 'text',
            'scope' => 'global',
        ]);

        $message = $this->message();

        app(\App\Services\Segmentation\SubscriberFieldFilterService::class)->syncFilters(
            $message,
            MessageFieldFilter::MODE_INCLUDE,
            [['custom_field_id' => $foreignField->id, 'operator' => 'any_of', 'values' => ['x']]]
        );

        $this->assertCount(0, $message->fresh()->fieldFilters);
    }
}
