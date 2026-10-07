<?php

namespace Tests\Feature;

use App\Events\SubscriberUnsubscribed;
use App\Models\ContactList;
use App\Models\Mailbox;
use App\Models\Message;
use App\Models\MessageQueueEntry;
use App\Models\Subscriber;
use App\Models\User;
use App\Services\PlaceholderService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Concerns\RecordsSentMail;
use Tests\TestCase;

/**
 * RFC 8058 one-click unsubscribe. List mail carries List-Unsubscribe with a
 * signed HTTPS URL and List-Unsubscribe-Post: List-Unsubscribe=One-Click; a
 * mailbox provider POSTs List-Unsubscribe=One-Click to that URL and the
 * subscriber is unsubscribed at once. The URL used to answer GET only (POST:
 * 405), and a GET starts the two-step flow with a confirmation email.
 */
class OneClickUnsubscribeTest extends TestCase
{
    use RefreshDatabase;
    use RecordsSentMail;

    private User $user;
    private ContactList $list;
    private ContactList $otherList;
    private Mailbox $mailbox;
    private Subscriber $subscriber;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->recordSentMail();

        $this->user = User::factory()->create();
        $this->list = ContactList::create(['user_id' => $this->user->id, 'name' => 'Newsletter', 'type' => 'email']);
        $this->otherList = ContactList::create(['user_id' => $this->user->id, 'name' => 'Offers', 'type' => 'email']);
        $this->mailbox = Mailbox::create([
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

    private function message(array $lists, array $attributes = []): Message
    {
        $message = Message::create(array_merge([
            'user_id' => $this->user->id,
            'channel' => 'email',
            'type' => 'broadcast',
            'subject' => 'Hello',
            'content' => '<p>Body</p>',
            'status' => 'scheduled',
        ], $attributes));
        $message->contactLists()->attach(collect($lists)->pluck('id'));

        return $message;
    }

    private function listUrl(?ContactList $list = null): string
    {
        return app(PlaceholderService::class)->generateUnsubscribeLink($this->subscriber, $list ?? $this->list);
    }

    private function membershipStatus(ContactList $list): ?string
    {
        return $this->subscriber->contactLists()->where('contact_lists.id', $list->id)->first()?->pivot->status;
    }

    private function headerUrl(array $headers): string
    {
        $this->assertMatchesRegularExpression('/^<https?:\/\/[^>]+>$/', $headers['List-Unsubscribe']);

        return trim($headers['List-Unsubscribe'], '<>');
    }

    // ---- Headers ---------------------------------------------------------

    public function test_broadcast_and_autoresponder_carry_one_click_headers(): void
    {
        foreach (['broadcast', 'autoresponder'] as $type) {
            $sent = $this->runSendJob($this->message([$this->list], ['type' => $type]), $this->subscriber);

            $this->assertSame('<' . $this->listUrl() . '>', $sent['headers']['List-Unsubscribe'], $type);
            $this->assertSame('List-Unsubscribe=One-Click', $sent['headers']['List-Unsubscribe-Post'], $type);
        }
    }

    public function test_message_to_several_lists_uses_the_list_the_subscriber_is_on(): void
    {
        $sent = $this->runSendJob($this->message([$this->list, $this->otherList]), $this->subscriber);

        $this->assertSame('<' . $this->listUrl() . '>', $sent['headers']['List-Unsubscribe']);
    }

    public function test_member_of_several_message_lists_gets_the_account_wide_url(): void
    {
        $this->subscriber->contactLists()->attach($this->otherList->id, ['status' => 'active', 'subscribed_at' => now()]);

        $sent = $this->runSendJob($this->message([$this->list, $this->otherList]), $this->subscriber);

        $url = $this->headerUrl($sent['headers']);
        $this->assertStringContainsString("/unsubscribe/{$this->subscriber->id}?", $url);
        $this->assertSame('List-Unsubscribe=One-Click', $sent['headers']['List-Unsubscribe-Post']);
    }

    public function test_single_api_send_without_a_list_gets_no_automatic_header(): void
    {
        $message = Message::create([
            'user_id' => $this->user->id,
            'channel' => 'email',
            'type' => 'broadcast',
            'subject' => 'Receipt',
            'content' => '<p>Receipt</p>',
            'status' => 'scheduled',
        ]);

        $sent = $this->runSendJob($message, $this->subscriber);

        $this->assertArrayNotHasKey('List-Unsubscribe', $sent['headers']);
        $this->assertArrayNotHasKey('List-Unsubscribe-Post', $sent['headers']);
    }

    public function test_configured_header_with_our_url_gets_the_post_header(): void
    {
        // As set on madrydochod's mailbox: the placeholder plus a mailto
        $this->mailbox->update(['custom_headers' => [
            ['key' => 'List-Unsubscribe', 'value' => '<[[unsubscribe_url]]>, <mailto:unsubscribe@example.com>'],
        ]]);

        $sent = $this->runSendJob($this->message([$this->list], ['mailbox_id' => $this->mailbox->id]), $this->subscriber);

        $this->assertSame('<' . $this->listUrl() . '>, <mailto:unsubscribe@example.com>', $sent['headers']['List-Unsubscribe']);
        $this->assertSame('List-Unsubscribe=One-Click', $sent['headers']['List-Unsubscribe-Post']);
    }

    public function test_configured_header_with_a_foreign_url_is_left_alone(): void
    {
        $this->mailbox->update(['custom_headers' => [
            ['key' => 'list-unsubscribe', 'value' => '<https://crm.example.com/optout>'],
        ]]);

        $sent = $this->runSendJob($this->message([$this->list]), $this->subscriber);

        $this->assertSame('<https://crm.example.com/optout>', $sent['headers']['List-Unsubscribe']);
        $this->assertArrayNotHasKey('List-Unsubscribe-Post', $sent['headers'], 'Their URL may not take a POST');
        $this->assertArrayNotHasKey('list-unsubscribe', $sent['headers'], 'Sent once, spelled the RFC way');
    }

    public function test_switching_the_feature_off_stops_the_automatic_header(): void
    {
        config(['netsendo.email.list_unsubscribe' => false]);

        $sent = $this->runSendJob($this->message([$this->list]), $this->subscriber);

        $this->assertArrayNotHasKey('List-Unsubscribe', $sent['headers']);
    }

    // ---- Endpoint ------------------------------------------------------------

    public function test_the_header_url_unsubscribes_in_one_post(): void
    {
        $message = $this->message([$this->list]);
        $pending = $this->message([$this->list]);
        $pending->queueEntries()->create([
            'subscriber_id' => $this->subscriber->id,
            'status' => MessageQueueEntry::STATUS_PLANNED,
            'planned_at' => now()->addDay(),
        ]);
        Event::fake([SubscriberUnsubscribed::class]);

        $sent = $this->runSendJob($message, $this->subscriber);

        $this->post($this->headerUrl($sent['headers']), ['List-Unsubscribe' => 'One-Click'])->assertOk();

        $this->assertSame('unsubscribed', $this->membershipStatus($this->list));
        $this->assertSame(0, $pending->queueEntries()->count(), 'What was still planned for the list is dropped');
        Event::assertDispatched(SubscriberUnsubscribed::class, fn ($e) => $e->list->id === $this->list->id && $e->subscriber->id === $this->subscriber->id);

        // Idempotent: a second POST (providers retry) changes nothing
        $this->post($this->headerUrl($sent['headers']), ['List-Unsubscribe' => 'One-Click'])->assertOk();
        Event::assertDispatchedTimes(SubscriberUnsubscribed::class, 1);
    }

    public function test_post_without_the_one_click_body_does_not_unsubscribe(): void
    {
        $this->post($this->listUrl(), [])->assertStatus(400);
        $this->post($this->listUrl(), ['List-Unsubscribe' => 'Yes'])->assertStatus(400);

        $this->assertSame('active', $this->membershipStatus($this->list));
    }

    public function test_post_with_a_tampered_url_is_refused(): void
    {
        $url = str_replace("/{$this->list->id}?", "/{$this->otherList->id}?", $this->listUrl());

        $this->post($url, ['List-Unsubscribe' => 'One-Click'])->assertForbidden();
        $this->post("/unsubscribe/{$this->subscriber->id}/{$this->list->id}", ['List-Unsubscribe' => 'One-Click'])->assertForbidden();

        $this->assertSame('active', $this->membershipStatus($this->list));
    }

    public function test_get_still_asks_for_confirmation_first(): void
    {
        $this->get($this->listUrl())->assertOk();

        $this->assertSame('active', $this->membershipStatus($this->list));
    }

    public function test_account_wide_one_click_leaves_every_list(): void
    {
        $this->subscriber->contactLists()->attach($this->otherList->id, ['status' => 'active', 'subscribed_at' => now()]);

        $url = app(PlaceholderService::class)->generateGlobalUnsubscribeLink($this->subscriber);
        $this->post($url, ['List-Unsubscribe' => 'One-Click'])->assertOk();

        $this->assertSame('unsubscribed', $this->membershipStatus($this->list));
        $this->assertSame('unsubscribed', $this->membershipStatus($this->otherList));
    }

    public function test_one_click_urls_need_no_csrf_token(): void
    {
        // Laravel skips CSRF checks in tests, so check the exemption itself
        $middleware = $this->app->make(ValidateCsrfToken::class);
        $inExceptArray = new \ReflectionMethod($middleware, 'inExceptArray');

        $request = Request::create($this->listUrl(), 'POST', ['List-Unsubscribe' => 'One-Click']);
        $this->assertTrue($inExceptArray->invoke($middleware, $request));

        $global = Request::create("/unsubscribe/{$this->subscriber->id}?signature=x", 'POST');
        $this->assertTrue($inExceptArray->invoke($middleware, $global));

        $this->assertFalse($inExceptArray->invoke($middleware, Request::create("/preferences/{$this->subscriber->id}", 'POST')));
    }
}
