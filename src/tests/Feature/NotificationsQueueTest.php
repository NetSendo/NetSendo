<?php

namespace Tests\Feature;

use App\Events\SubscriberSignedUp;
use App\Events\SubscriberUnsubscribed;
use App\Listeners\SendNewSubscriberNotification;
use App\Listeners\SendUnsubscribeConfirmation;
use App\Models\ContactList;
use App\Models\Subscriber;
use App\Models\User;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The unsubscribed confirmation and the new-subscriber notification are
 * queued on `notifications`. The bundled worker ran `queue:work` without
 * `--queue`, so it read `default` only and they were never sent; both
 * listeners were also pinned to the `database` connection, a table no worker
 * read when QUEUE_CONNECTION was redis (and the entrypoint empties it).
 */
class NotificationsQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_listeners_use_the_default_connection_and_the_notifications_queue(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $list = ContactList::create(['user_id' => $user->id, 'name' => 'Newsletter', 'type' => 'email']);
        $subscriber = Subscriber::create(['user_id' => $user->id, 'email' => 'reader@example.com', 'status' => 'active']);

        event(new SubscriberUnsubscribed($subscriber, $list, 'one_click'));
        event(new SubscriberSignedUp($subscriber, $list));

        foreach ([SendUnsubscribeConfirmation::class, SendNewSubscriberNotification::class] as $listener) {
            Queue::assertPushedOn('notifications', CallQueuedListener::class, fn ($job) => $job->class === $listener);
            // No pinned connection: the job goes where QUEUE_CONNECTION says
            $this->assertNull((new \ReflectionClass($listener))->getDefaultProperties()['connection'] ?? null, $listener);
        }
    }

    public function test_bundled_workers_listen_to_the_notifications_queue(): void
    {
        $composeFiles = array_filter(
            [base_path('../docker-compose.yml'), base_path('../docker-compose.dev.yml')],
            'file_exists'
        );

        if ($composeFiles === []) {
            $this->markTestSkipped('docker-compose files are not part of this checkout');
        }

        foreach ($composeFiles as $file) {
            preg_match('/^\s*command:\s*php artisan queue:work\b(.*)$/m', file_get_contents($file), $match);

            $this->assertNotEmpty($match, basename($file) . ' has a queue worker');
            $this->assertMatchesRegularExpression('/--queue=(\S*,)?notifications\b/', $match[1], basename($file));
            $this->assertMatchesRegularExpression('/--queue=default,/', $match[1], basename($file) . ': default goes first');
        }
    }
}
