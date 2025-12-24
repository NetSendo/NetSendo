<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactList;
use App\Models\Subscriber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ListSubscriptionController extends Controller
{
    /**
     * Subscribe to a list via API
     *
     * Requires valid list API key in Authorization header
     */
    public function subscribe(Request $request, $listId)
    {
        // Find the list
        $list = ContactList::find($listId);

        if (!$list) {
            return response()->json([
                'success' => false,
                'error' => 'List not found',
            ], 404);
        }

        // Validate API key
        $authHeader = $request->header('Authorization');
        $apiKey = null;

        if ($authHeader && preg_match('/Bearer\s+(.+)/', $authHeader, $matches)) {
            $apiKey = $matches[1];
        }

        if (!$apiKey || $list->api_key !== $apiKey) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid or missing API key',
            ], 401);
        }

        // Check if signups are allowed
        if (!$list->canAcceptSignups()) {
            return response()->json([
                'success' => false,
                'error' => 'This list is not accepting new subscriptions',
            ], 403);
        }

        // Build validation rules based on list type
        $emailRule = $list->type === 'email' ? 'required|email' : 'nullable|email';
        $phoneRule = $list->type === 'sms' ? 'required|string|max:50' : 'nullable|string|max:50';

        // Validate input with dynamic rules
        $validator = Validator::make($request->all(), [
            'email' => $emailRule,
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'phone' => $phoneRule,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $email = $request->email ? strtolower(trim($request->email)) : null;
        $phone = $request->phone ? trim($request->phone) : null;

        // Find subscriber by email or phone depending on list type
        $subscriber = null;

        if ($email) {
            $subscriber = Subscriber::where('email', $email)
                ->where('user_id', $list->user_id)
                ->first();
        }

        // For SMS lists, also try to find by phone
        if (!$subscriber && $phone && $list->type === 'sms') {
            $subscriber = Subscriber::where('phone', $phone)
                ->where('user_id', $list->user_id)
                ->first();
        }

        if (!$subscriber) {
            $subscriber = Subscriber::create([
                'email' => $email,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'phone' => $phone,
                'user_id' => $list->user_id,
            ]);
        } else {
            // Update subscriber data if provided
            if ($request->first_name) {
                $subscriber->first_name = $request->first_name;
            }
            if ($request->last_name) {
                $subscriber->last_name = $request->last_name;
            }
            if ($request->phone) {
                $subscriber->phone = $request->phone;
            }
            $subscriber->save();
        }

        // Check if already subscribed to this list
        $existing = $list->subscribers()
            ->where('subscriber_id', $subscriber->id)
            ->first();

        if ($existing) {
            $status = $existing->pivot->status;

            if ($status === 'active') {
                return response()->json([
                    'success' => true,
                    'message' => 'Already subscribed',
                    'subscriber_id' => $subscriber->id,
                    'status' => 'existing',
                ], 200);
            }

            // Reactivate if was unsubscribed
            $list->subscribers()->updateExistingPivot($subscriber->id, [
                'status' => 'active',
                'subscribed_at' => now(),
                'unsubscribed_at' => null,
            ]);
        } else {
            // Add to list
            $list->subscribers()->attach($subscriber->id, [
                'status' => 'active',
                'subscribed_at' => now(),
            ]);
        }

        // Trigger webhook if configured
        $list->triggerWebhook('subscribe', [
            'email' => $subscriber->email,
            'first_name' => $subscriber->first_name,
            'last_name' => $subscriber->last_name,
            'subscriber_id' => $subscriber->id,
        ]);

        // Sync to parent list if configured
        $list->syncToParentList($subscriber, 'subscribe');

        return response()->json([
            'success' => true,
            'message' => 'Successfully subscribed',
            'subscriber_id' => $subscriber->id,
            'status' => 'new',
        ], 201);
    }

    /**
     * Unsubscribe from a list via API
     */
    public function unsubscribe(Request $request, $listId)
    {
        // Find the list
        $list = ContactList::find($listId);

        if (!$list) {
            return response()->json([
                'success' => false,
                'error' => 'List not found',
            ], 404);
        }

        // Validate API key
        $authHeader = $request->header('Authorization');
        $apiKey = null;

        if ($authHeader && preg_match('/Bearer\s+(.+)/', $authHeader, $matches)) {
            $apiKey = $matches[1];
        }

        if (!$apiKey || $list->api_key !== $apiKey) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid or missing API key',
            ], 401);
        }

        // Validate input
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $email = strtolower(trim($request->email));

        // Find subscriber
        $subscriber = Subscriber::where('email', $email)
            ->where('user_id', $list->user_id)
            ->first();

        if (!$subscriber) {
            return response()->json([
                'success' => false,
                'error' => 'Subscriber not found',
            ], 404);
        }

        // Check if subscribed to this list
        $existing = $list->subscribers()
            ->where('subscriber_id', $subscriber->id)
            ->first();

        if (!$existing) {
            return response()->json([
                'success' => false,
                'error' => 'Not subscribed to this list',
            ], 404);
        }

        // Unsubscribe
        $list->subscribers()->updateExistingPivot($subscriber->id, [
            'status' => 'unsubscribed',
            'unsubscribed_at' => now(),
        ]);

        // Trigger webhook if configured
        $list->triggerWebhook('unsubscribe', [
            'email' => $subscriber->email,
            'subscriber_id' => $subscriber->id,
        ]);

        // Sync to parent list if configured
        $list->syncToParentList($subscriber, 'unsubscribe');

        return response()->json([
            'success' => true,
            'message' => 'Successfully unsubscribed',
        ], 200);
    }
}
