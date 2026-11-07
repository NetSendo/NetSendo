<?php

namespace App\Services;

use App\Licensing\Feature;
use App\Licensing\FeatureRequiresGold;
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
        $stored = $this->stored();

        return $stored['plan'] === 'GOLD' && $stored['has_key'] && !$this->hasPassed($stored['expires_at'])
            ? 'GOLD'
            : 'SILVER';
    }

    /**
     * The plan saved with the licence, regardless of expiry (null without a licence).
     */
    public function storedPlan(): ?string
    {
        return $this->stored()['plan'];
    }

    /**
     * A GOLD licence is stored but its expiry date has passed.
     */
    public function isGoldExpired(): bool
    {
        $stored = $this->stored();

        return $stored['plan'] === 'GOLD' && $this->hasPassed($stored['expires_at']);
    }

    /**
     * Whether the licence in effect includes the feature.
     */
    public function allows(Feature $feature): bool
    {
        return $feature->plan() === 'SILVER' || $this->isGoldPlan();
    }

    /**
     * @throws FeatureRequiresGold when the licence in effect does not include the feature
     */
    public function ensureAllows(Feature $feature): void
    {
        if (!$this->allows($feature)) {
            throw new FeatureRequiresGold($feature);
        }
    }

    /**
     * Every licensed feature with whether the licence in effect includes it (for the frontend).
     *
     * @return array<string, bool>
     */
    public function features(): array
    {
        $gold = $this->isGoldPlan();
        $features = [];

        foreach (Feature::cases() as $feature) {
            $features[$feature->value] = $feature->plan() === 'SILVER' || $gold;
        }

        return $features;
    }

    /**
     * The stored licence, read in one query (every GOLD check goes through here).
     *
     * @return array{plan: ?string, has_key: bool, expires_at: ?string}
     */
    private function stored(): array
    {
        $values = Setting::whereIn('key', ['license_plan', 'license_key', 'license_expires_at'])
            ->pluck('value', 'key');

        $plan = $values->get('license_plan');

        return [
            'plan' => $plan ? strtoupper($plan) : null,
            'has_key' => $values->has('license_key'),
            'expires_at' => $values->get('license_expires_at'),
        ];
    }

    /**
     * An expiry date that has passed (no date = GOLD without an end date).
     */
    private function hasPassed(?string $expiresAt): bool
    {
        if (!$expiresAt) {
            return false;
        }

        try {
            return Carbon::parse($expiresAt)->isPast();
        } catch (\Throwable $e) {
            Log::warning('Unreadable licence expiry date', ['value' => $expiresAt]);
            return false;
        }
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
        if ($this->allows(Feature::UnlimitedAiCampaigns)) {
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
            'features' => $this->features(),
            'campaignCount' => $this->getUserCampaignCount($userId),
            'campaignLimit' => $this->getCampaignLimit(),
            'remainingCampaigns' => $this->getRemainingCampaigns($userId),
            'canCreateCampaign' => $this->canCreateCampaign($userId),
        ];
    }
}
