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

class CheckDomainDnsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

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

        try {
            Log::info('CheckDomainDnsJob: Starting DNS check', [
                'domain' => $this->domain->domain,
                'domain_id' => $this->domain->id,
            ]);

            // First verify CNAME if not already verified
            if (!$this->domain->cname_verified) {
                $verified = $verificationService->verifyCname($this->domain);

                if (!$verified) {
                    Log::info('CheckDomainDnsJob: CNAME not verified yet', [
                        'domain' => $this->domain->domain,
                    ]);

                    // Schedule next check
                    $this->domain->scheduleNextCheck();
                    return;
                }
            }

            // Perform DNS check
            $result = $verificationService->checkDnsRecords($this->domain);

            Log::info('CheckDomainDnsJob: DNS check completed', [
                'domain' => $this->domain->domain,
                'overall_status' => $this->domain->fresh()->overall_status,
                'spf_status' => $result->spf->status,
                'dkim_status' => $result->dkim->status,
                'dmarc_status' => $result->dmarc->status,
            ]);

            // Check if we need to send alerts
            $this->checkAndSendAlerts($result);

        } catch (\Exception $e) {
            Log::error('CheckDomainDnsJob: Error checking DNS', [
                'domain' => $this->domain->domain,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Check if alerts should be sent based on status changes.
     */
    private function checkAndSendAlerts($result): void
    {
        if (!$this->domain->alerts_enabled) {
            return;
        }

        $domain = $this->domain->fresh();

        // Check for critical issues
        if ($domain->overall_status === DomainConfiguration::OVERALL_CRITICAL) {
            // TODO: Send alert notification
            Log::warning('CheckDomainDnsJob: Domain has critical issues', [
                'domain' => $domain->domain,
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('CheckDomainDnsJob failed', [
            'domain' => $this->domain->domain,
            'error' => $exception->getMessage(),
        ]);
    }
}
