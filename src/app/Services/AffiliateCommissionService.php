<?php

namespace App\Services;

use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use App\Models\AffiliateConversion;
use App\Models\AffiliateLevelRule;
use App\Models\AffiliateOffer;
use App\Models\AffiliateProgram;
use Illuminate\Support\Facades\Log;

class AffiliateCommissionService
{
    /**
     * Calculate and create commissions for a conversion.
     * Silver: Max 2 levels (direct + tier 2)
     * Gold: Multi-level (N levels based on program settings)
     */
    public function calculateCommissions(AffiliateConversion $conversion): array
    {
        $commissions = [];

        $affiliate = $conversion->affiliate;
        $offer = $conversion->offer;
        $program = $offer->program;

        if (!$affiliate || !$offer || !$program) {
            return $commissions;
        }

        // Only calculate commissions for purchases
        if ($conversion->type !== 'purchase') {
            return $commissions;
        }

        $maxLevels = $program->max_levels ?? 2;
        $currentAffiliate = $affiliate;
        $level = 1;

        while ($currentAffiliate && $level <= $maxLevels) {
            $commissionAmount = $this->calculateCommissionAmount(
                $program,
                $offer,
                $conversion->amount,
                $level
            );

            if ($commissionAmount > 0) {
                $commission = AffiliateCommission::create([
                    'conversion_id' => $conversion->id,
                    'affiliate_id' => $currentAffiliate->id,
                    'offer_id' => $offer->id,
                    'level' => $level,
                    'commission_amount' => $commissionAmount,
                    'currency' => $conversion->currency,
                    'status' => 'pending',
                    'available_at' => now()->addDays(30), // 30-day hold period
                ]);

                $commissions[] = $commission;

                Log::info('Affiliate commission created', [
                    'commission_id' => $commission->id,
                    'affiliate_id' => $currentAffiliate->id,
                    'level' => $level,
                    'amount' => $commissionAmount,
                ]);
            }

            // Move to parent affiliate for next level
            $currentAffiliate = $currentAffiliate->parent;
            $level++;
        }

        return $commissions;
    }

    /**
     * Calculate commission amount based on rules.
     */
    private function calculateCommissionAmount(
        AffiliateProgram $program,
        AffiliateOffer $offer,
        float $purchaseAmount,
        int $level
    ): float {
        // First check for level-specific rules (Gold feature)
        $levelRule = AffiliateLevelRule::where('program_id', $program->id)
            ->where('level', $level)
            ->first();

        if ($levelRule) {
            if ($levelRule->commission_type === 'percent') {
                return round($purchaseAmount * ($levelRule->commission_value / 100), 2);
            }
            return $levelRule->commission_value;
        }

        // Use offer-level commission settings
        if ($level === 1) {
            if ($offer->commission_type === 'percent') {
                return round($purchaseAmount * ($offer->commission_value / 100), 2);
            }
            return $offer->commission_value;
        }

        // Level 2+ uses reduced rates (50% of L1 by default)
        $reductionFactor = 0.5;

        if ($offer->commission_type === 'percent') {
            $baseRate = $offer->commission_value * $reductionFactor;
            return round($purchaseAmount * ($baseRate / 100), 2);
        }

        return round($offer->commission_value * $reductionFactor, 2);
    }

    /**
     * Approve a pending commission.
     */
    public function approveCommission(int $commissionId): AffiliateCommission
    {
        $commission = AffiliateCommission::findOrFail($commissionId);
        $commission->approve();

        Log::info('Commission approved', ['commission_id' => $commissionId]);

        return $commission;
    }

    /**
     * Approve all pending commissions that are past their hold period.
     */
    public function approveEligibleCommissions(int $programId): int
    {
        $count = AffiliateCommission::whereHas('offer', function ($q) use ($programId) {
                $q->where('program_id', $programId);
            })
            ->where('status', 'pending')
            ->where('available_at', '<=', now())
            ->update([
                'status' => 'approved',
                'approved_at' => now(),
            ]);

        Log::info('Bulk commission approval', [
            'program_id' => $programId,
            'count' => $count,
        ]);

        return $count;
    }

    /**
     * Make approved commissions payable.
     */
    public function makeCommissionsPayable(int $programId): int
    {
        $count = AffiliateCommission::whereHas('offer', function ($q) use ($programId) {
                $q->where('program_id', $programId);
            })
            ->where('status', 'approved')
            ->update([
                'status' => 'payable',
            ]);

        Log::info('Commissions made payable', [
            'program_id' => $programId,
            'count' => $count,
        ]);

        return $count;
    }

    /**
     * Reject a commission.
     */
    public function rejectCommission(int $commissionId, string $reason): AffiliateCommission
    {
        $commission = AffiliateCommission::findOrFail($commissionId);
        $commission->reject($reason);

        Log::info('Commission rejected', [
            'commission_id' => $commissionId,
            'reason' => $reason,
        ]);

        return $commission;
    }

    /**
     * Bulk approve commissions by IDs.
     */
    public function bulkApprove(array $commissionIds): int
    {
        return AffiliateCommission::whereIn('id', $commissionIds)
            ->where('status', 'pending')
            ->update([
                'status' => 'approved',
                'approved_at' => now(),
            ]);
    }

    /**
     * Get commission summary for an affiliate.
     */
    public function getAffiliateSummary(int $affiliateId): array
    {
        $commissions = AffiliateCommission::where('affiliate_id', $affiliateId);

        return [
            'total_earned' => (float) $commissions->sum('commission_amount'),
            'pending' => (float) (clone $commissions)->where('status', 'pending')->sum('commission_amount'),
            'approved' => (float) (clone $commissions)->where('status', 'approved')->sum('commission_amount'),
            'payable' => (float) (clone $commissions)->where('status', 'payable')->sum('commission_amount'),
            'paid' => (float) (clone $commissions)->where('status', 'paid')->sum('commission_amount'),
            'rejected' => (float) (clone $commissions)->where('status', 'rejected')->sum('commission_amount'),
        ];
    }
}
