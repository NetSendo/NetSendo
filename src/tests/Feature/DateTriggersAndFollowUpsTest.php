<?php

namespace Tests\Feature;

use App\Models\AutomationRule;
use App\Models\AutomationRuleLog;
use App\Models\ContactList;
use App\Models\CrmContact;
use App\Models\CrmFollowUpEnrollment;
use App\Models\CrmFollowUpSequence;
use App\Models\CrmFollowUpStep;
use App\Models\CrmTask;
use App\Models\CustomField;
use App\Models\Subscriber;
use App\Models\User;
use App\Services\Automation\DateTriggerService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Seen in client logs on 2.11.2:
 * - DateTriggerService queried a `contactList` relation that does not exist
 *   (Subscriber has contactLists), every minute, so birthday, anniversary and
 *   "date reached" automations never ran. Behind it were a `birth_date`
 *   column and a custom-field `slug` that do not exist either, an OR that
 *   escaped the account filter, events without the subscriber's lists (so a
 *   message sent "on the birthday", whose rule is limited to its list, never
 *   matched) and date rules that ran on any other rule's date.
 * - A CRM follow-up enrollment of a deleted (soft-deleted) contact failed with
 *   "Attempt to read property id on null" every five minutes, forever.
 */
class DateTriggersAndFollowUpsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected ContactList $list;
    protected CustomField $birthday;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-12 10:00:00', 'UTC'));

        $this->user = User::factory()->create();
        $this->list = ContactList::create(['user_id' => $this->user->id, 'name' => 'Newsletter', 'type' => 'email']);
        $this->birthday = CustomField::create([
            'user_id' => $this->user->id,
            'name' => 'data_urodzin',
            'label' => 'Data urodzin',
            'type' => 'date',
            'scope' => 'global',
        ]);
    }

    protected function subscriber(string $email, ?string $birthday = null, string $listStatus = 'active', ?User $owner = null, ?ContactList $list = null): Subscriber
    {
        $owner ??= $this->user;
        $subscriber = Subscriber::create([
            'user_id' => $owner->id,
            'email' => $email,
            'status' => 'active',
            'is_active_global' => true,
        ]);
        $subscriber->contactLists()->attach(($list ?? $this->list)->id, ['status' => $listStatus, 'subscribed_at' => now()]);

        if ($birthday !== null) {
            $subscriber->fieldValues()->create(['custom_field_id' => $this->birthday->id, 'value' => $birthday]);
        }

        return $subscriber;
    }

    protected function rule(string $event, array $config, string $tag): AutomationRule
    {
        return AutomationRule::create([
            'user_id' => $this->user->id,
            'name' => $tag,
            'trigger_event' => $event,
            'trigger_config' => $config,
            'conditions' => [],
            'actions' => [['type' => 'add_tag', 'config' => ['tag_name' => $tag]]],
            'is_active' => true,
        ]);
    }

    protected function hasTag(Subscriber $subscriber, string $tag): bool
    {
        return $subscriber->tags()->where('name', $tag)->exists();
    }

    public function test_birthday_rules_limited_to_a_list_run_for_todays_birthdays(): void
    {
        // what a message sent "on the subscriber's birthday" creates
        $this->rule('subscriber_birthday', ['list_id' => $this->list->id], 'urodziny');

        $iso = $this->subscriber('iso@example.com', '1990-11-12');
        $dotted = $this->subscriber('kropki@example.com', '12.11.1985');
        $otherDay = $this->subscriber('inny-dzien@example.com', '1990-03-04');
        $noField = $this->subscriber('bez-pola@example.com');
        $unsubscribed = $this->subscriber('wypisany@example.com', '1990-11-12', 'unsubscribed');

        Log::spy();
        $stats = app(DateTriggerService::class)->processAll();

        Log::shouldNotHaveReceived('error');
        $this->assertSame(2, $stats['birthdays_processed']);
        $this->assertTrue($this->hasTag($iso, 'urodziny'));
        $this->assertTrue($this->hasTag($dotted, 'urodziny'));
        $this->assertFalse($this->hasTag($otherDay, 'urodziny'));
        $this->assertFalse($this->hasTag($noField, 'urodziny'));
        $this->assertFalse($this->hasTag($unsubscribed, 'urodziny'));

        // runs every minute: the same day does not run twice
        $this->assertSame(0, app(DateTriggerService::class)->processAll()['birthdays_processed']);
    }

    public function test_birthdays_of_another_account_do_not_leak_in(): void
    {
        $this->rule('subscriber_birthday', [], 'urodziny');

        $other = User::factory()->create();
        $otherList = ContactList::create(['user_id' => $other->id, 'name' => 'Inne', 'type' => 'email']);
        $foreign = $this->subscriber('obcy@example.com', '1990-11-12', 'active', $other, $otherList);

        $this->assertSame(0, app(DateTriggerService::class)->processAll()['birthdays_processed']);
        $this->assertFalse($this->hasTag($foreign, 'urodziny'));
    }

    public function test_a_rule_for_another_list_does_not_run(): void
    {
        $otherList = ContactList::create(['user_id' => $this->user->id, 'name' => 'Klienci', 'type' => 'email']);
        $this->rule('subscriber_birthday', ['list_id' => $otherList->id], 'urodziny-klienci');

        $subscriber = $this->subscriber('newsletter@example.com', '1990-11-12');

        app(DateTriggerService::class)->processAll();

        $this->assertFalse($this->hasTag($subscriber, 'urodziny-klienci'));
    }

    public function test_subscription_anniversary_runs_for_subscribers_who_joined_on_this_day_in_an_earlier_year(): void
    {
        $this->rule('subscription_anniversary', ['list_id' => $this->list->id], 'rocznica');

        $this->travelTo(Carbon::parse('2024-11-12 09:00:00', 'UTC'));
        $twoYears = $this->subscriber('dwa-lata@example.com');
        $this->travelTo(Carbon::parse('2026-11-12 08:00:00', 'UTC'));
        $today = $this->subscriber('dzis@example.com');
        $this->travelTo(Carbon::parse('2026-11-12 10:00:00', 'UTC'));

        Log::spy();
        $stats = app(DateTriggerService::class)->processAll();

        Log::shouldNotHaveReceived('error');
        $this->assertSame(1, $stats['anniversaries_processed']);
        $this->assertTrue($this->hasTag($twoYears, 'rocznica'));
        $this->assertFalse($this->hasTag($today, 'rocznica'));
    }

    public function test_date_rules_run_once_on_their_own_date_only(): void
    {
        $todayA = $this->rule('date_reached', ['date' => '2026-11-12'], 'dzis-a');
        $todayB = $this->rule('date_reached', ['date' => '2026-11-12'], 'dzis-b');
        $tomorrow = $this->rule('date_reached', ['date' => '2026-11-13'], 'jutro');

        $subscriber = $this->subscriber('data@example.com');

        Log::spy();
        app(DateTriggerService::class)->processAll();
        app(DateTriggerService::class)->processAll();

        Log::shouldNotHaveReceived('error');
        $this->assertTrue($this->hasTag($subscriber, 'dzis-a'));
        $this->assertTrue($this->hasTag($subscriber, 'dzis-b'));
        $this->assertFalse($this->hasTag($subscriber, 'jutro'), 'a rule for tomorrow ran today');
        $this->assertSame(1, AutomationRuleLog::where('automation_rule_id', $todayA->id)->count());
        $this->assertSame(1, AutomationRuleLog::where('automation_rule_id', $todayB->id)->count());
        $this->assertSame(0, AutomationRuleLog::where('automation_rule_id', $tomorrow->id)->count());
    }

    public function test_a_follow_up_of_a_deleted_contact_is_cancelled_instead_of_failing_forever(): void
    {
        $subscriber = $this->subscriber('crm@example.com');
        $contact = CrmContact::create(['subscriber_id' => $subscriber->id, 'user_id' => $this->user->id, 'status' => 'lead']);
        $sequence = CrmFollowUpSequence::create(['user_id' => $this->user->id, 'name' => 'Po rozmowie', 'trigger_type' => 'manual', 'is_active' => true]);
        $step = CrmFollowUpStep::create(['sequence_id' => $sequence->id, 'position' => 1, 'delay_days' => 0, 'delay_hours' => 0, 'action_type' => 'task', 'task_title' => 'Zadzwoń']);
        $enrollment = CrmFollowUpEnrollment::create([
            'sequence_id' => $sequence->id,
            'crm_contact_id' => $contact->id,
            'current_step_id' => $step->id,
            'status' => 'active',
            'started_at' => now()->subDay(),
            'next_action_at' => now()->subMinute(),
            'steps_completed' => 0,
        ]);

        $contact->delete(); // soft delete: the enrollment stays

        $this->artisan('crm:process-follow-ups')->assertSuccessful();

        $this->assertSame('cancelled', $enrollment->fresh()->status);
        $this->assertSame(0, CrmTask::count());

        // the next run has nothing left to fail on
        $this->artisan('crm:process-follow-ups')->assertSuccessful();
    }
}
