<?php

namespace App\Http\Controllers;

use App\Events\SubscriberUnsubscribed;
use App\Models\ContactList;
use App\Models\Subscriber;
use App\Models\SystemEmail;
use App\Models\SystemPage;
use App\Services\PlaceholderService;
use App\Services\SystemEmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

/**
 * Handles public unsubscribe flow with email confirmation.
 *
 * Flow:
 * 1. User clicks unsubscribe link in email
 * 2. System sends confirmation email with signed link
 * 3. User clicks confirmation link to actually unsubscribe
 */
class UnsubscribeController extends Controller
{
    public function __construct(
        protected PlaceholderService $placeholderService,
        protected SystemEmailService $systemEmailService
    ) {}

    /**
     * Handle unsubscribe request - sends confirmation email instead of instant unsubscribe.
     *
     * This is the first step - user clicked unsubscribe link in a campaign email.
     * We don't unsubscribe immediately, instead we send a confirmation email.
     */
    public function confirm(Request $request, Subscriber $subscriber, ContactList $list)
    {
        if (!$request->hasValidSignature()) {
            return $this->renderSystemPage('unsubscribe_error', $subscriber, $list);
        }

        try {
            // Check if subscriber is actually in this list
            $pivot = $subscriber->contactLists()->where('contact_lists.id', $list->id)->first();

            if (!$pivot) {
                // Subscriber not in this list
                Log::info('Subscriber not in list during unsubscribe request', [
                    'subscriber_id' => $subscriber->id,
                    'list_id' => $list->id,
                ]);
                return $this->renderSystemPage('unsubscribe_success', $subscriber, $list);
            }

            $currentStatus = $pivot->pivot->status ?? 'active';

            if ($currentStatus === 'unsubscribed') {
                // Already unsubscribed
                return $this->renderSystemPage('unsubscribe_success', $subscriber, $list);
            }

            // Generate confirmation link (valid for 24 hours)
            $confirmUrl = URL::signedRoute('subscriber.unsubscribe.process', [
                'subscriber' => $subscriber->id,
                'list' => $list->id,
            ], now()->addHours(24));

            // Send confirmation email
            $this->sendUnsubscribeConfirmationEmail($subscriber, $list, $confirmUrl);

            Log::info('Unsubscribe confirmation email sent', [
                'subscriber_id' => $subscriber->id,
                'list_id' => $list->id,
            ]);

            // Show page informing user to check their email
            return $this->renderSystemPage('unsubscribe_confirm_sent', $subscriber, $list);

        } catch (\Exception $e) {
            Log::error('Failed to send unsubscribe confirmation', [
                'subscriber_id' => $subscriber->id,
                'list_id' => $list->id,
                'error' => $e->getMessage(),
            ]);
            return $this->renderSystemPage('unsubscribe_error', $subscriber, $list);
        }
    }

    /**
     * Process the actual unsubscribe action.
     *
     * This is called when user clicks the confirmation link from the email.
     * This is the only place where unsubscribe actually happens.
     */
    public function process(Request $request, Subscriber $subscriber, ContactList $list)
    {
        Log::debug('Unsubscribe process started', [
            'subscriber_id' => $subscriber->id,
            'subscriber_email' => $subscriber->email,
            'list_id' => $list->id,
            'list_name' => $list->name,
            'has_valid_signature' => $request->hasValidSignature(),
        ]);

        if (!$request->hasValidSignature()) {
            Log::warning('Invalid unsubscribe confirmation signature', [
                'subscriber_id' => $subscriber->id,
                'list_id' => $list->id,
            ]);
            return $this->renderSystemPage('unsubscribe_error', $subscriber, $list);
        }

        try {
            // Check if subscriber is actually in this list
            $pivot = $subscriber->contactLists()->where('contact_lists.id', $list->id)->first();

            Log::debug('Unsubscribe process - pivot check', [
                'subscriber_id' => $subscriber->id,
                'list_id' => $list->id,
                'pivot_found' => $pivot !== null,
                'pivot_status' => $pivot?->pivot->status ?? 'none',
            ]);

            if (!$pivot) {
                Log::info('Subscriber not in list during unsubscribe process', [
                    'subscriber_id' => $subscriber->id,
                    'list_id' => $list->id,
                ]);
                return $this->renderSystemPage('unsubscribe_success', $subscriber, $list);
            }

            $currentStatus = $pivot->pivot->status ?? 'active';

            if ($currentStatus === 'unsubscribed') {
                // Already unsubscribed
                Log::debug('Subscriber already unsubscribed', [
                    'subscriber_id' => $subscriber->id,
                    'list_id' => $list->id,
                ]);
                return $this->renderSystemPage('unsubscribe_success', $subscriber, $list);
            }

            // Update subscription status - this is the actual unsubscribe
            $updateResult = $subscriber->contactLists()->updateExistingPivot($list->id, [
                'status' => 'unsubscribed',
                'unsubscribed_at' => now(),
            ]);

            Log::info('Subscriber unsubscribed from list (confirmed)', [
                'subscriber_id' => $subscriber->id,
                'list_id' => $list->id,
                'update_result' => $updateResult,
            ]);

            // Dispatch SubscriberUnsubscribed event for automations and email notification
            event(new SubscriberUnsubscribed($subscriber, $list, 'link_confirmed'));

            return $this->renderSystemPage('unsubscribe_success', $subscriber, $list);

        } catch (\Exception $e) {
            Log::error('Unsubscribe process failed', [
                'subscriber_id' => $subscriber->id,
                'list_id' => $list->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->renderSystemPage('unsubscribe_error', $subscriber, $list);
        }
    }

    /**
     * Global unsubscribe - redirects to preferences page.
     *
     * When user clicks unsubscribe from a message sent to multiple lists,
     * they are redirected to the preferences page where they can manage all lists.
     */
    public function globalUnsubscribe(Request $request, Subscriber $subscriber)
    {
        if (!$request->hasValidSignature()) {
            return $this->renderSystemPage('unsubscribe_error', $subscriber, null);
        }

        // Redirect to preferences page with new signed URL
        $preferencesUrl = $this->placeholderService->generateManageLink($subscriber);

        return redirect($preferencesUrl);
    }

    /**
     * Send unsubscribe confirmation email.
     */
    protected function sendUnsubscribeConfirmationEmail(Subscriber $subscriber, ContactList $list, string $confirmUrl): void
    {
        // Use system email template
        $systemEmail = SystemEmail::getBySlug('unsubscribe_request', $list->id);

        if (!$systemEmail || !$systemEmail->is_active) {
            // Fallback to default template
            $subject = 'Confirm Your Unsubscribe Request';
            $content = '<h2>Confirm Unsubscribe</h2><p>We received a request to unsubscribe from <strong>[[list-name]]</strong>.</p><p>Click the link below to confirm:</p><p><a href="[[unsubscribe-link]]">Yes, unsubscribe me</a></p><p>If you did not request this, you can ignore this email.</p>';
        } else {
            $subject = $systemEmail->subject;
            $content = $systemEmail->content;
        }

        // Replace placeholders
        $subject = $this->placeholderService->replacePlaceholders($subject, $subscriber);
        $content = $this->placeholderService->replacePlaceholders($content, $subscriber);
        $content = str_replace('[[list-name]]', $list->name, $content);
        $subject = str_replace('[[list-name]]', $list->name, $subject);
        $content = str_replace('[[unsubscribe-link]]', $confirmUrl, $content);

        // Send the email
        $this->systemEmailService->sendToSubscriber($subscriber, $list, $subject, $content);
    }

    /**
     * Render a system page with placeholder replacement.
     */
    protected function renderSystemPage(string $slug, Subscriber $subscriber, ?ContactList $list, array $extraPlaceholders = [])
    {
        $listId = $list?->id;
        $systemPage = SystemPage::getBySlug($slug, $listId);

        if (!$systemPage) {
            $systemPage = SystemPage::getBySlug($slug, null);
        }

        // Fallback pages for new slugs
        if (!$systemPage) {
            $fallbackContent = match($slug) {
                'unsubscribe_confirm_sent' => '<h1>Check Your Email</h1><p>We have sent you a confirmation email. Please click the link in the email to unsubscribe from this list.</p>',
                default => '<h1>Page not found</h1>',
            };
            $title = match($slug) {
                'unsubscribe_confirm_sent' => 'Confirmation Email Sent',
                default => 'NetSendo',
            };
        } else {
            $title = $systemPage->title ?? 'NetSendo';
            $fallbackContent = $systemPage->content ?? '<h1>Page not found</h1>';
        }

        $content = $systemPage?->content ?? $fallbackContent;

        // Replace placeholders
        $title = $this->placeholderService->replacePlaceholders($title, $subscriber);
        $content = $this->placeholderService->replacePlaceholders($content, $subscriber);

        if ($list) {
            $content = str_replace('[[list-name]]', $list->name, $content);
            $title = str_replace('[[list-name]]', $list->name, $title);
        }

        // Extra placeholders (like unsubscribe-link)
        foreach ($extraPlaceholders as $key => $value) {
            $content = str_replace("[[{$key}]]", $value, $content);
        }

        // Determine icon based on slug
        $icon = match (true) {
            str_contains($slug, 'success') => 'success',
            str_contains($slug, 'error') => 'error',
            str_contains($slug, 'confirm') || str_contains($slug, 'sent') => 'info',
            default => 'info',
        };

        return view('forms.system-page', [
            'title' => $title,
            'content' => $content,
            'icon' => $icon,
            'systemPage' => $systemPage,
        ]);
    }
}
