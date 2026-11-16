<?php

namespace Tests\Feature;

use App\Events\SubscriberSignedUp;
use App\Events\SubscriberUnsubscribed;
use App\Models\ContactList;
use App\Models\Mailbox;
use App\Models\Message;
use App\Models\MessageQueueEntry;
use App\Models\Subscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Tests\Feature\Concerns\RecordsSentMail;
use Tests\TestCase;

/**
 * The preferences page that [[unsubscribe]] of a message to several lists and
 * [[manage]] lead to. On 2026-11-10 three people who clicked "unsubscribe" ended
 * up on all 40 public lists of an account: something ticked every box of the
 * page and opened the confirmation link within a minute (the way mail scanners
 * do). The page now offers only the subscriber's own lists, leaves at once, and
 * rejoins only after a button on the page the confirmation link opens.
 */
class SubscriberPreferencesTest extends TestCase
{
    use RefreshDatabase;
    use RecordsSentMail;

    private User $user;
    private Subscriber $subscriber;
    /** Public, active */
    private ContactList $news;
    /** Public, left earlier */
    private ContactList $offers;
    /** Public, never joined */
    private ContactList $webinars;
    /** Private, active */
    private ContactList $buyers;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->recordSentMail();

        $this->user = User::factory()->create();
        Mailbox::create([
            'user_id' => $this->user->id,
            'name' => 'Main',
            'provider' => 'smtp',
            'from_email' => 'news@example.com',
            'from_name' => 'News',
            'is_default' => true,
            'is_active' => true,
            'allowed_types' => ['broadcast', 'autoresponder', 'system'],
            'credentials' => ['host' => 'localhost', 'port' => 1025],
        ]);

        $this->news = $this->list('Newsletter');
        $this->offers = $this->list('Offers');
        $this->webinars = $this->list('Webinars');
        $this->buyers = $this->list('Course buyers', public: false);

        $this->subscriber = Subscriber::create([
            'user_id' => $this->user->id,
            'email' => 'reader@example.com',
            'status' => 'active',
            'is_active_global' => true,
        ]);
        $this->subscriber->contactLists()->attach($this->news->id, ['status' => 'active', 'subscribed_at' => now(), 'source' => 'form']);
        $this->subscriber->contactLists()->attach($this->offers->id, ['status' => 'unsubscribed', 'subscribed_at' => now()->subMonth(), 'unsubscribed_at' => now()->subWeek()]);
        $this->subscriber->contactLists()->attach($this->buyers->id, ['status' => 'active', 'subscribed_at' => now(), 'source' => 'api']);
    }

    private function list(string $name, bool $public = true, ?User $user = null): ContactList
    {
        return ContactList::create([
            'user_id' => ($user ?? $this->user)->id,
            'name' => $name,
            'type' => 'email',
            'is_public' => $public,
        ]);
    }

    private function pageUrl(): string
    {
        return URL::signedRoute('subscriber.preferences', ['subscriber' => $this->subscriber->id]);
    }

    private function save(array $lists, array $extra = [])
    {
        return $this->post(route('subscriber.preferences.update', $this->subscriber), array_merge([
            'signed_url' => $this->pageUrl(),
            'lists' => $lists,
        ], $extra));
    }

    private function membership(ContactList $list): ?string
    {
        return $this->subscriber->contactLists()->where('contact_lists.id', $list->id)->first()?->pivot->status;
    }

    private function confirmationLink(): string
    {
        $mail = end($this->sentMail);
        $this->assertNotFalse($mail, 'A confirmation email was sent');
        $this->assertMatchesRegularExpression('#href="([^"]+/preferences/\d+/confirm[^"]+)"#', $mail['html']);
        preg_match('#href="([^"]+/preferences/\d+/confirm[^"]+)"#', $mail['html'], $m);

        return html_entity_decode($m[1]);
    }

    private function legacyLink(array $selected): string
    {
        return URL::signedRoute('subscriber.preferences.confirm', [
            'subscriber' => $this->subscriber->id,
            'changes' => base64_encode(json_encode(['selected_lists' => $selected, 'requested_at' => now()->toISOString()])),
        ], now()->addHours(24));
    }

    private function formAction(string $html, string $formId): string
    {
        $this->assertMatchesRegularExpression('#<form[^>]*action="([^"]+)"[^>]*id="' . $formId . '"#', $html);
        preg_match('#<form[^>]*action="([^"]+)"[^>]*id="' . $formId . '"#', $html, $m);

        return html_entity_decode($m[1]);
    }

    // ---- The page ------------------------------------------------------------

    public function test_the_page_offers_only_the_subscribers_own_public_lists(): void
    {
        $html = $this->get($this->pageUrl())->assertOk()->getContent();

        $this->assertStringContainsString('Newsletter', $html);
        $this->assertStringContainsString('Offers', $html);
        $this->assertStringNotContainsString('Webinars', $html, 'A list never joined is not offered');
        $this->assertStringNotContainsString('Course buyers', $html, 'Private list names stay private');

        $this->assertMatchesRegularExpression('#value="' . $this->news->id . '"\s+checked#', $html);
        $this->assertDoesNotMatchRegularExpression('#value="' . $this->offers->id . '"\s+checked#', $html);
    }

    public function test_the_page_speaks_the_subscribers_or_the_accounts_language(): void
    {
        $this->get($this->pageUrl())->assertOk()->assertSee('Your subscriptions')->assertSee('Unsubscribe me from everything');

        $this->user->update(['locale' => 'pl']);
        $this->get($this->pageUrl())
            ->assertOk()
            ->assertSee('<html lang="pl">', false)
            ->assertSee('Twoje subskrypcje')
            ->assertSee('Wypisz mnie ze wszystkiego')
            ->assertSee('Zapisz zmiany')
            ->assertDontSee('Manage Your Subscriptions');

        $this->subscriber->update(['language' => 'de']);
        $this->get($this->pageUrl())->assertOk()->assertSee('Ihre Abonnements');
    }

    public function test_an_unsigned_page_is_refused(): void
    {
        $this->get(route('subscriber.preferences', $this->subscriber))->assertOk()->assertDontSee('preferencesForm');
    }

    // ---- Saving ----------------------------------------------------------------

    public function test_unticking_a_list_unsubscribes_at_once_without_an_email(): void
    {
        $pending = Message::create(['user_id' => $this->user->id, 'channel' => 'email', 'type' => 'autoresponder', 'subject' => 'Day 2', 'content' => '<p>x</p>', 'status' => 'scheduled']);
        $pending->contactLists()->attach($this->news->id);
        $pending->queueEntries()->create(['subscriber_id' => $this->subscriber->id, 'status' => MessageQueueEntry::STATUS_PLANNED, 'planned_at' => now()->addDay()]);
        Event::fake([SubscriberUnsubscribed::class, SubscriberSignedUp::class]);

        $this->save([])->assertOk();

        $this->assertSame('unsubscribed', $this->membership($this->news));
        $this->assertSame('unsubscribed', $this->membership($this->offers));
        $this->assertSame('active', $this->membership($this->buyers), 'A list not on the page is left alone');
        $this->assertNull($this->membership($this->webinars));
        $this->assertSame([], $this->sentMail, 'Leaving needs no confirmation email');
        $this->assertSame(0, $pending->queueEntries()->count(), 'What was planned for the list is dropped');

        Event::assertDispatched(SubscriberUnsubscribed::class, fn ($e) => $e->list->is($this->news) && $e->reason === 'preferences');
        Event::assertDispatchedTimes(SubscriberUnsubscribed::class, 1);
        Event::assertNotDispatched(SubscriberSignedUp::class);
    }

    public function test_a_form_with_every_box_ticked_joins_no_list_the_subscriber_never_joined(): void
    {
        // The 2026-11-10 case: every list of the account in the form, the link opened at once
        Event::fake([SubscriberSignedUp::class, SubscriberUnsubscribed::class]);
        $all = ContactList::where('user_id', $this->user->id)->pluck('id')->all();
        $foreign = $this->list('Other account', user: User::factory()->create());

        $this->save([...$all, $foreign->id, 999999])->assertOk();

        $this->assertNull($this->membership($this->webinars));
        $this->assertNull($this->membership($foreign));
        $this->assertSame('active', $this->membership($this->news));
        $this->assertSame('unsubscribed', $this->membership($this->offers), 'Rejoining waits for the confirmation');
        Event::assertNotDispatched(SubscriberSignedUp::class);
        Event::assertNotDispatched(SubscriberUnsubscribed::class);

        // Opening the emailed link (what a scanner does) changes nothing
        $link = $this->confirmationLink();
        $page = $this->get($link)->assertOk()->assertSee('Offers')->assertDontSee('Webinars')->getContent();
        $this->assertSame('unsubscribed', $this->membership($this->offers));
        Event::assertNotDispatched(SubscriberSignedUp::class);

        // The button of that page rejoins the list the subscriber had left, and nothing else
        $this->assertMatchesRegularExpression('#<form method="POST" action="([^"]+)"#', $page);
        preg_match('#<form method="POST" action="([^"]+)"#', $page, $m);
        $this->post(html_entity_decode($m[1]))->assertOk();

        $this->assertSame('active', $this->membership($this->offers));
        $this->assertNull($this->membership($this->webinars));
        $this->assertNull($this->membership($foreign));
        $this->assertSame(3, $this->subscriber->contactLists()->wherePivot('status', 'active')->count());
        Event::assertDispatchedTimes(SubscriberSignedUp::class, 1);
        Event::assertDispatched(SubscriberSignedUp::class, fn ($e) => $e->list->is($this->offers) && $e->source === 'preferences_reactivation');
    }

    public function test_a_tampered_confirmation_link_is_refused(): void
    {
        $this->save([$this->news->id, $this->offers->id])->assertOk();
        $link = $this->confirmationLink();

        $forged = preg_replace('/changes=[^&]+/', 'changes=' . urlencode(base64_encode(json_encode(['rejoin' => [$this->webinars->id]]))), $link);

        $this->get($forged)->assertOk()->assertDontSee('Webinars');
        $this->post($forged)->assertOk();

        $this->assertNull($this->membership($this->webinars));
        $this->assertSame('unsubscribed', $this->membership($this->offers));
    }

    public function test_links_emailed_before_the_update_never_join_new_lists(): void
    {
        $link = $this->legacyLink([$this->offers->id, $this->webinars->id]);

        $this->get($link)->assertOk()->assertSee('Offers')->assertSee('Newsletter');
        $this->assertSame('active', $this->membership($this->news), 'Opening the link changes nothing');

        $this->post($link)->assertOk();

        $this->assertSame('active', $this->membership($this->offers));
        $this->assertSame('unsubscribed', $this->membership($this->news), 'Left out of the old selection');
        $this->assertNull($this->membership($this->webinars));
        $this->assertSame('active', $this->membership($this->buyers));
    }

    public function test_an_old_link_with_nothing_selected_leaves_the_accounts_lists_only(): void
    {
        // Contamination: the subscriber also sits on another account's public list
        $foreign = $this->list('List B', user: User::factory()->create());
        $this->subscriber->contactLists()->attach($foreign->id, ['status' => 'active', 'subscribed_at' => now(), 'source' => 'import']);

        $this->post($this->legacyLink([]))->assertOk();

        $this->assertSame('unsubscribed', $this->membership($this->news));
        $this->assertSame('active', $this->membership($foreign));
    }

    public function test_unsubscribe_from_everything_leaves_every_list_private_ones_too(): void
    {
        $html = $this->get($this->pageUrl())->assertOk()->getContent();
        $action = $this->formAction($html, 'unsubscribeAllForm');

        $this->assertStringContainsString('name="List-Unsubscribe" value="One-Click"', $html);
        $this->post($action, ['List-Unsubscribe' => 'One-Click'])->assertOk();

        $this->assertSame('unsubscribed', $this->membership($this->news));
        $this->assertSame('unsubscribed', $this->membership($this->buyers));
        $this->assertNull($this->membership($this->webinars));
    }

    public function test_a_subscriber_without_active_lists_gets_no_unsubscribe_button(): void
    {
        $this->subscriber->contactLists()->updateExistingPivot($this->news->id, ['status' => 'unsubscribed']);
        $this->subscriber->contactLists()->updateExistingPivot($this->buyers->id, ['status' => 'unsubscribed']);

        $this->get($this->pageUrl())
            ->assertOk()
            ->assertDontSee('unsubscribeAllForm')
            ->assertSee('This address is not subscribed to any list.');
    }
}
