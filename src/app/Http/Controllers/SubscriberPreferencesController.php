<?php

namespace App\Http\Controllers;

use App\Events\SubscriberSignedUp;
use App\Http\Controllers\Concerns\SpeaksSubscriberLanguage;
use App\Models\ContactList;
use App\Models\Subscriber;
use App\Models\SuppressionList;
use App\Models\SystemEmail;
use App\Models\SystemPage;
use App\Services\PlaceholderService;
use App\Services\SystemEmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

/**
 * The public preferences page (/preferences/{subscriber}, signed), where
 * [[unsubscribe]] of a message to several lists and [[manage]] lead.
 *
 * - It lists the public lists the subscriber is on or has left; lists they
 *   never joined are not offered. Mail scanners open the links of incoming
 *   mail, tick every box of a form and follow the confirmation link they get:
 *   on 2026-11-10 that subscribed three people to all 40 lists of an account.
 * - Unticking a list unsubscribes from it at once.
 * - Ticking a list the subscriber left asks for a confirmation from the
 *   inbox, applied with a button on the page the link opens, never by
 *   opening the link alone.
 * - "Unsubscribe me from everything" sends the RFC 8058 one-click request to
 *   the account-wide unsubscribe URL (UnsubscribeController::globalOneClick).
 */
class SubscriberPreferencesController extends Controller
{
    use SpeaksSubscriberLanguage;

    public function __construct(
        protected PlaceholderService $placeholderService,
        protected SystemEmailService $systemEmailService
    ) {}

    /**
     * Show the preferences page. Requires a signed URL.
     */
    public function show(Request $request, Subscriber $subscriber)
    {
        if (!$request->hasValidSignature()) {
            return $this->renderSystemPage('unsubscribe_error', $subscriber, null);
        }

        $userId = $this->getSubscriberUserId($subscriber);

        if (!$userId) {
            return $this->renderSystemPage('unsubscribe_error', $subscriber, null);
        }

        $this->useSubscriberLocale($subscriber);

        $lists = $this->manageableLists($subscriber, $userId);

        return view('public.preferences', [
            'subscriber' => $subscriber,
            'lists' => $lists,
            'subscribedListIds' => $lists->filter(fn ($list) => $this->isActive($list))->pluck('id')->all(),
            'signedUrl' => $request->fullUrl(),
            'unsubscribeAllUrl' => $this->placeholderService->generateGlobalUnsubscribeLink($subscriber),
            'activeListCount' => $subscriber->contactLists()->wherePivot('status', Subscriber::STATUS_ACTIVE)->count(),
            'availableLanguages' => config('netsendo.languages'),
            'currentLanguage' => $subscriber->language,
        ]);
    }

    /**
     * Save the page: unticked lists are left at once, ticked lists the
     * subscriber had left are rejoined after a confirmation email.
     */
    public function update(Request $request, Subscriber $subscriber)
    {
        $this->useSubscriberLocale($subscriber);

        $originalUrl = $request->input('signed_url');
        if (!$originalUrl || !URL::hasValidSignature(request()->create($originalUrl))) {
            return back()->withErrors(['error' => __('preferences.invalid_link')]);
        }

        $validated = $request->validate([
            'lists' => 'nullable|array',
            'lists.*' => 'integer',
            'language' => 'nullable|string|max:5',
        ]);

        // Update subscriber language preference immediately (no confirmation needed)
        if (array_key_exists('language', $validated)) {
            $subscriber->update(['language' => $validated['language']]);
            $this->useSubscriberLocale($subscriber);
        }

        $userId = $this->getSubscriberUserId($subscriber);

        if (!$userId) {
            return $this->renderSystemPage('unsubscribe_error', $subscriber, null);
        }

        $selected = array_map('intval', $validated['lists'] ?? []);
        $lists = $this->manageableLists($subscriber, $userId);

        $leave = $lists->filter(fn ($list) => $this->isActive($list) && !in_array($list->id, $selected, true));
        $rejoin = $lists->filter(fn ($list) => !$this->isActive($list) && in_array($list->id, $selected, true));

        // Leaving takes nothing more than this page: the link was sent to this address
        foreach ($leave as $list) {
            $subscriber->unsubscribeFromList($list->id, 'preferences');
        }

        Log::info('Subscriber preferences saved', [
            'subscriber_id' => $subscriber->id,
            'left_list_ids' => $leave->pluck('id')->values()->all(),
            'rejoin_requested_list_ids' => $rejoin->pluck('id')->values()->all(),
            'ignored_list_ids' => array_values(array_diff($selected, $lists->pluck('id')->all())),
        ]);

        if ($rejoin->isEmpty()) {
            return $this->renderSystemPage('preference_update_success', $subscriber, null);
        }

        $confirmUrl = URL::signedRoute('subscriber.preferences.confirm', [
            'subscriber' => $subscriber->id,
            'changes' => base64_encode(json_encode([
                'rejoin' => $rejoin->pluck('id')->values()->all(),
                'requested_at' => now()->toISOString(),
            ])),
        ], now()->addHours(24));

        $this->sendConfirmationEmail($subscriber, $confirmUrl);

        return $this->renderSystemPage('preference_confirm_sent', $subscriber, null, [
            'message' => __('preferences.confirm_sent'),
        ]);
    }

    /**
     * The link of the confirmation email: shows what will change and a button.
     * Opening the link changes nothing — scanners that fetch every link of an
     * email must not rejoin anybody.
     */
    public function confirm(Request $request, Subscriber $subscriber)
    {
        $plan = $this->confirmationPlan($request, $subscriber);

        if (!$plan) {
            return $this->renderSystemPage('unsubscribe_error', $subscriber, null);
        }

        if ($plan['rejoin']->isEmpty() && $plan['leave']->isEmpty()) {
            return $this->renderSystemPage('preference_update_success', $subscriber, null);
        }

        $this->useSubscriberLocale($subscriber);

        return view('public.preferences-confirm', [
            'subscriber' => $subscriber,
            'rejoin' => $plan['rejoin'],
            'leave' => $plan['leave'],
            // The raw query string: the signature covers the parameters in their original order
            'action' => $request->url() . '?' . $request->server->get('QUERY_STRING'),
        ]);
    }

    /**
     * The button of the confirmation page: applies the changes.
     */
    public function applyConfirmed(Request $request, Subscriber $subscriber)
    {
        $plan = $this->confirmationPlan($request, $subscriber);

        if (!$plan) {
            return $this->renderSystemPage('unsubscribe_error', $subscriber, null);
        }

        foreach ($plan['leave'] as $list) {
            $subscriber->unsubscribeFromList($list->id, 'preferences');
        }

        foreach ($plan['rejoin'] as $list) {
            $subscriber->contactLists()->updateExistingPivot($list->id, [
                'status' => Subscriber::STATUS_ACTIVE,
                'subscribed_at' => now(),
                'resubscribed_at' => now(),
                'unsubscribed_at' => null,
            ]);

            event(new SubscriberSignedUp($subscriber, $list, null, 'preferences_reactivation'));
        }

        Log::info('Subscriber preferences confirmed', [
            'subscriber_id' => $subscriber->id,
            'rejoined_list_ids' => $plan['rejoin']->pluck('id')->all(),
            'left_list_ids' => $plan['leave']->pluck('id')->all(),
        ]);

        return $this->renderSystemPage('preference_update_success', $subscriber, null);
    }

    /**
     * What a signed confirmation link asks for, limited to the lists the
     * subscriber can change on the page: ['rejoin' => lists, 'leave' => lists],
     * or null for an invalid link.
     */
    protected function confirmationPlan(Request $request, Subscriber $subscriber): ?array
    {
        if (!$request->hasValidSignature()) {
            return null;
        }

        $changes = json_decode((string) base64_decode((string) $request->query('changes'), true), true);
        $userId = $this->getSubscriberUserId($subscriber);

        if (!is_array($changes) || !$userId) {
            return null;
        }

        $lists = $this->manageableLists($subscriber, $userId);

        if (isset($changes['rejoin']) && is_array($changes['rejoin'])) {
            $ids = array_map('intval', $changes['rejoin']);

            return [
                'rejoin' => $lists->filter(fn ($list) => !$this->isActive($list) && in_array($list->id, $ids, true))->values(),
                'leave' => collect(),
            ];
        }

        // Links emailed before 2.9.0 carry the whole selection of the page: lists
        // left out are left, ticked lists the subscriber had left are rejoined, and
        // lists they never joined are not joined.
        if (isset($changes['selected_lists']) && is_array($changes['selected_lists'])) {
            $ids = array_map('intval', $changes['selected_lists']);

            return [
                'rejoin' => $lists->filter(fn ($list) => !$this->isActive($list) && in_array($list->id, $ids, true))->values(),
                'leave' => $lists->filter(fn ($list) => $this->isActive($list) && !in_array($list->id, $ids, true))->values(),
            ];
        }

        return null;
    }

    /**
     * The lists a subscriber can change on the page: public email lists of
     * their account they are on or have left (bounced and unconfirmed
     * memberships are not theirs to change here).
     */
    protected function manageableLists(Subscriber $subscriber, int $userId): Collection
    {
        return $subscriber->contactLists()
            ->where('contact_lists.user_id', $userId)
            ->where('contact_lists.is_public', true)
            ->where('contact_lists.type', 'email')
            ->wherePivotIn('status', [Subscriber::STATUS_ACTIVE, Subscriber::STATUS_UNSUBSCRIBED])
            ->orderBy('contact_lists.name')
            ->get();
    }

    protected function isActive(ContactList $list): bool
    {
        return $list->pivot?->status === Subscriber::STATUS_ACTIVE;
    }

    /**
     * Get the user ID that owns this subscriber (from any of their lists).
     */
    protected function getSubscriberUserId(Subscriber $subscriber): ?int
    {
        return $subscriber->user_id ?? $subscriber->contactLists()->first()?->user_id;
    }

    /**
     * Send the email that confirms rejoining lists.
     */
    protected function sendConfirmationEmail(Subscriber $subscriber, string $confirmUrl): void
    {
        // Get first list to find user context
        $list = $subscriber->contactLists()->first();

        if (!$list) {
            Log::warning('Cannot send preference confirmation - no list found', [
                'subscriber_id' => $subscriber->id,
            ]);
            return;
        }

        // Use system email template
        $systemEmail = SystemEmail::getBySlug('preference_confirm', $list->id);

        if (!$systemEmail || !$systemEmail->is_active) {
            $subject = __('preferences.email.subject');
            $content = '<h2>' . e(__('preferences.email.heading')) . '</h2>'
                . '<p>' . e(__('preferences.email.body')) . '</p>'
                . '<p><a href="[[confirm-link]]">' . e(__('preferences.email.link')) . '</a></p>'
                . '<p>' . e(__('preferences.email.ignore')) . '</p>';
        } else {
            $subject = $systemEmail->subject;
            $content = $systemEmail->content;
        }

        // Replace placeholders
        $subject = $this->placeholderService->replacePlaceholders($subject, $subscriber);
        $content = $this->placeholderService->replacePlaceholders($content, $subscriber);
        $content = str_replace('[[confirm-link]]', $confirmUrl, $content);

        // Send the email
        $this->systemEmailService->sendToSubscriber($subscriber, $list, $subject, $content);
    }

    /**
     * Render a system page with placeholder replacement.
     */
    protected function renderSystemPage(string $slug, ?Subscriber $subscriber, ?ContactList $list, array $extraPlaceholders = [])
    {
        $listId = $list?->id;
        $systemPage = SystemPage::getBySlug($slug, $listId);

        if (!$systemPage) {
            $systemPage = SystemPage::getBySlug($slug, null);
        }

        // Fallback pages for new slugs
        if (!$systemPage) {
            $fallbackContent = match($slug) {
                'preference_confirm_sent' => '<h1>Check Your Email</h1><p>We have sent you a confirmation email. Please click the link in the email to apply your changes.</p>',
                'preference_update_success' => '<h1>Preferences Updated</h1><p>Your subscription preferences have been successfully updated.</p>',
                'deletion_confirm_sent' => '<h1>Check Your Email</h1><p>We have sent you a confirmation email. Please click the link in the email to permanently delete your data.</p><p><strong>This action cannot be undone.</strong></p>',
                'deletion_success' => '<h1>Data Deleted</h1><p>All your personal data has been permanently deleted from our system.</p><p>You will no longer receive any communications from us.</p>',
                default => '<h1>Page not found</h1>',
            };
            $title = match($slug) {
                'preference_confirm_sent' => 'Confirmation Email Sent',
                'preference_update_success' => 'Preferences Updated',
                'deletion_confirm_sent' => 'Deletion Confirmation',
                'deletion_success' => 'Data Deleted',
                default => 'NetSendo',
            };
        } else {
            $title = $systemPage->title ?? 'NetSendo';
            $fallbackContent = $systemPage->content ?? '<h1>Page not found</h1>';
        }

        $content = $systemPage?->content ?? $fallbackContent;

        // Replace placeholders (only if subscriber exists)
        if ($subscriber) {
            $title = $this->placeholderService->replacePlaceholders($title, $subscriber);
            $content = $this->placeholderService->replacePlaceholders($content, $subscriber);
        }

        if ($list) {
            $content = str_replace('[[list-name]]', $list->name, $content);
            $title = str_replace('[[list-name]]', $list->name, $title);
        }

        // Extra placeholders
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

    /**
     * Request data deletion (GDPR Right to be Forgotten).
     * Sends confirmation email before actual deletion.
     */
    public function requestDeletion(Request $request, Subscriber $subscriber)
    {
        // Validate the original signature
        $originalUrl = $request->input('signed_url');
        if (!$originalUrl || !URL::hasValidSignature(request()->create($originalUrl))) {
            return back()->withErrors(['error' => 'Invalid or expired link.']);
        }

        // Generate deletion confirmation link (valid for 24 hours)
        $confirmUrl = URL::signedRoute('subscriber.data.delete.confirm', [
            'subscriber' => $subscriber->id,
        ], now()->addHours(24));

        // Send confirmation email
        $this->sendDeletionConfirmationEmail($subscriber, $confirmUrl);

        Log::info('GDPR deletion request initiated', [
            'subscriber_id' => $subscriber->id,
            'email' => $subscriber->email,
        ]);

        return $this->renderSystemPage('deletion_confirm_sent', $subscriber, null);
    }

    /**
     * Confirm and process data deletion (GDPR Right to be Forgotten).
     * This permanently deletes the subscriber and adds email to suppression list.
     */
    public function confirmDeletion(Request $request, Subscriber $subscriber)
    {
        if (!$request->hasValidSignature()) {
            return $this->renderSystemPage('unsubscribe_error', $subscriber, null);
        }

        $userId = $subscriber->user_id;
        $email = $subscriber->email;

        try {
            DB::transaction(function () use ($subscriber, $userId, $email) {
                // 1. Add to suppression list (to prevent re-adding)
                SuppressionList::suppress($userId, $email, 'gdpr_erasure');

                // 2. Delete all related data
                // - Field values (custom fields)
                $subscriber->fieldValues()->delete();

                // - Tags
                $subscriber->tags()->detach();

                // - Contact list relationships
                $subscriber->contactLists()->detach();

                // - Email tracking data (if exists)
                if (method_exists($subscriber, 'emailEvents')) {
                    $subscriber->emailEvents()->delete();
                }

                // - Funnel subscriptions (if exists)
                if (method_exists($subscriber, 'funnelSubscriptions')) {
                    $subscriber->funnelSubscriptions()->delete();
                }

                // 3. Hard delete the subscriber (not soft delete)
                $subscriber->forceDelete();
            });

            Log::info('GDPR deletion completed', [
                'email' => $email,
                'user_id' => $userId,
            ]);

            return $this->renderSystemPage('deletion_success', null, null, [
                'email' => $email,
            ]);

        } catch (\Exception $e) {
            Log::error('GDPR deletion failed', [
                'subscriber_id' => $subscriber->id ?? 'deleted',
                'error' => $e->getMessage(),
            ]);
            return $this->renderSystemPage('unsubscribe_error', $subscriber, null);
        }
    }

    /**
     * Send deletion confirmation email.
     */
    protected function sendDeletionConfirmationEmail(Subscriber $subscriber, string $confirmUrl): void
    {
        $list = $subscriber->contactLists()->first();

        // Use system email template or fallback
        $systemEmail = SystemEmail::getBySlug('deletion_confirm', $list?->id);

        if (!$systemEmail || !$systemEmail->is_active) {
            $subject = 'Confirm Data Deletion Request';
            $content = '<h2>Confirm Data Deletion</h2>
                <p>We received a request to permanently delete all your data from our system.</p>
                <p><strong>This action cannot be undone.</strong> All your subscription data, preferences, and history will be permanently removed.</p>
                <p>Click the link below to confirm deletion:</p>
                <p><a href="[[confirm-link]]">Yes, delete all my data</a></p>
                <p>If you did not request this, you can ignore this email.</p>';
        } else {
            $subject = $systemEmail->subject;
            $content = $systemEmail->content;
        }

        // Replace placeholders
        $subject = $this->placeholderService->replacePlaceholders($subject, $subscriber);
        $content = $this->placeholderService->replacePlaceholders($content, $subscriber);
        $content = str_replace('[[confirm-link]]', $confirmUrl, $content);

        // Send the email
        if ($list) {
            $this->systemEmailService->sendToSubscriber($subscriber, $list, $subject, $content);
        } else {
            // Fallback: find any mailbox from user
            $userId = $subscriber->user_id;
            $firstList = ContactList::where('user_id', $userId)->first();
            if ($firstList) {
                $this->systemEmailService->sendToSubscriber($subscriber, $firstList, $subject, $content);
            }
        }
    }
}
