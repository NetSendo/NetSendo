<?php

namespace App\Http\Controllers;

use App\Events\SubscriberUnsubscribed;
use App\Models\ContactList;
use App\Models\Subscriber;
use App\Models\SystemPage;
use App\Services\PlaceholderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Handles public unsubscribe flow with proper system pages.
 */
class UnsubscribeController extends Controller
{
    public function __construct(
        protected PlaceholderService $placeholderService
    ) {}

    /**
     * Show confirmation page before unsubscribing (optional step).
     */
    public function confirm(Request $request, Subscriber $subscriber, ContactList $list)
    {
        if (!$request->hasValidSignature()) {
            return $this->renderSystemPage('unsubscribe_error', $subscriber, $list);
        }

        // If confirmation is required (could be a list setting)
        $requireConfirmation = $list->settings['unsubscribe_confirmation'] ?? false;

        if (!$requireConfirmation) {
            // Skip confirmation, proceed to unsubscribe directly
            return $this->process($request, $subscriber, $list);
        }

        // Show confirmation page with link to actually unsubscribe
        return $this->renderSystemPage('unsubscribe_confirm', $subscriber, $list, [
            'unsubscribe-link' => route('subscriber.unsubscribe.process', [
                'subscriber' => $subscriber->id,
                'list' => $list->id,
            ]) . '?' . $request->getQueryString(),
        ]);
    }

    /**
     * Process the unsubscribe action.
     */
    public function process(Request $request, Subscriber $subscriber, ContactList $list)
    {
        if (!$request->hasValidSignature()) {
            Log::warning('Invalid unsubscribe signature', [
                'subscriber_id' => $subscriber->id,
                'list_id' => $list->id,
            ]);
            return $this->renderSystemPage('unsubscribe_error', $subscriber, $list);
        }

        try {
            // Check if subscriber is actually in this list
            $pivot = $subscriber->contactLists()->where('contact_lists.id', $list->id)->first();

            if (!$pivot) {
                // Subscriber not in this list - show error or success (debatable)
                Log::info('Subscriber not in list during unsubscribe', [
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

            // Update subscription status
            $subscriber->contactLists()->updateExistingPivot($list->id, [
                'status' => 'unsubscribed',
                'unsubscribed_at' => now(),
            ]);

            Log::info('Subscriber unsubscribed from list', [
                'subscriber_id' => $subscriber->id,
                'list_id' => $list->id,
            ]);

            // Dispatch SubscriberUnsubscribed event for automations and email notification
            event(new SubscriberUnsubscribed($subscriber, $list, 'link'));

            return $this->renderSystemPage('unsubscribe_success', $subscriber, $list);

        } catch (\Exception $e) {
            Log::error('Unsubscribe failed', [
                'subscriber_id' => $subscriber->id,
                'list_id' => $list->id,
                'error' => $e->getMessage(),
            ]);
            return $this->renderSystemPage('unsubscribe_error', $subscriber, $list);
        }
    }

    /**
     * Global unsubscribe from all lists.
     */
    public function globalUnsubscribe(Request $request, Subscriber $subscriber)
    {
        if (!$request->hasValidSignature()) {
            return $this->renderSystemPage('unsubscribe_error', $subscriber, null);
        }

        try {
            // Unsubscribe from all lists
            $subscriber->contactLists()->updateExistingPivot(
                $subscriber->contactLists->pluck('id')->toArray(),
                ['status' => 'unsubscribed', 'unsubscribed_at' => now()]
            );

            // Set global inactive
            $subscriber->update(['is_active_global' => false]);

            Log::info('Subscriber globally unsubscribed', [
                'subscriber_id' => $subscriber->id,
            ]);

            // Get first list for page rendering (or create dummy)
            $list = $subscriber->contactLists->first();

            if ($list) {
                event(new SubscriberUnsubscribed($subscriber, $list, 'global'));
            }

            return $this->renderSystemPage('unsubscribe_success', $subscriber, $list);

        } catch (\Exception $e) {
            Log::error('Global unsubscribe failed', [
                'subscriber_id' => $subscriber->id,
                'error' => $e->getMessage(),
            ]);
            return $this->renderSystemPage('unsubscribe_error', $subscriber, null);
        }
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

        $title = $systemPage->title ?? 'NetSendo';
        $content = $systemPage->content ?? '<h1>Page not found</h1>';

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
            str_contains($slug, 'confirm') => 'warning',
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
