<?php

namespace App\Services;

use App\Models\CampaignPlan;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class LicenseService
{
    /**
     * Campaign limit for SILVER plan
     */
    public const SILVER_CAMPAIGN_LIMIT = 3;

    /**
     * The plan in effect, used for every GOLD/SILVER decision in the app.
     *
     * GOLD only while a GOLD key is stored and its expiry (end of the paid period plus grace,
     * kept in step by license:verify) has not passed. Anything else (no licence, no key, an
     * expired or unreadable GOLD) works as the free SILVER plan.
     */
    public function getCurrentPlan(): string
    {
        return $this->storedPlan() === 'GOLD' && $this->hasKey() && !$this->isGoldExpired() ? 'GOLD' : 'SILVER';
    }

    /**
     * The plan saved with the licence, regardless of expiry (null without a licence).
     */
    public function storedPlan(): ?string
    {
        $plan = Setting::where('key', 'license_plan')->value('value');
        return $plan ? strtoupper($plan) : null;
    }

    /**
     * A GOLD licence is stored but its expiry date has passed.
     */
    public function isGoldExpired(): bool
    {
        if ($this->storedPlan() !== 'GOLD') {
            return false;
        }

        $expiresAt = Setting::where('key', 'license_expires_at')->value('value');
        if (!$expiresAt) {
            return false; // GOLD without an end date
        }

        try {
            return Carbon::parse($expiresAt)->isPast();
        } catch (\Throwable $e) {
            Log::warning('Unreadable licence expiry date', ['value' => $expiresAt]);
            return false;
        }
    }

    private function hasKey(): bool
    {
        return Setting::where('key', 'license_key')->exists();
    }

    /**
     * Check if the current plan is GOLD
     */
    public function isGoldPlan(): bool
    {
        return $this->getCurrentPlan() === 'GOLD';
    }

    /**
     * Check if the current plan is SILVER
     */
    public function isSilverPlan(): bool
    {
        return $this->getCurrentPlan() === 'SILVER';
    }

    /**
     * Get the campaign limit for the current plan
     * Returns null for unlimited (GOLD)
     */
    public function getCampaignLimit(): ?int
    {
        if ($this->isGoldPlan()) {
            return null; // Unlimited
        }

        return self::SILVER_CAMPAIGN_LIMIT;
    }

    /**
     * Get the current campaign count for a user
     */
    public function getUserCampaignCount(?int $userId = null): int
    {
        $userId = $userId ?? Auth::id();

        if (!$userId) {
            return 0;
        }

        return CampaignPlan::forUser($userId)->count();
    }

    /**
     * Get the number of remaining campaigns a user can create
     * Returns null for unlimited (GOLD plan)
     */
    public function getRemainingCampaigns(?int $userId = null): ?int
    {
        $limit = $this->getCampaignLimit();

        if ($limit === null) {
            return null; // Unlimited
        }

        $currentCount = $this->getUserCampaignCount($userId);
        return max(0, $limit - $currentCount);
    }

    /**
     * Check if the user can create a new campaign
     */
    public function canCreateCampaign(?int $userId = null): bool
    {
        if ($this->isGoldPlan()) {
            return true;
        }

        $remaining = $this->getRemainingCampaigns($userId);
        return $remaining === null || $remaining > 0;
    }

    /**
     * Get license information for frontend
     */
    public function getLicenseInfo(?int $userId = null): array
    {
        return [
            'plan' => $this->getCurrentPlan(),
            'isGold' => $this->isGoldPlan(),
            'goldExpired' => $this->isGoldExpired(),
            'campaignCount' => $this->getUserCampaignCount($userId),
            'campaignLimit' => $this->getCampaignLimit(),
            'remainingCampaigns' => $this->getRemainingCampaigns($userId),
            'canCreateCampaign' => $this->canCreateCampaign($userId),
        ];
    }
}
