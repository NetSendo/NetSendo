<?php

namespace App\Events;

use App\Models\Subscriber;
use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmailClicked
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $messageId,
        public int $subscriberId,
        public string $url,
        public ?string $ipAddress = null,
        public ?string $userAgent = null
    ) {}

    /**
     * Get the subscriber instance
     */
    public function getSubscriber(): ?Subscriber
    {
        return Subscriber::find($this->subscriberId);
    }

    /**
     * Get the message instance
     */
    public function getMessage(): ?Message
    {
        return Message::find($this->messageId);
    }

    /**
     * Get context for automation processing
     */
    public function getContext(): array
    {
        $subscriber = $this->getSubscriber();
        $message = $this->getMessage();

        return [
            'subscriber_id' => $this->subscriberId,
            'subscriber_email' => $subscriber?->email,
            'message_id' => $this->messageId,
            'message_subject' => $message?->subject,
            'list_id' => $message?->contact_list_id,
            'clicked_url' => $this->url,
            'ip_address' => $this->ipAddress,
        ];
    }
}
