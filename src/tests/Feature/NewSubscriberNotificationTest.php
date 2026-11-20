<?php

namespace Tests\Feature;

use App\Events\SubscriberSignedUp;
use App\Listeners\SendNewSubscriberNotification;
use App\Models\ContactList;
use App\Models\Subscriber;
use App\Models\User;
use App\Services\SystemEmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Mockery;
use Tests\TestCase;

/**
 * A migration of 15 000 contacts through the CSV import sent the list owner
 * 15 000 "New subscriber on list" emails, burnt the SMTP quota and got the
 * domain greylisted at Gmail. An empty "Notification email" field did not help
 * either: it falls back to the account's own address. Bulk operations now
 * never notify per contact, and the account has a switch for the rest.
 */
class NewSubscriberNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private ContactList $list;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['email' => 'owner@example.com']);
        $this->list = $this->makeList('Newsletter');
    }

    private function makeList(string $name): ContactList
    {
        return ContactList::create([
            'user_id' => $this->user->id,
            'name' => $name,
            'type' => 'email',
            'is_public' => true,
            'settings' => [],
            'webhook_events' => [],
            'sync_settings' => [],
            'required_fields' => [],
        ]);
    }

    private function makeSubscriber(string $email): Subscriber
    {
        return Subscriber::create([
            'user_id' => $this->user->id,
            'email' => $email,
            'is_active_global' => true,
        ]);
    }

    /**
     * Run the listener with a mocked mail service; returns the recipients the
     * listener tried to notify.
     */
    private function runListener(SubscriberSignedUp $event): array
    {
        $sent = [];
        $service = Mockery::mock(SystemEmailService::class);
        $service->shouldReceive('send')
            ->andReturnUsing(function ($slug, $subscriber, $list, $extra, $recipient) use (&$sent) {
                $sent[] = [$slug, $recipient];

                return true;
            });

        (new SendNewSubscriberNotification($service))->handle($event);

        return $sent;
    }

    public function test_csv_import_does_not_notify_the_admin_per_contact(): void
    {
        Event::fake([SubscriberSignedUp::class]);

        $path = tempnam(sys_get_temp_dir(), 'imp');
        File::put($path, "email,first_name\njeden@example.com,Jan\ndwa@example.com,Anna\n");

        $this->actingAs($this->user)->post(route('subscribers.import.store'), [
            'file' => new UploadedFile($path, 'migration.csv', 'text/csv', null, true),
            'contact_list_id' => $this->list->id,
        ])->assertSessionHasNoErrors();

        // Sequences and automations still start for the new contacts...
        Event::assertDispatchedTimes(SubscriberSignedUp::class, 2);
        // ...but none of the events asks for an admin email
        Event::assertNotDispatched(SubscriberSignedUp::class, fn ($e) => $e->notifyAdmin);
    }

    public function test_bulk_add_to_list_does_not_notify_the_admin(): void
    {
        Event::fake([SubscriberSignedUp::class]);

        $subscriber = $this->makeSubscriber('reader@example.com');

        $this->actingAs($this->user)->post(route('subscribers.bulk-add-to-list'), [
            'ids' => [$subscriber->id],
            'target_list_id' => $this->list->id,
        ]);

        Event::assertDispatched(SubscriberSignedUp::class, fn ($e) => $e->notifyAdmin === false);
    }

    public function test_a_single_signup_still_notifies_the_account_email_when_no_address_is_set(): void
    {
        $subscriber = $this->makeSubscriber('reader@example.com');

        $sent = $this->runListener(new SubscriberSignedUp($subscriber, $this->list, null, 'form'));

        $this->assertSame([['new_subscriber_notification', 'owner@example.com']], $sent);
    }

    public function test_a_bulk_event_sends_nothing(): void
    {
        $subscriber = $this->makeSubscriber('reader@example.com');

        $sent = $this->runListener(new SubscriberSignedUp($subscriber, $this->list, null, 'import', notifyAdmin: false));

        $this->assertSame([], $sent);
    }

    public function test_the_account_switch_turns_the_notification_off_for_every_list(): void
    {
        $this->user->update(['settings' => ['subscription' => ['notify_admin' => false]]]);
        $this->list->update(['settings' => ['subscription' => ['notification_email' => 'sales@example.com']]]);

        $subscriber = $this->makeSubscriber('reader@example.com');

        $sent = $this->runListener(new SubscriberSignedUp($subscriber, $this->list->fresh(), null, 'form'));

        $this->assertSame([], $sent);
    }

    public function test_the_switch_is_saved_from_the_defaults_page(): void
    {
        $this->actingAs($this->user)->post(route('defaults.store'), [
            'settings' => [
                'subscription' => [
                    'double_optin' => false,
                    'notification_email' => '',
                    'notify_admin' => false,
                    'delete_unconfirmed' => false,
                ],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertFalse($this->user->fresh()->settings['subscription']['notify_admin']);
    }

    public function test_the_switch_can_be_set_over_the_api(): void
    {
        $this->assertArrayHasKey(
            'settings.subscription.notify_admin',
            \App\Services\Lists\ListSettingsSchema::rules($this->user->id, 'settings', false)
        );
        // A list has no switch of its own — it is turned off per list in System emails
        $this->assertArrayNotHasKey(
            'settings.subscription.notify_admin',
            \App\Services\Lists\ListSettingsSchema::rules($this->user->id)
        );
    }
}
