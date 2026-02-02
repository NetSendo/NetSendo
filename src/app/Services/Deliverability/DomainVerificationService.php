<?php

namespace App\Services\Deliverability;

use App\Models\DomainConfiguration;
use Illuminate\Support\Facades\Log;

class DomainVerificationService
{
    public function __construct(
        private DnsLookupService $dnsLookup
    ) {}

    /**
     * Get the verify domain from APP_URL
     */
    private function getVerifyDomain(): string
    {
        $appUrl = config('app.url');
        $parsed = parse_url($appUrl);
        return $parsed['host'] ?? 'localhost';
    }

    /**
     * Check if the application is running on localhost/development environment
     * DNS verification cannot work in this environment
     */
    public static function isLocalhostEnvironment(): bool
    {
        $appUrl = config('app.url');
        $parsed = parse_url($appUrl);
        $host = $parsed['host'] ?? 'localhost';

        $localHosts = ['localhost', '127.0.0.1', '0.0.0.0', '::1'];

        // Check direct match
        if (in_array($host, $localHosts, true)) {
            return true;
        }

        // Check for .local or .test domains (common dev domains)
        if (preg_match('/\.(local|test|localhost|dev)$/i', $host)) {
            return true;
        }

        return false;
    }

    /**
     * Generate CNAME instruction for user
     */
    public function generateCnameInstruction(DomainConfiguration $config): array
    {
        return [
            'record_type' => 'CNAME',
            'host' => '_netsendo.' . $config->domain,
            'target' => $config->cname_selector . '.' . $this->getVerifyDomain(),
            'ttl' => 3600,
            'display_host' => '_netsendo', // Simplified for user
        ];
    }

    /**
     * Verify CNAME record is properly configured
     */
    public function verifyCname(DomainConfiguration $config): bool
    {
        $expectedHost = '_netsendo.' . $config->domain;
        $expectedTarget = $config->cname_selector . '.' . $this->getVerifyDomain();

        // Clear cache to get fresh result
        $this->dnsLookup->clearCache($config->domain);

        $actualTarget = $this->dnsLookup->lookupCname($expectedHost);

        if ($actualTarget === null) {
            Log::info("CNAME verification failed for {$config->domain}: No record found");
            return false;
        }

        // Normalize targets (remove trailing dots)
        $actualTarget = rtrim($actualTarget, '.');
        $expectedTarget = rtrim($expectedTarget, '.');

        $verified = strtolower($actualTarget) === strtolower($expectedTarget);

        if ($verified && !$config->cname_verified) {
            $config->update([
                'cname_verified' => true,
                'cname_verified_at' => now(),
            ]);

            // Schedule immediate DNS check
            $config->scheduleNextCheck();
        }

        return $verified;
    }

    /**
     * Check all DNS records for a domain
     */
    public function checkDnsRecords(DomainConfiguration $config): DnsCheckResult
    {
        $result = new DnsCheckResult();

        // Clear cache for fresh lookup
        $this->dnsLookup->clearCache($config->domain);

        // Check SPF
        $spfRecord = $this->dnsLookup->getSpfRecord($config->domain);
        $result->spf = $this->analyzeSpfRecord($spfRecord);

        // Check DKIM (using NetSendo selector)
        $dkimRecord = $this->dnsLookup->getDkimRecord($config->domain, 'netsendo');
        $result->dkim = $this->analyzeDkimRecord($dkimRecord);

        // Check DMARC
        $dmarcRecord = $this->dnsLookup->getDmarcRecord($config->domain);
        $result->dmarc = $this->analyzeDmarcRecord($dmarcRecord);

        // Store raw records
        $result->rawRecords = [
            'spf' => $spfRecord,
            'dkim' => $dkimRecord,
            'dmarc' => $dmarcRecord,
        ];

        // Update domain configuration
        $this->updateDomainStatus($config, $result);

        return $result;
    }

    /**
     * Analyze SPF record and return status
     */
    private function analyzeSpfRecord(?string $record): RecordAnalysis
    {
        $analysis = new RecordAnalysis('spf');

        if ($record === null) {
            $analysis->status = DomainConfiguration::STATUS_WARNING;
            $analysis->issues[] = [
                'code' => 'spf_missing',
                'severity' => 'warning',
                'message_key' => 'deliverability.issues.spf_missing',
            ];
            return $analysis;
        }

        $parsed = $this->dnsLookup->parseSpfRecord($record);
        $analysis->parsed = $parsed;

        // Check for NetSendo include
        $hasNetsendoInclude = false;
        foreach ($parsed['includes'] as $include) {
            if (str_contains($include, 'netsendo') || str_contains($include, 'sendgrid') || str_contains($include, 'amazonses')) {
                $hasNetsendoInclude = true;
                break;
            }
        }

        if (!$hasNetsendoInclude) {
            $analysis->status = DomainConfiguration::STATUS_WARNING;
            $analysis->issues[] = [
                'code' => 'spf_no_include',
                'severity' => 'warning',
                'message_key' => 'deliverability.issues.spf_no_include',
            ];
        }

        // Check for -all (hard fail) vs ~all (soft fail)
        if ($parsed['all'] === '-all') {
            $analysis->status = $analysis->status ?? DomainConfiguration::STATUS_VALID;
        } elseif ($parsed['all'] === '~all') {
            $analysis->status = $analysis->status ?? DomainConfiguration::STATUS_VALID;
            $analysis->warnings[] = [
                'code' => 'spf_softfail',
                'message_key' => 'deliverability.warnings.spf_softfail',
            ];
        } elseif ($parsed['all'] === '?all' || $parsed['all'] === '+all') {
            $analysis->status = DomainConfiguration::STATUS_WARNING;
            $analysis->issues[] = [
                'code' => 'spf_permissive',
                'severity' => 'warning',
                'message_key' => 'deliverability.issues.spf_permissive',
            ];
        }

        if ($analysis->status === null) {
            $analysis->status = DomainConfiguration::STATUS_VALID;
        }

        return $analysis;
    }

    /**
     * Analyze DKIM record and return status
     */
    private function analyzeDkimRecord(?string $record): RecordAnalysis
    {
        $analysis = new RecordAnalysis('dkim');

        if ($record === null) {
            $analysis->status = DomainConfiguration::STATUS_PENDING;
            $analysis->issues[] = [
                'code' => 'dkim_missing',
                'severity' => 'warning',
                'message_key' => 'deliverability.issues.dkim_missing',
            ];
            return $analysis;
        }

        // Basic validation - check for required parts
        if (!str_contains($record, 'p=')) {
            $analysis->status = DomainConfiguration::STATUS_CRITICAL;
            $analysis->issues[] = [
                'code' => 'dkim_invalid',
                'severity' => 'critical',
                'message_key' => 'deliverability.issues.dkim_invalid',
            ];
            return $analysis;
        }

        $analysis->status = DomainConfiguration::STATUS_VALID;
        return $analysis;
    }

    /**
     * Analyze DMARC record and return status
     */
    private function analyzeDmarcRecord(?string $record): RecordAnalysis
    {
        $analysis = new RecordAnalysis('dmarc');

        if ($record === null) {
            $analysis->status = DomainConfiguration::STATUS_CRITICAL;
            $analysis->issues[] = [
                'code' => 'dmarc_missing',
                'severity' => 'critical',
                'message_key' => 'deliverability.issues.dmarc_missing',
            ];
            return $analysis;
        }

        $parsed = $this->dnsLookup->parseDmarcRecord($record);
        $analysis->parsed = $parsed;

        // Check policy
        if ($parsed['policy'] === 'none') {
            $analysis->status = DomainConfiguration::STATUS_WARNING;
            $analysis->issues[] = [
                'code' => 'dmarc_none',
                'severity' => 'warning',
                'message_key' => 'deliverability.issues.dmarc_none',
            ];
        } elseif ($parsed['policy'] === 'quarantine') {
            $analysis->status = DomainConfiguration::STATUS_VALID;
            $analysis->warnings[] = [
                'code' => 'dmarc_quarantine',
                'message_key' => 'deliverability.warnings.dmarc_quarantine',
            ];
        } else {
            $analysis->status = DomainConfiguration::STATUS_VALID;
        }

        // Check for reporting
        if (empty($parsed['rua'])) {
            $analysis->warnings[] = [
                'code' => 'dmarc_no_reporting',
                'message_key' => 'deliverability.warnings.dmarc_no_reporting',
            ];
        }

        return $analysis;
    }

    /**
     * Update domain configuration with check results
     */
    private function updateDomainStatus(DomainConfiguration $config, DnsCheckResult $result): void
    {
        $config->update([
            'spf_status' => $result->spf->status,
            'dkim_status' => $result->dkim->status,
            'dmarc_status' => $result->dmarc->status,
            'dns_records' => $result->rawRecords,
            'last_check_at' => now(),
        ]);

        // Recalculate overall status
        $config->recalculateStatus();

        // Add to history
        $config->addCheckToHistory([
            'spf' => $result->spf->status,
            'dkim' => $result->dkim->status,
            'dmarc' => $result->dmarc->status,
            'overall' => $config->overall_status,
        ]);

        // Schedule next check
        $config->scheduleNextCheck();
    }

    /**
     * Get human-readable status for UI
     */
    public function getHumanReadableStatus(DomainConfiguration $config): array
    {
        return [
            'overall' => $this->translateStatus($config->overall_status),
            'cname' => [
                'verified' => $config->cname_verified,
                'label_key' => $config->cname_verified
                    ? 'deliverability.cname.verified'
                    : 'deliverability.cname.pending',
            ],
            'spf' => $this->translateRecordStatus('spf', $config->spf_status),
            'dkim' => $this->translateRecordStatus('dkim', $config->dkim_status),
            'dmarc' => $this->translateRecordStatus('dmarc', $config->dmarc_status),
            'dmarc_policy' => [
                'policy' => $config->dmarc_policy,
                'label_key' => 'deliverability.dmarc_policy.' . $config->dmarc_policy,
            ],
        ];
    }

    /**
     * Translate status to UI-friendly format
     */
    private function translateStatus(string $status): array
    {
        $translations = [
            DomainConfiguration::OVERALL_PENDING => [
                'color' => 'yellow',
                'icon' => 'clock',
                'label_key' => 'deliverability.status.pending',
            ],
            DomainConfiguration::OVERALL_SAFE => [
                'color' => 'green',
                'icon' => 'shield-check',
                'label_key' => 'deliverability.status.safe',
            ],
            DomainConfiguration::OVERALL_WARNING => [
                'color' => 'yellow',
                'icon' => 'exclamation-triangle',
                'label_key' => 'deliverability.status.warning',
            ],
            DomainConfiguration::OVERALL_CRITICAL => [
                'color' => 'red',
                'icon' => 'x-circle',
                'label_key' => 'deliverability.status.critical',
            ],
        ];

        return $translations[$status] ?? $translations[DomainConfiguration::OVERALL_PENDING];
    }

    /**
     * Translate record status
     */
    private function translateRecordStatus(string $recordType, string $status): array
    {
        $baseKey = "deliverability.{$recordType}";

        return [
            'status' => $status,
            'label_key' => "{$baseKey}.{$status}",
        ];
    }
}

/**
 * Result of DNS check
 */
class DnsCheckResult
{
    public RecordAnalysis $spf;
    public RecordAnalysis $dkim;
    public RecordAnalysis $dmarc;
    public array $rawRecords = [];

    public function __construct()
    {
        $this->spf = new RecordAnalysis('spf');
        $this->dkim = new RecordAnalysis('dkim');
        $this->dmarc = new RecordAnalysis('dmarc');
    }

    public function hasIssues(): bool
    {
        return !empty($this->spf->issues)
            || !empty($this->dkim->issues)
            || !empty($this->dmarc->issues);
    }

    public function hasCriticalIssues(): bool
    {
        $allIssues = array_merge(
            $this->spf->issues,
            $this->dkim->issues,
            $this->dmarc->issues
        );

        foreach ($allIssues as $issue) {
            if (($issue['severity'] ?? '') === 'critical') {
                return true;
            }
        }

        return false;
    }
}

/**
 * Analysis of a single DNS record
 */
class RecordAnalysis
{
    public string $type;
    public ?string $status = null;
    public array $issues = [];
    public array $warnings = [];
    public ?array $parsed = null;

    public function __construct(string $type)
    {
        $this->type = $type;
    }
}
