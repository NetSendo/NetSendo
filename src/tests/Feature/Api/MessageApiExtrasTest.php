<?php

namespace Tests\Feature\Api;

use App\Models\ApiKey;
use App\Models\AutomationRule;
use App\Models\ContactList;
use App\Models\Mailbox;
use App\Models\Message;
use App\Models\MessageQueueEntry;
use App\Models\Subscriber;
use App\Models\Tag;
use App\Models\Template;
use App\Models\User;
use App\Services\Mail\MailProviderInterface;
use App\Services\Mail\MailProviderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * The v1 messages API exposes what the web editor offers: triggers synced to
 * automation rules, tags, translations, tracked links, test sends, preview,
 * duplication, activation and recipient counts.
 */
class MessageApiExtrasTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $key;
    private ContactList $list;
    private Mailbox $mailbox;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->key = ApiKey::generate($this->user->id, 'Agent', ['messages:write'])['key'];

        $this->list = ContactList::create([
            'user_id' => $this->user->id,
            'name' => 'Readers',
            'type' => 'email',
            'is_public' => true,
        ]);

        $this->mailbox = Mailbox::create([
            'user_id' => $this->user->id,
            'name' => 'Main',
            'provider' => 'smtp',
            'from_email' => 'hello@example.com',
            'from_name' => 'Tester',
            'is_default' => true,
            'is_active' => true,
            'allowed_types' => ['broadcast', 'autoresponder', 'system'],
            'credentials' => ['host' => 'localhost', 'port' => 1025],
        ]);
    }

    private function api(?string $key = null)
    {
        return $this->withHeaders(['Authorization' => 'Bearer ' . ($key ?? $this->key)]);
    }

    private function createMessage(array $attributes = [], ?User $owner = null): Message
    {
        $owner ??= $this->user;

        $message = Message::create(array_merge([
            'user_id' => $owner->id,
            'channel' => 'email',
            'type' => 'autoresponder',
            'subject' => 'Hi [[first_name]]',
            'content' => '<html><body><p>Hello [[first_name]]</p></body></html>',
            'status' => 'draft',
            'is_active' => false,
            'day' => 0,
            'mailbox_id' => $owner->is($this->user) ? $this->mailbox->id : null,
        ], $attributes));

        if ($owner->is($this->user)) {
            $message->contactLists()->attach($this->list->id);
        }

        return $message;
    }

    private function subscribe(string $email, string $firstName = 'Anna'): Subscriber
    {
        $subscriber = Subscriber::create([
            'user_id' => $this->user->id,
            'email' => $email,
            'first_name' => $firstName,
            'status' => 'active',
            'is_active_global' => true,
        ]);

        $subscriber->contactLists()->attach($this->list->id, [
            'status' => 'active',
            'subscribed_at' => now(),
        ]);

        return $subscriber;
    }

    /**
     * Replace the mail provider with a spy recording what would be sent.
     */
    private function fakeMailProvider(array &$sent): void
    {
        $provider = Mockery::mock(MailProviderInterface::class);
        $provider->shouldReceive('send')->andReturnUsing(function ($to, $toName, $subject, $htmlContent) use (&$sent) {
            $sent[] = compact('to', 'subject', 'htmlContent');
            return true;
        });

        $this->mock(MailProviderService::class, function ($service) use ($provider) {
            $service->shouldReceive('getProvider')->andReturn($provider);
        });
    }

    public function test_store_with_trigger_creates_an_automation_rule_and_returns_editor_fields(): void
    {
        $tag = Tag::create(['user_id' => $this->user->id, 'name' => 'Promo']);

        $response = $this->api()->postJson('/api/v1/messages', [
            'subject' => 'Welcome',
            'channel' => 'email',
            'type' => 'autoresponder',
            'content' => '<p>Hi</p>',
            'day' => 2,
            'contact_list_ids' => [$this->list->id],
            'trigger_type' => 'signup',
            'send_in_subscriber_timezone' => true,
            'tag_ids' => [$tag->id],
            'translations' => [
                ['language' => 'en', 'subject' => 'Welcome EN', 'content' => '<p>Hello</p>'],
            ],
            'tracked_links' => [
                ['url' => 'https://example.com/offer', 'subscribe_to_list_ids' => [$this->list->id]],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.trigger_type', 'signup')
            ->assertJsonPath('data.send_in_subscriber_timezone', true)
            ->assertJsonPath('data.tag_ids', [$tag->id])
            ->assertJsonPath('data.translations.0.subject', 'Welcome EN')
            ->assertJsonPath('data.tracked_links.0.url', 'https://example.com/offer')
            ->assertJsonPath('data.automation_rule.trigger_event', 'subscriber_signup')
            ->assertJsonPath('data.automation_rule.is_active', false);

        $id = $response->json('data.id');
        $rule = AutomationRule::where('trigger_source', 'message')->where('trigger_source_id', $id)->first();

        $this->assertNotNull($rule);
        $this->assertSame($this->list->id, $rule->trigger_config['list_id']);
        $this->assertSame([['type' => 'send_email', 'config' => ['message_id' => $id]]], $rule->actions);
    }

    public function test_update_clearing_the_trigger_removes_the_rule_and_activation_enables_it(): void
    {
        $id = $this->api()->postJson('/api/v1/messages', [
            'subject' => 'Welcome',
            'channel' => 'email',
            'type' => 'autoresponder',
            'content' => '<p>Hi</p>',
            'contact_list_ids' => [$this->list->id],
            'trigger_type' => 'signup',
        ])->json('data.id');

        $this->api()->postJson("/api/v1/campaigns/{$id}/toggle-active", ['is_active' => true])
            ->assertOk()
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.status', 'scheduled');

        $this->assertTrue((bool) AutomationRule::where('trigger_source_id', $id)->value('is_active'));

        $this->api()->putJson("/api/v1/messages/{$id}", ['trigger_type' => null])
            ->assertOk()
            ->assertJsonPath('data.automation_rule', null);

        $this->assertSame(0, AutomationRule::where('trigger_source', 'message')->where('trigger_source_id', $id)->count());
    }

    public function test_update_replaces_tags_and_translations(): void
    {
        $message = $this->createMessage();
        $tagA = Tag::create(['user_id' => $this->user->id, 'name' => 'A']);
        $tagB = Tag::create(['user_id' => $this->user->id, 'name' => 'B']);
        $message->tags()->sync([$tagA->id]);
        $message->translations()->create(['language' => 'de', 'subject' => 'Hallo']);

        $this->api()->putJson("/api/v1/messages/{$message->id}", [
            'tag_ids' => [$tagB->id],
            'translations' => [['language' => 'en', 'subject' => 'Hello']],
        ])->assertOk()
            ->assertJsonPath('data.tag_ids', [$tagB->id])
            ->assertJsonCount(1, 'data.translations')
            ->assertJsonPath('data.translations.0.language', 'en');

        $this->api()->getJson("/api/v1/messages/{$message->id}")
            ->assertOk()
            ->assertJsonPath('data.tag_ids', [$tagB->id])
            ->assertJsonPath('data.translations.0.subject', 'Hello')
            ->assertJsonPath('data.ab_test_config.enabled', false);
    }

    public function test_update_is_active_promotes_a_draft_queue_message(): void
    {
        $message = $this->createMessage();

        $this->api()->putJson("/api/v1/messages/{$message->id}", ['is_active' => true])
            ->assertOk()
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.is_active', true);
    }

    public function test_references_to_another_accounts_records_are_rejected(): void
    {
        $other = User::factory()->create();
        $foreignTag = Tag::create(['user_id' => $other->id, 'name' => 'Theirs']);
        $foreignList = ContactList::create(['user_id' => $other->id, 'name' => 'Theirs', 'type' => 'email']);
        $foreignMessage = $this->createMessage([], $other);
        $message = $this->createMessage();

        $this->api()->putJson("/api/v1/messages/{$message->id}", [
            'tag_ids' => [$foreignTag->id],
            'trigger_type' => 'opened_message',
            'trigger_config' => ['message_id' => $foreignMessage->id],
            'tracked_links' => [['url' => 'https://example.com', 'subscribe_to_list_ids' => [$foreignList->id]]],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['tag_ids.0', 'trigger_config.message_id', 'tracked_links.0.subscribe_to_list_ids.0']);
    }

    public function test_template_id_accepts_public_system_templates_but_not_other_accounts_private_ones(): void
    {
        $other = User::factory()->create();
        $system = Template::create(['user_id' => null, 'name' => 'System', 'content' => '<p>x</p>', 'is_public' => true]);
        $foreign = Template::create(['user_id' => $other->id, 'name' => 'Private', 'content' => '<p>x</p>', 'is_public' => false]);
        $message = $this->createMessage();

        $this->api()->putJson("/api/v1/messages/{$message->id}", ['template_id' => $system->id])
            ->assertOk()
            ->assertJsonPath('data.template_id', $system->id);

        $this->api()->putJson("/api/v1/messages/{$message->id}", ['template_id' => $foreign->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['template_id']);
    }

    public function test_actions_on_another_accounts_message_return_404(): void
    {
        $other = User::factory()->create();
        $foreign = $this->createMessage([], $other);

        foreach (['test', 'preview', 'duplicate', 'toggle-active', 'resend-failed', 'send-to-missed'] as $action) {
            $this->api()->postJson("/api/v1/messages/{$foreign->id}/{$action}", ['email' => 'qa@example.com'])
                ->assertStatus(404);
        }

        $this->api()->getJson("/api/v1/messages/{$foreign->id}/recipients-count")->assertStatus(404);
    }

    public function test_test_send_uses_the_mailbox_and_resolves_placeholders(): void
    {
        $sent = [];
        $this->fakeMailProvider($sent);
        $this->subscribe('anna@example.com', 'Anna');
        $message = $this->createMessage(['preheader' => 'Just for you']);

        $this->api()->postJson("/api/v1/messages/{$message->id}/test", [
            'emails' => ['qa@example.com', 'anna@example.com'],
        ])->assertOk()
            ->assertJsonCount(2, 'data.sent')
            ->assertJsonPath('data.mailbox.id', $this->mailbox->id);

        $this->assertCount(2, $sent);
        $this->assertSame('qa@example.com', $sent[0]['to']);
        // No subscriber with the test address: the first list subscriber personalises it
        $this->assertSame('[TEST] Hi Anna', $sent[0]['subject']);
        $this->assertStringContainsString('Hello Anna', $sent[1]['htmlContent']);
        $this->assertStringContainsString('Just for you', $sent[1]['htmlContent']);

        // A test send never touches the campaign's queue
        $this->assertSame(0, $message->queueEntries()->count());
    }

    public function test_test_send_falls_back_to_sample_data_and_needs_write_permission(): void
    {
        $sent = [];
        $this->fakeMailProvider($sent);
        $message = $this->createMessage();

        $this->api()->postJson("/api/v1/messages/{$message->id}/test", ['email' => 'qa@example.com'])
            ->assertOk()
            ->assertJsonPath('data.sent.0.personalised_with.type', 'sample');
        $this->assertSame('[TEST] Hi Jan', $sent[0]['subject']);

        $readOnly = ApiKey::generate($this->user->id, 'Reader', ['messages:read'])['key'];
        $this->api($readOnly)->postJson("/api/v1/messages/{$message->id}/test", ['email' => 'qa@example.com'])
            ->assertStatus(403);
    }

    public function test_preview_renders_placeholders_for_a_given_subscriber(): void
    {
        $subscriber = $this->subscribe('ola@example.com', 'Ola');
        $message = $this->createMessage(['content' => '<p>Hello [[first_name]] [[unknown_thing]]</p>']);
        $message->translations()->create(['language' => 'en', 'subject' => 'Hey [[first_name]]', 'content' => '<p>EN [[first_name]]</p>']);

        $readOnly = ApiKey::generate($this->user->id, 'Reader', ['messages:read'])['key'];

        $this->api($readOnly)->postJson("/api/v1/messages/{$message->id}/preview", ['subscriber_id' => $subscriber->id])
            ->assertOk()
            ->assertJsonPath('data.subject', 'Hi Ola')
            ->assertJsonPath('data.personalised_with.subscriber_id', $subscriber->id);

        $this->api($readOnly)->getJson("/api/v1/messages/{$message->id}/preview?language=en&subscriber_email=ola@example.com")
            ->assertOk()
            ->assertJsonPath('data.subject', 'Hey Ola')
            ->assertJsonPath('data.html', '<p>EN Ola</p>');

        // Sample data when nobody is on the lists; unknown placeholders are reported
        $empty = $this->createMessage(['content' => '<p>Hello [[first_name]] [[unknown_thing]]</p>']);
        $empty->contactLists()->detach();

        $this->api($readOnly)->postJson("/api/v1/messages/{$empty->id}/preview")
            ->assertOk()
            ->assertJsonPath('data.personalised_with.type', 'sample')
            ->assertJsonPath('data.html', '<p>Hello Jan [[unknown_thing]]</p>')
            ->assertJsonPath('data.unresolved_placeholders', ['[[unknown_thing]]']);

        $other = User::factory()->create();
        $foreignSubscriber = Subscriber::create(['user_id' => $other->id, 'email' => 'x@example.com', 'status' => 'active']);
        $this->api($readOnly)->postJson("/api/v1/messages/{$message->id}/preview", ['subscriber_id' => $foreignSubscriber->id])
            ->assertStatus(404);
    }

    public function test_duplicate_creates_a_draft_copy(): void
    {
        $message = $this->createMessage(['status' => 'scheduled', 'is_active' => true, 'sent_count' => 4, 'trigger_type' => 'signup']);
        $message->translations()->create(['language' => 'en', 'subject' => 'Hello']);

        $response = $this->api()->postJson("/api/v1/messages/{$message->id}/duplicate", ['subject' => 'Copy'])
            ->assertStatus(201)
            ->assertJsonPath('data.subject', 'Copy')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.sent_count', 0)
            ->assertJsonPath('data.trigger_type', 'signup')
            ->assertJsonPath('data.contact_lists.0.id', $this->list->id)
            ->assertJsonPath('data.translations.0.subject', 'Hello');

        $this->assertNotSame($message->id, $response->json('data.id'));
    }

    public function test_toggle_active_rejects_broadcasts_and_toggles_without_a_value(): void
    {
        $broadcast = $this->createMessage(['type' => 'broadcast', 'day' => null]);
        $this->api()->postJson("/api/v1/messages/{$broadcast->id}/toggle-active")->assertStatus(422);

        $message = $this->createMessage(['status' => 'scheduled', 'is_active' => true]);
        $this->api()->postJson("/api/v1/messages/{$message->id}/toggle-active")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    public function test_recipients_count_reports_the_audience(): void
    {
        $this->subscribe('a@example.com');
        $this->subscribe('b@example.com');
        $message = $this->createMessage(['type' => 'broadcast', 'day' => null]);

        $readOnly = ApiKey::generate($this->user->id, 'Reader', ['messages:read'])['key'];

        $this->api($readOnly)->getJson("/api/v1/campaigns/{$message->id}/recipients-count")
            ->assertOk()
            ->assertJsonPath('data.recipients_count', 2)
            ->assertJsonPath('data.queue_stats', null);
    }

    public function test_web_editor_test_send_and_preview_still_work_through_the_shared_service(): void
    {
        $sent = [];
        $this->fakeMailProvider($sent);
        $subscriber = $this->subscribe('anna@example.com', 'Anna');

        $this->actingAs($this->user)->postJson(route('messages.test'), [
            'email' => 'nobody@example.com',
            'subject' => 'Hi [[first_name]]',
            'content' => '<p>[[email]]</p>',
            'mailbox_id' => null,
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertSame('[TEST] Hi Jan', $sent[0]['subject']);
        $this->assertSame('<p>nobody@example.com</p>', $sent[0]['htmlContent']);

        $this->actingAs($this->user)->postJson(route('messages.preview'), [
            'subject' => 'Hi [[first_name]]',
            'content' => '<p>x</p>',
            'preheader' => 'Pre',
            'subscriber_id' => $subscriber->id,
        ])->assertOk()
            ->assertJsonPath('subject', 'Hi Anna')
            ->assertJsonPath('content', "<!-- Preheader text -->\n<div style=\"display: none; max-height: 0; overflow: hidden;\">\n    Pre\n</div>\n<p>x</p>");
    }

    public function test_resend_failed_needs_confirmation(): void
    {
        $subscriber = $this->subscribe('a@example.com');
        $message = $this->createMessage(['type' => 'broadcast', 'day' => null, 'status' => 'sent']);
        $message->queueEntries()->create([
            'subscriber_id' => $subscriber->id,
            'status' => MessageQueueEntry::STATUS_FAILED,
            'planned_at' => now(),
            'error_message' => 'quota',
        ]);

        $this->api()->postJson("/api/v1/messages/{$message->id}/resend-failed")
            ->assertStatus(409)
            ->assertJsonPath('failed_count', 1);

        $this->api()->postJson("/api/v1/messages/{$message->id}/resend-failed", ['confirm' => true])
            ->assertOk()
            ->assertJsonPath('data.requeued', 1)
            ->assertJsonPath('data.status', 'scheduled');

        $this->assertSame(MessageQueueEntry::STATUS_PLANNED, $message->queueEntries()->first()->status);
    }
}
