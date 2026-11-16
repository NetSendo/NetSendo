<?php

namespace Tests\Feature;

use App\Models\ContactList;
use App\Models\Mailbox;
use App\Models\Message;
use App\Models\Subscriber;
use App\Models\User;
use App\Services\PlaceholderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Concerns\RecordsSentMail;
use Tests\TestCase;

/**
 * [[unsubscribe_now]]: a page with one button that unsubscribes at once — from
 * the list the message came from, or from every list — with no confirmation
 * email and no preferences page. Opening the link alone changes nothing.
 */
class UnsubscribeNowTest extends TestCase
{
    use RefreshDatabase;
    use RecordsSentMail;

    private User $user;
    private ContactList $list;
    private ContactList $otherList;
    private Subscriber $subscriber;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->recordSentMail();

        $this->user = User::factory()->create();
        $this->list = ContactList::create(['user_id' => $this->user->id, 'name' => 'Newsletter', 'type' => 'email']);
        $this->otherList = ContactList::create(['user_id' => $this->user->id, 'name' => 'Offers', 'type' => 'email']);
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
        $this->subscriber = Subscriber::create([
            'user_id' => $this->user->id,
            'email' => 'reader@example.com',
            'status' => 'active',
            'is_active_global' => true,
        ]);
        $this->subscriber->contactLists()->attach($this->list->id, ['status' => 'active', 'subscribed_at' => now()]);
    }

    private function nowUrl(?ContactList $list = null): string
    {
        return app(PlaceholderService::class)->generateUnsubscribeNowLink($this->subscriber, $list);
    }

    private function membership(ContactList $list): ?string
    {
        return $this->subscriber->contactLists()->where('contact_lists.id', $list->id)->first()?->pivot->status;
    }

    private function sendWithPlaceholder(array $lists): array
    {
        $message = Message::create([
            'user_id' => $this->user->id,
            'channel' => 'email',
            'type' => 'broadcast',
            'subject' => 'Hello',
            'content' => '<p><a href="[[unsubscribe_now]]">Wypisz się</a></p>',
            'plain_text' => 'Wypisz się: [[unsubscribe_now]]',
            'status' => 'scheduled',
            'tracking_enabled' => false,
        ]);
        $message->contactLists()->attach(collect($lists)->pluck('id'));

        return $this->runSendJob($message, $this->subscriber);
    }

    private function buttonAction(string $html): string
    {
        $this->assertMatchesRegularExpression('#<form method="POST" action="([^"]+)"#', $html);
        preg_match('#<form method="POST" action="([^"]+)"#', $html, $m);

        return html_entity_decode($m[1]);
    }

    public function test_the_placeholder_links_the_list_the_message_came_from(): void
    {
        $sent = $this->sendWithPlaceholder([$this->list]);

        $this->assertStringContainsString('href="' . e($this->nowUrl($this->list)) . '"', $sent['html']);
        $this->assertStringContainsString("/unsubscribe/{$this->subscriber->id}/{$this->list->id}/now?", $sent['html']);
        $this->assertStringContainsString($this->nowUrl($this->list), (string) $sent['text']);
    }

    public function test_a_message_to_several_lists_links_the_one_the_subscriber_is_on_else_every_list(): void
    {
        $sent = $this->sendWithPlaceholder([$this->list, $this->otherList]);
        $this->assertStringContainsString('href="' . e($this->nowUrl($this->list)) . '"', $sent['html']);

        $this->subscriber->contactLists()->attach($this->otherList->id, ['status' => 'active', 'subscribed_at' => now()]);
        $sent = $this->sendWithPlaceholder([$this->list, $this->otherList]);
        $this->assertStringContainsString('href="' . e($this->nowUrl()) . '"', $sent['html']);
        $this->assertStringContainsString("/unsubscribe/{$this->subscriber->id}/now?", $sent['html']);
    }

    public function test_opening_the_link_changes_nothing_and_the_button_unsubscribes(): void
    {
        $html = $this->get($this->nowUrl($this->list))
            ->assertOk()
            ->assertSee('Unsubscribe reader@example.com from “Newsletter”?')
            ->getContent();

        $this->assertSame('active', $this->membership($this->list), 'Scanners open every link of an email');
        $before = count($this->sentMail);

        $this->post($this->buttonAction($html), ['List-Unsubscribe' => 'One-Click'])->assertOk();

        $this->assertSame('unsubscribed', $this->membership($this->list));
        foreach (array_slice($this->sentMail, $before) as $mail) {
            $this->assertStringNotContainsStringIgnoringCase('confirm', $mail['subject'], 'No confirmation request');
        }
    }

    public function test_without_a_list_the_button_leaves_every_list(): void
    {
        $this->subscriber->contactLists()->attach($this->otherList->id, ['status' => 'active', 'subscribed_at' => now()]);

        $html = $this->get($this->nowUrl())->assertOk()->assertSee('Unsubscribe reader@example.com from every list?')->getContent();
        $this->post($this->buttonAction($html), ['List-Unsubscribe' => 'One-Click'])->assertOk();

        $this->assertSame('unsubscribed', $this->membership($this->list));
        $this->assertSame('unsubscribed', $this->membership($this->otherList));
    }

    public function test_the_page_speaks_the_accounts_language(): void
    {
        $this->user->update(['locale' => 'pl']);

        $this->get($this->nowUrl($this->list))
            ->assertOk()
            ->assertSee('Wypisać adres reader@example.com z listy „Newsletter”?')
            ->assertSee('Wypisz mnie');
    }

    public function test_an_unsigned_or_tampered_link_is_refused(): void
    {
        $this->get(route('subscriber.unsubscribe.now', [$this->subscriber, $this->list]))->assertForbidden();
        $this->get(str_replace("/{$this->list->id}/now", "/{$this->otherList->id}/now", $this->nowUrl($this->list)))->assertForbidden();
    }

    public function test_an_address_already_unsubscribed_sees_no_button(): void
    {
        $this->subscriber->contactLists()->updateExistingPivot($this->list->id, ['status' => 'unsubscribed']);

        $this->get($this->nowUrl($this->list))->assertOk()->assertDontSee('List-Unsubscribe');
        $this->get($this->nowUrl())->assertOk()->assertDontSee('List-Unsubscribe');
    }

    public function test_it_works_on_a_sleeping_instance(): void
    {
        Http::fake();
        config(['netsendo.dormant' => true]);

        $html = $this->get($this->nowUrl())->assertOk()->assertSee('from every list?')->getContent();
        $this->post($this->buttonAction($html), ['List-Unsubscribe' => 'One-Click'])->assertOk();

        $this->assertSame('unsubscribed', $this->membership($this->list));

        $html = $this->get($this->nowUrl($this->otherList))->assertOk()->getContent();
        $this->assertStringNotContainsString('List-Unsubscribe', $html, 'Not on that list');
    }
}
