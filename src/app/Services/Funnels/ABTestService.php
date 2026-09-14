<?php

namespace App\Services\Funnels;

use App\Models\Funnel;
use App\Models\FunnelStep;
use App\Models\FunnelAbTest;
use App\Models\FunnelAbVariant;
use App\Models\FunnelAbEnrollment;
use App\Models\FunnelSubscriber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ABTestService
{
    /**
     * Create a new A/B test for a split step.
     */
    public function create(FunnelStep $splitStep, array $data): FunnelAbTest
    {
        return DB::transaction(function () use ($splitStep, $data) {
            $test = FunnelAbTest::create([
                'funnel_id' => $splitStep->funnel_id,
                'split_step_id' => $splitStep->id,
                'name' => $data['name'] ?? 'Test A/B',
                'description' => $data['description'] ?? null,
                'status' => FunnelAbTest::STATUS_DRAFT,
                'sample_size' => $data['sample_size'] ?? null,
                'confidence_level' => $data['confidence_level'] ?? 95,
                'winning_metric' => $data['winning_metric'] ?? FunnelAbTest::METRIC_CONVERSION_RATE,
                'settings' => $data['settings'] ?? [],
            ]);

            // Variants from the split step configuration
            $variants = $splitStep->getSplitVariants() ?: FunnelStep::normalizeSplitVariants([
                ['name' => 'Wariant A', 'weight' => 50],
                ['name' => 'Wariant B', 'weight' => 50],
            ]);

            return $this->syncVariantRows($test, $splitStep->funnel_id, $variants);
        });
    }

    /**
     * Bring a test's variants in line with its split step, matched by variant
     * key so results stay with their variant: a variant added on the step gets
     * one, a renamed, reweighted or reconnected one is updated, a removed one is
     * deleted with its enrollments. Removing the declared winner reopens the
     * test for the variants left, which would otherwise have no path to follow.
     */
    public function syncVariants(FunnelAbTest $test, FunnelStep $splitStep): FunnelAbTest
    {
        return $this->syncVariantRows($test, $splitStep->funnel_id, $splitStep->getSplitVariants());
    }

    /**
     * After a split step is saved: its test, once it has one, follows the change.
     */
    public function syncTestForStep(FunnelStep $splitStep): void
    {
        $test = $this->getTestForStep($splitStep);

        if ($test) {
            $this->syncVariants($test, $splitStep);
        }
    }

    /**
     * @param  array  $variants  normalized (FunnelStep::normalizeSplitVariants())
     */
    protected function syncVariantRows(FunnelAbTest $test, int $funnelId, array $variants): FunnelAbTest
    {
        if ($variants === []) {
            return $test->loadMissing('variants');
        }

        // Variants created before they had keys follow the step's by position
        $rows = $test->variants()->orderBy('id')->get()
            ->keyBy(fn (FunnelAbVariant $row, int $index) => $row->variant_key ?? 'v' . ($index + 1));

        // The foreign key admits existing steps only, and a variant path must stay in the funnel
        $targetIds = array_filter(array_column($variants, 'next_step_id'));
        $targetIds = $targetIds ? FunnelStep::where('funnel_id', $funnelId)->whereIn('id', $targetIds)->pluck('id')->all() : [];

        $changed = false;

        foreach ($variants as $variant) {
            $row = $rows->pull($variant['key']) ?? new FunnelAbVariant(['ab_test_id' => $test->id]);

            $row->fill([
                'name' => $variant['name'],
                'weight' => $variant['weight'],
                'next_step_id' => in_array($variant['next_step_id'], $targetIds) ? $variant['next_step_id'] : null,
                'metadata' => array_merge($row->metadata ?? [], ['key' => $variant['key']]),
            ]);

            if (!$row->exists || $row->isDirty()) {
                $row->save();
                $changed = true;
            }
        }

        foreach ($rows as $removed) {
            if ($test->isCompleted() && (int) $test->winner_variant_id === $removed->id) {
                $test->update([
                    'status' => FunnelAbTest::STATUS_RUNNING,
                    'winner_variant_id' => null,
                    'winner_declared_at' => null,
                ]);

                Log::info("A/B test {$test->id} reopened: its winner {$removed->name} was removed from the step");
            }

            $removed->delete();
            $changed = true;
        }

        return $changed ? $test->load('variants') : $test->loadMissing('variants');
    }

    /**
     * Enroll a subscriber into an A/B test using weighted distribution.
     */
    public function enrollSubscriber(
        FunnelAbTest $test,
        FunnelSubscriber $funnelSubscriber
    ): ?FunnelAbVariant {
        // Only running tests accept enrollments
        if (!$test->isRunning()) {
            Log::warning("Attempted to enroll subscriber in non-running test {$test->id}");
            return null;
        }

        // Check if already enrolled
        $existing = FunnelAbEnrollment::where('ab_test_id', $test->id)
            ->where('funnel_subscriber_id', $funnelSubscriber->id)
            ->first();

        if ($existing) {
            return $existing->variant;
        }

        // Select variant using weighted random distribution
        $variant = $this->selectVariant($test);

        if (!$variant) {
            Log::error("No variants available for test {$test->id}");
            return null;
        }

        // Create enrollment
        FunnelAbEnrollment::create([
            'ab_test_id' => $test->id,
            'variant_id' => $variant->id,
            'funnel_subscriber_id' => $funnelSubscriber->id,
            'subscriber_id' => $funnelSubscriber->subscriber_id,
        ]);

        // Increment variant enrollment counter
        $variant->recordEnrollment();

        Log::info("Enrolled subscriber {$funnelSubscriber->subscriber_id} into variant {$variant->name} for test {$test->id}");

        return $variant;
    }

    /**
     * Select a variant using weighted random distribution.
     */
    protected function selectVariant(FunnelAbTest $test): ?FunnelAbVariant
    {
        $variants = $test->variants;

        if ($variants->isEmpty()) {
            return null;
        }

        // Calculate total weight
        $totalWeight = $variants->sum('weight');

        if ($totalWeight <= 0) {
            // If no weights, distribute evenly
            return $variants->random();
        }

        // Generate random number
        $random = mt_rand(1, $totalWeight);

        // Select variant based on weight
        $cumulativeWeight = 0;
        foreach ($variants as $variant) {
            $cumulativeWeight += $variant->weight;
            if ($random <= $cumulativeWeight) {
                return $variant;
            }
        }

        // Fallback to first variant
        return $variants->first();
    }

    /**
     * Record a conversion for an enrolled subscriber.
     */
    public function recordConversion(
        FunnelAbTest $test,
        FunnelSubscriber $funnelSubscriber,
        float $value = 0
    ): bool {
        $enrollment = FunnelAbEnrollment::where('ab_test_id', $test->id)
            ->where('funnel_subscriber_id', $funnelSubscriber->id)
            ->first();

        if (!$enrollment) {
            Log::warning("No enrollment found for subscriber {$funnelSubscriber->id} in test {$test->id}");
            return false;
        }

        $enrollment->markConverted($value);

        // Check if we should auto-declare winner
        if ($test->hasSufficientSample()) {
            $this->checkForWinner($test);
        }

        return true;
    }

    /**
     * Record an event (open, click, etc.) for an enrollment.
     */
    public function recordEvent(
        FunnelAbTest $test,
        FunnelSubscriber $funnelSubscriber,
        string $eventType,
        array $eventData = []
    ): bool {
        $enrollment = FunnelAbEnrollment::where('ab_test_id', $test->id)
            ->where('funnel_subscriber_id', $funnelSubscriber->id)
            ->first();

        if (!$enrollment) {
            return false;
        }

        $enrollment->recordEvent($eventType, $eventData);
        return true;
    }

    /**
     * Check if a winner can be declared based on statistics.
     */
    public function checkForWinner(FunnelAbTest $test): ?FunnelAbVariant
    {
        if (!$test->isRunning()) {
            return $test->winnerVariant;
        }

        // Need at least the sample size
        if (!$test->hasSufficientSample()) {
            return null;
        }

        $variants = $test->variants;
        if ($variants->count() < 2) {
            return null;
        }

        // Get rates based on winning metric
        $rates = $variants->map(function ($variant) use ($test) {
            return [
                'variant' => $variant,
                'rate' => match ($test->winning_metric) {
                    FunnelAbTest::METRIC_CONVERSION_RATE => $variant->getConversionRate(),
                    FunnelAbTest::METRIC_CLICK_RATE => $variant->getClickRate(),
                    FunnelAbTest::METRIC_OPEN_RATE => $variant->getOpenRate(),
                    default => $variant->getConversionRate(),
                },
            ];
        })->sortByDesc('rate')->values();

        $leader = $rates->first();
        $runnerUp = $rates->get(1);

        if (!$leader || !$runnerUp) {
            return null;
        }

        // Simple statistical significance check
        // (In production, use proper chi-squared or z-test)
        $leaderRate = $leader['rate'];
        $runnerUpRate = $runnerUp['rate'];

        // Require at least 10% difference and minimum enrollments per variant
        $minEnrollments = 30;
        if ($leader['variant']->enrollments < $minEnrollments ||
            $runnerUp['variant']->enrollments < $minEnrollments) {
            return null;
        }

        // Calculate lift percentage
        if ($runnerUpRate > 0) {
            $lift = (($leaderRate - $runnerUpRate) / $runnerUpRate) * 100;
        } else {
            $lift = $leaderRate > 0 ? 100 : 0;
        }

        // Require at least 10% lift to declare winner
        if ($lift >= 10) {
            $test->complete($leader['variant']);
            Log::info("Winner declared for test {$test->id}: {$leader['variant']->name} with {$leaderRate}% rate");
            return $leader['variant'];
        }

        return null;
    }

    /**
     * Get test statistics for display.
     */
    public function getTestStats(FunnelAbTest $test): array
    {
        $test->load('variants');

        return [
            'id' => $test->id,
            'name' => $test->name,
            'status' => $test->status,
            'winning_metric' => $test->winning_metric,
            'sample_size' => $test->sample_size,
            'total_enrollments' => $test->getTotalEnrollments(),
            'total_conversions' => $test->getTotalConversions(),
            'overall_conversion_rate' => $test->getOverallConversionRate(),
            'has_sufficient_sample' => $test->hasSufficientSample(),
            'winner_id' => $test->winner_variant_id,
            'started_at' => $test->started_at?->toIso8601String(),
            'winner_declared_at' => $test->winner_declared_at?->toIso8601String(),
            'variants' => $test->variants->map->getStatsArray()->toArray(),
        ];
    }

    /**
     * Get the test associated with a split step.
     */
    public function getTestForStep(FunnelStep $step): ?FunnelAbTest
    {
        return FunnelAbTest::where('split_step_id', $step->id)
            ->with('variants')
            ->first();
    }

    /**
     * Create or get test for a split step (lazy initialization), its variants
     * in line with the step's.
     */
    public function getOrCreateTest(FunnelStep $splitStep): FunnelAbTest
    {
        $test = $this->getTestForStep($splitStep);

        if (!$test) {
            return $this->create($splitStep, [
                'name' => $splitStep->name ?? 'Test A/B',
            ]);
        }

        // The step may have changed since (the builder syncs on save, other paths do not)
        return $this->syncVariants($test, $splitStep);
    }
}
