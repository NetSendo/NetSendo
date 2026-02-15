<?php

namespace App\Http\Controllers;

use App\Events\SubscriberUnsubscribed;
use App\Models\ContactList;
use App\Models\Subscriber;
use App\Models\SuppressionList;
use App\Models\SystemEmail;
use App\Models\SystemPage;
use App\Services\PlaceholderService;
use App\Services\SystemEmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

/**
 * Handles public subscriber preferences page where users can manage their subscriptions.
 * Users can see all public lists and choose which ones to subscribe to or unsubscribe from.
 */
class SubscriberPreferencesController extends Controller
{
    public function __construct(
        protected PlaceholderService $placeholderService,
        protected SystemEmailService $systemEmailService
    ) {}

    /**
     * Show the preferences page with all public lists.
     * Requires signed URL for security.
     */
    public function show(Request $request, Subscriber $subscriber)
    {
        if (!$request->hasValidSignature()) {
            return $this->renderSystemPage('unsubscribe_error', $subscriber, null);
        }

        // Get subscriber's user to find their public lists
        $userId = $this->getSubscriberUserId($subscriber);

        if (!$userId) {
            return $this->renderSystemPage('unsubscribe_error', $subscriber, null);
        }

        // Get all public lists for this user
        $publicLists = ContactList::where('user_id', $userId)
            ->public()
            ->email()
            ->orderBy('name')
            ->get();

        // Get subscriber's current subscriptions with their status
        $subscribedListIds = $subscriber->contactLists()
            ->wherePivot('status', 'active')
            ->pluck('contact_lists.id')
            ->toArray();

        return view('public.preferences', [
            'subscriber' => $subscriber,
            'lists' => $publicLists,
            'subscribedListIds' => $subscribedListIds,
            'signedUrl' => $request->fullUrl(),
            'availableLanguages' => config('netsendo.languages'),
            'currentLanguage' => $subscriber->language,
        ]);
    }

    /**
     * Process preferences update request.
     * Does NOT apply changes immediately - sends confirmation email instead.
     */
    public function update(Request $request, Subscriber $subscriber)
    {
        // Validate the original signature
        $originalUrl = $request->input('signed_url');
        if (!$originalUrl || !URL::hasValidSignature(request()->create($originalUrl))) {
            return back()->withErrors(['error' => 'Invalid or expired link.']);
        }

        $validated = $request->validate([
            'lists' => 'nullable|array',
            'lists.*' => 'integer|exists:contact_lists,id',
            'language' => 'nullable|string|max:5',
        ]);

        $selectedListIds = $validated['lists'] ?? [];

        // Update subscriber language preference immediately (no confirmation needed)
        if (array_key_exists('language', $validated)) {
            $subscriber->update(['language' => $validated['language']]);
        }

        // Store the pending changes in session (or temporary storage)
        $pendingChanges = [
            'selected_lists' => $selectedListIds,
            'requested_at' => now()->toISOString(),
        ];

        // Generate confirmation link
        $confirmUrl = URL::signedRoute('subscriber.preferences.confirm', [
            'subscriber' => $subscriber->id,
            'changes' => base64_encode(json_encode($pendingChanges)),
        ], now()->addHours(24));

        // Send confirmation email
        $this->sendConfirmationEmail($subscriber, $pendingChanges, $confirmUrl);

        return $this->renderSystemPage('preference_confirm_sent', $subscriber, null, [
            'message' => 'We have sent you a confirmation email. Please click the link in the email to apply your changes.',
        ]);
    }

    /**
     * Confirm and apply the preference changes.
     * Called from signed URL in confirmation email.
     */
    public function confirm(Request $request, Subscriber $subscriber)
    {
        if (!$request->hasValidSignature()) {
            return $this->renderSystemPage('unsubscribe_error', $subscriber, null);
        }

        $changesEncoded = $request->query('changes');
        if (!$changesEncoded) {
            return $this->renderSystemPage('unsubscribe_error', $subscriber, null);
        }

        $pendingChanges = json_decode(base64_decode($changesEncoded), true);
        if (!$pendingChanges || !isset($pendingChanges['selected_lists'])) {
            return $this->renderSystemPage('unsubscribe_error', $subscriber, null);
        }

        $selectedListIds = array_map('intval', $pendingChanges['selected_lists']);

        // Get subscriber's user to find their public lists
        $userId = $this->getSubscriberUserId($subscriber);

        Log::info('Subscriber preferences confirm - starting', [
            'subscriber_id' => $subscriber->id,
            'selected_lists' => $selectedListIds,
            'resolved_user_id' => $userId,
            'subscriber_user_id' => $subscriber->user_id,
        ]);

        if (!$userId) {
            Log::error('Subscriber preferences confirm - no user ID found', [
                'subscriber_id' => $subscriber->id,
            ]);
            return $this->renderSystemPage('unsubscribe_error', $subscriber, null);
        }

        // Get all public lists for this user
        $publicListIds = ContactList::where('user_id', $userId)
            ->public()
            ->email()
            ->pluck('id')
            ->toArray();

        Log::info('Subscriber preferences confirm - public lists', [
            'subscriber_id' => $subscriber->id,
            'public_list_ids' => $publicListIds,
            'public_list_count' => count($publicListIds),
        ]);

        if (empty($publicListIds)) {
            Log::warning('Subscriber preferences confirm - NO PUBLIC LISTS FOUND! This is likely the issue.', [
                'subscriber_id' => $subscriber->id,
                'user_id' => $userId,
            ]);
        }

        try {
            $changesApplied = [];

            // Apply changes only to public lists
            foreach ($publicListIds as $listId) {
                $isSelected = in_array($listId, $selectedListIds, true);
                $existingPivot = $subscriber->contactLists()
                    ->where('contact_lists.id', $listId)
                    ->first();

                $pivotStatus = $existingPivot?->pivot->status ?? 'none';

                Log::info('Subscriber preferences confirm - processing list', [
                    'subscriber_id' => $subscriber->id,
                    'list_id' => $listId,
                    'is_selected' => $isSelected,
                    'existing_pivot_status' => $pivotStatus,
                ]);

                if ($isSelected) {
                    // Subscribe to the list
                    if (!$existingPivot) {
                        $subscriber->contactLists()->attach($listId, [
                            'status' => 'active',
                            'subscribed_at' => now(),
                            'source' => 'preferences',
                        ]);
                        $changesApplied[] = ['list_id' => $listId, 'action' => 'attached'];
                        Log::info('Subscriber preferences confirm - attached new list', [
                            'subscriber_id' => $subscriber->id,
                            'list_id' => $listId,
                        ]);
                    } elseif ($existingPivot->pivot->status !== 'active') {
                        $result = $subscriber->contactLists()->updateExistingPivot($listId, [
                            'status' => 'active',
                            'subscribed_at' => now(),
                        ]);
                        $changesApplied[] = ['list_id' => $listId, 'action' => 'reactivated', 'result' => $result];
                        Log::info('Subscriber preferences confirm - reactivated list', [
                            'subscriber_id' => $subscriber->id,
                            'list_id' => $listId,
                            'update_result' => $result,
                        ]);
                    }
                } else {
                    // Unsubscribe from the list
                    if ($existingPivot && $existingPivot->pivot->status === 'active') {
                        $result = $subscriber->contactLists()->updateExistingPivot($listId, [
                            'status' => 'unsubscribed',
                            'unsubscribed_at' => now(),
                        ]);
                        $changesApplied[] = ['list_id' => $listId, 'action' => 'unsubscribed', 'result' => $result];
                        Log::info('Subscriber preferences confirm - unsubscribed from list', [
                            'subscriber_id' => $subscriber->id,
                            'list_id' => $listId,
                            'update_result' => $result,
                        ]);

                        // Dispatch event for automations
                        $list = ContactList::find($listId);
                        if ($list) {
                            event(new SubscriberUnsubscribed($subscriber, $list, 'preferences'));
                        }
                    }
                }
            }

            // VERIFICATION: Check actual DB state after updates
            $subscriber->load('contactLists');
            $finalActiveListIds = $subscriber->contactLists()
                ->wherePivot('status', 'active')
                ->pluck('contact_lists.id')
                ->toArray();

            Log::info('Subscriber preferences updated - FINAL STATE', [
                'subscriber_id' => $subscriber->id,
                'requested_selected_lists' => $selectedListIds,
                'final_active_list_ids' => $finalActiveListIds,
                'changes_applied' => $changesApplied,
                'public_lists_processed' => $publicListIds,
            ]);

            return $this->renderSystemPage('preference_update_success', $subscriber, null);

        } catch (\Exception $e) {
            Log::error('Failed to update subscriber preferences', [
                'subscriber_id' => $subscriber->id,
                'error' => $e->getMessage(),
            ]);
            return $this->renderSystemPage('unsubscribe_error', $subscriber, null);
        }
    }

    /**
     * Get the user ID that owns this subscriber (from any of their lists).
     */
    protected function getSubscriberUserId(Subscriber $subscriber): ?int
    {
        return $subscriber->user_id ?? $subscriber->contactLists()->first()?->user_id;
    }

    /**
     * Send confirmation email for preference changes.
     */
    protected function sendConfirmationEmail(Subscriber $subscriber, array $pendingChanges, string $confirmUrl): void
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
            // Fallback to default template
            $subject = 'Confirm Your Subscription Preferences';
            $content = '<h2>Confirm Your Changes</h2><p>Click the link below to confirm your subscription preferences:</p><p><a href="[[confirm-link]]">Confirm changes</a></p><p>If you did not request this change, you can ignore this email.</p>';
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
