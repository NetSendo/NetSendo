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
     * Get provider-specific configuration for DNS verification
     */
    public function getProviderConfig(?string $provider): array
    {
        if ($provider === null) {
            return [
                'spf_includes' => ['netsendo', 'sendgrid', 'amazonses'], // Generic fallback
                'dkim_selectors' => ['netsendo', 'default'],
                'provider_name' => null,
            ];
        }

        return match($provider) {
            'sendgrid' => [
                'spf_includes' => ['sendgrid.net'],
                'dkim_selectors' => ['s1', 's2', 'sendgrid'],
                'provider_name' => 'SendGrid',
            ],
            'gmail' => [
                'spf_includes' => ['_spf.google.com'],
                'dkim_selectors' => ['google', 'default'],
                'provider_name' => 'Gmail',
            ],
            'smtp' => [
                'spf_includes' => [], // Custom SMTP - user manages their own SPF
                'dkim_selectors' => ['default', 'mail', 'dkim'],
                'provider_name' => 'SMTP',
            ],
            default => [
                'spf_includes' => [],
                'dkim_selectors' => ['default'],
                'provider_name' => null,
            ],
        };
    }

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

        // Get provider from linked mailbox (if any)
        $provider = $config->mailbox?->provider;
        $providerConfig = $this->getProviderConfig($provider);

        // Check SPF with provider-specific includes
        $spfRecord = $this->dnsLookup->getSpfRecord($config->domain);
        $result->spf = $this->analyzeSpfRecord($spfRecord, $providerConfig);

        // Check DKIM using provider-specific selectors
        $dkimRecord = null;
        $checkedSelectors = [];
        foreach ($providerConfig['dkim_selectors'] as $selector) {
            $dkimRecord = $this->dnsLookup->getDkimRecord($config->domain, $selector);
            $checkedSelectors[] = $selector;
            if ($dkimRecord !== null) {
                break; // Found a valid DKIM record
            }
        }
        $result->dkim = $this->analyzeDkimRecord($dkimRecord, $checkedSelectors);

        // Check DMARC
        $dmarcRecord = $this->dnsLookup->getDmarcRecord($config->domain);
        $result->dmarc = $this->analyzeDmarcRecord($dmarcRecord);

        // Store raw records
        $result->rawRecords = [
            'spf' => $spfRecord,
            'dkim' => $dkimRecord,
            'dmarc' => $dmarcRecord,
            'provider' => $providerConfig['provider_name'],
            'dkim_selectors_checked' => $checkedSelectors,
        ];

        // Update domain configuration
        $this->updateDomainStatus($config, $result);

        return $result;
    }
    /**
     * Analyze SPF record and return status
     */
    private function analyzeSpfRecord(?string $record, array $providerConfig = []): RecordAnalysis
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

        // Get required SPF includes for the provider
        $requiredIncludes = $providerConfig['spf_includes'] ?? ['netsendo', 'sendgrid', 'amazonses'];
        $providerName = $providerConfig['provider_name'] ?? null;

        // Skip include check if provider doesn't require specific includes (e.g., custom SMTP)
        if (!empty($requiredIncludes)) {
            $hasRequiredInclude = false;
            foreach ($parsed['includes'] as $include) {
                foreach ($requiredIncludes as $required) {
                    if (str_contains($include, $required)) {
                        $hasRequiredInclude = true;
                        break 2;
                    }
                }
            }

            if (!$hasRequiredInclude) {
                $analysis->status = DomainConfiguration::STATUS_WARNING;
                $analysis->issues[] = [
                    'code' => 'spf_no_include',
                    'severity' => 'warning',
                    'message_key' => 'deliverability.issues.spf_no_provider_include',
                    'context' => [
                        'provider' => $providerName,
                        'required' => implode(', ', $requiredIncludes),
                    ],
                ];
            }
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
    private function analyzeDkimRecord(?string $record, array $checkedSelectors = []): RecordAnalysis
    {
        $analysis = new RecordAnalysis('dkim');

        if ($record === null) {
            $analysis->status = DomainConfiguration::STATUS_PENDING;
            $analysis->issues[] = [
                'code' => 'dkim_missing',
                'severity' => 'warning',
                'message_key' => 'deliverability.issues.dkim_missing',
                'context' => [
                    'selectors_checked' => implode(', ', $checkedSelectors),
                ],
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

    /**
     * Generate optimal DMARC record for one-click setup
     *
     * @param DomainConfiguration $domain
     * @return array Contains 'initial' and 'recommended' record configurations
     */
    public function generateOptimalDmarcRecord(DomainConfiguration $domain): array
    {
        $reportEmail = 'dmarc-reports@' . $domain->domain;

        return [
            'current' => [
                'policy' => $domain->dmarc_policy ?? 'none',
                'record' => $domain->dns_records['dmarc'] ?? null,
            ],
            'initial' => [
                'host' => '_dmarc.' . $domain->domain,
                'host_display' => '_dmarc',
                'type' => 'TXT',
                'value' => "v=DMARC1; p=quarantine; pct=100; rua=mailto:{$reportEmail}; ruf=mailto:{$reportEmail}; adkim=s; aspf=s",
                'ttl' => 3600,
                'policy' => 'quarantine',
                'explanation_key' => 'deliverability.dmarc_generator.initial_explanation',
                'is_recommended_first_step' => true,
            ],
            'recommended' => [
                'host' => '_dmarc.' . $domain->domain,
                'host_display' => '_dmarc',
                'type' => 'TXT',
                'value' => "v=DMARC1; p=reject; pct=100; rua=mailto:{$reportEmail}; adkim=s; aspf=s",
                'ttl' => 3600,
                'policy' => 'reject',
                'explanation_key' => 'deliverability.dmarc_generator.recommended_explanation',
                'upgrade_after_days' => 14,
            ],
            'minimal' => [
                'host' => '_dmarc.' . $domain->domain,
                'host_display' => '_dmarc',
                'type' => 'TXT',
                'value' => "v=DMARC1; p=quarantine; rua=mailto:{$reportEmail}",
                'ttl' => 3600,
                'policy' => 'quarantine',
                'explanation_key' => 'deliverability.dmarc_generator.minimal_explanation',
            ],
            'report_email' => $reportEmail,
            'can_upgrade' => $domain->dmarc_policy === 'quarantine',
            'should_configure' => in_array($domain->dmarc_policy, [null, 'none', '']),
        ];
    }

    /**
     * Generate optimal SPF record for one-click setup
     *
     * @param DomainConfiguration $domain
     * @return array Contains optimized SPF configuration
     */
    public function generateOptimalSpfRecord(DomainConfiguration $domain): array
    {
        $provider = $domain->mailbox?->provider ?? null;
        $providerConfig = $this->getProviderConfig($provider);

        // Build the optimal SPF includes
        $includes = [];
        $explanation = [];

        // Add provider-specific includes
        foreach ($providerConfig['spf_includes'] as $include) {
            if (!empty($include)) {
                $includes[] = "include:{$include}";
                $explanation[] = $providerConfig['provider_name'] ?? $include;
            }
        }

        // If no specific provider, add NetSendo default
        if (empty($includes)) {
            $includes[] = 'include:_spf.netsendo.com';
            $explanation[] = 'NetSendo';
        }

        $includesStr = implode(' ', $includes);
        $lookupCount = count($includes);

        // Get current SPF to check if we need IP addresses
        $currentSpf = $domain->dns_records['spf'] ?? null;
        $parsedCurrent = $currentSpf ? $this->dnsLookup->parseSpfRecord($currentSpf) : null;

        // Preserve existing IP addresses from current SPF
        $ipAddresses = [];
        if ($parsedCurrent && !empty($parsedCurrent['ips'])) {
            foreach ($parsedCurrent['ips'] as $ip) {
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    $ipAddresses[] = "ip4:{$ip}";
                } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                    $ipAddresses[] = "ip6:{$ip}";
                }
            }
        }

        $ipStr = !empty($ipAddresses) ? ' ' . implode(' ', $ipAddresses) : '';

        return [
            'current' => [
                'record' => $currentSpf,
                'parsed' => $parsedCurrent,
                'lookup_count' => $this->countSpfLookups($currentSpf),
            ],
            'optimal' => [
                'host' => $domain->domain,
                'host_display' => '@',
                'type' => 'TXT',
                'value' => "v=spf1{$ipStr} {$includesStr} -all",
                'ttl' => 3600,
                'lookup_count' => $lookupCount,
                'providers' => $explanation,
                'uses_hard_fail' => true,
                'explanation_key' => 'deliverability.spf_generator.optimal_explanation',
            ],
            'soft_fail' => [
                'host' => $domain->domain,
                'host_display' => '@',
                'type' => 'TXT',
                'value' => "v=spf1{$ipStr} {$includesStr} ~all",
                'ttl' => 3600,
                'lookup_count' => $lookupCount,
                'uses_hard_fail' => false,
                'explanation_key' => 'deliverability.spf_generator.softfail_explanation',
            ],
            'provider' => $providerConfig['provider_name'],
            'max_lookups' => 10,
            'is_within_limit' => $lookupCount <= 10,
            'needs_optimization' => $this->countSpfLookups($currentSpf) > 8,
        ];
    }

    /**
     * Count DNS lookups in SPF record (approximate)
     */
    private function countSpfLookups(?string $spf): int
    {
        if (!$spf) {
            return 0;
        }

        $count = 0;

        // Count includes
        $count += preg_match_all('/include:/i', $spf);

        // Count redirects
        $count += preg_match_all('/redirect=/i', $spf);

        // Count mx
        $count += preg_match_all('/\bmx\b/i', $spf);

        // Count a records
        $count += preg_match_all('/\ba\b/i', $spf);

        // Count ptr (deprecated but still counts)
        $count += preg_match_all('/\bptr\b/i', $spf);

        return $count;
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
