<?php

namespace App\Services;

use App\Mail\SystemEmailMailable;
use App\Models\ContactList;
use App\Models\Subscriber;
use App\Models\SystemEmail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Service for sending system emails.
 * Centralized handling of all automatic email notifications.
 */
class SystemEmailService
{
    /**
     * Send a system email by slug.
     */
    public function send(
        string $slug,
        Subscriber $subscriber,
        ContactList $list,
        array $extraData = [],
        ?string $recipientOverride = null
    ): bool {
        // Get the system email template
        $systemEmail = SystemEmail::getBySlug($slug, $list->id);

        if (!$systemEmail) {
            Log::warning("System email not found: {$slug}", [
                'list_id' => $list->id,
            ]);
            return false;
        }

        // Determine recipient
        $recipient = $recipientOverride ?? $subscriber->email;

        if (!$recipient) {
            Log::warning("No recipient email for system email: {$slug}", [
                'subscriber_id' => $subscriber->id,
            ]);
            return false;
        }

        try {
            Mail::to($recipient)->send(
                new SystemEmailMailable($subscriber, $list, $systemEmail, $extraData)
            );

            Log::info("System email sent: {$slug}", [
                'subscriber_id' => $subscriber->id,
                'list_id' => $list->id,
                'recipient' => $recipient,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to send system email: {$slug}", [
                'subscriber_id' => $subscriber->id,
                'list_id' => $list->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send signup confirmation email (double opt-in).
     */
    public function sendSignupConfirmation(Subscriber $subscriber, ContactList $list): bool
    {
        $activationLink = $this->generateActivationLink($subscriber, $list);

        return $this->send('signup_confirmation', $subscriber, $list, [
            'activation-link' => $activationLink,
        ]);
    }

    /**
     * Send activation confirmation email (after user confirms).
     */
    public function sendActivationConfirmation(Subscriber $subscriber, ContactList $list): bool
    {
        return $this->send('activation_confirmation', $subscriber, $list);
    }

    /**
     * Send email to already active subscriber trying to re-subscribe.
     */
    public function sendAlreadyActiveNotification(Subscriber $subscriber, ContactList $list): bool
    {
        return $this->send('already_active_resubscribe', $subscriber, $list);
    }

    /**
     * Send email to inactive subscriber who re-subscribed.
     */
    public function sendInactiveResubscribeNotification(Subscriber $subscriber, ContactList $list): bool
    {
        return $this->send('inactive_resubscribe', $subscriber, $list);
    }

    /**
     * Send unsubscribe confirmation email.
     */
    public function sendUnsubscribeConfirmation(Subscriber $subscriber, ContactList $list): bool
    {
        $resubscribeLink = $this->generateResubscribeLink($subscriber, $list);

        return $this->send('unsubscribed_confirmation', $subscriber, $list, [
            'resubscribe-link' => $resubscribeLink,
        ]);
    }

    /**
     * Send unsubscribe request email (confirm unsubscribe).
     */
    public function sendUnsubscribeRequest(Subscriber $subscriber, ContactList $list): bool
    {
        $unsubscribeLink = URL::signedRoute('subscriber.unsubscribe.confirm', [
            'subscriber' => $subscriber->id,
            'list' => $list->id,
        ], now()->addDays(7));

        return $this->send('unsubscribe_request', $subscriber, $list, [
            'unsubscribe-link' => $unsubscribeLink,
        ]);
    }

    /**
     * Send data edit access email.
     */
    public function sendDataEditAccess(Subscriber $subscriber, ContactList $list): bool
    {
        $editLink = $this->generateEditLink($subscriber);

        return $this->send('data_edit_access', $subscriber, $list, [
            'edit-link' => $editLink,
        ]);
    }

    /**
     * Generate signed activation link.
     */
    protected function generateActivationLink(Subscriber $subscriber, ContactList $list): string
    {
        return URL::signedRoute('subscriber.activate', [
            'subscriber' => $subscriber->id,
            'list' => $list->id,
        ], now()->addDays(7));
    }

    /**
     * Generate resubscribe link.
     */
    protected function generateResubscribeLink(Subscriber $subscriber, ContactList $list): string
    {
        return URL::signedRoute('subscriber.resubscribe', [
            'subscriber' => $subscriber->id,
            'list' => $list->id,
        ], now()->addDays(30));
    }

    /**
     * Generate edit profile link.
     */
    protected function generateEditLink(Subscriber $subscriber): string
    {
        return URL::signedRoute('subscriber.edit-profile', [
            'subscriber' => $subscriber->id,
        ], now()->addHours(24));
    }
}
