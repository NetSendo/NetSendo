<?php

namespace App\Services\Funnels;

use App\Models\Funnel;
use App\Models\FunnelStep;
use App\Models\Message;
use App\Models\ContactList;
use App\Models\SubscriptionForm;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FunnelService
{
    /**
     * Create a new funnel.
     */
    public function create(array $data): Funnel
    {
        return DB::transaction(function () use ($data) {
            $funnel = Funnel::create([
                'user_id' => $data['user_id'],
                'name' => $data['name'],
                'status' => Funnel::STATUS_DRAFT,
                'trigger_type' => $data['trigger_type'] ?? Funnel::TRIGGER_LIST_SIGNUP,
                'trigger_list_id' => $data['trigger_list_id'] ?? null,
                'trigger_form_id' => $data['trigger_form_id'] ?? null,
                'trigger_tag' => $data['trigger_tag'] ?? null,
                'settings' => $data['settings'] ?? [],
            ]);

            // Create default start step
            $funnel->steps()->create([
                'type' => FunnelStep::TYPE_START,
                'name' => 'Start',
                'position_x' => 250,
                'position_y' => 50,
                'order' => 0,
            ]);

            return $funnel;
        });
    }

    /**
     * Update funnel basic info.
     */
    public function update(Funnel $funnel, array $data): Funnel
    {
        $funnel->fill([
            'name' => $data['name'] ?? $funnel->name,
            'trigger_type' => $data['trigger_type'] ?? $funnel->trigger_type,
            'trigger_list_id' => $data['trigger_list_id'] ?? $funnel->trigger_list_id,
            'trigger_form_id' => $data['trigger_form_id'] ?? $funnel->trigger_form_id,
            'trigger_tag' => $data['trigger_tag'] ?? $funnel->trigger_tag,
            'settings' => $data['settings'] ?? $funnel->settings,
        ]);

        $funnel->save();

        return $funnel;
    }

    /**
     * Update funnel steps from flow builder data.
     */
    public function updateSteps(Funnel $funnel, array $nodes, array $edges = []): Funnel
    {
        return DB::transaction(function () use ($funnel, $nodes, $edges) {
            // Create temporary ID mapping
            $tempIdMap = [];
            $existingIds = $funnel->steps()->pluck('id')->toArray();
            $incomingIds = [];

            // First pass: create/update all nodes
            foreach ($nodes as $index => $node) {
                $nodeId = $node['id'] ?? null;
                $isNew = !is_numeric($nodeId) || !in_array($nodeId, $existingIds);

                $stepData = [
                    'type' => $node['type'] ?? FunnelStep::TYPE_EMAIL,
                    'name' => $node['data']['name'] ?? null,
                    'message_id' => $node['data']['message_id'] ?? null,
                    'delay_value' => $node['data']['delay_value'] ?? null,
                    'delay_unit' => $node['data']['delay_unit'] ?? null,
                    'condition_type' => $node['data']['condition_type'] ?? null,
                    'condition_config' => $node['data']['condition_config'] ?? null,
                    'action_type' => $node['data']['action_type'] ?? null,
                    'action_config' => $node['data']['action_config'] ?? null,
                    // New enhanced fields
                    'sms_content' => $node['data']['sms_content'] ?? null,
                    'wait_until_type' => $node['data']['wait_until_type'] ?? null,
                    'wait_until_date' => $node['data']['wait_until_date'] ?? null,
                    'wait_until_time' => $node['data']['wait_until_time'] ?? null,
                    'wait_until_timezone' => $node['data']['wait_until_timezone'] ?? null,
                    'goal_name' => $node['data']['goal_name'] ?? null,
                    'goal_type' => $node['data']['goal_type'] ?? null,
                    'goal_value' => $node['data']['goal_value'] ?? null,
                    'goal_config' => $node['data']['goal_config'] ?? null,
                    'split_variants' => $node['data']['split_variants'] ?? null,
                    'position_x' => (int) ($node['position']['x'] ?? 250),
                    'position_y' => (int) ($node['position']['y'] ?? 100),
                    'order' => $index,
                ];

                if ($isNew) {
                    $step = $funnel->steps()->create($stepData);
                    $tempIdMap[$nodeId] = $step->id;
                } else {
                    $step = FunnelStep::find($nodeId);
                    $step->update($stepData);
                    $tempIdMap[$nodeId] = $step->id;
                }

                $incomingIds[] = $tempIdMap[$nodeId];
            }

            // Delete steps that are no longer present
            $funnel->steps()
                ->whereNotIn('id', $incomingIds)
                ->delete();

            // Second pass: update connections
            foreach ($edges as $edge) {
                $sourceId = $tempIdMap[$edge['source']] ?? null;
                $targetId = $tempIdMap[$edge['target']] ?? null;
                $handleId = $edge['sourceHandle'] ?? 'default';

                if (!$sourceId || !$targetId) {
                    continue;
                }

                $sourceStep = FunnelStep::find($sourceId);
                if (!$sourceStep) {
                    continue;
                }

                // Determine which connection field to update
                if ($sourceStep->isCondition()) {
                    if ($handleId === 'yes' || $handleId === 'true') {
                        $sourceStep->next_step_yes_id = $targetId;
                    } elseif ($handleId === 'no' || $handleId === 'false') {
                        $sourceStep->next_step_no_id = $targetId;
                    } else {
                        $sourceStep->next_step_id = $targetId;
                    }
                } else {
                    $sourceStep->next_step_id = $targetId;
                }

                $sourceStep->save();
            }

            return $funnel->fresh('steps');
        });
    }

    /**
     * Get available messages for email steps.
     */
    public function getAvailableMessages(int $userId): \Illuminate\Database\Eloquent\Collection
    {
        return Message::where('user_id', $userId)
            ->where('status', 'ready')
            ->orderBy('subject')
            ->get(['id', 'subject', 'created_at']);
    }

    /**
     * Get available contact lists for triggers.
     */
    public function getAvailableLists(int $userId): \Illuminate\Database\Eloquent\Collection
    {
        return ContactList::where('user_id', $userId)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Get available forms for triggers.
     */
    public function getAvailableForms(int $userId): \Illuminate\Database\Eloquent\Collection
    {
        return SubscriptionForm::where('user_id', $userId)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Prepare funnel data for flow builder.
     */
    public function prepareForBuilder(Funnel $funnel): array
    {
        $nodes = [];
        $edges = [];

        foreach ($funnel->steps as $step) {
            $nodes[] = [
                'id' => (string) $step->id,
                'type' => $step->type,
                'position' => [
                    'x' => $step->position_x,
                    'y' => $step->position_y,
                ],
                'data' => [
                    'name' => $step->name,
                    'display_name' => $step->display_name,
                    'message_id' => $step->message_id,
                    'message_subject' => $step->message?->subject,
                    'delay_value' => $step->delay_value,
                    'delay_unit' => $step->delay_unit,
                    'delay_display' => $step->delay_display,
                    'condition_type' => $step->condition_type,
                    'condition_config' => $step->condition_config,
                    'action_type' => $step->action_type,
                    'action_config' => $step->action_config,
                    // New enhanced fields
                    'sms_content' => $step->sms_content,
                    'wait_until_type' => $step->wait_until_type,
                    'wait_until_date' => $step->wait_until_date?->format('Y-m-d'),
                    'wait_until_time' => $step->wait_until_time,
                    'wait_until_timezone' => $step->wait_until_timezone,
                    'goal_name' => $step->goal_name,
                    'goal_type' => $step->goal_type,
                    'goal_value' => $step->goal_value,
                    'goal_config' => $step->goal_config,
                    'split_variants' => $step->split_variants,
                ],
            ];

            // Add edges
            if ($step->next_step_id) {
                $edges[] = [
                    'id' => "e{$step->id}-{$step->next_step_id}",
                    'source' => (string) $step->id,
                    'target' => (string) $step->next_step_id,
                    'sourceHandle' => 'default',
                ];
            }
            if ($step->next_step_yes_id) {
                $edges[] = [
                    'id' => "e{$step->id}-{$step->next_step_yes_id}-yes",
                    'source' => (string) $step->id,
                    'target' => (string) $step->next_step_yes_id,
                    'sourceHandle' => 'yes',
                    'label' => 'Tak',
                ];
            }
            if ($step->next_step_no_id) {
                $edges[] = [
                    'id' => "e{$step->id}-{$step->next_step_no_id}-no",
                    'source' => (string) $step->id,
                    'target' => (string) $step->next_step_no_id,
                    'sourceHandle' => 'no',
                    'label' => 'Nie',
                ];
            }
        }

        return [
            'nodes' => $nodes,
            'edges' => $edges,
        ];
    }

    /**
     * Validate funnel before activation.
     */
    public function validate(Funnel $funnel): array
    {
        $errors = [];

        // Must have at least start step
        $startStep = $funnel->steps()->where('type', FunnelStep::TYPE_START)->first();
        if (!$startStep) {
            $errors[] = 'Lejek musi mieć krok startowy.';
        }

        // Must have at least one email or action step
        $actionSteps = $funnel->steps()
            ->whereIn('type', [FunnelStep::TYPE_EMAIL, FunnelStep::TYPE_ACTION])
            ->count();
        if ($actionSteps === 0) {
            $errors[] = 'Lejek musi zawierać przynajmniej jeden krok z emailem lub akcją.';
        }

        // Check trigger configuration
        if ($funnel->trigger_type === Funnel::TRIGGER_LIST_SIGNUP && !$funnel->trigger_list_id) {
            $errors[] = 'Wybierz listę dla triggera "Po zapisie na listę".';
        }
        if ($funnel->trigger_type === Funnel::TRIGGER_FORM_SUBMIT && !$funnel->trigger_form_id) {
            $errors[] = 'Wybierz formularz dla triggera "Po wypełnieniu formularza".';
        }
        if ($funnel->trigger_type === Funnel::TRIGGER_TAG_ADDED && !$funnel->trigger_tag) {
            $errors[] = 'Podaj tag dla triggera "Po dodaniu tagu".';
        }

        // Check email steps have messages assigned
        $emailStepsWithoutMessage = $funnel->steps()
            ->where('type', FunnelStep::TYPE_EMAIL)
            ->whereNull('message_id')
            ->count();
        if ($emailStepsWithoutMessage > 0) {
            $errors[] = "Wszystkie kroki emailowe muszą mieć przypisany email ({$emailStepsWithoutMessage} bez emaila).";
        }

        return $errors;
    }

    /**
     * Get funnel statistics with advanced analytics.
     */
    public function getStats(Funnel $funnel): array
    {
        $baseStats = $funnel->getStats();

        // Per-step stats with conversion tracking
        $stepStats = [];
        $prevStepCompleted = $baseStats['total_subscribers'] ?? 0;

        $orderedSteps = $funnel->steps->sortBy('order');

        foreach ($orderedSteps as $step) {
            $atStep = $funnel->subscribers()
                ->where('current_step_id', $step->id)
                ->count();

            $completedStep = $funnel->subscribers()
                ->where('steps_completed', '>=', $step->order + 1)
                ->count();

            // Calculate drop-off from previous step
            $dropOff = max(0, $prevStepCompleted - $completedStep - $atStep);

            // Conversion rate from previous step
            $conversionRate = $prevStepCompleted > 0
                ? round(($completedStep / $prevStepCompleted) * 100, 1)
                : 0;

            $stepStats[] = [
                'id' => $step->id,
                'name' => $step->display_name,
                'type' => $step->type,
                'at_step' => $atStep,
                'completed' => $completedStep,
                'drop_off' => $dropOff,
                'conversion_rate' => $conversionRate,
            ];

            $prevStepCompleted = $completedStep;
        }

        // Time metrics
        $timeMetrics = $this->calculateTimeMetrics($funnel);

        // A/B Test stats
        $abTestStats = $this->getAbTestStats($funnel);

        return array_merge($baseStats, [
            'step_stats' => $stepStats,
            'time_metrics' => $timeMetrics,
            'ab_tests' => $abTestStats,
            'steps_count' => $orderedSteps->count(),
        ]);
    }

    /**
     * Calculate time metrics for funnel completion.
     */
    protected function calculateTimeMetrics(Funnel $funnel): array
    {
        $completedSubscribers = $funnel->subscribers()
            ->where('status', FunnelSubscriber::STATUS_COMPLETED)
            ->whereNotNull('completed_at')
            ->whereNotNull('enrolled_at')
            ->get();

        if ($completedSubscribers->isEmpty()) {
            return [
                'avg_completion_time' => null,
                'min_completion_time' => null,
                'max_completion_time' => null,
                'median_completion_time' => null,
            ];
        }

        $completionTimes = $completedSubscribers->map(function ($sub) {
            return $sub->enrolled_at->diffInMinutes($sub->completed_at);
        })->filter()->values();

        if ($completionTimes->isEmpty()) {
            return [
                'avg_completion_time' => null,
                'min_completion_time' => null,
                'max_completion_time' => null,
                'median_completion_time' => null,
            ];
        }

        $sorted = $completionTimes->sort()->values();
        $count = $sorted->count();
        $median = $count % 2 === 0
            ? ($sorted[$count / 2 - 1] + $sorted[$count / 2]) / 2
            : $sorted[floor($count / 2)];

        return [
            'avg_completion_time' => round($completionTimes->avg()),
            'min_completion_time' => $completionTimes->min(),
            'max_completion_time' => $completionTimes->max(),
            'median_completion_time' => round($median),
        ];
    }

    /**
     * Get A/B test statistics for the funnel.
     */
    protected function getAbTestStats(Funnel $funnel): array
    {
        $abTests = \App\Models\FunnelAbTest::where('funnel_id', $funnel->id)
            ->with('variants')
            ->get();

        return $abTests->map(function ($test) {
            return [
                'id' => $test->id,
                'name' => $test->name,
                'status' => $test->status,
                'winning_metric' => $test->winning_metric,
                'winner_id' => $test->winner_variant_id,
                'total_enrollments' => $test->getTotalEnrollments(),
                'overall_conversion_rate' => $test->getOverallConversionRate(),
                'variants' => $test->variants->map(function ($variant) {
                    return [
                        'id' => $variant->id,
                        'name' => $variant->name,
                        'weight' => $variant->weight,
                        'enrollments' => $variant->enrollments,
                        'conversions' => $variant->conversions,
                        'conversion_rate' => $variant->getConversionRate(),
                        'is_winner' => $variant->isWinner(),
                    ];
                })->toArray(),
            ];
        })->toArray();
    }
}
