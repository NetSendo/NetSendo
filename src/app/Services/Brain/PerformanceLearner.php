<?php

namespace App\Services\Brain;

use App\Models\AiBrainSettings;
use App\Models\AiPerformanceSnapshot;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * PerformanceLearner — Extracts actionable tuning signals from historical campaign data.
 *
 * Analyzes AiPerformanceSnapshot records to identify patterns:
 * - Best days/hours for sending
 * - Winning subject line patterns
 * - Top-performing campaign types
 * - Underperforming audiences
 *
 * These signals are injected into SituationAnalyzer, CampaignCalendarService,
 * and TaskScorer to create a closed-loop learning system.
 */
class PerformanceLearner
{
    /**
     * Minimum snapshots needed before generating meaningful signals.
     */
    private const MIN_SNAPSHOTS = 3;

    /**
     * How many days of data to analyze.
     */
    private const ANALYSIS_WINDOW_DAYS = 60;

    /**
     * Generate tuning signals from historical performance data.
     *
     * @return array{has_data: bool, best_days: array, best_hours: array, winning_types: array, avg_rates: array, top_patterns: array, recommendations: string}
     */
    public function getTuningSignals(User $user): array
    {
        $snapshots = AiPerformanceSnapshot::forUser($user->id)
            ->recent(self::ANALYSIS_WINDOW_DAYS)
            ->orderByDesc('captured_at')
            ->limit(50)
            ->get();

        if ($snapshots->count() < self::MIN_SNAPSHOTS) {
            return ['has_data' => false];
        }

        return [
            'has_data' => true,
            'total_campaigns_analyzed' => $snapshots->count(),
            'avg_rates' => $this->calculateAverageRates($snapshots),
            'best_days' => $this->findBestDays($snapshots),
            'best_hours' => $this->findBestHours($snapshots),
            'winning_types' => $this->findWinningTypes($snapshots),
            'top_patterns' => $this->extractTopPatterns($snapshots),
            'underperforming' => $this->identifyUnderperforming($snapshots),
            'recommendations' => $this->buildRecommendationsSummary($snapshots),
        ];
    }

    /**
     * Calculate a performance affinity score for a task based on historical data.
     * Used by TaskScorer as the 5th scoring dimension.
     *
     * @return int Score 0-25
     */
    public function scorePerformanceAffinity(array $task, array $signals): int
    {
        if (empty($signals['has_data'])) {
            return 12; // Neutral score when no data
        }

        $score = 10; // Base score
        $category = $task['category'] ?? '';
        $agent = $task['agent'] ?? '';

        // Boost if agent/category matches historically winning types
        $winningTypes = $signals['winning_types'] ?? [];
        foreach ($winningTypes as $type) {
            if (str_contains($category, $type['type'] ?? '') ||
                str_contains($agent, $type['agent'] ?? '')) {
                $score += min(8, (int) ($type['success_rate'] * 8));
                break;
            }
        }

        // Boost tasks that target patterns similar to top performers
        $topPatterns = $signals['top_patterns'] ?? [];
        $taskText = strtolower(($task['title'] ?? '') . ' ' . ($task['action'] ?? ''));
        foreach ($topPatterns as $pattern) {
            $patternText = strtolower($pattern['pattern'] ?? '');
            if (!empty($patternText) && str_contains($taskText, $patternText)) {
                $score += 5;
                break;
            }
        }

        // Penalize if task targets underperforming categories
        $underperforming = $signals['underperforming'] ?? [];
        foreach ($underperforming as $up) {
            if (str_contains($category, $up['category'] ?? '')) {
                $score -= 5;
                break;
            }
        }

        return max(0, min(25, $score));
    }

    /**
     * Format tuning signals as a prompt section for AI context injection.
     */
    public function formatAsPromptContext(array $signals): string
    {
        if (empty($signals['has_data'])) {
            return '';
        }

        $lines = ["--- PERFORMANCE INSIGHTS (from {$signals['total_campaigns_analyzed']} recent campaigns) ---"];

        // Average rates
        $avg = $signals['avg_rates'] ?? [];
        if (!empty($avg)) {
            $lines[] = "Average performance: Open Rate {$avg['open_rate']}%, Click Rate {$avg['click_rate']}%";
        }

        // Best days
        $bestDays = $signals['best_days'] ?? [];
        if (!empty($bestDays)) {
            $dayNames = array_map(fn($d) => $d['day_name'] ?? '', $bestDays);
            $lines[] = "Best send days: " . implode(', ', array_filter($dayNames));
        }

        // Best hours
        $bestHours = $signals['best_hours'] ?? [];
        if (!empty($bestHours)) {
            $hours = array_map(fn($h) => ($h['hour'] ?? 0) . ':00', $bestHours);
            $lines[] = "Best send hours: " . implode(', ', $hours);
        }

        // Top patterns from what_worked
        $patterns = $signals['top_patterns'] ?? [];
        if (!empty($patterns)) {
            $lines[] = "Winning patterns: " . implode('; ', array_map(fn($p) => $p['pattern'] ?? '', $patterns));
        }

        // Recommendations
        if (!empty($signals['recommendations'])) {
            $lines[] = "Recommendations: " . $signals['recommendations'];
        }

        $lines[] = "---";
        return implode("\n", $lines) . "\n";
    }

    /**
     * Format user strategy settings as a prompt section.
     */
    public function formatStrategyAsPromptContext(array $strategy, string $agentType): string
    {
        if (empty($strategy)) {
            return '';
        }

        $lines = ["--- USER STRATEGY CONSTRAINTS ({$agentType}) ---"];

        foreach ($strategy as $key => $value) {
            $label = str_replace('_', ' ', ucfirst($key));
            if (is_array($value)) {
                if (empty($value)) continue;
                $lines[] = "{$label}: " . implode(', ', $value);
            } elseif (is_bool($value)) {
                $lines[] = "{$label}: " . ($value ? 'Yes' : 'No');
            } else {
                $lines[] = "{$label}: {$value}";
            }
        }

        $lines[] = "---";
        return implode("\n", $lines) . "\n";
    }

    /**
     * Calculate average performance rates.
     */
    protected function calculateAverageRates($snapshots): array
    {
        return [
            'open_rate' => round($snapshots->avg('open_rate'), 2),
            'click_rate' => round($snapshots->avg('click_rate'), 2),
            'unsubscribe_rate' => round($snapshots->avg('unsubscribe_rate'), 3),
            'bounce_rate' => round($snapshots->avg('bounce_rate'), 3),
        ];
    }

    /**
     * Find the best days of the week for sending.
     */
    protected function findBestDays($snapshots): array
    {
        $dayNames = ['', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

        $byDay = $snapshots
            ->filter(fn($s) => $s->campaign_sent_at !== null)
            ->groupBy(fn($s) => $s->campaign_sent_at->dayOfWeekIso)
            ->map(fn($group, $day) => [
                'day' => (int) $day,
                'day_name' => $dayNames[$day] ?? "Day {$day}",
                'avg_open_rate' => round($group->avg('open_rate'), 2),
                'campaigns' => $group->count(),
            ])
            ->sortByDesc('avg_open_rate')
            ->values()
            ->take(3)
            ->toArray();

        return $byDay;
    }

    /**
     * Find the best hours for sending.
     */
    protected function findBestHours($snapshots): array
    {
        $byHour = $snapshots
            ->filter(fn($s) => $s->campaign_sent_at !== null)
            ->groupBy(fn($s) => $s->campaign_sent_at->hour)
            ->map(fn($group, $hour) => [
                'hour' => (int) $hour,
                'avg_open_rate' => round($group->avg('open_rate'), 2),
                'campaigns' => $group->count(),
            ])
            ->sortByDesc('avg_open_rate')
            ->values()
            ->take(3)
            ->toArray();

        return $byHour;
    }

    /**
     * Find which campaign types perform best.
     */
    protected function findWinningTypes($snapshots): array
    {
        $byType = $snapshots
            ->groupBy('agent_type')
            ->map(fn($group, $type) => [
                'type' => $type,
                'agent' => $type,
                'avg_open_rate' => round($group->avg('open_rate'), 2),
                'success_rate' => round(
                    $group->filter(fn($s) => $s->isAboveAverage())->count() / max(1, $group->count()),
                    2
                ),
                'campaigns' => $group->count(),
            ])
            ->sortByDesc('success_rate')
            ->values()
            ->toArray();

        return $byType;
    }

    /**
     * Extract top-performing patterns from what_worked data.
     */
    protected function extractTopPatterns($snapshots): array
    {
        $patterns = [];

        // Collect all "what_worked" entries from above-average campaigns
        $topCampaigns = $snapshots->filter(fn($s) => $s->isAboveAverage());

        foreach ($topCampaigns as $snapshot) {
            $whatWorked = $snapshot->what_worked ?? [];
            foreach ($whatWorked as $item) {
                if (is_string($item) && strlen($item) > 5) {
                    $key = mb_strtolower(mb_substr($item, 0, 50));
                    if (!isset($patterns[$key])) {
                        $patterns[$key] = [
                            'pattern' => $item,
                            'count' => 0,
                        ];
                    }
                    $patterns[$key]['count']++;
                }
            }
        }

        // Sort by frequency and return top 5
        usort($patterns, fn($a, $b) => $b['count'] - $a['count']);
        return array_slice($patterns, 0, 5);
    }

    /**
     * Identify consistently underperforming areas.
     */
    protected function identifyUnderperforming($snapshots): array
    {
        $underperforming = [];

        $belowAverage = $snapshots->filter(fn($s) => !$s->isAboveAverage());
        if ($belowAverage->count() < 2) {
            return [];
        }

        // Aggregate what_to_improve patterns
        $improvements = [];
        foreach ($belowAverage as $snapshot) {
            $toImprove = $snapshot->what_to_improve ?? [];
            foreach ($toImprove as $item) {
                if (is_string($item) && strlen($item) > 5) {
                    $key = mb_strtolower(mb_substr($item, 0, 50));
                    if (!isset($improvements[$key])) {
                        $improvements[$key] = [
                            'category' => $snapshot->agent_type ?? 'unknown',
                            'issue' => $item,
                            'count' => 0,
                        ];
                    }
                    $improvements[$key]['count']++;
                }
            }
        }

        usort($improvements, fn($a, $b) => $b['count'] - $a['count']);
        return array_slice($improvements, 0, 3);
    }

    /**
     * Build a concise recommendations summary string.
     */
    protected function buildRecommendationsSummary($snapshots): string
    {
        $parts = [];
        $avgOpen = round($snapshots->avg('open_rate'), 1);
        $avgClick = round($snapshots->avg('click_rate'), 1);

        if ($avgOpen < 20) {
            $parts[] = "Focus on improving subject lines — current avg open rate ({$avgOpen}%) is below industry standard";
        } elseif ($avgOpen > 30) {
            $parts[] = "Strong open rates ({$avgOpen}%) — maintain current subject line approach";
        }

        if ($avgClick < 2) {
            $parts[] = "CTR needs work ({$avgClick}%) — experiment with CTA placement and copy";
        } elseif ($avgClick > 4) {
            $parts[] = "Excellent click-through ({$avgClick}%) — audience is highly engaged";
        }

        $topCampaigns = $snapshots->filter(fn($s) => $s->isAboveAverage());
        if ($topCampaigns->count() > 0) {
            $successRate = round(($topCampaigns->count() / $snapshots->count()) * 100);
            $parts[] = "{$successRate}% of campaigns exceeded benchmarks";
        }

        return implode('. ', $parts);
    }
}
