<?php

namespace App\Services\Brain;

use App\Models\AiBrainSettings;
use App\Models\AiCampaignCalendar;
use App\Models\AiPerformanceSnapshot;
use App\Models\RevenueEvent;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Brain 2.0 Phase 5 — the "CFO": attention-budget allocation.
 *
 * The weekly send budget (max_sends_per_week) is a scarce resource —
 * every send spends subscriber attention. This service measures RPM
 * (revenue per 1000 delivered) per campaign type from the calendar→plan→
 * snapshot→revenue chain and allocates the budget with Thompson-style
 * sampling: proven earners get more slots, untested types keep an
 * optimistic prior so they still get explored.
 */
class AllocationService
{
    public const ARMS = ['newsletter', 'promotion', 'nurturing', 'win_back', 'announcement'];

    /**
     * Compute this week's send allocation.
     *
     * @return array{budget: int, allocation: array<string,int>, arms: array}
     */
    public function computeAllocation(User $user): array
    {
        $settings = AiBrainSettings::getForUser($user->id);
        $budget = (int) ($settings->getStrategyForAgent('campaign')['max_sends_per_week'] ?? 5);
        $budget = max(1, min(20, $budget));

        $armStats = $this->measureArms($user);

        // Thompson-lite sampling: mean + noise scaled by uncertainty
        $sampled = [];
        foreach ($armStats as $arm => $stats) {
            if ($stats['sample'] >= (int) config('brain.allocator.min_arm_samples', 2)) {
                $uncertainty = $stats['rpm_mean'] / sqrt($stats['sample'] + 1);
                $sampled[$arm] = max(0.01, $stats['rpm_mean'] + $this->gaussianNoise() * $uncertainty);
            } else {
                // Optimistic prior — untested arms stay in the race
                $prior = (float) config('brain.allocator.exploration_prior_rpm', 50.0);
                $sampled[$arm] = max(0.01, $prior + $this->gaussianNoise() * ($prior / 2));
            }
        }

        // Proportional allocation of budget slots to sampled scores
        $total = array_sum($sampled);
        $allocation = [];
        $assigned = 0;
        arsort($sampled);

        foreach ($sampled as $arm => $score) {
            $slots = (int) floor($budget * $score / $total);
            $allocation[$arm] = $slots;
            $assigned += $slots;
        }

        // Distribute remaining slots to the highest-sampled arms
        $remaining = $budget - $assigned;
        foreach (array_keys($sampled) as $arm) {
            if ($remaining <= 0) {
                break;
            }
            $allocation[$arm]++;
            $remaining--;
        }

        $allocation = array_filter($allocation);

        return [
            'budget' => $budget,
            'allocation' => $allocation,
            'arms' => $armStats,
        ];
    }

    /**
     * Format the allocation as a prompt section for the weekly planner.
     */
    public function formatAsPromptContext(array $result): string
    {
        $lines = ["--- SEND BUDGET ALLOCATION (data-driven, Thompson sampling over measured RPM) ---"];
        $lines[] = "Weekly budget: {$result['budget']} campaigns. Allocate campaign types accordingly:";

        foreach ($result['allocation'] as $arm => $slots) {
            $stats = $result['arms'][$arm] ?? null;
            $evidence = $stats && $stats['sample'] >= 2
                ? sprintf('measured RPM %.2f from %d campaigns', $stats['rpm_mean'], $stats['sample'])
                : 'untested — exploration slot';
            $lines[] = "- {$arm}: {$slots} send(s) ({$evidence})";
        }

        $lines[] = "Follow this allocation when choosing campaign_type for the week.";
        $lines[] = "---";

        return implode("\n", $lines) . "\n";
    }

    /**
     * Measure RPM per campaign type via calendar→plan→snapshot→revenue.
     *
     * @return array<string, array{rpm_mean: float, revenue: float, delivered: int, sample: int}>
     */
    protected function measureArms(User $user): array
    {
        $lookback = (int) config('brain.allocator.lookback_days', 90);
        $stats = array_fill_keys(self::ARMS, ['rpm_mean' => 0.0, 'revenue' => 0.0, 'delivered' => 0, 'sample' => 0]);

        try {
            $entries = AiCampaignCalendar::forUser($user->id)
                ->where('status', 'completed')
                ->where('planned_date', '>=', now()->subDays($lookback))
                ->get();

            foreach ($entries as $entry) {
                $arm = $entry->campaign_type;
                $planId = ($entry->metadata ?? [])['plan_id'] ?? null;
                if (!isset($stats[$arm]) || !$planId) {
                    continue;
                }

                $snapshot = AiPerformanceSnapshot::forUser($user->id)
                    ->where('ai_action_plan_id', $planId)
                    ->first();
                if (!$snapshot || !$snapshot->message_id) {
                    continue;
                }

                $revenue = ((int) RevenueEvent::forUser($user->id)
                    ->where('attributed_message_id', $snapshot->message_id)
                    ->sum('amount')) / 100;

                $stats[$arm]['revenue'] += $revenue;
                $stats[$arm]['delivered'] += (int) $snapshot->sent_count;
                $stats[$arm]['sample']++;
            }

            foreach ($stats as $arm => &$armStats) {
                if ($armStats['delivered'] > 0) {
                    $armStats['rpm_mean'] = round($armStats['revenue'] / $armStats['delivered'] * 1000, 2);
                }
            }
            unset($armStats);
        } catch (\Exception $e) {
            Log::warning('AllocationService: arm measurement failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $stats;
    }

    /**
     * Standard-normal noise via Box-Muller.
     */
    protected function gaussianNoise(): float
    {
        $u1 = max(mt_rand(1, mt_getrandmax()) / mt_getrandmax(), 1e-10);
        $u2 = mt_rand(0, mt_getrandmax()) / mt_getrandmax();

        return sqrt(-2 * log($u1)) * cos(2 * M_PI * $u2);
    }
}
