<?php

namespace App\Services\Deliverability;

use App\Models\DomainConfiguration;
use App\Models\Mailbox;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class DomainVerificationService
{
    public function __construct(
        private DnsLookupService $dnsLookup
    ) {}

    /**
     * DKIM selectors tried for a custom SMTP server whose provider is not recognised.
     * Covers the usual defaults of hosting panels, Microsoft 365, Google and the big ESPs.
     */
    private const GENERIC_DKIM_SELECTORS = ['default', 'mail', 'dkim', 'selector1', 'selector2', 'google', 'k1', 's1', 's2', 'key1'];

    /**
     * Known SMTP relays, recognised by the mailbox's SMTP host.
     *
     * spf_includes: includes the domain's own SPF record needs for this relay. Empty when the relay
     * sends with its own envelope domain (MAIL FROM), so the From domain's SPF is not checked for it.
     * dkim_selector_required: the relay signs with per-domain selectors (tokens) that cannot be
     * guessed, so DKIM can only be checked once the user enters one of them.
     */
    private const SMTP_RELAYS = [
        [
            'pattern' => '/^email-smtp(-fips)?\.[a-z0-9-]+\.amazonaws\.com$/',
            'provider_name' => 'Amazon SES',
            'spf_includes' => [],
            'dkim_selectors' => [],
            'dkim_selector_required' => true,
        ],
        [
            'pattern' => '/(^|\.)sendgrid\.net$/',
            'provider_name' => 'SendGrid',
            'spf_includes' => [],
            'dkim_selectors' => ['s1', 's2'],
            'dkim_selector_required' => false,
        ],
        [
            'pattern' => '/^(smtp|smtp-relay)\.gmail\.com$/',
            'provider_name' => 'Google Workspace',
            'spf_includes' => ['_spf.google.com'],
            'dkim_selectors' => ['google'],
            'dkim_selector_required' => false,
        ],
        [
            'pattern' => '/^smtp\.office365\.com$|(^|\.)mail\.protection\.outlook\.com$/',
            'provider_name' => 'Microsoft 365',
            'spf_includes' => ['spf.protection.outlook.com'],
            'dkim_selectors' => ['selector1', 'selector2'],
            'dkim_selector_required' => false,
        ],
        [
            'pattern' => '/(^|\.)mailgun\.org$/',
            'provider_name' => 'Mailgun',
            'spf_includes' => ['mailgun.org'],
            'dkim_selectors' => ['smtp', 'mailo', 'k1', 'krs', 'mx', 'pic'],
            'dkim_selector_required' => false,
        ],
    ];

    /**
     * Get provider-specific configuration for DNS verification
     *
     * @param string|null $smtpHost SMTP host of an SMTP mailbox, used to recognise known relays
     */
    public function getProviderConfig(?string $provider, ?string $smtpHost = null): array
    {
        if ($provider === null) {
            return [
                'spf_includes' => [], // No default includes - use aggregated config
                'dkim_selectors' => ['netsendo', 'default'],
                'provider_name' => null,
                'dkim_selector_required' => false,
            ];
        }

        if ($provider === 'smtp') {
            $host = strtolower(trim((string) $smtpHost));
            foreach (self::SMTP_RELAYS as $relay) {
                if ($host !== '' && preg_match($relay['pattern'], $host)) {
                    return [
                        'spf_includes' => $relay['spf_includes'],
                        'dkim_selectors' => $relay['dkim_selectors'],
                        'provider_name' => $relay['provider_name'],
                        'dkim_selector_required' => $relay['dkim_selector_required'],
                    ];
                }
            }
        }

        return match($provider) {
            'sendgrid' => [
                'spf_includes' => ['sendgrid.net'],
                'dkim_selectors' => ['s1', 's2', 'sendgrid'],
                'provider_name' => 'SendGrid',
                'dkim_selector_required' => false,
            ],
            'gmail' => [
                'spf_includes' => ['_spf.google.com'],
                'dkim_selectors' => ['google', 'default'],
                'provider_name' => 'Gmail',
                'dkim_selector_required' => false,
            ],
            'smtp' => [
                'spf_includes' => [], // Custom SMTP - user manages their own SPF
                'dkim_selectors' => self::GENERIC_DKIM_SELECTORS,
                'provider_name' => 'SMTP',
                'dkim_selector_required' => false,
            ],
            // The addresses are added per mailbox (getMailboxProviderConfig): an include of the
            // installation's host would need an SPF record there that nobody publishes
            'nmi' => [
                'spf_includes' => [],
                'spf_ips' => [],
                'dkim_selectors' => ['netsendo', 'default'],
                'provider_name' => 'NetSendo MTA',
                'dkim_selector_required' => false,
            ],
            default => [
                'spf_includes' => [],
                'dkim_selectors' => ['default'],
                'provider_name' => null,
                'dkim_selector_required' => false,
            ],
        };
    }

    /**
     * Provider configuration of one mailbox (looks at the SMTP host of SMTP mailboxes)
     */
    public function getMailboxProviderConfig(Mailbox $mailbox): array
    {
        $smtpHost = null;
        if ($mailbox->provider === Mailbox::PROVIDER_SMTP) {
            $smtpHost = $mailbox->getDecryptedCredentials()['host'] ?? null;
        }

        $config = $this->getProviderConfig($mailbox->provider, $smtpHost);

        // NetSendo's own MTA sends from this server: the domain's SPF record must name the
        // address the mail leaves from, the mailbox's dedicated IP or the server's own
        if ($mailbox->provider === Mailbox::PROVIDER_NMI) {
            $ip = $mailbox->dedicatedIp?->ip_address
                ?? app(\App\Services\Nmi\MtaStatusService::class)->serverAddress();
            $config['spf_ips'] = $ip ? [$ip] : [];
        }

        return $config;
    }

    /**
     * Selectors the user entered for the domain (comma or space separated), normalised
     */
    public static function parseDkimSelectors(?string $value): array
    {
        $selectors = [];
        foreach (preg_split('/[\s,;]+/', (string) $value) as $selector) {
            $selector = strtolower(trim($selector));
            // Accept "token._domainkey.example.com" pasted from a provider console
            $selector = preg_replace('/\._domainkey(\..*)?$/', '', $selector);
            if ($selector !== '' && preg_match('/^[a-z0-9]([a-z0-9._-]{0,62})$/', $selector)) {
                $selectors[] = $selector;
            }
        }

        return array_values(array_unique($selectors));
    }

    /**
     * Get the verify domain from APP_URL
     */
    /** "ip4:203.0.113.10" or "ip6:2001:db8::1" */
    public static function spfIpMechanism(string $ip): string
    {
        return (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? 'ip6:' : 'ip4:') . $ip;
    }

    /**
     * Whether an ip4/ip6 mechanism of the record (with or without a CIDR range and a "+"
     * qualifier) covers the address. Includes are not followed: an address that only an
     * included record names is reported as missing.
     */
    public static function spfCoversIp(array $mechanisms, string $ip): bool
    {
        $packed = @inet_pton($ip);
        if ($packed === false) {
            return false;
        }

        foreach ($mechanisms as $mechanism) {
            if (! preg_match('/^\+?ip[46]:([^\/\s]+)(?:\/(\d{1,3}))?$/i', trim($mechanism), $match)) {
                continue;
            }

            $network = @inet_pton($match[1]);
            if ($network === false || strlen($network) !== strlen($packed)) {
                continue;
            }

            $bits = isset($match[2]) && $match[2] !== '' ? (int) $match[2] : strlen($packed) * 8;
            $bits = min($bits, strlen($packed) * 8);
            $bytes = intdiv($bits, 8);
            $rest = $bits % 8;

            if (substr($network, 0, $bytes) !== substr($packed, 0, $bytes)) {
                continue;
            }
            if ($rest === 0) {
                return true;
            }

            $mask = (0xFF << (8 - $rest)) & 0xFF;
            if ((ord($network[$bytes]) & $mask) === (ord($packed[$bytes]) & $mask)) {
                return true;
            }
        }

        return false;
    }

    private function getVerifyDomain(): string
    {
        $appUrl = config('app.url');
        $parsed = parse_url($appUrl);
        return $parsed['host'] ?? 'localhost';
    }

    /**
     * Get all active mailboxes that use a specific domain
     * (based on from_email domain matching)
     */
    public function getMailboxesForDomain(DomainConfiguration $domain): Collection
    {
        return Mailbox::where('user_id', $domain->user_id)
            ->where('from_email', 'like', '%@' . $domain->domain)
            ->active()
            ->get();
    }

    /**
     * Get aggregated provider configuration for a domain
     * Collects SPF includes from ALL mailboxes using this domain
     */
    public function getAggregatedProviderConfig(DomainConfiguration $domain): array
    {
        $mailboxes = $this->getMailboxesForDomain($domain);

        // If no mailboxes found, fall back to linked mailbox (legacy behavior)
        if ($mailboxes->isEmpty() && $domain->mailbox) {
            $mailboxes = collect([$domain->mailbox]);
        }

        $allIncludes = [];
        $allIps = [];
        $allSelectors = [];
        $providers = [];
        $selectorRequiredBy = [];

        foreach ($mailboxes as $mailbox) {
            $config = $this->getMailboxProviderConfig($mailbox);
            $allIncludes = array_merge($allIncludes, $config['spf_includes']);
            $allIps = array_merge($allIps, $config['spf_ips'] ?? []);
            $allSelectors = array_merge($allSelectors, $config['dkim_selectors']);
            if ($config['provider_name']) {
                $providers[] = $config['provider_name'];
            }
            if ($config['dkim_selector_required'] ?? false) {
                $selectorRequiredBy[] = $config['provider_name'];
            }
        }

        if ($mailboxes->isEmpty()) {
            $allSelectors = $this->getProviderConfig(null)['dkim_selectors'];
        }

        // Selectors the user entered come first: they are the ones that really sign the mail
        $customSelectors = self::parseDkimSelectors($domain->dkim_selector);
        $providers = array_values(array_unique($providers));

        // So are the keys NetSendo's own MTA signs with: the domain's key and the keys of
        // the dedicated IPs assigned to it (their selectors are generated, never guessable)
        $nmiSelectors = array_values(array_filter(array_merge(
            [$domain->nmi_dkim_selector],
            $domain->dedicatedIps()->whereNotNull('dkim_selector')->pluck('dkim_selector')->all()
        )));

        return [
            'spf_includes' => array_values(array_unique($allIncludes)),
            'spf_ips' => array_values(array_unique($allIps)),
            'dkim_selectors' => array_values(array_unique(array_merge($customSelectors, $nmiSelectors, $allSelectors))),
            'custom_dkim_selectors' => $customSelectors,
            'provider_name' => count($providers) === 1 ? $providers[0] : null,
            'providers' => $providers,
            'dkim_selector_required_by' => array_values(array_unique($selectorRequiredBy)),
        ];
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

        // Get aggregated provider config from ALL mailboxes using this domain
        $providerConfig = $this->getAggregatedProviderConfig($config);

        // Check SPF with aggregated includes from all providers
        $spfRecord = $this->dnsLookup->getSpfRecord($config->domain);
        $result->spf = $this->analyzeSpfRecord($spfRecord, $providerConfig);

        // Check DKIM using aggregated selectors from all providers
        $dkimRecord = null;
        $checkedSelectors = [];
        foreach ($providerConfig['dkim_selectors'] as $selector) {
            $dkimRecord = $this->dnsLookup->getDkimRecord($config->domain, $selector);
            $checkedSelectors[] = $selector;
            if ($dkimRecord !== null) {
                break; // Found a valid DKIM record
            }
        }
        $result->dkim = $this->analyzeDkimRecord($dkimRecord, $checkedSelectors, $providerConfig);

        // Check DMARC
        $dmarcRecord = $this->dnsLookup->getDmarcRecord($config->domain);
        $result->dmarc = $this->analyzeDmarcRecord($dmarcRecord);

        // Store raw records with all providers info
        $result->rawRecords = [
            'spf' => $spfRecord,
            'dkim' => $dkimRecord,
            'dmarc' => $dmarcRecord,
            'provider' => $providerConfig['provider_name'],
            'providers' => $providerConfig['providers'] ?? [],
            'dkim_selectors_checked' => $checkedSelectors,
            'dkim_selector_found' => $dkimRecord !== null ? end($checkedSelectors) : null,
            'dkim_selector_required_by' => $providerConfig['dkim_selector_required_by'] ?? [],
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

        // Get required SPF includes for the provider (from aggregated config)
        $requiredIncludes = $providerConfig['spf_includes'] ?? [];
        $providers = $providerConfig['providers'] ?? [];
        $providerName = !empty($providers) ? implode(', ', $providers) : ($providerConfig['provider_name'] ?? null);

        // Skip include check if no specific includes are required (e.g., no mailboxes, custom SMTP)
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

        // Addresses NetSendo's own MTA sends from must be in the record
        $missingIps = array_values(array_filter(
            $providerConfig['spf_ips'] ?? [],
            fn (string $ip) => ! self::spfCoversIp($parsed['mechanisms'] ?? [], $ip)
        ));
        if (! empty($missingIps)) {
            $analysis->status = DomainConfiguration::STATUS_WARNING;
            $analysis->issues[] = [
                'code' => 'spf_no_ip',
                'severity' => 'warning',
                'message_key' => 'deliverability.issues.spf_missing_ip',
                'context' => [
                    'ips' => implode(', ', $missingIps),
                    'mechanisms' => implode(' ', array_map(fn ($ip) => self::spfIpMechanism($ip), $missingIps)),
                ],
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
    private function analyzeDkimRecord(?string $record, array $checkedSelectors = [], array $providerConfig = []): RecordAnalysis
    {
        $analysis = new RecordAnalysis('dkim');

        if ($record === null) {
            $analysis->status = DomainConfiguration::STATUS_PENDING;
            $selectorRequiredBy = $providerConfig['dkim_selector_required_by'] ?? [];

            // The relay signs with selectors of its own (e.g. Amazon SES Easy DKIM tokens) and the user has not entered one yet
            if (!empty($selectorRequiredBy) && empty($providerConfig['custom_dkim_selectors'])) {
                $analysis->issues[] = [
                    'code' => 'dkim_selector_needed',
                    'severity' => 'warning',
                    'message_key' => 'deliverability.issues.dkim_selector_needed',
                    'context' => [
                        'provider' => implode(', ', $selectorRequiredBy),
                    ],
                ];
                return $analysis;
            }

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
            // The policy actually published, so the generator and the upgrade advice start from it
            'dmarc_policy' => $result->dmarc->parsed['policy'] ?? DomainConfiguration::DMARC_NONE,
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
        $overall = $this->translateStatus($config->overall_status);
        $spf = $this->translateRecordStatus('spf', $config->spf_status);
        $dkim = $this->translateRecordStatus('dkim', $config->dkim_status);
        $dmarc = $this->translateRecordStatus('dmarc', $config->dmarc_status);

        return [
            'summary' => __($overall['label_key']),
            'overall' => $overall,
            'cname' => [
                'verified' => $config->cname_verified,
                'label_key' => $config->cname_verified
                    ? 'deliverability.cname.verified'
                    : 'deliverability.cname.pending',
            ],
            'spf' => __($spf['label_key']),
            'dkim' => __($dkim['label_key']),
            'dmarc' => __($dmarc['label_key']),
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
        $currentRecord = $domain->dns_records['dmarc'] ?? null;
        $currentParsed = $currentRecord ? $this->dnsLookup->parseDmarcRecord($currentRecord) : null;

        // Keep the report address the domain already publishes; otherwise suggest one on the domain
        $rua = $currentParsed['rua'] ?? null;
        $reportEmail = $rua
            ? preg_replace('/^mailto:/i', '', trim(explode(',', $rua)[0]))
            : 'dmarc-reports@' . $domain->domain;
        $ruaValue = $rua ?: "mailto:{$reportEmail}";

        // Relaxed alignment (the DMARC default) and no ruf: strict alignment fails mail sent from
        // subdomains or relays that sign with a parent domain, and few receivers send forensic reports.
        $record = fn (string $policy) => "v=DMARC1; p={$policy}; rua={$ruaValue}";
        $entry = fn (string $policy, string $explanation) => [
            'host' => '_dmarc.' . $domain->domain,
            'host_display' => '_dmarc',
            'type' => 'TXT',
            'value' => $record($policy),
            'ttl' => 3600,
            'policy' => $policy,
            'explanation_key' => 'deliverability.dmarc_generator.' . $explanation,
        ];

        $currentPolicy = $currentRecord ? ($domain->dmarc_policy ?? 'none') : null;

        return [
            'current' => [
                'policy' => $currentPolicy,
                'record' => $currentRecord,
            ],
            // Step 1: monitor only. Nothing is blocked while the reports show who sends for the domain.
            'initial' => $entry('none', 'initial_explanation') + [
                'is_recommended_first_step' => true,
            ],
            // Step 2: unauthenticated mail goes to spam
            'quarantine' => $entry('quarantine', 'quarantine_explanation') + [
                'upgrade_after_days' => 14,
            ],
            // Step 3: unauthenticated mail is rejected
            'recommended' => $entry('reject', 'recommended_explanation') + [
                'upgrade_after_days' => 28,
            ],
            'minimal' => [
                'host' => '_dmarc.' . $domain->domain,
                'host_display' => '_dmarc',
                'type' => 'TXT',
                'value' => 'v=DMARC1; p=none',
                'ttl' => 3600,
                'policy' => 'none',
                'explanation_key' => 'deliverability.dmarc_generator.minimal_explanation',
            ],
            'report_email' => $reportEmail,
            'can_upgrade' => in_array($currentPolicy, ['none', 'quarantine'], true),
            'should_configure' => $currentRecord === null,
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
        // Get aggregated provider config from ALL mailboxes using this domain
        $providerConfig = $this->getAggregatedProviderConfig($domain);
        $providers = $providerConfig['providers'] ?? [];

        $currentSpf = $domain->dns_records['spf'] ?? null;
        $parsedCurrent = $currentSpf ? $this->dnsLookup->parseSpfRecord($currentSpf) : null;

        // Keep every mechanism of the current record: other services (the mail host, a CRM,
        // a helpdesk) send for the domain too, and dropping them would make their mail fail SPF.
        $terms = [];
        $modifiers = [];
        if ($currentSpf) {
            foreach (preg_split('/\s+/', trim($currentSpf)) as $term) {
                $lower = strtolower($term);
                if ($lower === '' || $lower === 'v=spf1' || preg_match('/^[+\-~?]?all$/', $lower)) {
                    continue;
                }
                if (preg_match('/^(redirect|exp)=/', $lower)) {
                    $modifiers[] = $term;
                    continue;
                }
                $terms[] = $term;
            }
        }

        // Add the addresses NetSendo's own MTA sends from, unless the record has them
        $added = [];
        foreach ($providerConfig['spf_ips'] ?? [] as $ip) {
            if (self::spfCoversIp(array_map('strtolower', $terms), $ip)) {
                continue;
            }
            $terms[] = self::spfIpMechanism($ip);
            $added[] = self::spfIpMechanism($ip);
        }

        // Add the includes the sending providers need and the record does not have yet
        $currentIncludes = $parsedCurrent['includes'] ?? [];
        foreach ($providerConfig['spf_includes'] as $include) {
            if ($include === '' || in_array(strtolower($include), $currentIncludes, true)) {
                continue;
            }
            $terms[] = "include:{$include}";
            $added[] = $include;
        }

        // Nothing to build on: no record yet and no provider whose SPF include NetSendo knows.
        // A guessed include would be wrong, so ask the user to take it from their SMTP provider.
        $known = !empty($terms) || !empty($modifiers);
        $hasRedirect = (bool) preg_grep('/^redirect=/i', $modifiers);

        $build = function (string $all) use ($terms, $modifiers, $hasRedirect) {
            $parts = array_merge(['v=spf1'], $terms);
            // "redirect=" only applies when the record has no "all"
            if (!$hasRedirect) {
                $parts[] = $all;
            }
            return implode(' ', array_merge($parts, $modifiers));
        };

        $optimalValue = $known ? $build('-all') : null;
        $softValue = $known ? $build('~all') : null;
        $lookupCount = $known ? $this->countSpfLookups($optimalValue) : 0;

        return [
            'current' => [
                'record' => $currentSpf,
                'parsed' => $parsedCurrent,
                'lookup_count' => $this->countSpfLookups($currentSpf),
            ],
            'optimal' => $known ? [
                'host' => $domain->domain,
                'host_display' => '@',
                'type' => 'TXT',
                'value' => $optimalValue,
                'ttl' => 3600,
                'lookup_count' => $lookupCount,
                'providers' => $providers,
                'added_includes' => $added,
                'uses_hard_fail' => true,
                'explanation_key' => 'deliverability.spf_generator.optimal_explanation',
            ] : null,
            'soft_fail' => $known ? [
                'host' => $domain->domain,
                'host_display' => '@',
                'type' => 'TXT',
                'value' => $softValue,
                'ttl' => 3600,
                'lookup_count' => $lookupCount,
                'uses_hard_fail' => false,
                'explanation_key' => 'deliverability.spf_generator.softfail_explanation',
            ] : null,
            'providers' => $providers,
            'needs_provider_include' => !$known,
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

        // Count exists
        $count += preg_match_all('/\bexists:/i', $spf);

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
