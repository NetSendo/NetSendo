<?php

namespace Tests\Feature;

use App\Models\AutomationRule;
use App\Models\ContactList;
use App\Models\CustomField;
use App\Models\Subscriber;
use App\Models\SubscriberFieldValue;
use App\Models\User;
use App\Services\Automation\AutomationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The automation rule conditions on a field (field_equals, field_not_equals,
 * field_contains, field_is_empty, field_is_not_empty) looked custom fields up
 * by a `slug` column custom_fields does not have, so the query failed — and,
 * as conditions are evaluated before any rule runs, took every rule for that
 * event down with it.
 */
class AutomationFieldConditionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->user = User::factory()->create();
    }

    private function makeSubscriber(string $email = 'jan@example.com', array $attributes = []): Subscriber
    {
        return Subscriber::create($attributes + [
            'user_id' => $this->user->id,
            'email' => $email,
            'status' => 'active',
            'is_active_global' => true,
        ]);
    }

    private function makeList(string $name = 'Newsletter'): ContactList
    {
        return ContactList::create([
            'user_id' => $this->user->id,
            'name' => $name,
            'type' => 'email',
            'is_public' => true,
        ]);
    }

    private function customField(string $name, array $attributes = []): CustomField
    {
        return CustomField::create($attributes + [
            'user_id' => $this->user->id,
            'name' => $name,
            'label' => ucfirst($name),
            'type' => 'text',
            'scope' => 'global',
        ]);
    }

    private function setValue(Subscriber $subscriber, CustomField $field, ?string $value): void
    {
        SubscriberFieldValue::create(['subscriber_id' => $subscriber->id, 'custom_field_id' => $field->id, 'value' => $value]);
    }

    /**
     * Whether a rule of the subscriber's account with this one condition matches.
     */
    private function conditionMet(Subscriber $subscriber, string $type, ?string $field, mixed $value = null): bool
    {
        $rule = new AutomationRule([
            'user_id' => $subscriber->user_id,
            'conditions' => [array_filter(['type' => $type, 'field' => $field, 'value' => $value], fn ($v) => $v !== null)],
            'condition_logic' => 'all',
        ]);

        return app(AutomationService::class)->evaluateConditions($rule, [
            'subscriber_id' => $subscriber->id,
            'user_id' => $subscriber->user_id,
        ]);
    }

    private function makeRule(string $name, array $conditions, string $tagName): AutomationRule
    {
        return AutomationRule::create([
            'user_id' => $this->user->id,
            'name' => $name,
            'trigger_event' => 'subscriber_signup',
            'trigger_config' => [],
            'conditions' => $conditions,
            'condition_logic' => 'all',
            'actions' => [['type' => 'add_tag', 'config' => ['tag_name' => $tagName]]],
            'is_active' => true,
        ]);
    }

    // ===== Running rules =====

    public function test_a_rule_with_a_custom_field_condition_runs_and_other_rules_still_do(): void
    {
        $city = $this->customField('city');
        $krakow = $this->makeSubscriber('krakow@example.com');
        $this->setValue($krakow, $city, 'Kraków');
        $gdansk = $this->makeSubscriber('gdansk@example.com');
        $this->setValue($gdansk, $city, 'Gdańsk');

        $this->makeRule('From Kraków', [['type' => 'field_equals', 'field' => 'city', 'value' => 'Kraków']], 'krakow');
        $this->makeRule('Everyone', [], 'everyone');

        $service = app(AutomationService::class);

        foreach ([$krakow, $gdansk] as $subscriber) {
            $service->processEvent('subscriber_signup', [
                'subscriber_id' => $subscriber->id,
                'user_id' => $subscriber->user_id,
            ]);
        }

        $this->assertEqualsCanonicalizing(['krakow', 'everyone'], $krakow->tags()->pluck('name')->all());
        $this->assertEqualsCanonicalizing(['everyone'], $gdansk->tags()->pluck('name')->all());
    }

    // ===== Standard fields =====

    public function test_standard_subscriber_fields_can_be_tested(): void
    {
        $subscriber = $this->makeSubscriber('Jan.Kowalski@Example.com', [
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
            'phone' => '+48 600 100 200',
            'gender' => 'male',
            'language' => 'pl',
            'source' => 'form',
            'subscribed_at' => '2026-09-01 08:30:00',
        ]);

        $this->assertTrue($this->conditionMet($subscriber, 'field_equals', 'first_name', 'Jan'));
        $this->assertTrue($this->conditionMet($subscriber, 'field_not_equals', 'last_name', 'Nowak'));
        $this->assertTrue($this->conditionMet($subscriber, 'field_contains', 'email', '@example.com'));
        $this->assertTrue($this->conditionMet($subscriber, 'field_is_not_empty', 'phone'));
        $this->assertTrue($this->conditionMet($subscriber, 'field_equals', 'gender', 'male'));
        $this->assertTrue($this->conditionMet($subscriber, 'field_equals', 'language', 'pl'));
        $this->assertTrue($this->conditionMet($subscriber, 'field_equals', 'source', 'form'));
        $this->assertTrue($this->conditionMet($subscriber, 'field_contains', 'subscribed_at', '2026-09'));
        $this->assertTrue($this->conditionMet($subscriber, 'field_is_empty', 'device'));
        $this->assertTrue($this->conditionMet($subscriber, 'field_equals', 'fname', 'jan'));

        $this->assertFalse($this->conditionMet($subscriber, 'field_equals', 'first_name', 'Anna'));
        $this->assertFalse($this->conditionMet($subscriber, 'field_not_equals', 'first_name', 'JAN'));
        $this->assertFalse($this->conditionMet($subscriber, 'field_is_empty', 'phone'));
        $this->assertFalse($this->conditionMet($subscriber, 'field_is_not_empty', 'device'));
    }

    // ===== Custom fields, scoped to the account =====

    public function test_custom_fields_are_read_by_name_from_the_subscribers_account(): void
    {
        $owner = $this->user;
        $subscriber = $this->makeSubscriber();

        // Another account's field of the same name, created first
        $this->user = User::factory()->create();
        $this->customField('plan', ['default_value' => 'enterprise']);
        $this->user = $owner;

        $this->assertTrue($this->conditionMet($subscriber, 'field_is_empty', 'plan'));
        $this->assertFalse($this->conditionMet($subscriber, 'field_equals', 'plan', 'enterprise'));

        $plan = $this->customField('plan', ['default_value' => 'free']);

        $this->assertTrue($this->conditionMet($subscriber, 'field_equals', 'plan', 'free'));

        $this->setValue($subscriber, $plan, 'Pro');

        $this->assertTrue($this->conditionMet($subscriber, 'field_equals', 'plan', 'pro'));
        $this->assertTrue($this->conditionMet($subscriber, 'field_not_equals', 'plan', 'free'));
    }

    public function test_a_list_field_applies_to_subscribers_of_that_list(): void
    {
        $vip = $this->makeList('VIP');
        $member = $this->makeSubscriber('member@example.com');
        $member->contactLists()->attach($vip->id, ['status' => 'active', 'subscribed_at' => now()]);
        $outsider = $this->makeSubscriber('outsider@example.com');

        $this->customField('tier', ['scope' => 'list', 'contact_list_id' => $vip->id, 'default_value' => 'gold']);

        $this->assertTrue($this->conditionMet($member, 'field_equals', 'tier', 'gold'));
        $this->assertTrue($this->conditionMet($outsider, 'field_is_empty', 'tier'));
    }

    // ===== Comparison =====

    public function test_values_compare_as_trimmed_text_ignoring_case(): void
    {
        $subscriber = $this->makeSubscriber();
        $city = $this->customField('city');
        $this->setValue($subscriber, $city, '  Kraków ');

        $this->assertTrue($this->conditionMet($subscriber, 'field_equals', 'city', 'KRAKÓW'));
        $this->assertTrue($this->conditionMet($subscriber, 'field_equals', 'city', ' kraków  '));
        $this->assertFalse($this->conditionMet($subscriber, 'field_not_equals', 'city', 'kraków'));
        $this->assertTrue($this->conditionMet($subscriber, 'field_contains', 'city', 'RAK'));
        $this->assertFalse($this->conditionMet($subscriber, 'field_contains', 'city', 'Warsz'));

        // A number saved in the rule compares as its text
        $age = $this->customField('age', ['type' => 'number']);
        $this->setValue($subscriber, $age, '10');

        $this->assertTrue($this->conditionMet($subscriber, 'field_equals', 'age', 10));
        $this->assertFalse($this->conditionMet($subscriber, 'field_equals', 'age', '10.0'));
    }

    public function test_empty_and_missing_values(): void
    {
        $subscriber = $this->makeSubscriber();
        $this->setValue($subscriber, $this->customField('nickname'), '   ');
        $this->setValue($subscriber, $this->customField('children'), '0');
        $this->customField('city');

        // Whitespace only is empty; "0" is a value
        $this->assertTrue($this->conditionMet($subscriber, 'field_is_empty', 'nickname'));
        $this->assertFalse($this->conditionMet($subscriber, 'field_is_not_empty', 'nickname'));
        $this->assertTrue($this->conditionMet($subscriber, 'field_is_not_empty', 'children'));
        $this->assertFalse($this->conditionMet($subscriber, 'field_is_empty', 'children'));

        // A field with no value is not equal to any text
        $this->assertTrue($this->conditionMet($subscriber, 'field_not_equals', 'city', 'Kraków'));
        $this->assertFalse($this->conditionMet($subscriber, 'field_equals', 'city', 'Kraków'));

        // "Contains" with nothing to look for is never met
        $this->assertFalse($this->conditionMet($subscriber, 'field_contains', 'children'));
        $this->assertFalse($this->conditionMet($subscriber, 'field_contains', 'children', '  '));

        // An unknown name has no value
        $this->assertTrue($this->conditionMet($subscriber, 'field_is_empty', 'no_such_field'));
    }

    public function test_a_condition_without_a_field_is_never_met(): void
    {
        $subscriber = $this->makeSubscriber('jan@example.com', ['first_name' => 'Jan']);

        foreach (['field_equals', 'field_not_equals', 'field_contains', 'field_is_empty', 'field_is_not_empty'] as $type) {
            $this->assertFalse($this->conditionMet($subscriber, $type, null, 'Jan'), $type);
            $this->assertFalse($this->conditionMet($subscriber, $type, '  ', 'Jan'), $type);
        }
    }

    // ===== Builder =====

    public function test_the_builder_offers_standard_fields_and_the_accounts_custom_fields(): void
    {
        $this->customField('city', ['label' => 'Miasto']);
        $this->customField('language', ['label' => 'Języki']);

        $owner = $this->user;
        $this->user = User::factory()->create();
        $this->customField('secret', ['label' => 'Not yours']);
        $this->user = $owner;

        $rule = $this->makeRule('Rule', [['type' => 'field_equals', 'field' => 'city', 'value' => 'Kraków']], 'tag');

        foreach ([route('automations.create'), route('automations.edit', $rule)] as $url) {
            $this->withoutVite()
                ->actingAs($this->user)
                ->get($url)
                ->assertOk()
                ->assertInertia(function ($page) {
                    $fields = $page->toArray()['props']['conditionFields'];

                    $this->assertContains('first_name', $fields['standard']);
                    $this->assertContains('email', $fields['standard']);
                    // Hidden by the account's own "language" field, which is the one evaluated
                    $this->assertNotContains('language', $fields['standard']);

                    $this->assertSame(['city', 'language'], collect($fields['custom'])->pluck('name')->sort()->values()->all());
                });
        }
    }

    public function test_a_field_condition_saved_in_the_builder_keeps_its_field(): void
    {
        $this->actingAs($this->user)
            ->post(route('automations.store'), [
                'name' => 'From Kraków',
                'trigger_event' => 'subscriber_signup',
                'conditions' => [['type' => 'field_contains', 'field' => 'city', 'value' => 'Kraków']],
                'condition_logic' => 'all',
                'actions' => [['type' => 'add_tag', 'config' => ['tag_name' => 'krakow']]],
                'is_active' => true,
            ])
            ->assertRedirect(route('automations.index'));

        $this->assertSame(
            [['type' => 'field_contains', 'field' => 'city', 'value' => 'Kraków']],
            AutomationRule::where('name', 'From Kraków')->first()?->conditions,
        );
    }
}
