<?php

namespace App\Http\Controllers;

use App\Models\ContactList;
use App\Models\Subscriber;
use App\Models\SystemPage;
use App\Services\PlaceholderService;
use App\Services\SystemEmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Handles subscriber activation (double opt-in confirmation).
 */
class ActivationController extends Controller
{
    public function __construct(
        protected SystemEmailService $emailService,
        protected PlaceholderService $placeholderService
    ) {}

    /**
     * Activate a subscriber from a signed email link.
     */
    public function activate(Request $request, Subscriber $subscriber, ContactList $list)
    {
        // Validate signed URL
        if (!$request->hasValidSignature()) {
            Log::warning('Invalid activation link signature', [
                'subscriber_id' => $subscriber->id,
                'list_id' => $list->id,
            ]);
            return $this->renderSystemPage('activation_error', $subscriber, $list);
        }

        try {
            // Check if subscriber is already active in this list
            $pivot = $subscriber->contactLists()->where('contact_lists.id', $list->id)->first();

            if (!$pivot) {
                // Subscriber not found in this list
                Log::warning('Subscriber not found in list during activation', [
                    'subscriber_id' => $subscriber->id,
                    'list_id' => $list->id,
                ]);
                return $this->renderSystemPage('activation_error', $subscriber, $list);
            }

            $currentStatus = $pivot->pivot->status ?? 'pending';

            if ($currentStatus === 'active') {
                // Already active
                Log::info('Subscriber already active', [
                    'subscriber_id' => $subscriber->id,
                    'list_id' => $list->id,
                ]);
                return $this->renderSystemPage('activation_success', $subscriber, $list);
            }

            // Activate the subscriber
            $subscriber->contactLists()->updateExistingPivot($list->id, [
                'status' => 'active',
                'confirmed_at' => now(),
            ]);

            // Also set global active status
            if (!$subscriber->is_active_global) {
                $subscriber->update(['is_active_global' => true]);
            }

            Log::info('Subscriber activated successfully', [
                'subscriber_id' => $subscriber->id,
                'list_id' => $list->id,
            ]);

            // Send activation confirmation email
            $this->emailService->sendActivationConfirmation($subscriber, $list);

            // Trigger SubscriberSignedUp event for automations (now confirmed)
            event(new \App\Events\SubscriberSignedUp($subscriber, $list, null, 'activation'));

            return $this->renderSystemPage('activation_success', $subscriber, $list);

        } catch (\Exception $e) {
            Log::error('Activation failed', [
                'subscriber_id' => $subscriber->id,
                'list_id' => $list->id,
                'error' => $e->getMessage(),
            ]);
            return $this->renderSystemPage('activation_error', $subscriber, $list);
        }
    }

    /**
     * Resubscribe a previously unsubscribed subscriber.
     */
    public function resubscribe(Request $request, Subscriber $subscriber, ContactList $list)
    {
        if (!$request->hasValidSignature()) {
            return $this->renderSystemPage('activation_error', $subscriber, $list);
        }

        try {
            // Check if already subscribed
            $pivot = $subscriber->contactLists()->where('contact_lists.id', $list->id)->first();

            if ($pivot && $pivot->pivot->status === 'active') {
                // Already active
                return $this->renderSystemPage('signup_exists_active', $subscriber, $list);
            }

            if ($pivot) {
                // Reactivate existing subscription
                $subscriber->contactLists()->updateExistingPivot($list->id, [
                    'status' => 'active',
                    'resubscribed_at' => now(),
                ]);
            } else {
                // Create new subscription
                $subscriber->contactLists()->attach($list->id, [
                    'status' => 'active',
                    'subscribed_at' => now(),
                ]);
            }

            // Ensure global active
            if (!$subscriber->is_active_global) {
                $subscriber->update(['is_active_global' => true]);
            }

            Log::info('Subscriber resubscribed', [
                'subscriber_id' => $subscriber->id,
                'list_id' => $list->id,
            ]);

            // Send notification
            $this->emailService->sendInactiveResubscribeNotification($subscriber, $list);

            return $this->renderSystemPage('signup_success', $subscriber, $list);

        } catch (\Exception $e) {
            Log::error('Resubscribe failed', [
                'subscriber_id' => $subscriber->id,
                'list_id' => $list->id,
                'error' => $e->getMessage(),
            ]);
            return $this->renderSystemPage('signup_error', $subscriber, $list);
        }
    }

    /**
     * Render a system page with placeholder replacement.
     */
    protected function renderSystemPage(string $slug, Subscriber $subscriber, ContactList $list)
    {
        $systemPage = SystemPage::getBySlug($slug, $list->id);

        if (!$systemPage) {
            $systemPage = SystemPage::getBySlug($slug, null);
        }

        $title = $systemPage->title ?? 'NetSendo';
        $content = $systemPage->content ?? '<h1>Page not found</h1>';

        // Replace placeholders
        $title = $this->placeholderService->replacePlaceholders($title, $subscriber);
        $content = $this->placeholderService->replacePlaceholders($content, $subscriber);
        $content = str_replace('[[list-name]]', $list->name, $content);

        // Determine icon based on slug
        $icon = match (true) {
            str_contains($slug, 'success') => 'success',
            str_contains($slug, 'error') => 'error',
            str_contains($slug, 'exists') || str_contains($slug, 'active') => 'warning',
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
