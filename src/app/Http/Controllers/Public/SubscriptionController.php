<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\ContactList;
use App\Models\Subscriber;
use App\Models\ExternalPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class SubscriptionController extends Controller
{
    public function store(Request $request, $listUuid) 
    {
        // Assuming lists might be identified by UUID in public forms for security, 
        // but for now let's assume ID or verify if ContactList has UUID.
        // If not, we use ID but locally we should be careful.
        // Let's assume ID for simplicity as per current Model.
        
        $list = ContactList::findOrFail($listUuid); // Or find by uuid if implemented

        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'first_name' => 'nullable|string|max:255',
            'custom_fields' => 'nullable|array',
        ]);

        // Check if subscriber exists
        $subscriber = $list->subscribers()->where('email', $validated['email'])->first();

        if (!$subscriber) {
            $subscriber = $list->subscribers()->create([
                'email' => $validated['email'],
                'first_name' => $validated['first_name'] ?? null,
                'status' => 'active', // or 'unconfirmed' if double optin
                // 'custom_fields' => ... // if supported
            ]);
        }

        // Determine Redirect Page
        // Logic: 
        // 1. Check if double opt-in is enabled -> 'confirmation' page
        // 2. Else -> 'success' page
        // 3. If subscriber already existed -> 'exists_active' or related

        $pageType = 'success';
        $settings = $list->settings;
        
        // Simple logic for now
        $pageConfig = $settings['pages'][$pageType] ?? null;

        if ($pageConfig) {
            return $this->handleRedirect($pageConfig, $subscriber);
        }

        return response()->json(['message' => 'Subscribed successfully.']);
    }

    protected function handleRedirect($pageConfig, $subscriber)
    {
        $type = $pageConfig['type'] ?? 'system';

        if ($type === 'external' && !empty($pageConfig['external_page_id'])) {
            $externalPage = ExternalPage::find($pageConfig['external_page_id']);
            if ($externalPage) {
                // Prepare params for placeholder replacement
                $params = [
                    'externalPage' => $externalPage->id,
                    'email' => $subscriber->email,
                    'first_name' => $subscriber->first_name,
                    'last_name' => $subscriber->last_name,
                    'subscriber_id' => $subscriber->id,
                ];

                // Add custom fields if available
                // Assuming custom_fields is a JSON attribute on subscriber or related model
                // For now, if we had them in request, we can pass them
                // or if defined on subscriber model cast.
                if (isset($subscriber->custom_fields) && is_array($subscriber->custom_fields)) {
                    foreach ($subscriber->custom_fields as $key => $value) {
                        $params[$key] = $value;
                    }
                }
                
                return redirect()->route('page.show', $params);
            }
        } elseif ($type === 'custom' && !empty($pageConfig['url'])) {
            return Redirect::away($pageConfig['url']);
        }

        // System default (fallback)
        return response()->json(['message' => 'Subscribed successfully (System Page).']);
    }
}
