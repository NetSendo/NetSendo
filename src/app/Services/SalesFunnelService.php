<?php

namespace App\Services;

use App\Models\ContactList;
use App\Models\SalesFunnel;
use App\Models\Subscriber;
use Illuminate\Support\Facades\Log;

class SalesFunnelService
{
    /**
     * Process purchase actions for a sales funnel.
     * - Subscribe customer to target list
     * - Add purchase tag
     */
    public function processPurchase(SalesFunnel $funnel, ?string $email, ?string $name = null): void
    {
        if (!$email) {
            Log::warning('Sales funnel: cannot process purchase without email', [
                'funnel_id' => $funnel->id,
            ]);
            return;
        }

        Log::info('Processing sales funnel purchase', [
            'funnel_id' => $funnel->id,
            'email' => $email,
            'target_list_id' => $funnel->target_list_id,
            'purchase_tag' => $funnel->purchase_tag,
        ]);

        // Subscribe to target list
        if ($funnel->target_list_id) {
            $this->subscribeToList($funnel, $email, $name);
        }

        // Add purchase tag
        if ($funnel->purchase_tag) {
            $this->addPurchaseTag($funnel, $email);
        }
    }

    /**
     * Subscribe customer to target list.
     */
    private function subscribeToList(SalesFunnel $funnel, string $email, ?string $name): void
    {
        try {
            $list = ContactList::find($funnel->target_list_id);
            if (!$list) {
                Log::warning('Sales funnel: target list not found', [
                    'funnel_id' => $funnel->id,
                    'list_id' => $funnel->target_list_id,
                ]);
                return;
            }

            // Find or create subscriber
            $subscriber = Subscriber::firstOrCreate(
                ['email' => $email],
                [
                    'first_name' => $this->extractFirstName($name),
                    'last_name' => $this->extractLastName($name),
                    'source' => 'sales_funnel',
                ]
            );

            // Attach to list if not already attached
            if (!$subscriber->lists()->where('contact_list_id', $list->id)->exists()) {
                $subscriber->lists()->attach($list->id, [
                    'subscribed_at' => now(),
                    'source' => 'sales_funnel:' . $funnel->id,
                ]);

                Log::info('Sales funnel: subscriber added to list', [
                    'funnel_id' => $funnel->id,
                    'subscriber_id' => $subscriber->id,
                    'list_id' => $list->id,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Sales funnel: failed to subscribe to list', [
                'funnel_id' => $funnel->id,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Add purchase tag to subscriber.
     */
    private function addPurchaseTag(SalesFunnel $funnel, string $email): void
    {
        try {
            $subscriber = Subscriber::where('email', $email)->first();
            if (!$subscriber) {
                Log::info('Sales funnel: subscriber not found for tag', [
                    'funnel_id' => $funnel->id,
                    'email' => $email,
                ]);
                return;
            }

            // Get current tags and add new one
            $tags = $subscriber->tags ?? [];
            if (!in_array($funnel->purchase_tag, $tags)) {
                $tags[] = $funnel->purchase_tag;
                $subscriber->update(['tags' => $tags]);

                Log::info('Sales funnel: tag added to subscriber', [
                    'funnel_id' => $funnel->id,
                    'subscriber_id' => $subscriber->id,
                    'tag' => $funnel->purchase_tag,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Sales funnel: failed to add tag', [
                'funnel_id' => $funnel->id,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Extract first name from full name.
     */
    private function extractFirstName(?string $name): ?string
    {
        if (!$name) return null;
        $parts = explode(' ', trim($name), 2);
        return $parts[0] ?? null;
    }

    /**
     * Extract last name from full name.
     */
    private function extractLastName(?string $name): ?string
    {
        if (!$name) return null;
        $parts = explode(' ', trim($name), 2);
        return $parts[1] ?? null;
    }
}
