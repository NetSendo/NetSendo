<?php

namespace Tests\Feature;

use App\Events\SubscriberUnsubscribed;
use App\Models\ContactList;
use App\Models\Subscriber;
use App\Models\User;
use App\Services\PlaceholderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Concerns\RecordsSentMail;
use Tests\TestCase;

/**
 * A sleeping NetSendo Cloud instance (NETSENDO_DORMANT=true): the subscription has ended and
 * the data is kept. Unsubscribe links in messages sent before keep working, nothing is sent,
 * and every other page, the API and sign-in show the "instance asleep" page (503).
 */
class DormantModeTest extends TestCase
{
    use RefreshDatabase;
    use RecordsSentMail;

    private ContactList $list;
    private Subscriber $subscriber;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
        $this->recordSentMail();

        $user = User::factory()->create();
        $this->list = ContactList::create(['user_id' => $user->id, 'name' => 'Newsletter', 'type' => 'email']);
        $this->subscriber = Subscriber::create([
            'user_id' => $user->id,
            'email' => 'reader@example.com',
            'status' => 'active',
            'is_active_global' => true,
        ]);
        $this->subscriber->contactLists()->attach($this->list->id, ['status' => 'active', 'subscribed_at' => now()]);

        config(['netsendo.dormant' => true]);
    }

    private function listUrl(): string
    {
        return app(PlaceholderService::class)->generateUnsubscribeLink($this->subscriber, $this->list);
    }

    private function membershipStatus(): ?string
    {
        return $this->subscriber->contactLists()->where('contact_lists.id', $this->list->id)->first()?->pivot->status;
    }

    public function test_pages_and_sign_in_show_the_asleep_page(): void
    {
        $this->get('/login')
            ->assertStatus(503)
            ->assertSee('This NetSendo instance is asleep')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertHeader('Retry-After', '86400');

        $this->withHeader('Accept-Language', 'pl-PL,pl;q=0.9')->get('/')
            ->assertStatus(503)
            ->assertSee('Ta instancja NetSendo jest uśpiona');

        $this->post('/login', ['email' => 'owner@example.com', 'password' => 'secret'])->assertStatus(503);
    }

    public function test_the_api_answers_json_503(): void
    {
        $this->getJson('/api/v1/lists')
            ->assertStatus(503)
            ->assertJson(['error' => 'instance_asleep']);
    }

    public function test_the_health_check_still_answers(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_an_unsubscribe_link_asks_on_the_page_instead_of_emailing(): void
    {
        $this->get($this->listUrl())
            ->assertOk()
            ->assertSee('name="List-Unsubscribe"', false)
            ->assertSee('value="One-Click"', false);

        $this->assertSame('active', $this->membershipStatus(), 'Not before the person confirms');
        $this->assertSame([], $this->sentMail, 'No confirmation email from a sleeping instance');
    }

    public function test_confirming_unsubscribes_without_automations_webhooks_or_email(): void
    {
        $this->post($this->listUrl(), ['List-Unsubscribe' => 'One-Click'])->assertOk();

        $this->assertSame('unsubscribed', $this->membershipStatus());
        $this->assertFalse(Event::hasListeners(SubscriberUnsubscribed::class));
        $this->assertSame([], $this->sentMail);
        Http::assertNothingSent();
    }

    public function test_an_unsigned_unsubscribe_link_is_refused(): void
    {
        $this->get("/unsubscribe/{$this->subscriber->id}/{$this->list->id}")->assertForbidden();
        $this->post("/unsubscribe/{$this->subscriber->id}/{$this->list->id}", ['List-Unsubscribe' => 'One-Click'])->assertForbidden();

        $this->assertSame('active', $this->membershipStatus());
    }

    public function test_the_preferences_page_unsubscribes_from_every_list_in_one_click(): void
    {
        $other = ContactList::create(['user_id' => $this->list->user_id, 'name' => 'Offers', 'type' => 'email']);
        $this->subscriber->contactLists()->attach($other->id, ['status' => 'active', 'subscribed_at' => now()]);

        // [[unsubscribe]] in a message sent to several lists, and [[manage]], lead here
        $placeholders = app(PlaceholderService::class);
        $preferencesUrl = $placeholders->generateUnsubscribeLink($this->subscriber);
        $this->assertSame($placeholders->generateManageLink($this->subscriber), $preferencesUrl);

        $page = $this->get($preferencesUrl)
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('You will be unsubscribed from all their lists.')
            ->assertSee('name="List-Unsubscribe"', false)
            ->assertSee('value="One-Click"', false);

        $this->assertSame(1, preg_match('#<form method="POST" action="([^"]+)">#', $page->getContent(), $form));
        $action = html_entity_decode($form[1]);
        $this->assertMatchesRegularExpression("#/unsubscribe/{$this->subscriber->id}\?signature=[0-9a-f]{64}$#", $action);
        $this->assertSame($placeholders->generateGlobalUnsubscribeLink($this->subscriber), $action);
        $this->assertSame(['active', 'active'], $this->statuses(), 'Not before the person confirms');

        $this->post($action, ['List-Unsubscribe' => 'One-Click'])->assertOk();

        $this->assertSame(['unsubscribed', 'unsubscribed'], $this->statuses());
        $this->assertSame([], $this->sentMail);
        Http::assertNothingSent();
    }

    public function test_an_unsigned_or_tampered_preferences_link_is_refused(): void
    {
        $this->get("/preferences/{$this->subscriber->id}")
            ->assertForbidden()
            ->assertSee('This link is not valid')
            ->assertDontSee('name="List-Unsubscribe"', false);

        $tampered = preg_replace('#signature=[0-9a-f]#', 'signature=x', app(PlaceholderService::class)->generateManageLink($this->subscriber));
        $this->get($tampered)->assertForbidden();

        $another = Subscriber::create(['user_id' => $this->list->user_id, 'email' => 'other@example.com', 'status' => 'active']);
        $forAnother = str_replace("/preferences/{$another->id}?", "/preferences/{$this->subscriber->id}?", app(PlaceholderService::class)->generateManageLink($another));
        $this->get($forAnother)->assertForbidden();

        $this->assertSame('active', $this->membershipStatus());
    }

    public function test_the_rest_of_the_preferences_pages_stays_asleep(): void
    {
        $preferencesUrl = app(PlaceholderService::class)->generateManageLink($this->subscriber);
        $id = $this->subscriber->id;

        $this->post($preferencesUrl, ['lists' => []])->assertStatus(503);
        $this->get("/preferences/{$id}/confirm")->assertStatus(503);
        $this->post("/preferences/{$id}/delete")->assertStatus(503);

        $this->assertSame('active', $this->membershipStatus());
        $this->assertSame([], $this->sentMail);
    }

    /** @return list<string> statuses of every list membership, by list id */
    private function statuses(): array
    {
        return $this->subscriber->contactLists()->orderBy('contact_lists.id')->get()->pluck('pivot.status')->all();
    }

    public function test_an_awake_instance_is_not_affected(): void
    {
        config(['netsendo.dormant' => false]);

        $this->get('/login')->assertOk();
        $this->assertTrue(Event::hasListeners(SubscriberUnsubscribed::class));
    }
}
