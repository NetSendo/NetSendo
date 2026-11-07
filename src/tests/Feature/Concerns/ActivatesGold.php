<?php

namespace Tests\Feature\Concerns;

use App\Models\Setting;

/**
 * A stored GOLD licence for tests of GOLD features (App\Licensing\Feature). Without it a
 * test installation runs as SILVER, the free plan.
 */
trait ActivatesGold
{
    /** An active GOLD licence; with a past date, a GOLD licence that has expired. */
    protected function activateGold(?string $expiresAt = null): void
    {
        Setting::updateOrCreate(['key' => 'license_key'], ['value' => 'Z29sZA==-test-key']);
        Setting::updateOrCreate(['key' => 'license_plan'], ['value' => 'GOLD']);

        if ($expiresAt) {
            Setting::updateOrCreate(['key' => 'license_expires_at'], ['value' => $expiresAt]);
        }
    }
}
