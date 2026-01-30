<?php

namespace App\Services;

use App\Models\AbTest;
use App\Models\AbTestVariant;
use App\Models\Message;
use App\Models\MessageQueueEntry;
use App\Models\Subscriber;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AbTestService
{
    protected AbTestStatisticsService $statisticsService;

    public function __construct(AbTestStatisticsService $statisticsService)
    {
        $this->statisticsService = $statisticsService;
    }

    /**
     * Create a new A/B test for a message.
     */
    public function createTest(Message $message, array $config): AbTest
    {
        return DB::transaction(function () use ($message, $config) {
            $test = AbTest::create([
                'message_id' => $message->id,
                'user_id' => $message->user_id,
                'name' => $config['name'] ?? null,
                'status' => AbTest::STATUS_DRAFT,
                'test_type' => $config['test_type'] ?? AbTest::TYPE_SUBJECT,
                'winning_metric' => $config['winning_metric'] ?? AbTest::METRIC_OPEN_RATE,
                'sample_percentage' => $config['sample_percentage'] ?? 20,
                'test_duration_hours' => $config['test_duration_hours'] ?? 4,
                'auto_select_winner' => $config['auto_select_winner'] ?? true,
                'confidence_threshold' => $config['confidence_threshold'] ?? 95,
                'test_settings' => $config['settings'] ?? null,
            ]);

            // Create variants if provided
            if (!empty($config['variants'])) {
                foreach ($config['variants'] as $index => $variantData) {
                    $this->createVariant($test, $variantData, $index === 0);
                }
            } else {
                // Create default A variant from message
                $this->createVariant($test, [
                    'variant_letter' => 'A',
                    'subject' => $message->subject,
                    'preheader' => $message->preheader,
                    'content' => null, // Use message content by default
                ], true);
            }

            return $test->load('variants');
        });
    }

    /**
     * Create a variant for a test.
     */
    public function createVariant(AbTest $test, array $data, bool $isControl = false): AbTestVariant
    {
        return AbTestVariant::create([
            'ab_test_id' => $test->id,
            'variant_letter' => $data['variant_letter'] ?? $this->getNextVariantLetter($test),
            'subject' => $data['subject'] ?? null,
            'preheader' => $data['preheader'] ?? null,
            'content' => $data['content'] ?? null,
            'from_name' => $data['from_name'] ?? null,
            'from_email' => $data['from_email'] ?? null,
            'scheduled_send_time' => $data['scheduled_send_time'] ?? null,
            'weight' => $data['weight'] ?? 100,
            'is_control' => $isControl,
            'is_ai_generated' => $data['is_ai_generated'] ?? false,
            'metadata' => $data['metadata'] ?? null,
        ]);
    }

    /**
     * Get the next available variant letter.
     */
    protected function getNextVariantLetter(AbTest $test): string
    {
        $usedLetters = $test->variants()->pluck('variant_letter')->toArray();

        foreach (AbTestVariant::VARIANT_LETTERS as $letter) {
            if (!in_array($letter, $usedLetters)) {
                return $letter;
            }
        }

        throw new \RuntimeException('Maximum number of variants (5) reached');
    }

    /**
     * Start running a test.
     */
    public function startTest(AbTest $test): void
    {
        if ($test->status !== AbTest::STATUS_DRAFT) {
            throw new \InvalidArgumentException('Only draft tests can be started');
        }

        if ($test->variants()->count() < 2) {
            throw new \InvalidArgumentException('At least 2 variants are required to start a test');
        }

        $test->update([
            'status' => AbTest::STATUS_RUNNING,
            'test_started_at' => now(),
        ]);

        Log::info("A/B Test started", [
            'test_id' => $test->id,
            'message_id' => $test->message_id,
            'variants' => $test->variants()->count(),
        ]);
    }

    /**
     * Pause a running test.
     */
    public function pauseTest(AbTest $test): void
    {
        if ($test->status !== AbTest::STATUS_RUNNING) {
            throw new \InvalidArgumentException('Only running tests can be paused');
        }

        $test->update(['status' => AbTest::STATUS_PAUSED]);
    }

    /**
     * Resume a paused test.
     */
    public function resumeTest(AbTest $test): void
    {
        if ($test->status !== AbTest::STATUS_PAUSED) {
            throw new \InvalidArgumentException('Only paused tests can be resumed');
        }

        $test->update(['status' => AbTest::STATUS_RUNNING]);
    }

    /**
     * Cancel a test.
     */
    public function cancelTest(AbTest $test): void
    {
        if (in_array($test->status, [AbTest::STATUS_COMPLETED, AbTest::STATUS_CANCELLED])) {
            throw new \InvalidArgumentException('Test is already finished');
        }

        $test->update([
            'status' => AbTest::STATUS_CANCELLED,
            'test_ended_at' => now(),
        ]);
    }

    /**
     * Assign a variant to a subscriber using balanced distribution.
     *
     * Instead of pure random selection (which can cause uneven distribution with small samples),
     * this method assigns subscribers to the variant that is most "behind" its target ratio.
     * This ensures even distribution (e.g., 1-1-1-1 instead of potentially 4-0 with random).
     */
    public function assignVariant(AbTest $test, Subscriber $subscriber): AbTestVariant
    {
        $variants = $test->variants()->get();

        if ($variants->count() === 0) {
            throw new \RuntimeException('No variants available for assignment');
        }

        if ($variants->count() === 1) {
            return $variants->first();
        }

        // Get current counts for each variant
        $variantCounts = [];
        $totalWeight = $variants->sum('weight');

        foreach ($variants as $variant) {
            $variantCounts[$variant->id] = [
                'variant' => $variant,
                'count' => MessageQueueEntry::where('ab_test_variant_id', $variant->id)
                    ->whereIn('status', [
                        MessageQueueEntry::STATUS_PLANNED,
                        MessageQueueEntry::STATUS_QUEUED,
                        MessageQueueEntry::STATUS_SENT,
                    ])
                    ->count(),
                'weight' => $variant->weight,
                'target_ratio' => $variant->weight / $totalWeight,
            ];
        }

        // Calculate total assigned so far
        $totalAssigned = array_sum(array_column($variantCounts, 'count'));

        // Find the variant that is most "behind" its target ratio
        // Formula: (current_count / (total + 1)) vs target_ratio
        // We pick the variant where adding one more would bring it closest to its target
        $bestVariant = null;
        $bestScore = PHP_INT_MAX;

        foreach ($variantCounts as $data) {
            // Calculate how far this variant would be from its target if we assign to it
            $newCount = $data['count'] + 1;
            $newTotal = $totalAssigned + 1;
            $newRatio = $newCount / $newTotal;
            $deviation = abs($newRatio - $data['target_ratio']);

            // In case of tie (both equally close to target), add small random factor
            $score = $deviation + (mt_rand(0, 1000) / 10000000);

            if ($score < $bestScore) {
                $bestScore = $score;
                $bestVariant = $data['variant'];
            }
        }

        return $bestVariant ?? $variants->first();
    }

    /**
     * Get variant distribution statistics.
     */
    public function getVariantDistribution(AbTest $test): array
    {
        $distribution = [];
        $totalSent = 0;

        foreach ($test->variants as $variant) {
            $sent = $variant->getSentCount();
            $totalSent += $sent;
            $distribution[$variant->variant_letter] = [
                'sent' => $sent,
                'percentage' => 0,
            ];
        }

        // Calculate percentages
        if ($totalSent > 0) {
            foreach ($distribution as $letter => &$data) {
                $data['percentage'] = round(($data['sent'] / $totalSent) * 100, 1);
            }
        }

        return $distribution;
    }

    /**
     * Get comprehensive results for a test.
     */
    public function getResults(AbTest $test): array
    {
        $variants = [];
        $test->load('variants');

        foreach ($test->variants as $variant) {
            $metrics = $variant->getMetrics();
            $variants[$variant->variant_letter] = array_merge($metrics, [
                'id' => $variant->id,
                'variant_letter' => $variant->variant_letter,
                'subject' => $variant->subject,
                'is_control' => $variant->is_control,
                'is_winner' => $test->winner_variant_id === $variant->id,
            ]);
        }

        // Get statistical significance
        $significance = $this->calculateStatisticalSignificance($test);

        return [
            'test_id' => $test->id,
            'status' => $test->status,
            'winning_metric' => $test->winning_metric,
            'variants' => $variants,
            'statistical_significance' => $significance,
            'confidence_threshold' => $test->confidence_threshold,
            'is_significant' => $significance >= $test->confidence_threshold,
            'winner' => $test->winnerVariant?->variant_letter,
            'test_started_at' => $test->test_started_at?->toIso8601String(),
            'test_ended_at' => $test->test_ended_at?->toIso8601String(),
            'duration_elapsed' => $test->hasDurationElapsed(),
        ];
    }

    /**
     * Calculate statistical significance for the test.
     */
    public function calculateStatisticalSignificance(AbTest $test): float
    {
        $variants = $test->variants()->get();

        if ($variants->count() < 2) {
            return 0.0;
        }

        // Get the two best performing variants
        $sorted = $variants->sortByDesc(function ($variant) use ($test) {
            return match ($test->winning_metric) {
                AbTest::METRIC_OPEN_RATE => $variant->getOpenRate(),
                AbTest::METRIC_CLICK_RATE => $variant->getClickRate(),
                default => $variant->getOpenRate(),
            };
        });

        $best = $sorted->first();
        $second = $sorted->skip(1)->first();

        // Get metrics based on winning metric type
        $getMetric = fn($v) => match ($test->winning_metric) {
            AbTest::METRIC_OPEN_RATE => ['successes' => $v->getUniqueOpenCount(), 'total' => $v->getSentCount()],
            AbTest::METRIC_CLICK_RATE => ['successes' => $v->getUniqueClickCount(), 'total' => $v->getSentCount()],
            default => ['successes' => $v->getUniqueOpenCount(), 'total' => $v->getSentCount()],
        };

        $metricsA = $getMetric($best);
        $metricsB = $getMetric($second);

        if ($metricsA['total'] === 0 || $metricsB['total'] === 0) {
            return 0.0;
        }

        return $this->statisticsService->calculateConfidence(
            $metricsA['successes'],
            $metricsA['total'],
            $metricsB['successes'],
            $metricsB['total']
        );
    }

    /**
     * Determine and set the winner variant.
     */
    public function determineWinner(AbTest $test): ?AbTestVariant
    {
        $winner = $test->determineWinner();

        if ($winner) {
            $test->update([
                'winner_variant_id' => $winner->id,
            ]);
        }

        return $winner;
    }

    /**
     * Complete test and optionally send winner to remaining audience.
     */
    public function completeTest(AbTest $test, bool $sendToRemaining = true): void
    {
        $winner = $this->determineWinner($test);

        $test->update([
            'status' => AbTest::STATUS_COMPLETED,
            'test_ended_at' => now(),
            'final_results' => $this->getResults($test),
        ]);

        Log::info("A/B Test completed", [
            'test_id' => $test->id,
            'winner_variant' => $winner?->variant_letter,
            'send_to_remaining' => $sendToRemaining,
        ]);

        if ($sendToRemaining && $winner) {
            $this->sendWinnerToRemaining($test);
        }
    }

    /**
     * Send the winning variant to the remaining audience.
     */
    public function sendWinnerToRemaining(AbTest $test): void
    {
        if (!$test->winner_variant_id) {
            throw new \InvalidArgumentException('No winner selected');
        }

        // Get remaining subscribers (those not in the test sample)
        $message = $test->message;
        $testedSubscriberIds = MessageQueueEntry::where('message_id', $message->id)
            ->whereNotNull('ab_test_variant_id')
            ->pluck('subscriber_id')
            ->toArray();

        $remainingSubscribers = $message->getUniqueRecipients()
            ->reject(fn($s) => in_array($s->id, $testedSubscriberIds));

        // Create queue entries for remaining subscribers with winner variant
        foreach ($remainingSubscribers as $subscriber) {
            MessageQueueEntry::updateOrCreate(
                [
                    'message_id' => $message->id,
                    'subscriber_id' => $subscriber->id,
                ],
                [
                    'ab_test_variant_id' => $test->winner_variant_id,
                    'status' => 'planned',
                ]
            );
        }

        $test->update(['winner_sent_at' => now()]);

        Log::info("Sending winner to remaining audience", [
            'test_id' => $test->id,
            'winner_variant_id' => $test->winner_variant_id,
            'remaining_count' => $remainingSubscribers->count(),
        ]);
    }

    /**
     * Manually select a winner variant.
     */
    public function selectWinnerManually(AbTest $test, AbTestVariant $variant): void
    {
        if ($variant->ab_test_id !== $test->id) {
            throw new \InvalidArgumentException('Variant does not belong to this test');
        }

        $test->update([
            'winner_variant_id' => $variant->id,
            'status' => AbTest::STATUS_COMPLETED,
            'test_ended_at' => now(),
            'final_results' => $this->getResults($test),
        ]);

        Log::info("Winner manually selected", [
            'test_id' => $test->id,
            'winner_variant' => $variant->variant_letter,
        ]);
    }

    /**
     * Process all tests that are ready for evaluation.
     */
    public function processReadyTests(): void
    {
        $tests = AbTest::readyToEvaluate()
            ->where('auto_select_winner', true)
            ->get();

        foreach ($tests as $test) {
            try {
                $significance = $this->calculateStatisticalSignificance($test);

                if ($significance >= $test->confidence_threshold || $test->hasDurationElapsed()) {
                    $this->completeTest($test, true);
                }
            } catch (\Exception $e) {
                Log::error("Error processing A/B test", [
                    'test_id' => $test->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Get recommended sample size for a test.
     */
    public function getRecommendedSampleSize(
        float $baselineRate,
        float $minimumDetectableEffect = 0.1, // 10% improvement
        float $power = 0.8
    ): int {
        return $this->statisticsService->calculateMinimumSampleSize(
            $baselineRate,
            $minimumDetectableEffect,
            $power
        );
    }
}
