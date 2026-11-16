<?php

namespace Tests\Feature;

use App\Models\AutomationRule;
use App\Models\ContactList;
use App\Models\CustomField;
use App\Models\Message;
use App\Models\Subscriber;
use App\Models\User;
use App\Services\Automation\AutomationCatalog;
use App\Services\Automation\FieldChangeNotifier;
use App\Services\Automation\InactivityTriggerService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Seen on a live installation (2.11.4): the default "Cold Lead Detection" rule
 * was active everywhere with 0 runs, because nothing raised
 * "subscriber_inactive". Neither did anything raise field_updated,
 * specific_link_clicked or pixel_return_visit. Behind it:
 * - open/click tracking never wrote the subscriber's opens_count,
 *   clicks_count, last_opened_at, last_clicked_at, so every engagement view
 *   read 0 ("never engaged: 711 of 711" on a list with 233 openers);
 * - email events read messages.contact_list_id, dropped in December 2025, so
 *   a list filter on an open, click or bounce rule never matched.
 */
class AutomationDeadTriggersTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected ContactList $list;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-16 10:00:00', 'UTC'));
        FieldChangeNotifier::flush();

        $this->user = User::factory()->create();
        $this->list = ContactList::create(['user_id' => $this->user->id, 'name' => 'Newsletter', 'type' => 'email']);
    }

    protected function subscriber(string $email, ?Carbon $joined = null, ?ContactList $list = null): Subscriber
    {
        $joined ??= now()->subDays(100);

        $subscriber = Subscriber::create([
            'user_id' => $this->user->id,
            'email' => $email,
            'status' => 'active',
            'is_active_global' => true,
        ]);
        DB::table('subscribers')->where('id', $subscriber->id)->update(['created_at' => $joined]);
        $subscriber->contactLists()->attach(($list ?? $this->list)->id, ['status' => 'active', 'subscribed_at' => $joined]);

        return $subscriber->fresh();
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

    protected function message(): Message
    {
        $message = Message::create([
            'user_id' => $this->user->id,
            'channel' => 'email',
            'type' => 'broadcast',
            'subject' => 'Hello',
            'content' => '<p>Body</p>',
            'status' => 'sent',
        ]);
        $message->contactLists()->attach($this->list->id);

        return $message;
    }

    protected function hasTag(Subscriber $subscriber, string $tag): bool
    {
        return $subscriber->tags()->where('name', $tag)->exists();
    }

    protected function trackingUrl(string $kind, Message $message, Subscriber $subscriber, string $query = ''): string
    {
        $hash = hash_hmac('sha256', "{$message->id}.{$subscriber->id}", config('app.key'));

        return "/t/{$kind}/{$message->id}/{$subscriber->id}/{$hash}{$query}";
    }

    public function test_opens_and_clicks_update_the_subscriber_engagement_columns(): void
    {
        $subscriber = $this->subscriber('czytelnik@example.com');
        $message = $this->message();

        $this->get($this->trackingUrl('open', $message, $subscriber))->assertOk();
        $this->get($this->trackingUrl('open', $message, $subscriber))->assertOk();
        $this->get($this->trackingUrl('click', $message, $subscriber, '?url=' . urlencode('https://example.com/a')));
        $this->get($this->trackingUrl('click', $message, $subscriber, '?url=' . urlencode('https://example.com/b')));

        $subscriber->refresh();
        $this->assertSame(1, $subscriber->opens_count, 'unique opens: one message');
        $this->assertSame(1, $subscriber->clicks_count, 'unique clicks: one message');
        $this->assertTrue($subscriber->last_opened_at->equalTo(now()));
        $this->assertTrue($subscriber->last_clicked_at->equalTo(now()));
    }

    public function test_inactive_subscribers_run_the_rule_once_per_quiet_spell(): void
    {
        $rule = $this->rule('subscriber_inactive', ['days' => 30], 'zimny');

        $quiet = $this->subscriber('cichy@example.com');
        $recentReader = $this->subscriber('czyta@example.com');
        DB::table('subscribers')->where('id', $recentReader->id)->update(['last_opened_at' => now()->subDays(3)]);
        $newcomer = $this->subscriber('nowy@example.com', now()->subDays(5));

        $service = app(InactivityTriggerService::class);
        $this->assertSame(1, $service->processAll());
        $this->assertTrue($this->hasTag($quiet, 'zimny'));
        $this->assertFalse($this->hasTag($recentReader, 'zimny'));
        $this->assertFalse($this->hasTag($newcomer, 'zimny'));
        $this->assertSame(1, $rule->fresh()->execution_count);

        // hourly: nothing new
        $this->assertSame(0, $service->processAll());

        // an open ends the spell; 31 quiet days later the rule runs again
        Subscriber::recordOpen($quiet->id, true);
        $this->assertSame(0, $service->processAll());
        $this->travel(31)->days();
        // by now the other two have been quiet for 30+ days as well
        $this->assertSame(3, $service->processAll());
        $this->assertSame(4, $rule->fresh()->execution_count);
    }

    public function test_inactivity_rules_of_different_lengths_run_each_at_their_own_time(): void
    {
        $this->rule('subscriber_inactive', ['days' => 30], 'po-30');
        $this->rule('subscriber_inactive', ['inactive_days' => 90], 'po-90'); // key the message editor saves

        $subscriber = $this->subscriber('cichy@example.com', now()->subDays(60));

        app(InactivityTriggerService::class)->processAll();

        $this->assertTrue($this->hasTag($subscriber, 'po-30'));
        $this->assertFalse($this->hasTag($subscriber, 'po-90'));
    }

    public function test_an_inactivity_rule_with_a_list_counts_from_joining_that_list(): void
    {
        $other = ContactList::create(['user_id' => $this->user->id, 'name' => 'Kurs', 'type' => 'email']);
        $this->rule('subscriber_inactive', ['days' => 30, 'list_id' => $other->id], 'kurs-cisza');

        $subscriber = $this->subscriber('cichy@example.com');
        $subscriber->contactLists()->attach($other->id, ['status' => 'active', 'subscribed_at' => now()->subDays(10)]);

        app(InactivityTriggerService::class)->processAll();
        $this->assertFalse($this->hasTag($subscriber, 'kurs-cisza'), 'joined the list 10 days ago');

        $this->travel(21)->days();
        app(InactivityTriggerService::class)->processAll();
        $this->assertTrue($this->hasTag($subscriber->fresh(), 'kurs-cisza'));
    }

    public function test_a_click_on_a_given_link_runs_specific_link_rules_and_list_filters_match(): void
    {
        $this->rule('specific_link_clicked', ['link_url' => 'https://shop.example.com/offer*'], 'oferta');
        $this->rule('specific_link_clicked', [], 'bez-linku');
        $this->rule('email_clicked', ['list_id' => $this->list->id], 'klik-z-listy');

        $subscriber = $this->subscriber('klik@example.com');
        $message = $this->message();

        $this->get($this->trackingUrl('click', $message, $subscriber, '?url=' . urlencode('https://shop.example.com/blog')));
        $this->assertFalse($this->hasTag($subscriber, 'oferta'));
        $this->assertTrue($this->hasTag($subscriber, 'klik-z-listy'), 'list filter matches the message lists');

        $this->get($this->trackingUrl('click', $message, $subscriber, '?url=' . urlencode('https://shop.example.com/offer/black-friday')));
        $this->assertTrue($this->hasTag($subscriber, 'oferta'));
        $this->assertFalse($this->hasTag($subscriber, 'bez-linku'), 'a rule without a link does not run on every click');
    }

    public function test_field_changes_of_existing_subscribers_run_field_rules(): void
    {
        $city = CustomField::create([
            'user_id' => $this->user->id,
            'name' => 'miasto',
            'label' => 'Miasto',
            'type' => 'text',
            'scope' => 'global',
        ]);
        $this->rule('field_updated', ['field' => 'miasto', 'value' => 'warszawa'], 'z-warszawy');
        $this->rule('field_updated', ['field' => 'phone'], 'nowy-telefon');

        $subscriber = $this->subscriber('pole@example.com');

        $subscriber->setCustomFieldValue('miasto', 'Kraków');
        $this->assertFalse($this->hasTag($subscriber, 'z-warszawy'));

        $subscriber->setCustomFieldValue('miasto', ' Warszawa ');
        $this->assertTrue($this->hasTag($subscriber, 'z-warszawy'));

        $subscriber->update(['phone' => '+48600100200']);
        $this->assertTrue($this->hasTag($subscriber, 'nowy-telefon'));

        // a brand-new subscriber's fields are not "changes"
        $fresh = Subscriber::create(['user_id' => $this->user->id, 'email' => 'nowy@example.com', 'status' => 'active', 'is_active_global' => true]);
        $fresh->fieldValues()->create(['custom_field_id' => $city->id, 'value' => 'Warszawa']);
        $this->assertFalse($this->hasTag($fresh, 'z-warszawy'));
    }

    public function test_a_field_rule_that_writes_its_own_field_does_not_loop(): void
    {
        CustomField::create(['user_id' => $this->user->id, 'name' => 'licznik', 'label' => 'Licznik', 'type' => 'text', 'scope' => 'global']);
        $rule = AutomationRule::create([
            'user_id' => $this->user->id,
            'name' => 'loop',
            'trigger_event' => 'field_updated',
            'trigger_config' => ['field' => 'licznik'],
            'conditions' => [],
            'actions' => [['type' => 'update_field', 'config' => ['field' => 'licznik', 'value' => 'x']]],
            'is_active' => true,
        ]);

        $subscriber = $this->subscriber('petla@example.com');
        $subscriber->setCustomFieldValue('licznik', '1');

        $this->assertSame(1, $rule->fresh()->execution_count);
    }

    public function test_the_builder_offers_only_triggers_that_fire(): void
    {
        $selectable = AutomationRule::getSelectableTriggerEvents();

        foreach (['subscriber_inactive', 'field_updated', 'specific_link_clicked', 'pixel_return_visit'] as $working) {
            $this->assertArrayHasKey($working, $selectable);
            $this->assertTrue(AutomationCatalog::triggers()[$working]['available']);
        }

        $this->assertArrayNotHasKey('list_join', $selectable);
        $this->assertArrayNotHasKey('subscriber_activated', $selectable);
        $this->assertArrayHasKey('list_join', AutomationRule::getSelectableTriggerEvents('list_join'), 'an old rule keeps its trigger in the editor');
    }
}
