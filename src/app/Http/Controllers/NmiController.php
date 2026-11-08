<?php

namespace App\Http\Controllers;

use App\Models\DedicatedIpAddress;
use App\Models\DomainConfiguration;
use App\Models\IpPool;
use App\Models\IpProviderSetting;
use App\Services\Nmi\BlacklistMonitorService;
use App\Services\Nmi\DkimKeyManager;
use App\Services\Nmi\HostingProviders;
use App\Services\Nmi\IpProvisioningService;
use App\Services\Deliverability\DnsLookupService;
use App\Services\Nmi\IpWarmingService;
use App\Services\Nmi\MailServerName;
use App\Services\Nmi\MtaEndpoint;
use App\Services\Nmi\MtaProbe;
use App\Services\Nmi\NmiSwitch;
use App\Services\Nmi\NmiSetupService;
use App\Services\Nmi\OutboundPortCheck;
use App\Services\Nmi\SetupGuide;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class NmiController extends Controller
{
    public function __construct(
        private DkimKeyManager $dkimManager,
        private IpWarmingService $warmingService,
        private BlacklistMonitorService $blacklistService,
        private IpProvisioningService $provisioningService,
        private NmiSetupService $setupService
    ) {}

    /**
     * Get NMI dashboard data
     */
    public function dashboard(Request $request): InertiaResponse|JsonResponse
    {
        $user = $request->user();

        $pools = IpPool::forUser($user->id)->active()->withCount('ipAddresses')->get();

        $ips = DedicatedIpAddress::whereHas('pool', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->active()->with('domainConfiguration')->withCount('mailboxes')->get();

        $stats = [
            'total_pools' => $pools->count(),
            'total_ips' => $ips->count(),
            'warming_ips' => $ips->where('warming_status', 'warming')->count(),
            'warmed_ips' => $ips->where('warming_status', 'warmed')->count(),
            'blacklisted_ips' => $ips->filter(fn($ip) => $ip->isBlacklisted())->count(),
            'avg_reputation' => round($ips->avg('reputation_score') ?? 100, 1),
        ];

        $data = [
            'stats' => $stats,
            'pools' => $pools,
            'ips' => $ips->map(fn($ip) => $this->formatIpForResponse($ip)),
            // Switched on in the panel (or NMI_ENABLED=true in .env); the key read before did
            // not exist, so the "setup required" banner was shown on every installation
            'nmiEnabled' => NmiSwitch::enabled(),
            'nmiSwitch' => [
                'forced_by_env' => NmiSwitch::forcedByEnv(),
                'can_toggle' => $user->isAdmin(),
            ],
            // Where to get a server with own IP addresses (the guide compares them)
            'hostingProviders' => HostingProviders::all(),
        ];

        // Return JSON for AJAX requests
        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        }

        return Inertia::render('Nmi/Index', $data);
    }

    /**
     * Switch NetSendo MTA on for the installation (admin; GOLD through the route)
     */
    public function enableInstallation(Request $request)
    {
        return $this->switchInstallation($request, true);
    }

    /**
     * Switch NetSendo MTA off for the installation (admin; allowed without GOLD). The MTA
     * loses all logins at once; domains, IPs and mailboxes stay as they are.
     */
    public function disableInstallation(Request $request)
    {
        return $this->switchInstallation($request, false);
    }

    private function switchInstallation(Request $request, bool $enabled)
    {
        abort_unless($request->user()->isAdmin(), 403, __('nmi.switch_admin_only'));

        if (!$enabled && NmiSwitch::forcedByEnv()) {
            throw ValidationException::withMessages(['nmi' => __('nmi.switch_forced_by_env')]);
        }

        NmiSwitch::set($enabled);

        return $this->respond($request, $enabled ? __('nmi.switched_on') : __('nmi.switched_off'), [
            'enabled' => NmiSwitch::enabled(),
        ]);
    }

    /**
     * The setup guide: each step to a working sending server with its live state
     */
    public function guide(Request $request, SetupGuide $guide): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ['steps' => $guide->steps($request->user())],
        ]);
    }

    /**
     * List IP pools
     */
    public function listPools(Request $request): JsonResponse
    {
        $user = $request->user();

        $pools = IpPool::forUser($user->id)
            ->with('ipAddresses')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $pools,
        ]);
    }

    /**
     * Create a new IP pool
     */
    public function createPool(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in([IpPool::TYPE_SHARED, IpPool::TYPE_DEDICATED])],
            'description' => ['nullable', 'string', 'max:500'],
            'max_ips' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $pool = IpPool::create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'type' => $validated['type'],
            'description' => $validated['description'] ?? null,
            'max_ips' => $validated['max_ips'] ?? 10,
            'is_active' => true,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => __('nmi.pool_created'),
                'data' => $pool,
            ], 201);
        }

        return redirect()->route('settings.nmi.dashboard')
            ->with('success', __('nmi.pool_created'));
    }

    /**
     * Get IP pool details
     */
    public function getPool(Request $request, IpPool $pool): InertiaResponse|JsonResponse
    {
        $this->authorizePool($request, $pool);

        $pool->load('ipAddresses.domainConfiguration');

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $pool,
            ]);
        }

        return Inertia::render('Nmi/PoolDetail', [
            'pool' => $pool,
        ]);
    }

    /**
     * Delete an IP pool
     */
    public function deletePool(Request $request, IpPool $pool)
    {
        $this->authorizePool($request, $pool);

        if ($pool->ipAddresses()->count() > 0) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => __('nmi.pool_not_empty'),
                ], 400);
            }
            return redirect()->back()->with('error', __('nmi.pool_not_empty'));
        }

        $pool->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => __('nmi.pool_deleted'),
            ]);
        }

        return redirect()->route('settings.nmi.dashboard')
            ->with('success', __('nmi.pool_deleted'));
    }

    /**
     * Get IP details with warming and blacklist status
     */
    public function getIp(Request $request, DedicatedIpAddress $ip): InertiaResponse|JsonResponse
    {
        $this->authorizeIp($request, $ip);

        $ip->load('domainConfiguration')->loadCount('mailboxes');

        $data = [
            'ip' => $this->formatIpForResponse($ip),
            'warming' => $this->warmingService->getWarmingStatus($ip),
            'blacklist' => $this->blacklistService->getSummary($ip),
            'dkim' => $this->dkimManager->getDnsInstructions($ip),
            // Hosting providers, with the instructions for the IP's own
            'hostingProviders' => HostingProviders::all(),
            // Domains of the user the IP can be assigned to (Deliverability Shield)
            'domains' => DomainConfiguration::forUser($request->user()->id)
                ->orderBy('domain')
                ->get()
                ->map(fn (DomainConfiguration $domain) => $this->formatDomainForResponse($domain))
                ->values(),
        ];

        // Return JSON for AJAX requests
        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        }

        return Inertia::render('Nmi/IpDetail', $data);
    }

    /**
     * Start IP warming
     */
    public function startWarming(Request $request, DedicatedIpAddress $ip)
    {
        $this->authorizeIp($request, $ip);

        try {
            $this->warmingService->startWarming($ip);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => __('nmi.warming_started'),
                    'data' => $this->warmingService->getWarmingStatus($ip),
                ]);
            }

            return redirect()->back()->with('success', __('nmi.warming_started'));
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 400);
            }
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Generate DKIM keys for an IP
     */
    public function generateDkim(Request $request, DedicatedIpAddress $ip)
    {
        $this->authorizeIp($request, $ip);

        try {
            $keyPair = $this->dkimManager->generateForIp($ip);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => __('nmi.dkim_generated'),
                    'data' => [
                        'selector' => $keyPair['selector'],
                        'dns_name' => $keyPair['dns_name'],
                        'dns_record' => $keyPair['dns_record'],
                    ],
                ]);
            }

            return redirect()->back()->with('success', __('nmi.dkim_generated'));
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 400);
            }
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Assign the IP to a domain of the same user (or move it to another one)
     */
    public function assignDomain(Request $request, DedicatedIpAddress $ip)
    {
        $this->authorizeIp($request, $ip);

        $validated = $request->validate([
            'domain_configuration_id' => [
                'required',
                'integer',
                Rule::exists('domain_configurations', 'id')
                    ->where('user_id', $request->user()->id)
                    ->whereNull('deleted_at'),
            ],
        ], [
            'domain_configuration_id.exists' => __('nmi.domain_not_found'),
        ]);

        $domain = DomainConfiguration::findOrFail($validated['domain_configuration_id']);
        $this->setupService->assignIpToDomain($ip, $domain);

        return $this->respond($request, __('nmi.ip_assigned', ['domain' => $domain->domain]), $this->formatIpForResponse(
            $ip->fresh()->load('domainConfiguration')->loadCount('mailboxes')
        ));
    }

    /**
     * Take the IP away from its domain (allowed without GOLD, like deleting)
     */
    public function unassignDomain(Request $request, DedicatedIpAddress $ip)
    {
        $this->authorizeIp($request, $ip);

        $this->setupService->unassignIp($ip);

        return $this->respond($request, __('nmi.ip_unassigned'), $this->formatIpForResponse(
            $ip->fresh()->load('domainConfiguration')->loadCount('mailboxes')
        ));
    }

    /**
     * Switch NMI on for a verified domain; generates its SMTP login on the MTA
     */
    public function enableDomain(Request $request, DomainConfiguration $domain)
    {
        $this->authorize('update', $domain);

        $this->setupService->enable($domain);

        return $this->respond($request, __('nmi.domain_enabled', ['domain' => $domain->domain]), $this->formatDomainForResponse($domain->fresh()));
    }

    /**
     * Switch NMI off for a domain (allowed without GOLD)
     */
    public function disableDomain(Request $request, DomainConfiguration $domain)
    {
        $this->authorize('update', $domain);

        $this->setupService->disable($domain);

        return $this->respond($request, __('nmi.domain_disabled', ['domain' => $domain->domain]), $this->formatDomainForResponse($domain->fresh()));
    }

    /**
     * JSON for AJAX calls, back with a flash message for Inertia visits
     */
    private function respond(Request $request, string $message, array $data)
    {
        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $data,
            ]);
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * Change the name of an IP: the name the MTA introduces itself with when it sends from
     * this IP, and the one the IP's PTR record has to point to
     */
    public function updateIp(Request $request, DedicatedIpAddress $ip)
    {
        $this->authorizeIp($request, $ip);

        $validated = $request->validate([
            'hostname' => [
                'sometimes',
                'required',
                'string',
                'max:253',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (!MailServerName::isValid((string) $value)) {
                        $fail(__('nmi.hostname_invalid'));
                    }
                },
            ],
            // The hosting provider of the server (the IP page shows its instructions)
            'provider' => ['sometimes', 'required', 'string', Rule::in(array_merge(HostingProviders::keys(), [DedicatedIpAddress::PROVIDER_MANUAL]))],
        ]);

        if (array_key_exists('hostname', $validated)) {
            $hostname = MailServerName::normalize($validated['hostname']);
            if ($hostname !== $ip->hostname) {
                // The PTR check was for the old name
                $ip->update(['hostname' => $hostname, 'ptr_verified' => false, 'ptr_verified_at' => null]);
            }
        }
        if (array_key_exists('provider', $validated)) {
            $ip->update(['provider' => $validated['provider']]);
        }

        return $this->respond($request, __('nmi.ip_updated'), $this->formatIpForResponse(
            $ip->fresh()->load('domainConfiguration')->loadCount('mailboxes')
        ));
    }

    /**
     * Check the PTR record of an IP: it has to point to the IP's name, and the name back
     * to the IP. The PTR record is set at the provider of the server, not in the domain's DNS.
     */
    public function verifyPtr(Request $request, DedicatedIpAddress $ip, MailServerName $names, DnsLookupService $dns): JsonResponse
    {
        $this->authorizeIp($request, $ip);

        // Asked for: no answer from the cache
        $dns->forgetAddressRecords((string) MailServerName::normalizeIp((string) $ip->ip_address), (string) $ip->hostname);

        $check = $names->checkIp($ip);
        $ip->update([
            'ptr_record' => $check['ptr'],
            'ptr_verified' => $check['ok'],
            'ptr_verified_at' => $check['ok'] ? now() : null,
        ]);

        return response()->json([
            'success' => true,
            'data' => $check + [
                'ip' => $ip->ip_address,
                'message' => $check['ok']
                    ? __('nmi.ptr_ok', ['ip' => $ip->ip_address, 'name' => $check['name']])
                    : __('nmi.ptr_not_ok', ['ip' => $ip->ip_address, 'name' => (string) $check['name']]),
            ],
        ]);
    }

    /**
     * Verify DKIM DNS record
     */
    public function verifyDkim(Request $request, DedicatedIpAddress $ip): JsonResponse
    {
        $this->authorizeIp($request, $ip);

        $verified = $this->dkimManager->verifyDkimRecord($ip);

        return response()->json([
            'success' => true,
            'data' => [
                'verified' => $verified,
                'message' => $verified
                    ? __('nmi.dkim_verified')
                    : __('nmi.dkim_not_verified'),
            ],
        ]);
    }

    /**
     * Check IP against blacklists
     */
    public function checkBlacklist(Request $request, DedicatedIpAddress $ip)
    {
        $this->authorizeIp($request, $ip);

        $results = $this->blacklistService->checkAndUpdate($ip);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => $this->blacklistService->getSummary($ip),
                    'details' => $this->blacklistService->getDetails($ip),
                ],
            ]);
        }

        return redirect()->back()->with('success', __('nmi.blacklist_checked'));
    }

    /**
     * Format IP for API response
     */
    private function formatIpForResponse(DedicatedIpAddress $ip): array
    {
        return [
            'id' => $ip->id,
            'ip_address' => $ip->ip_address,
            // As the mail server reports its addresses (MtaProbe)
            'normalized_ip' => MailServerName::normalizeIp((string) $ip->ip_address),
            'hostname' => $ip->hostname,
            'provider' => $ip->provider,
            'warming_status' => $ip->warming_status,
            'warming_progress' => $this->warmingService->getWarmingProgress($ip),
            'reputation_score' => $ip->reputation_score,
            'is_blacklisted' => $ip->isBlacklisted(),
            'delivery_rate' => $ip->getDeliveryRate(),
            'total_sent' => $ip->total_sent,
            'total_delivered' => $ip->total_delivered,
            'total_bounced' => $ip->total_bounced,
            'total_complaints' => $ip->total_complaints,
            'dkim_configured' => $ip->hasDkimKeys(),
            'ptr_verified' => (bool) $ip->ptr_verified,
            'ptr_record' => $ip->ptr_record,
            'ptr_verified_at' => $ip->ptr_verified_at?->toIso8601String(),
            'is_active' => $ip->is_active,
            'domain' => $ip->domainConfiguration ? $this->formatDomainForResponse($ip->domainConfiguration) : null,
            'mailboxes_count' => $ip->mailboxes_count ?? $ip->mailboxes()->count(),
        ];
    }

    /**
     * Domain as the NMI pages show it (never the SMTP password)
     */
    private function formatDomainForResponse(DomainConfiguration $domain): array
    {
        return [
            'id' => $domain->id,
            'domain' => $domain->domain,
            'cname_verified' => (bool) $domain->cname_verified,
            'nmi_enabled' => (bool) $domain->nmi_enabled,
            'nmi_ready' => $domain->isNmiReady(),
            'nmi_smtp_username' => $domain->nmi_smtp_username,
            'nmi_enabled_at' => $domain->nmi_enabled_at?->toIso8601String(),
            'dedicated_ip_id' => $domain->dedicated_ip_id,
        ];
    }

    /**
     * Authorize pool access
     */
    private function authorizePool(Request $request, IpPool $pool): void
    {
        if ($pool->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized access to this pool');
        }
    }

    /**
     * Authorize IP access
     */
    private function authorizeIp(Request $request, DedicatedIpAddress $ip): void
    {
        if ($ip->pool?->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized access to this IP');
        }
    }

    /**
     * Check MTA container status
     */
    public function getMtaStatus(OutboundPortCheck $portCheck, MtaProbe $probe, MailServerName $names): JsonResponse
    {
        $status = $probe->probe();

        return response()->json([
            'success' => true,
            'data' => [
                'online' => $status['online'],
                'host' => MtaEndpoint::host(),
                'port' => MtaEndpoint::port(),
                'message' => $status['message'],
                'checked_at' => now()->toIso8601String(),
                // The MTA delivers straight to the recipients' servers: port 25 must be open
                'outbound_port_25' => $portCheck->port25Open(),
                // Addresses of the server the MTA can send from (null: not known); a mailbox
                // sends from its dedicated IP only when the IP is among them
                'addresses' => $status['addresses'],
                // The name the MTA gives itself from the main address, and its PTR record
                'server_name' => $names->check(),
            ],
        ]);
    }

    /**
     * Add an IP address to a pool
     */
    public function addIpToPool(Request $request, IpPool $pool)
    {
        $this->authorizePool($request, $pool);

        // Check if pool can accept more IPs
        if (!$pool->canAddMoreIps()) {
            return response()->json([
                'success' => false,
                'message' => __('nmi.pool_max_ips_reached'),
            ], 400);
        }

        $validated = $request->validate([
            'ip_address' => [
                'required',
                'string',
                'max:45',
                function ($attribute, $value, $fail) {
                    if (!filter_var($value, FILTER_VALIDATE_IP)) {
                        $fail(__('nmi.invalid_ip_address'));
                    }
                },
                Rule::unique('dedicated_ip_addresses', 'ip_address')->whereNull('deleted_at'),
            ],
            'hostname' => [
                'required',
                'string',
                'max:253',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (!MailServerName::isValid((string) $value)) {
                        $fail(__('nmi.hostname_invalid'));
                    }
                },
            ],
            // The hosting provider of the server (the IP page shows its instructions)
            'provider' => ['nullable', 'string', Rule::in(array_merge(HostingProviders::keys(), [DedicatedIpAddress::PROVIDER_MANUAL]))],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        // The column is ENUM('ipv4', 'ipv6'); 4 and 6 failed on MySQL (strict mode) and SQLite
        $ipVersion = filter_var($validated['ip_address'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? 'ipv6' : 'ipv4';

        $ip = DedicatedIpAddress::create([
            'ip_pool_id' => $pool->id,
            'ip_address' => $validated['ip_address'],
            'hostname' => MailServerName::normalize($validated['hostname']),
            'ip_version' => $ipVersion,
            'provider' => $validated['provider'] ?? DedicatedIpAddress::PROVIDER_MANUAL,
            'warming_status' => DedicatedIpAddress::WARMING_NEW,
            'reputation_score' => 100,
            'is_active' => true,
            'status_message' => $validated['description'] ?? null,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => __('nmi.ip_added'),
                'data' => $this->formatIpForResponse($ip),
            ], 201);
        }

        return redirect()->route('settings.nmi.pools.show', $pool->id)
            ->with('success', __('nmi.ip_added'));
    }

    /**
     * Delete an IP address from a pool
     */
    public function deleteIp(Request $request, DedicatedIpAddress $ip)
    {
        $this->authorizeIp($request, $ip);

        // Check if IP has any mailboxes assigned
        if ($ip->mailboxes()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => __('nmi.ip_has_mailboxes'),
            ], 400);
        }

        // Its domain gets another primary IP (or none)
        if ($ip->domain_configuration_id) {
            $this->setupService->unassignIp($ip);
        }

        $poolId = $ip->ip_pool_id;
        $ip->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => __('nmi.ip_deleted'),
            ]);
        }

        return redirect()->route('settings.nmi.pools.show', $poolId)
            ->with('success', __('nmi.ip_deleted'));
    }

    /**
     * Get available IP providers
     */
    public function getProviders(): JsonResponse
    {
        $providers = $this->provisioningService->getAvailableProviders();

        return response()->json([
            'success' => true,
            'data' => [
                'providers' => $providers,
            ],
        ]);
    }

    /**
     * Provision a new IP from a cloud provider
     */
    public function provisionIp(Request $request, IpPool $pool): JsonResponse
    {
        $this->authorizePool($request, $pool);

        // Check if pool can accept more IPs
        if (!$pool->canAddMoreIps()) {
            return response()->json([
                'success' => false,
                'message' => __('nmi.pool_max_ips_reached'),
            ], 400);
        }

        $validated = $request->validate([
            'provider' => ['required', 'string', Rule::in(['vultr', 'linode', 'digitalocean'])],
            'region' => ['required', 'string', 'max:20'],
        ]);

        // Get user's API key for the provider
        $apiKey = IpProviderSetting::getApiKey($request->user()->id, $validated['provider']);

        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => __('nmi.provider_not_configured'),
            ], 400);
        }

        try {
            $ip = $this->provisioningService->provisionIpWithUserKey(
                $validated['provider'],
                $pool,
                $validated['region'],
                $apiKey
            );

            return response()->json([
                'success' => true,
                'message' => __('nmi.ip_provisioned'),
                'data' => $this->formatIpForResponse($ip),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get regions for a provider
     */
    public function getProviderRegions(Request $request, string $provider): JsonResponse
    {
        $apiKey = IpProviderSetting::getApiKey($request->user()->id, $provider);

        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => __('nmi.provider_not_configured'),
            ], 400);
        }

        $regions = $this->provisioningService->getRegions($provider, $apiKey);

        return response()->json([
            'success' => true,
            'data' => [
                'regions' => $regions,
            ],
        ]);
    }

    /**
     * Get provider settings for current user
     */
    public function getProviderSettings(Request $request): JsonResponse
    {
        $settings = IpProviderSetting::getForUser($request->user()->id);

        return response()->json([
            'success' => true,
            'data' => [
                'providers' => $settings,
            ],
        ]);
    }

    /**
     * Save provider settings for current user
     */
    public function saveProviderSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', Rule::in(['vultr', 'linode', 'digitalocean'])],
            'api_key' => ['nullable', 'string', 'max:255'],
            'enabled' => ['boolean'],
        ]);

        $user = $request->user();

        if (empty($validated['api_key'])) {
            // Delete the setting if API key is empty
            IpProviderSetting::where('user_id', $user->id)
                ->where('provider', $validated['provider'])
                ->delete();
        } else {
            IpProviderSetting::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'provider' => $validated['provider'],
                ],
                [
                    'api_key' => $validated['api_key'],
                    'enabled' => $validated['enabled'] ?? true,
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => __('nmi.provider_saved'),
            'data' => [
                'providers' => IpProviderSetting::getForUser($user->id),
            ],
        ]);
    }
}
