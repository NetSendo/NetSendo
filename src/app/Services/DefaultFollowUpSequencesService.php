<?php

namespace App\Services;

use App\Models\CrmFollowUpSequence;
use App\Models\CrmFollowUpStep;
use Illuminate\Support\Facades\DB;

class DefaultFollowUpSequencesService
{
    /**
     * Get all default sequence definitions.
     */
    public function getDefaultSequences(): array
    {
        return [
            'new_lead_nurture' => [
                'name' => __('crm.default_sequences.new_lead_nurture.name'),
                'description' => __('crm.default_sequences.new_lead_nurture.description'),
                'trigger_type' => 'on_contact_created',
                'steps' => [
                    [
                        'position' => 0,
                        'delay_days' => 0,
                        'delay_hours' => 1,
                        'action_type' => 'task',
                        'task_type' => 'call',
                        'task_title' => __('crm.default_sequences.new_lead_nurture.steps.0.title'),
                        'task_description' => __('crm.default_sequences.new_lead_nurture.steps.0.description'),
                        'task_priority' => 'high',
                        'condition_if_no_response' => 'continue',
                    ],
                    [
                        'position' => 1,
                        'delay_days' => 3,
                        'delay_hours' => 0,
                        'action_type' => 'task',
                        'task_type' => 'email',
                        'task_title' => __('crm.default_sequences.new_lead_nurture.steps.1.title'),
                        'task_description' => __('crm.default_sequences.new_lead_nurture.steps.1.description'),
                        'task_priority' => 'medium',
                        'condition_if_no_response' => 'continue',
                    ],
                    [
                        'position' => 2,
                        'delay_days' => 7,
                        'delay_hours' => 0,
                        'action_type' => 'task',
                        'task_type' => 'call',
                        'task_title' => __('crm.default_sequences.new_lead_nurture.steps.2.title'),
                        'task_description' => __('crm.default_sequences.new_lead_nurture.steps.2.description'),
                        'task_priority' => 'medium',
                        'condition_if_no_response' => 'continue',
                    ],
                    [
                        'position' => 3,
                        'delay_days' => 14,
                        'delay_hours' => 0,
                        'action_type' => 'task',
                        'task_type' => 'email',
                        'task_title' => __('crm.default_sequences.new_lead_nurture.steps.3.title'),
                        'task_description' => __('crm.default_sequences.new_lead_nurture.steps.3.description'),
                        'task_priority' => 'high',
                        'condition_if_no_response' => 'stop',
                    ],
                ],
            ],

            'contact_recovery' => [
                'name' => __('crm.default_sequences.contact_recovery.name'),
                'description' => __('crm.default_sequences.contact_recovery.description'),
                'trigger_type' => 'manual',
                'steps' => [
                    [
                        'position' => 0,
                        'delay_days' => 0,
                        'delay_hours' => 0,
                        'action_type' => 'task',
                        'task_type' => 'call',
                        'task_title' => __('crm.default_sequences.contact_recovery.steps.0.title'),
                        'task_description' => __('crm.default_sequences.contact_recovery.steps.0.description'),
                        'task_priority' => 'high',
                        'condition_if_no_response' => 'continue',
                    ],
                    [
                        'position' => 1,
                        'delay_days' => 3,
                        'delay_hours' => 0,
                        'action_type' => 'task',
                        'task_type' => 'email',
                        'task_title' => __('crm.default_sequences.contact_recovery.steps.1.title'),
                        'task_description' => __('crm.default_sequences.contact_recovery.steps.1.description'),
                        'task_priority' => 'medium',
                        'condition_if_no_response' => 'continue',
                    ],
                    [
                        'position' => 2,
                        'delay_days' => 7,
                        'delay_hours' => 0,
                        'action_type' => 'task',
                        'task_type' => 'call',
                        'task_title' => __('crm.default_sequences.contact_recovery.steps.2.title'),
                        'task_description' => __('crm.default_sequences.contact_recovery.steps.2.description'),
                        'task_priority' => 'high',
                        'condition_if_no_response' => 'stop',
                    ],
                ],
            ],

            'after_meeting' => [
                'name' => __('crm.default_sequences.after_meeting.name'),
                'description' => __('crm.default_sequences.after_meeting.description'),
                'trigger_type' => 'on_task_completed',
                'steps' => [
                    [
                        'position' => 0,
                        'delay_days' => 0,
                        'delay_hours' => 2,
                        'action_type' => 'task',
                        'task_type' => 'email',
                        'task_title' => __('crm.default_sequences.after_meeting.steps.0.title'),
                        'task_description' => __('crm.default_sequences.after_meeting.steps.0.description'),
                        'task_priority' => 'high',
                        'condition_if_no_response' => 'continue',
                    ],
                    [
                        'position' => 1,
                        'delay_days' => 2,
                        'delay_hours' => 0,
                        'action_type' => 'task',
                        'task_type' => 'call',
                        'task_title' => __('crm.default_sequences.after_meeting.steps.1.title'),
                        'task_description' => __('crm.default_sequences.after_meeting.steps.1.description'),
                        'task_priority' => 'medium',
                        'condition_if_no_response' => 'continue',
                    ],
                    [
                        'position' => 2,
                        'delay_days' => 5,
                        'delay_hours' => 0,
                        'action_type' => 'task',
                        'task_type' => 'call',
                        'task_title' => __('crm.default_sequences.after_meeting.steps.2.title'),
                        'task_description' => __('crm.default_sequences.after_meeting.steps.2.description'),
                        'task_priority' => 'high',
                        'condition_if_no_response' => 'stop',
                    ],
                ],
            ],

            'sales_closing' => [
                'name' => __('crm.default_sequences.sales_closing.name'),
                'description' => __('crm.default_sequences.sales_closing.description'),
                'trigger_type' => 'on_deal_stage_changed',
                'steps' => [
                    [
                        'position' => 0,
                        'delay_days' => 0,
                        'delay_hours' => 0,
                        'action_type' => 'task',
                        'task_type' => 'email',
                        'task_title' => __('crm.default_sequences.sales_closing.steps.0.title'),
                        'task_description' => __('crm.default_sequences.sales_closing.steps.0.description'),
                        'task_priority' => 'high',
                        'condition_if_no_response' => 'continue',
                    ],
                    [
                        'position' => 1,
                        'delay_days' => 1,
                        'delay_hours' => 0,
                        'action_type' => 'task',
                        'task_type' => 'call',
                        'task_title' => __('crm.default_sequences.sales_closing.steps.1.title'),
                        'task_description' => __('crm.default_sequences.sales_closing.steps.1.description'),
                        'task_priority' => 'high',
                        'condition_if_no_response' => 'continue',
                    ],
                    [
                        'position' => 2,
                        'delay_days' => 3,
                        'delay_hours' => 0,
                        'action_type' => 'task',
                        'task_type' => 'call',
                        'task_title' => __('crm.default_sequences.sales_closing.steps.2.title'),
                        'task_description' => __('crm.default_sequences.sales_closing.steps.2.description'),
                        'task_priority' => 'medium',
                        'condition_if_no_response' => 'continue',
                    ],
                    [
                        'position' => 3,
                        'delay_days' => 7,
                        'delay_hours' => 0,
                        'action_type' => 'task',
                        'task_type' => 'call',
                        'task_title' => __('crm.default_sequences.sales_closing.steps.3.title'),
                        'task_description' => __('crm.default_sequences.sales_closing.steps.3.description'),
                        'task_priority' => 'high',
                        'condition_if_no_response' => 'stop',
                    ],
                ],
            ],
        ];
    }

    /**
     * Create default sequences for a user.
     */
    public function createDefaultsForUser(int $userId): array
    {
        $createdSequences = [];

        DB::transaction(function () use ($userId, &$createdSequences) {
            foreach ($this->getDefaultSequences() as $key => $definition) {
                // Check if this default already exists for the user
                $exists = CrmFollowUpSequence::forUser($userId)
                    ->byDefaultKey($key)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $sequence = CrmFollowUpSequence::create([
                    'user_id' => $userId,
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'trigger_type' => $definition['trigger_type'],
                    'is_active' => true,
                    'is_default' => true,
                    'default_key' => $key,
                ]);

                foreach ($definition['steps'] as $stepData) {
                    $sequence->steps()->create($stepData);
                }

                $createdSequences[] = $sequence;
            }
        });

        return $createdSequences;
    }

    /**
     * Restore default sequences for a user (removes existing and creates new defaults).
     */
    public function restoreDefaultsForUser(int $userId): array
    {
        $createdSequences = [];

        DB::transaction(function () use ($userId, &$createdSequences) {
            // Delete all existing sequences for this user
            CrmFollowUpSequence::forUser($userId)->delete();

            // Create fresh defaults
            foreach ($this->getDefaultSequences() as $key => $definition) {
                $sequence = CrmFollowUpSequence::create([
                    'user_id' => $userId,
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'trigger_type' => $definition['trigger_type'],
                    'is_active' => true,
                    'is_default' => true,
                    'default_key' => $key,
                ]);

                foreach ($definition['steps'] as $stepData) {
                    $sequence->steps()->create($stepData);
                }

                $createdSequences[] = $sequence;
            }
        });

        return $createdSequences;
    }

    /**
     * Check if user has any default sequences.
     */
    public function hasDefaults(int $userId): bool
    {
        return CrmFollowUpSequence::forUser($userId)->default()->exists();
    }

    /**
     * Get count of default sequences available.
     */
    public function getDefaultsCount(): int
    {
        return count($this->getDefaultSequences());
    }
}
