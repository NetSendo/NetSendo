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
     * Permission tiers for agent step actions (Brain 2.0).
     * Canonical definitions live in ToolRegistry — these are aliases.
     *
     * - read: no side effects (queries, LLM generation)
     * - write: creates/updates internal records (drafts, tags, deals)
     * - send: causes outbound communication (email/SMS reaches people)
     * - destructive: mass mutations or live-automation control —
     *   ALWAYS requires approval, regardless of work mode
     */
    public const TIER_READ = Tools\ToolRegistry::TIER_READ;
    public const TIER_WRITE = Tools\ToolRegistry::TIER_WRITE;
    public const TIER_SEND = Tools\ToolRegistry::TIER_SEND;
    public const TIER_DESTRUCTIVE = Tools\ToolRegistry::TIER_DESTRUCTIVE;

    /**
     * Get the current work mode for a user (global).
     */
    public function getMode(User $user): string
    {
        $settings = AiBrainSettings::getForUser($user->id);
        return $settings->work_mode;
    }

    /**
     * Get the effective mode for a specific agent.
     * Checks per-agent overrides first, falls back to global work_mode.
     */
    public function getModeForAgent(User $user, string $agentType): string
    {
        $settings = AiBrainSettings::getForUser($user->id);
        return $settings->getAgentMode($agentType);
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
     * Get the permission tier for a step action type.
     * Delegates to the central ToolRegistry; unknown actions default to TIER_WRITE.
     */
    public function getActionTier(string $actionType): string
    {
        return Tools\ToolRegistry::tierOf($actionType);
    }

    /**
     * Check if a plan contains any destructive-tier steps.
     */
    public function planHasDestructiveSteps(AiActionPlan $plan): bool
    {
        return $plan->steps()
            ->get()
            ->contains(fn ($step) => $this->getActionTier($step->action_type) === self::TIER_DESTRUCTIVE);
    }

    /**
     * Decide whether a concrete plan requires user approval.
     *
     * Rules:
     * - any destructive-tier step => ALWAYS requires approval
     * - otherwise per-agent effective mode: autonomous executes,
     *   semi_auto/manual require approval
     */
    public function planRequiresApproval(AiActionPlan $plan, User $user): bool
    {
        if ($this->planHasDestructiveSteps($plan)) {
            return true;
        }

        return $this->requiresApproval($user, $plan->agent_type);
    }

    /**
     * Mode-based approval check for an agent type (per-agent override aware).
     */
    public function requiresApproval(User $user, string $agentType): bool
    {
        $settings = AiBrainSettings::getForUser($user->id);

        return match ($settings->getAgentMode($agentType)) {
            self::MODE_AUTONOMOUS => false,
            self::MODE_SEMI_AUTO => true,
            self::MODE_MANUAL => true,
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
