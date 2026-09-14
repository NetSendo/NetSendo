<?php

namespace App\Services\Mail;

use App\Events\CrmContactReplied;
use App\Models\CrmContact;
use App\Models\Mailbox;
use App\Models\Subscriber;
use Illuminate\Support\Facades\Log;

/**
 * Records replies read from a mailbox's reply inbox as CRM contact replies.
 */
class ReplyProcessingService
{
    /**
     * The mailbox owner's subscriber who sent this email as a reply, or null when
     * it is not one. Needs only the email's headers.
     */
    public function findReplyingSubscriber(Mailbox $mailbox, InboundEmail $email): ?Subscriber
    {
        if ($email->fromEmail === '' || !$email->isReply() || $email->isAutomatic()) {
            return null;
        }

        // A message the mailbox account sent itself
        if (in_array($email->fromEmail, $this->ownAddresses($mailbox), true)) {
            return null;
        }

        return Subscriber::where('user_id', $mailbox->user_id)
            ->where('email', $email->fromEmail)
            ->first();
    }

    /**
     * Record the email as a reply from the subscriber's CRM contact, adding the
     * subscriber to the CRM first when needed.
     *
     * @return CrmContact|null the contact, or null when the email is not recorded
     */
    public function process(Mailbox $mailbox, InboundEmail $email): ?CrmContact
    {
        $subscriber = $this->findReplyingSubscriber($mailbox, $email);
        if (!$subscriber) {
            return null;
        }

        $contact = CrmContact::withTrashed()->where('subscriber_id', $subscriber->id)->first();

        // Removed from the CRM on purpose: a reply does not bring the contact back
        if ($contact?->trashed()) {
            Log::debug("Reply mailbox {$mailbox->id}: reply from a deleted CRM contact skipped", [
                'contact_id' => $contact->id,
            ]);
            return null;
        }

        $messageId = $email->messageId();

        // Already recorded, e.g. read again after the folder was recreated or from a second mailbox
        if ($contact && $contact->activities()
            ->where('type', 'email_reply')
            ->where('metadata->message_id', $messageId)
            ->exists()) {
            return null;
        }

        $contact ??= CrmContact::createFromSubscriber($subscriber, ['source' => 'email_reply']);
        $excerpt = $email->excerpt();

        event(new CrmContactReplied(
            contact: $contact,
            subscriber: $subscriber,
            channel: 'email',
            messageId: $messageId,
            subject: $email->subject !== '' ? $email->subject : null,
            excerpt: $excerpt !== '' ? $excerpt : null,
        ));

        Log::info("Reply mailbox {$mailbox->id}: reply recorded", [
            'contact_id' => $contact->id,
            'subscriber_id' => $subscriber->id,
        ]);

        return $contact;
    }

    /**
     * @return list<string>
     */
    private function ownAddresses(Mailbox $mailbox): array
    {
        return array_values(array_map('strtolower', array_filter([
            $mailbox->from_email,
            $mailbox->reply_to,
            $mailbox->getDecryptedReplyCredentials()['username'] ?? null,
        ])));
    }
}
