<?php

namespace App\Services;

use App\Mail\SystemEmailMailable;
use App\Models\ContactList;
use App\Models\Mailbox;
use App\Models\Subscriber;
use App\Models\SystemEmail;
use App\Services\Mail\MailProviderService;
use App\Services\Mail\SystemMailService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Service for sending system emails.
 * Centralized handling of all automatic email notifications.
 */
class SystemEmailService
{
    protected SystemMailService $systemMailService;
    protected MailProviderService $mailProviderService;

    public function __construct(
        SystemMailService $systemMailService,
        MailProviderService $mailProviderService
    ) {
        $this->systemMailService = $systemMailService;
        $this->mailProviderService = $mailProviderService;
    }

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

        // Configure the mailbox for sending
        $mailbox = $this->getMailboxForList($list);
        $provider = null;

        if (!$mailbox) {
            Log::warning("No mailbox configured for system email: {$slug}", [
                'list_id' => $list->id,
                'user_id' => $list->user_id,
            ]);

            // Try to use SystemMailService as fallback
            if (!$this->systemMailService->prepare()) {
                Log::error("No mail configuration available for system email: {$slug}");
                return false;
            }
        } else {
            // Get provider for the mailbox
            try {
                $provider = $this->mailProviderService->getProvider($mailbox);
            } catch (\Exception $e) {
                Log::error("Failed to get mail provider for custom email: {$slug}", [
                    'mailbox_id' => $mailbox->id,
                    'error' => $e->getMessage(),
                ]);
                // Fallback to system mail service
                if (!$this->systemMailService->prepare()) {
                    return false;
                }
            }
        }

        try {
            // Create mailable
            $mailable = new SystemEmailMailable($subscriber, $list, $systemEmail, $extraData);

            if ($provider) {
                // Render content and send via provider
                $htmlContent = $mailable->render();
                $subject = $mailable->envelope()->subject;

                $provider->send(
                    $recipient,
                    $recipient, // Use email as name if name not available
                    $subject,
                    $htmlContent
                );
            } else {
                // Send via Laravel default (fallback)
                Mail::to($recipient)->send($mailable);
            }

            Log::info("System email sent: {$slug}", [
                'subscriber_id' => $subscriber->id,
                'list_id' => $list->id,
                'recipient' => $recipient,
                'mailbox_id' => $mailbox?->id,
                'mailbox_name' => $mailbox?->name,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to send system email: {$slug}", [
                'subscriber_id' => $subscriber->id,
                'list_id' => $list->id,
                'mailbox_id' => $mailbox?->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send a custom email to subscriber (not from template).
     * Used when content is already prepared (e.g., confirmation emails with dynamic links).
     */
    public function sendToSubscriber(
        Subscriber $subscriber,
        ContactList $list,
        string $subject,
        string $content,
        ?string $recipientOverride = null
    ): bool {
        // Determine recipient
        $recipient = $recipientOverride ?? $subscriber->email;

        if (!$recipient) {
            Log::warning("No recipient email for custom email", [
                'subscriber_id' => $subscriber->id,
            ]);
            return false;
        }

        // Configure the mailbox for sending
        $mailbox = $this->getMailboxForList($list);
        $provider = null;

        if (!$mailbox) {
            Log::warning("No mailbox configured for custom email", [
                'list_id' => $list->id,
                'user_id' => $list->user_id,
            ]);

            // Try to use SystemMailService as fallback
            if (!$this->systemMailService->prepare()) {
                Log::error("No mail configuration available for custom email");
                return false;
            }
        } else {
            // Get provider for the mailbox
            try {
                $provider = $this->mailProviderService->getProvider($mailbox);
            } catch (\Exception $e) {
                 Log::error("Failed to get mail provider for custom email", [
                    'mailbox_id' => $mailbox->id,
                    'error' => $e->getMessage(),
                ]);
                // Fallback to system mail service
                if (!$this->systemMailService->prepare()) {
                    return false;
                }
            }
        }

        try {
            $mailable = new \App\Mail\GenericHtmlMailable($subject, $content);

            if ($provider) {
                // Render content and send via provider
                $htmlContent = $mailable->render();

                $provider->send(
                    $recipient,
                    $recipient, // Use email as name
                    $subject,
                    $htmlContent
                );
            } else {
                Mail::to($recipient)->send($mailable);
            }

            Log::info("Custom email sent to subscriber", [
                'subscriber_id' => $subscriber->id,
                'list_id' => $list->id,
                'recipient' => $recipient,
                'mailbox_id' => $mailbox?->id,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to send custom email", [
                'subscriber_id' => $subscriber->id,
                'list_id' => $list->id,
                'mailbox_id' => $mailbox?->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get the appropriate mailbox for a contact list.
     * Priority: List's default mailbox -> User's default mailbox -> Any active system mailbox
     */
    protected function getMailboxForList(ContactList $list): ?Mailbox
    {
        // 1. Try list's default mailbox
        if ($list->default_mailbox_id) {
            $mailbox = Mailbox::where('id', $list->default_mailbox_id)
                ->where('is_active', true)
                ->whereJsonContains('allowed_types', Mailbox::TYPE_SYSTEM)
                ->first();

            if ($mailbox) {
                return $mailbox;
            }
        }

        // 2. Try user's default mailbox that can send system emails
        $userDefault = Mailbox::where('user_id', $list->user_id)
            ->where('is_active', true)
            ->where('is_default', true)
            ->whereJsonContains('allowed_types', Mailbox::TYPE_SYSTEM)
            ->first();

        if ($userDefault) {
            return $userDefault;
        }

        // 3. Try any active mailbox of the user that can send system emails
        return Mailbox::where('user_id', $list->user_id)
            ->where('is_active', true)
            ->whereJsonContains('allowed_types', Mailbox::TYPE_SYSTEM)
            ->first();
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
     * Send welcome email to new subscriber (no double opt-in).
     */
    public function sendSubscriptionWelcome(Subscriber $subscriber, ContactList $list): bool
    {
        return $this->send('subscription_welcome', $subscriber, $list);
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
