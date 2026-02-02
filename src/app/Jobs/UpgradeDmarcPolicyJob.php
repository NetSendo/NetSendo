<?php

namespace App\Jobs;

use App\Models\DomainConfiguration;
use App\Services\Deliverability\DomainVerificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class UpgradeDmarcPolicyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Days to wait before policy upgrade
     */
    private const UPGRADE_DAYS = 14;

    /**
     * Policy upgrade path
     */
    private const POLICY_PATH = [
        'none' => 'quarantine',
        'quarantine' => 'reject',
    ];

    public function __construct(
        public DomainConfiguration $domain
    ) {}

    /**
     * Execute the job.
     */
    public function handle(DomainVerificationService $verificationService): void
    {
        // Skip deleted domains
        if ($this->domain->trashed()) {
            return;
        }

        // Skip domains that haven't been verified
        if (!$this->domain->cname_verified) {
            return;
        }

        // Skip domains with DMARC issues
        if ($this->domain->dmarc_status !== 'pass') {
            return;
        }

        $currentPolicy = $this->domain->dmarc_policy;

        // Check if upgrade is possible
        if (!isset(self::POLICY_PATH[$currentPolicy])) {
            Log::info('UpgradeDmarcPolicyJob: Policy already at maximum', [
                'domain' => $this->domain->domain,
                'policy' => $currentPolicy,
            ]);
            return;
        }

        // Check if enough time has passed since last check
        $lastCheck = $this->domain->last_check_at;
        if ($lastCheck && $lastCheck->diffInDays(now()) < self::UPGRADE_DAYS) {
            Log::info('UpgradeDmarcPolicyJob: Not enough time passed for upgrade', [
                'domain' => $this->domain->domain,
                'days_since_check' => $lastCheck->diffInDays(now()),
                'required_days' => self::UPGRADE_DAYS,
            ]);
            return;
        }

        // Check recent delivery issues from check history
        $history = $this->domain->check_history ?? [];
        $recentIssues = collect($history)
            ->filter(fn($check) =>
                Carbon::parse($check['checked_at'])->isAfter(now()->subDays(self::UPGRADE_DAYS))
            )
            ->filter(fn($check) =>
                ($check['overall_status'] ?? '') === 'critical' ||
                ($check['overall_status'] ?? '') === 'warning'
            )
            ->count();

        if ($recentIssues > 0) {
            Log::info('UpgradeDmarcPolicyJob: Recent issues detected, skipping upgrade', [
                'domain' => $this->domain->domain,
                'recent_issues' => $recentIssues,
            ]);
            return;
        }

        // All checks passed - upgrade recommendation can be sent
        $newPolicy = self::POLICY_PATH[$currentPolicy];

        Log::info('UpgradeDmarcPolicyJob: Recommending policy upgrade', [
            'domain' => $this->domain->domain,
            'current_policy' => $currentPolicy,
            'recommended_policy' => $newPolicy,
        ]);

        // TODO: Send notification to user about recommended upgrade
        // For now, just log the recommendation
        // In production, this could:
        // 1. Send an email to the user
        // 2. Create an in-app notification
        // 3. Update a recommendations field in the domain config
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('UpgradeDmarcPolicyJob failed', [
            'domain' => $this->domain->domain,
            'error' => $exception->getMessage(),
        ]);
    }
}
