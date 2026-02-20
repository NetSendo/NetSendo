<?php

namespace App\Services\Brain;

use App\Models\AiBrainSettings;
use App\Models\AiActionPlan;
use App\Models\AiPendingApproval;
use App\Models\User;

class ModeController
{
    /**
     * Available work modes.
     */
    public const MODE_AUTONOMOUS = 'autonomous';
    public const MODE_SEMI_AUTO = 'semi_auto';
    public const MODE_MANUAL = 'manual';

    /**
     * Actions that always require approval regardless of mode.
     */
    private const CRITICAL_ACTIONS = [
        'delete_list',
        'delete_all_subscribers',
        'send_to_all',
        'change_domain_settings',
    ];

    /**
     * Get the current work mode for a user.
     */
    public function getMode(User $user): string
    {
        $settings = AiBrainSettings::getForUser($user->id);
        return $settings->work_mode;
    }

    /**
     * Set the work mode for a user.
     */
    public function setMode(User $user, string $mode): void
    {
        if (!in_array($mode, [self::MODE_AUTONOMOUS, self::MODE_SEMI_AUTO, self::MODE_MANUAL])) {
            throw new \InvalidArgumentException("Invalid work mode: {$mode}");
        }

        $settings = AiBrainSettings::getForUser($user->id);
        $settings->update(['work_mode' => $mode]);
    }

    /**
     * Check if an action requires user approval.
     */
    public function requiresApproval(string $actionType, User $user): bool
    {
        // Critical actions always need approval
        if (in_array($actionType, self::CRITICAL_ACTIONS)) {
            return true;
        }

        $settings = AiBrainSettings::getForUser($user->id);

        return match ($settings->work_mode) {
            self::MODE_AUTONOMOUS => false,
            self::MODE_SEMI_AUTO => true,
            self::MODE_MANUAL => true, // manual doesn't execute, but still "requires approval" conceptually
            default => true,
        };
    }

    /**
     * Request approval for an action plan.
     */
    public function requestApproval(AiActionPlan $plan, User $user, string $channel = 'web'): AiPendingApproval
    {
        $plan->update(['status' => 'pending_approval']);

        return AiPendingApproval::create([
            'ai_action_plan_id' => $plan->id,
            'user_id' => $user->id,
            'channel' => $channel,
            'status' => 'pending',
            'summary' => $this->buildApprovalSummary($plan),
            'expires_at' => now()->addHours(24),
        ]);
    }

    /**
     * Process an approval decision.
     */
    public function processApproval(int $approvalId, bool $approved, ?string $reason = null): AiPendingApproval
    {
        $approval = AiPendingApproval::findOrFail($approvalId);

        if ($approval->isExpired()) {
            throw new \RuntimeException('Approval has expired.');
        }

        if ($approved) {
            $approval->approve();
        } else {
            $approval->reject($reason);
        }

        return $approval->fresh();
    }

    /**
     * Get mode label for display.
     */
    public function getModeLabel(string $mode): string
    {
        return match ($mode) {
            self::MODE_AUTONOMOUS => 'brain.mode.autonomous_label',
            self::MODE_SEMI_AUTO => 'brain.mode.semi_auto_label',
            self::MODE_MANUAL => 'brain.mode.manual_label',
            default => $mode,
        };
    }

    /**
     * Get description for display.
     */
    public function getModeDescription(string $mode): string
    {
        return match ($mode) {
            self::MODE_AUTONOMOUS => 'brain.mode.autonomous_desc',
            self::MODE_SEMI_AUTO => 'brain.mode.semi_auto_desc',
            self::MODE_MANUAL => 'brain.mode.manual_desc',
            default => '',
        };
    }

    /**
     * Build a human-readable approval summary.
     */
    private function buildApprovalSummary(AiActionPlan $plan): string
    {
        $steps = $plan->steps()->orderBy('step_order')->get();
        $summary = "📋 **{$plan->title}**\n\n";

        if ($plan->description) {
            $summary .= "{$plan->description}\n\n";
        }

        $summary .= "**" . __('brain.steps_to_execute') . "**\n";
        foreach ($steps as $step) {
            $summary .= "  {$step->step_order}. {$step->title}\n";
        }

        $summary .= "\n🕐 " . __('brain.approval_expiry');

        return $summary;
    }
}
