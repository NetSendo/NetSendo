<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\GoldPurchaseService;
use Illuminate\Console\Command;

/**
 * Fallback for GOLD bought from the licence page: if the admin closed the page before the
 * payment went through, the scheduler activates the key as soon as the store confirms it.
 */
class ClaimGoldCommand extends Command
{
    protected $signature = 'license:claim-gold';

    protected $description = 'Activate GOLD bought in the NetSendo store once the payment is confirmed';

    public function handle(GoldPurchaseService $goldPurchase): int
    {
        if (!$goldPurchase->hasPendingPurchase()) {
            return self::SUCCESS;
        }

        $domain = Setting::where('key', 'license_domain')->value('value')
            ?? parse_url((string) config('app.url'), PHP_URL_HOST)
            ?? 'localhost';

        $result = $goldPurchase->claim($domain);
        $this->line('GOLD purchase: ' . $result['status'] . (isset($result['message']) ? ' (' . $result['message'] . ')' : ''));

        return self::SUCCESS;
    }
}
