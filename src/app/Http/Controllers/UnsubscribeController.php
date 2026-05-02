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
     * Global unsubscribe - shows a confirmation page with the option
     * to unsubscribe from ALL lists at once (Master Opt-Out).
     *
     * Requires a signed URL generated via [[unsubscribe_global]] placeholder.
     */
    public function globalUnsubscribe(Request $request, Subscriber $subscriber)
    {
        if (!$request->hasValidSignature()) {
            return $this->renderSystemPage('unsubscribe_error', $subscriber, null);
        }

        // Generate the signed URL for the actual global unsubscribe action
        $globalProcessUrl = URL::signedRoute('subscriber.unsubscribe.global.process', [
            'subscriber' => $subscriber->id,
        ], now()->addHours(24));

        // Generate the manage/preferences link as an alternative
        $manageUrl = $this->placeholderService->generateManageLink($subscriber);

        // Count how many active lists the subscriber has
        $activeListCount = $subscriber->contactLists()
            ->wherePivot('status', 'active')
            ->count();

        Log::info('Global unsubscribe page shown', [
            'subscriber_id' => $subscriber->id,
            'active_list_count' => $activeListCount,
        ]);

        return $this->renderSystemPage('unsubscribe_global_confirm', $subscriber, null, [
            'global_unsubscribe_url' => $globalProcessUrl,
            'manage_url' => $manageUrl,
            'active_list_count' => (string) $activeListCount,
        ]);
    }

    /**
     * Process the global unsubscribe action.
     *
     * Unsubscribes the subscriber from ALL lists belonging to their user/account.
     * Dispatches SubscriberUnsubscribed event for each list to trigger automations.
     */
    public function globalUnsubscribeProcess(Request $request, Subscriber $subscriber)
    {
        if (!$request->hasValidSignature()) {
            return $this->renderSystemPage('unsubscribe_error', $subscriber, null);
        }

        try {
            // Get all lists the subscriber is actively subscribed to
            $activeLists = $subscriber->contactLists()
                ->wherePivot('status', 'active')
                ->get();

            if ($activeLists->isEmpty()) {
                Log::info('Global unsubscribe - subscriber has no active lists', [
                    'subscriber_id' => $subscriber->id,
                ]);
                return $this->renderSystemPage('unsubscribe_global_success', $subscriber, null, [
                    'unsubscribed_count' => '0',
                ]);
            }

            $unsubscribedCount = 0;

            foreach ($activeLists as $list) {
                $subscriber->contactLists()->updateExistingPivot($list->id, [
                    'status' => 'unsubscribed',
                    'unsubscribed_at' => now(),
                ]);

                // Dispatch event for each list — ensures automations are triggered per-list
                event(new SubscriberUnsubscribed($subscriber, $list, 'global_unsubscribe'));

                $unsubscribedCount++;

                Log::info('Global unsubscribe - removed from list', [
                    'subscriber_id' => $subscriber->id,
                    'list_id' => $list->id,
                    'list_name' => $list->name,
                ]);
            }

            Log::info('Global unsubscribe completed', [
                'subscriber_id' => $subscriber->id,
                'subscriber_email' => $subscriber->email,
                'unsubscribed_count' => $unsubscribedCount,
            ]);

            return $this->renderSystemPage('unsubscribe_global_success', $subscriber, null, [
                'unsubscribed_count' => (string) $unsubscribedCount,
            ]);

        } catch (\Exception $e) {
            Log::error('Global unsubscribe failed', [
                'subscriber_id' => $subscriber->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->renderSystemPage('unsubscribe_error', $subscriber, null);
        }
    }

    /**
     * Send unsubscribe confirmation email.
     */
    protected function sendUnsubscribeConfirmationEmail(Subscriber $subscriber, ContactList $list, string $confirmUrl): void
    {
        Log::info('Sending unsubscribe confirmation email - START', [
            'subscriber_id' => $subscriber->id,
            'subscriber_email' => $subscriber->email,
            'list_id' => $list->id,
            'list_name' => $list->name,
        ]);

        // Use system email template
        $systemEmail = SystemEmail::getBySlug('unsubscribe_request', $list->id);

        if (!$systemEmail || !$systemEmail->is_active) {
            Log::info('Unsubscribe confirmation - using fallback template', [
                'subscriber_id' => $subscriber->id,
                'system_email_found' => $systemEmail !== null,
                'is_active' => $systemEmail?->is_active ?? false,
            ]);
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

        Log::info('Sending unsubscribe confirmation email - calling sendToSubscriber', [
            'subscriber_id' => $subscriber->id,
            'subject' => $subject,
        ]);

        // Send the email
        $this->systemEmailService->sendToSubscriber($subscriber, $list, $subject, $content);

        Log::info('Sending unsubscribe confirmation email - COMPLETED', [
            'subscriber_id' => $subscriber->id,
        ]);
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
                'unsubscribe_global_confirm' => '<h1>Unsubscribe from All Lists</h1>'
                    . '<p>You are currently subscribed to <strong>[[active_list_count]]</strong> mailing list(s).</p>'
                    . '<p>Choose how you would like to proceed:</p>'
                    . '<div class="btn-group">'
                    . '<a href="[[global_unsubscribe_url]]" class="btn btn-danger">Unsubscribe from Everything</a>'
                    . '<a href="[[manage_url]]" class="btn btn-outline">Manage My Preferences</a>'
                    . '</div>'
                    . '<p class="text-muted" style="margin-top: 24px; font-size: 13px;">Choosing "Unsubscribe from Everything" will remove you from all mailing lists immediately. You can use "Manage My Preferences" to selectively choose which lists to keep.</p>',
                'unsubscribe_global_success' => '<h1>You Have Been Unsubscribed</h1>'
                    . '<p>You have been successfully unsubscribed from all <strong>[[unsubscribed_count]]</strong> mailing list(s).</p>'
                    . '<p>You will no longer receive any emails from us.</p>'
                    . '<p class="text-muted" style="margin-top: 16px; font-size: 13px;">If this was a mistake, you can re-subscribe at any time through our website.</p>',
                default => '<h1>Page not found</h1>',
            };
            $title = match($slug) {
                'unsubscribe_confirm_sent' => 'Confirmation Email Sent',
                'unsubscribe_global_confirm' => 'Unsubscribe from All Lists',
                'unsubscribe_global_success' => 'Unsubscribed Successfully',
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
            str_contains($slug, 'global_confirm') => 'warning',
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
