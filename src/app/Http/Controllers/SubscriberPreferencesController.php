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
        ]);

        $selectedListIds = $validated['lists'] ?? [];

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

        Log::debug('Subscriber preferences confirm - parsing changes', [
            'subscriber_id' => $subscriber->id,
            'raw_selected_lists' => $pendingChanges['selected_lists'],
            'typed_selected_lists' => $selectedListIds,
        ]);

        // Get subscriber's user to find their public lists
        $userId = $this->getSubscriberUserId($subscriber);

        Log::debug('Subscriber preferences confirm - user identification', [
            'subscriber_id' => $subscriber->id,
            'resolved_user_id' => $userId,
            'subscriber_user_id' => $subscriber->user_id,
        ]);

        if (!$userId) {
            return $this->renderSystemPage('unsubscribe_error', $subscriber, null);
        }

        // Get all public lists for this user
        $publicListIds = ContactList::where('user_id', $userId)
            ->public()
            ->email()
            ->pluck('id')
            ->toArray();

        Log::debug('Subscriber preferences confirm - public lists found', [
            'subscriber_id' => $subscriber->id,
            'user_id' => $userId,
            'public_list_ids' => $publicListIds,
        ]);

        try {
            // Apply changes only to public lists
            foreach ($publicListIds as $listId) {
                $isSelected = in_array($listId, $selectedListIds);
                $existingPivot = $subscriber->contactLists()
                    ->where('contact_lists.id', $listId)
                    ->first();

                Log::debug('Subscriber preferences confirm - processing list', [
                    'subscriber_id' => $subscriber->id,
                    'list_id' => $listId,
                    'is_selected' => $isSelected,
                    'existing_pivot_status' => $existingPivot?->pivot->status ?? 'none',
                ]);

                if ($isSelected) {
                    // Subscribe to the list
                    if (!$existingPivot) {
                        $subscriber->contactLists()->attach($listId, [
                            'status' => 'active',
                            'subscribed_at' => now(),
                            'source' => 'preferences',
                        ]);
                        Log::debug('Subscriber preferences confirm - attached new list', [
                            'subscriber_id' => $subscriber->id,
                            'list_id' => $listId,
                        ]);
                    } elseif ($existingPivot->pivot->status !== 'active') {
                        $subscriber->contactLists()->updateExistingPivot($listId, [
                            'status' => 'active',
                            'subscribed_at' => now(),
                        ]);
                        Log::debug('Subscriber preferences confirm - reactivated list', [
                            'subscriber_id' => $subscriber->id,
                            'list_id' => $listId,
                        ]);
                    }
                } else {
                    // Unsubscribe from the list
                    if ($existingPivot && $existingPivot->pivot->status === 'active') {
                        $subscriber->contactLists()->updateExistingPivot($listId, [
                            'status' => 'unsubscribed',
                            'unsubscribed_at' => now(),
                        ]);
                        Log::debug('Subscriber preferences confirm - unsubscribed from list', [
                            'subscriber_id' => $subscriber->id,
                            'list_id' => $listId,
                        ]);

                        // Dispatch event for automations
                        $list = ContactList::find($listId);
                        if ($list) {
                            event(new SubscriberUnsubscribed($subscriber, $list, 'preferences'));
                        }
                    }
                }
            }

            Log::info('Subscriber preferences updated', [
                'subscriber_id' => $subscriber->id,
                'selected_lists' => $selectedListIds,
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
                'preference_confirm_sent' => '<h1>Check Your Email</h1><p>We have sent you a confirmation email. Please click the link in the email to apply your changes.</p>',
                'preference_update_success' => '<h1>Preferences Updated</h1><p>Your subscription preferences have been successfully updated.</p>',
                default => '<h1>Page not found</h1>',
            };
            $title = match($slug) {
                'preference_confirm_sent' => 'Confirmation Email Sent',
                'preference_update_success' => 'Preferences Updated',
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
}
