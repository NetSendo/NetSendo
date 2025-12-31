<?php

namespace App\Services;

use App\Models\CampaignPlan;
use App\Models\Setting;
use Illuminate\Support\Facades\Auth;

class LicenseService
{
    /**
     * Campaign limit for SILVER plan
     */
    public const SILVER_CAMPAIGN_LIMIT = 3;

    /**
     * Get the current license plan
     */
    public function getCurrentPlan(): string
    {
        $licensePlan = Setting::where('key', 'license_plan')->first();
        return $licensePlan ? strtoupper($licensePlan->value) : 'SILVER';
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
            'campaignCount' => $this->getUserCampaignCount($userId),
            'campaignLimit' => $this->getCampaignLimit(),
            'remainingCampaigns' => $this->getRemainingCampaigns($userId),
            'canCreateCampaign' => $this->canCreateCampaign($userId),
        ];
    }
}
