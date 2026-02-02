<?php

namespace App\Http\Controllers;

use App\Models\SystemEmail;
use App\Models\ContactList;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SystemEmailController extends Controller
{
    /**
     * Display a listing of system emails.
     */
    public function index(Request $request)
    {
        $lists = auth()->user()->contactLists()->select('id', 'name')->get();
        $selectedListId = $request->list_id;

        // Define logical order for system emails
        // Categories: Signup → Activation → Welcome → Resubscribe → Management → Unsubscribe → Admin
        $emailOrder = [
            'signup_confirmation',        // 1. Double opt-in verification
            'activation_email',           // 2. Activation link email
            'activation_confirmation',    // 3. Post-activation confirmation
            'subscription_welcome',       // 4. Welcome after subscription
            'welcome_email',              // 5. Alternative welcome email
            'already_active_resubscribe', // 6. Already active user tries to resubscribe
            'inactive_resubscribe',       // 7. Inactive user resubscribes
            'preference_confirm',         // 8. Preference change confirmation
            'data_edit_access',           // 9. Data edit access link
            'unsubscribe_request',        // 10. Unsubscribe confirmation request
            'unsubscribed_confirmation',  // 11. Unsubscribe confirmed
            'new_subscriber_notification', // 12. Admin notification
        ];

        // Get Global Defaults
        $globalEmails = SystemEmail::whereNull('contact_list_id')->get()->keyBy('slug');

        // If a list is selected, get its overrides
        $listEmails = collect();
        if ($selectedListId) {
            $listEmails = SystemEmail::where('contact_list_id', $selectedListId)->get()->keyBy('slug');
        }

        // Merge: Use List specific if exists, otherwise Global
        $emails = $globalEmails->map(function ($globalEmail) use ($listEmails, $selectedListId) {
            if ($listEmails->has($globalEmail->slug)) {
                $email = $listEmails->get($globalEmail->slug);
                $email->is_custom = true;
                return $email;
            }
            // Use global, but mark as default
            $email = $globalEmail;
            $email->is_custom = false;
            $email->context_list_id = $selectedListId;
            return $email;
        });

        // Sort by defined order
        $sortedEmails = collect($emailOrder)
            ->filter(fn($slug) => $emails->has($slug))
            ->map(fn($slug) => $emails->get($slug))
            ->values();

        // Add any emails not in the order list at the end
        $remainingEmails = $emails->filter(fn($email, $slug) => !in_array($slug, $emailOrder))->values();
        $sortedEmails = $sortedEmails->merge($remainingEmails);

        return Inertia::render('SystemEmail/Index', [
            'emails' => $sortedEmails,
            'lists' => $lists,
            'currentListId' => $selectedListId,
        ]);
    }

    /**
     * Show the form for editing a system email.
     */
    public function edit(Request $request, SystemEmail $systemEmail)
    {
        $listId = $systemEmail->contact_list_id ?? $request->list_id;

        $listName = __('system_emails.global_defaults');
        if ($listId) {
            $list = ContactList::find($listId);
            if ($list) {
                if ($list->user_id !== auth()->id()) {
                    abort(403);
                }
                $listName = $list->name;
            }
        }

        return Inertia::render('SystemEmail/Edit', [
            'email' => $systemEmail,
            'list_id' => $listId,
            'list_name' => $listName,
        ]);
    }

    /**
     * Update the specified system email.
     */
    public function update(Request $request, SystemEmail $systemEmail)
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'content' => 'required|string',
            'is_active' => 'boolean',
            'list_id' => 'nullable|exists:contact_lists,id',
        ]);

        $listId = $request->list_id;

        // Security check
        if ($listId) {
            $list = ContactList::where('id', $listId)->where('user_id', auth()->id())->firstOrFail();
        }

        // "Copy on Write" logic
        if ($listId && $systemEmail->contact_list_id === null) {
            // We are customizing a default email for a specific list
            SystemEmail::create([
                'slug' => $systemEmail->slug,
                'name' => $systemEmail->name,
                'subject' => $validated['subject'],
                'content' => $validated['content'],
                'is_active' => $validated['is_active'] ?? true,
                'contact_list_id' => $listId,
            ]);

            return redirect()->route('settings.system-emails.index', ['list_id' => $listId])
                ->with('success', __('system_emails.custom_created'));
        }

        // Normal update
        $systemEmail->update([
            'subject' => $validated['subject'],
            'content' => $validated['content'],
            'is_active' => $validated['is_active'] ?? $systemEmail->is_active,
        ]);

        return redirect()->route('settings.system-emails.index', ['list_id' => $systemEmail->contact_list_id])
            ->with('success', __('system_emails.updated'));
    }

    /**
     * Reset a list-specific email to use global default.
     */
    public function destroy(SystemEmail $systemEmail)
    {
        // Can only delete list-specific emails
        if ($systemEmail->contact_list_id === null) {
            return back()->with('error', __('system_emails.cannot_delete_global'));
        }

        $listId = $systemEmail->contact_list_id;
        $systemEmail->delete();

        return redirect()->route('settings.system-emails.index', ['list_id' => $listId])
            ->with('success', __('system_emails.reset_to_default'));
    }

    /**
     * Toggle active status for a list-specific email.
     * Cannot toggle global emails - they must always be active.
     */
    public function toggle(Request $request, SystemEmail $systemEmail)
    {
        $listId = $request->list_id;

        // Cannot toggle global emails
        if (!$listId) {
            return back()->with('error', __('system_emails.cannot_toggle_global'));
        }

        // Security check
        $list = ContactList::where('id', $listId)->where('user_id', auth()->id())->firstOrFail();

        // If this is a global email, create a list-specific copy with toggled status
        if ($systemEmail->contact_list_id === null) {
            SystemEmail::create([
                'slug' => $systemEmail->slug,
                'name' => $systemEmail->name,
                'subject' => $systemEmail->subject,
                'content' => $systemEmail->content,
                'is_active' => false, // Toggle: was active (global default), now inactive
                'contact_list_id' => $listId,
            ]);

            return redirect()->route('settings.system-emails.index', ['list_id' => $listId])
                ->with('success', __('system_emails.toggled'));
        }

        // Toggle existing list-specific email
        $systemEmail->update([
            'is_active' => !$systemEmail->is_active,
        ]);

        return redirect()->route('settings.system-emails.index', ['list_id' => $listId])
            ->with('success', __('system_emails.toggled'));
    }
}
