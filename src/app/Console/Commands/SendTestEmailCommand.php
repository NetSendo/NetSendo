<?php

namespace App\Console\Commands;

use App\Jobs\SendEmailJob;
use App\Models\Message;
use App\Models\Subscriber;
use Illuminate\Console\Command;

class SendTestEmailCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:send-test {message_id} {subscriber_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test email using SendEmailJob';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $messageId = $this->argument('message_id');
        $subscriberId = $this->argument('subscriber_id');

        $message = Message::find($messageId);
        if (!$message) {
            $this->error("Message with ID {$messageId} not found.");
            return;
        }

        $subscriber = Subscriber::find($subscriberId);
        if (!$subscriber) {
            $this->error("Subscriber with ID {$subscriberId} not found.");
            return;
        }

        $this->info("Dispatching SendEmailJob for Message: {$message->subject} to {$subscriber->email}...");

        SendEmailJob::dispatch($message, $subscriber);

        $this->info("Job dispatched.");
    }
}
