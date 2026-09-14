<?php

namespace Tests\Feature;

use App\Models\CustomField;
use App\Models\FunnelStep;
use App\Models\Subscriber;
use App\Models\SubscriberFieldValue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Concerns\BuildsFunnels;
use Tests\TestCase;

/**
 * The "field has value" condition read custom fields only, so first name,
 * email or language could never be tested; it fell back to any account's
 * same-named field default; and it compared loosely and case-sensitively.
 */
class FunnelFieldValueConditionTest extends TestCase
{
    use RefreshDatabase;
    use BuildsFunnels;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-14 10:00:00');
        Queue::fake();

        $this->setUpFunnelOwner();
    }

    /**
     * Whether a fresh funnel with this condition sends the subscriber down YES.
     */
    private function conditionMet(Subscriber $subscriber, string $field, string $operator, ?string $value = null): bool
    {
        $funnel = $this->makeFunnel();
        $yes = $this->makeStep($funnel, FunnelStep::TYPE_END, ['name' => 'yes']);
        $no = $this->makeStep($funnel, FunnelStep::TYPE_END, ['name' => 'no']);
        $condition = $this->makeStep($funnel, FunnelStep::TYPE_CONDITION, [
            'condition_type' => FunnelStep::CONDITION_FIELD_VALUE,
            'condition_config' => array_filter(['field' => $field, 'operator' => $operator, 'value' => $value], fn ($v) => $v !== null),
            'next_step_yes_id' => $yes->id,
            'next_step_no_id' => $no->id,
        ]);
        $this->makeChain($funnel, $condition);

        $stepId = $this->enroll($funnel, $subscriber)->current_step_id;
        $this->assertContains($stepId, [$yes->id, $no->id]);

        return $stepId === $yes->id;
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

        $this->assertTrue($this->conditionMet($subscriber, 'first_name', 'equals', 'Jan'));
        $this->assertTrue($this->conditionMet($subscriber, 'last_name', 'not_equals', 'Nowak'));
        $this->assertTrue($this->conditionMet($subscriber, 'email', 'contains', '@example.com'));
        $this->assertTrue($this->conditionMet($subscriber, 'phone', 'not_empty'));
        $this->assertTrue($this->conditionMet($subscriber, 'gender', 'equals', 'male'));
        $this->assertTrue($this->conditionMet($subscriber, 'language', 'equals', 'pl'));
        $this->assertTrue($this->conditionMet($subscriber, 'source', 'equals', 'form'));
        $this->assertTrue($this->conditionMet($subscriber, 'subscribed_at', 'contains', '2026-09'));
        $this->assertTrue($this->conditionMet($subscriber, 'device', 'empty'));

        // The placeholder aliases name the same fields
        $this->assertTrue($this->conditionMet($subscriber, 'fname', 'equals', 'jan'));

        $this->assertFalse($this->conditionMet($subscriber, 'first_name', 'equals', 'Anna'));
        $this->assertFalse($this->conditionMet($subscriber, 'language', 'equals', 'en'));
    }

    public function test_an_accounts_custom_field_takes_precedence_over_a_standard_field_of_the_same_name(): void
    {
        $subscriber = $this->makeSubscriber('jan@example.com', ['language' => 'pl']);
        $field = $this->customField('language', ['default_value' => 'fr']);
        $this->setValue($subscriber, $field, 'Polish, English');

        $this->assertTrue($this->conditionMet($subscriber, 'language', 'contains', 'english'));
        $this->assertFalse($this->conditionMet($subscriber, 'language', 'equals', 'pl'));

        // That field is this account's only: another account still reads the column
        $this->user = User::factory()->create();
        $other = $this->makeSubscriber('ola@example.com', ['language' => 'de']);

        $this->assertTrue($this->conditionMet($other, 'language', 'equals', 'DE'));
        $this->assertFalse($this->conditionMet($other, 'language', 'equals', 'fr'));
    }

    // ===== Custom fields, scoped to the account =====

    public function test_a_custom_field_default_comes_only_from_the_subscribers_own_account(): void
    {
        $owner = $this->user;
        $subscriber = $this->makeSubscriber();

        // Created first, so an unscoped lookup would find it first
        $this->user = User::factory()->create();
        $this->customField('plan', ['default_value' => 'enterprise']);
        $this->user = $owner;

        $this->assertNull($subscriber->getCustomFieldValue('plan'));
        $this->assertTrue($this->conditionMet($subscriber, 'plan', 'empty'));
        $this->assertFalse($this->conditionMet($subscriber, 'plan', 'equals', 'enterprise'));

        $this->customField('plan', ['default_value' => 'free']);

        $this->assertSame('free', $subscriber->getCustomFieldValue('plan'));
        $this->assertTrue($this->conditionMet($subscriber, 'plan', 'equals', 'free'));
    }

    public function test_a_list_field_applies_to_subscribers_of_that_list(): void
    {
        $vip = $this->makeList('VIP');
        $member = $this->makeSubscriber('member@example.com');
        $member->contactLists()->attach($vip->id, ['status' => 'active', 'subscribed_at' => now()]);
        $outsider = $this->makeSubscriber('outsider@example.com');

        $this->customField('tier', ['scope' => 'list', 'contact_list_id' => $vip->id, 'default_value' => 'gold']);

        $this->assertTrue($this->conditionMet($member, 'tier', 'equals', 'gold'));
        $this->assertTrue($this->conditionMet($outsider, 'tier', 'empty'));

        // A value the subscriber holds counts even off the list
        $field = CustomField::where('name', 'tier')->first();
        $this->setValue($outsider, $field, 'silver');

        $this->assertTrue($this->conditionMet($outsider, 'tier', 'equals', 'silver'));
    }

    public function test_a_subscribers_own_value_wins_over_the_default(): void
    {
        $subscriber = $this->makeSubscriber();
        $field = $this->customField('city', ['default_value' => 'Warszawa']);

        $this->assertTrue($this->conditionMet($subscriber, 'city', 'equals', 'warszawa'));

        $this->setValue($subscriber, $field, 'Kraków');

        $this->assertTrue($this->conditionMet($subscriber, 'city', 'equals', 'kraków'));
        $this->assertFalse($this->conditionMet($subscriber, 'city', 'equals', 'Warszawa'));
    }

    public function test_setting_a_custom_field_writes_to_the_subscribers_own_account(): void
    {
        $owner = $this->user;
        $this->user = User::factory()->create();
        $foreign = $this->customField('city');
        $this->user = $owner;
        $own = $this->customField('city');

        $subscriber = $this->makeSubscriber();
        $subscriber->setCustomFieldValue('city', 'Gdańsk');

        $this->assertSame(0, $foreign->values()->count());
        $this->assertSame('Gdańsk', $own->values()->value('value'));
        $this->assertSame('Gdańsk', $subscriber->getCustomFieldValue('city'));
    }

    // ===== Operators =====

    public function test_operators_compare_trimmed_text_ignoring_case(): void
    {
        $subscriber = $this->makeSubscriber();
        $city = $this->customField('city');
        $this->setValue($subscriber, $city, '  Kraków ');

        $this->assertTrue($this->conditionMet($subscriber, 'city', 'equals', 'KRAKÓW'));
        $this->assertTrue($this->conditionMet($subscriber, 'city', 'equals', ' kraków  '));
        $this->assertFalse($this->conditionMet($subscriber, 'city', 'not_equals', 'kraków'));
        $this->assertTrue($this->conditionMet($subscriber, 'city', 'contains', 'RAK'));
        $this->assertFalse($this->conditionMet($subscriber, 'city', 'contains', 'Warsz'));

        // Text, not loose numbers: "10.0" is not "10"
        $age = $this->customField('age', ['type' => 'number']);
        $this->setValue($subscriber, $age, '10');

        $this->assertTrue($this->conditionMet($subscriber, 'age', 'equals', '10'));
        $this->assertFalse($this->conditionMet($subscriber, 'age', 'equals', '10.0'));
    }

    public function test_empty_and_missing_values(): void
    {
        $subscriber = $this->makeSubscriber();
        $blank = $this->customField('nickname');
        $this->setValue($subscriber, $blank, '   ');
        $zero = $this->customField('children');
        $this->setValue($subscriber, $zero, '0');
        $this->customField('city');

        // Whitespace only is empty; "0" is a value
        $this->assertTrue($this->conditionMet($subscriber, 'nickname', 'empty'));
        $this->assertFalse($this->conditionMet($subscriber, 'nickname', 'not_empty'));
        $this->assertTrue($this->conditionMet($subscriber, 'children', 'not_empty'));
        $this->assertFalse($this->conditionMet($subscriber, 'children', 'empty'));

        // A field with no value is not equal to any text
        $this->assertTrue($this->conditionMet($subscriber, 'city', 'not_equals', 'Kraków'));
        $this->assertFalse($this->conditionMet($subscriber, 'city', 'equals', 'Kraków'));
        $this->assertTrue($this->conditionMet($subscriber, 'city', 'equals'));

        // "Contains" with nothing to look for is never met
        $this->assertFalse($this->conditionMet($subscriber, 'children', 'contains'));
        $this->assertFalse($this->conditionMet($subscriber, 'children', 'contains', '  '));

        // A condition without a field, or with an unknown operator, is not met
        $this->assertFalse($this->conditionMet($subscriber, '', 'empty'));
        $this->assertFalse($this->conditionMet($subscriber, 'children', 'greater_than', '1'));
    }

    // ===== Builder =====

    public function test_the_builder_offers_standard_fields_and_the_accounts_custom_fields(): void
    {
        $vip = $this->makeList('VIP');
        $b2b = $this->makeList('B2B');
        $this->customField('city', ['label' => 'Miasto']);
        $this->customField('tier', ['label' => 'Poziom', 'scope' => 'list', 'contact_list_id' => $vip->id]);
        $this->customField('tier', ['label' => 'Poziom B2B', 'scope' => 'list', 'contact_list_id' => $b2b->id]);
        $this->customField('language', ['label' => 'Języki']);

        $owner = $this->user;
        $this->user = User::factory()->create();
        $this->customField('secret', ['label' => 'Not yours']);
        $this->user = $owner;
        $funnel = $this->makeFunnel();

        foreach ([route('funnels.create'), route('funnels.edit', $funnel)] as $url) {
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

                    $this->assertSame(['city', 'language', 'tier'], collect($fields['custom'])->pluck('name')->sort()->values()->all());
                    $this->assertSame('Miasto', collect($fields['custom'])->firstWhere('name', 'city')['label']);
                });
        }
    }
}
