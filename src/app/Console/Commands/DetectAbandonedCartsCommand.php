<?php

namespace App\Console\Commands;

use App\Events\PixelCartAbandoned;
use App\Models\PixelEvent;
use App\Models\SubscriberDevice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DetectAbandonedCartsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pixel:detect-abandoned-carts
                            {--hours=1 : Hours of inactivity to consider cart abandoned}
                            {--max-age=48 : Ignore carts whose last cart activity is older than this many hours}
                            {--dry-run : Run without dispatching events}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Detect abandoned carts from pixel tracking data and trigger automations';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $maxAge = max($hours, (int) $this->option('max-age'));
        $dryRun = $this->option('dry-run');

        $this->info("Detecting abandoned carts (inactivity threshold: {$hours} hour(s), max age: {$maxAge} hour(s))...");

        $cutoffTime = now()->subHours($hours);
        $abandonedCount = 0;

        // Find devices that:
        // 1. Have an add_to_cart or checkout_started event
        // 2. No purchase event after that
        // 3. No activity in the last X hours
        // 4. Are linked to a subscriber (for automation targeting)
        // 5. Last cart activity no older than --max-age (no burst of week-old carts)
        // 6. Haven't been marked as abandoned since that cart activity

        $potentiallyAbandoned = $this->findPotentiallyAbandonedCarts($cutoffTime, now()->subHours($maxAge));

        foreach ($potentiallyAbandoned as $cartData) {
            $this->info("  → Processing device #{$cartData['device_id']} (subscriber #{$cartData['subscriber_id']})");

            // One trigger per cart: a new add_to_cart / checkout_started starts a new one.
            // (A 24-hour window used to re-trigger the same cart every day for a week.)
            $alreadyProcessed = PixelEvent::where('subscriber_device_id', $cartData['device_id'])
                ->where('subscriber_id', $cartData['subscriber_id'])
                ->where('event_type', 'cart_abandoned_triggered')
                ->where('occurred_at', '>=', $cartData['last_cart_activity'])
                ->exists();

            if ($alreadyProcessed) {
                $this->line("    Skipped (already processed for this cart)");
                continue;
            }

            if (!$dryRun) {
                // Mark as processed
                PixelEvent::create([
                    'subscriber_device_id' => $cartData['device_id'],
                    'subscriber_id' => $cartData['subscriber_id'],
                    'user_id' => $cartData['user_id'],
                    'event_type' => 'cart_abandoned_triggered',
                    'event_category' => 'system',
                    'page_url' => $cartData['last_cart_url'] ?? '',
                    'cart_value' => $cartData['cart_value'] ?? null,
                    'custom_data' => [
                        'last_cart_event' => $cartData['last_cart_event'],
                        'last_activity_at' => $cartData['last_activity_at'],
                    ],
                    'occurred_at' => now(),
                ]);

                // Dispatch event for automation
                event(new PixelCartAbandoned(
                    $cartData['subscriber_id'],
                    $cartData['device_id'],
                    $cartData['user_id'],
                    $cartData['cart_value'] ?? 0,
                    $cartData['product_id'] ?? null,
                    $cartData['product_name'] ?? null
                ));

                $this->info("    ✓ Abandoned cart event dispatched");
            } else {
                $this->info("    [DRY RUN] Would dispatch abandoned cart event");
            }

            $abandonedCount++;
        }

        $this->newLine();
        $this->info("Completed. Found {$abandonedCount} abandoned cart(s).");

        Log::info('DetectAbandonedCartsCommand completed', [
            'abandoned_count' => $abandonedCount,
            'dry_run' => $dryRun,
            'hours_threshold' => $hours,
        ]);

        return Command::SUCCESS;
    }

    /**
     * Find devices with potentially abandoned carts
     */
    protected function findPotentiallyAbandonedCarts($cutoffTime, $oldestCartActivity): array
    {
        // Get devices that had cart activity but no purchase
        $results = PixelEvent::query()
            ->select([
                'subscriber_device_id',
                'subscriber_id',
                'user_id',
            ])
            ->selectRaw('MAX(occurred_at) as last_activity_at')
            ->selectRaw('MAX(CASE WHEN event_type IN ("add_to_cart", "checkout_started") THEN occurred_at END) as last_cart_activity')
            ->selectRaw('MAX(CASE WHEN event_type = "purchase" THEN occurred_at END) as last_purchase_at')
            ->selectRaw('MAX(CASE WHEN event_type IN ("add_to_cart", "checkout_started") THEN cart_value END) as cart_value')
            ->selectRaw('MAX(CASE WHEN event_type IN ("add_to_cart", "checkout_started") THEN product_id END) as product_id')
            ->selectRaw('MAX(CASE WHEN event_type IN ("add_to_cart", "checkout_started") THEN product_name END) as product_name')
            ->selectRaw('MAX(CASE WHEN event_type IN ("add_to_cart", "checkout_started") THEN page_url END) as last_cart_url')
            ->selectRaw('MAX(CASE WHEN event_type IN ("add_to_cart", "checkout_started") THEN event_type END) as last_cart_event')
            ->whereNotNull('subscriber_id')
            ->whereIn('event_type', ['add_to_cart', 'checkout_started', 'purchase', 'page_view'])
            ->where('occurred_at', '>=', now()->subDays(7)) // Only look at last 7 days
            ->groupBy('subscriber_device_id', 'subscriber_id', 'user_id')
            ->havingRaw('last_cart_activity IS NOT NULL')
            ->havingRaw('last_cart_activity >= ?', [$oldestCartActivity])
            ->havingRaw('(last_purchase_at IS NULL OR last_purchase_at < last_cart_activity)')
            ->havingRaw('last_activity_at < ?', [$cutoffTime])
            ->get();

        return $results->map(fn($row) => [
            'device_id' => $row->subscriber_device_id,
            'subscriber_id' => $row->subscriber_id,
            'user_id' => $row->user_id,
            'last_activity_at' => $row->last_activity_at,
            'last_cart_activity' => $row->last_cart_activity,
            'last_cart_event' => $row->last_cart_event,
            'cart_value' => $row->cart_value,
            'product_id' => $row->product_id,
            'product_name' => $row->product_name,
            'last_cart_url' => $row->last_cart_url,
        ])->toArray();
    }
}
