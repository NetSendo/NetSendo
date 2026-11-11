<?php

namespace App\Http\Controllers;

use App\Models\DedicatedIpAddress;
use App\Models\DomainConfiguration;
use App\Models\IpPool;
use App\Models\IpProviderSetting;
use App\Services\Nmi\BlacklistMonitorService;
use App\Services\Nmi\DkimKeyManager;
use App\Services\Nmi\IpProvisioningService;
use App\Services\Nmi\IpWarmingService;
use App\Services\Nmi\MtaStatusService;
use App\Services\Nmi\Providers\IpProviderException;
use App\Services\Nmi\ReverseDnsChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class NmiController extends Controller
{
    public function __construct(
        private DkimKeyManager $dkimManager,
        private IpWarmingService $warmingService,
        private BlacklistMonitorService $blacklistService,
        private IpProvisioningService $provisioningService,
        private ReverseDnsChecker $reverseDns
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
        })->active()->get();

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
            // NMI_ENABLED (config/nmi.php). The key read here before, netsendo.nmi.enabled,
            // never existed, so the dashboard always showed the "setup required" banner.
            'nmiEnabled' => (bool) config('nmi.features.enabled', false),
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

        $data = [
            'ip' => $this->formatIpForResponse($ip),
            'warming' => $this->warmingService->getWarmingStatus($ip),
            'blacklist' => $this->blacklistService->getSummary($ip),
            'dkim' => $this->dkimManager->getDnsInstructions($ip),
            // How the address reached NetSendo and what it still needs on the server
            'setup' => $this->formatIpSetup($ip),
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
            'hostname' => $ip->hostname,
            'provider' => $ip->provider,
            'warming_status' => $ip->warming_status,
            'warming_progress' => $this->warmingService->getWarmingProgress($ip),
            'reputation_score' => $ip->reputation_score,
            'is_blacklisted' => $ip->isBlacklisted(),
            'delivery_rate' => $ip->getDeliveryRate(),
            'total_sent' => $ip->total_sent,
            'dkim_configured' => !empty($ip->dkim_selector),
            'ptr_record' => $ip->ptr_record,
            'ptr_verified' => (bool) $ip->ptr_verified,
            'provider_managed' => $this->provisioningService->isManaged($ip),
            'bind_address' => $ip->bind_address,
            'is_active' => $ip->is_active,
        ];
    }

    /**
     * What the IP's page shows about setting the address up: where it came from, its
     * reverse DNS, and whether the provider's API can change that.
     */
    private function formatIpSetup(DedicatedIpAddress $ip): array
    {
        $managed = $this->provisioningService->isManaged($ip);

        return [
            'provider' => $ip->provider,
            'managed' => $managed,
            'region' => $ip->provider_region,
            'bind_address' => $ip->bind_address,
            'hostname' => $ip->hostname,
            'ptr_record' => $ip->ptr_record,
            'ptr_verified' => (bool) $ip->ptr_verified,
            'ptr_verified_at' => $ip->ptr_verified_at?->toIso8601String(),
            'can_set_ptr' => $this->provisioningService->canSetReverseDns($ip),
        ];
    }

    /**
     * Look the address's reverse DNS up: it has to name the IP's host, and that host has
     * to resolve back to the address
     */
    public function checkPtr(Request $request, DedicatedIpAddress $ip): JsonResponse
    {
        $this->authorizeIp($request, $ip);

        $result = $this->reverseDns->check($ip);

        return response()->json([
            'success' => true,
            'message' => $result['verified'] ? __('nmi.ptr.verified') : __('nmi.ptr.not_verified'),
            'data' => ['check' => $result, 'setup' => $this->formatIpSetup($ip->refresh())],
        ]);
    }

    /**
     * Set the address's reverse DNS at the provider it was ordered from, then look it up
     */
    public function setPtr(Request $request, DedicatedIpAddress $ip): JsonResponse
    {
        $this->authorizeIp($request, $ip);

        try {
            $outcome = $this->provisioningService->setReverseDns($request->user()->id, $ip);
        } catch (IpProviderException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        if (! $outcome['set']) {
            return response()->json([
                'success' => false,
                'message' => $outcome['message'] ?? __('nmi.ptr.not_supported'),
            ], 422);
        }

        $result = $this->reverseDns->check($ip);

        return response()->json([
            'success' => true,
            'message' => __('nmi.ptr.set'),
            'data' => ['check' => $result, 'setup' => $this->formatIpSetup($ip->refresh())],
        ]);
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
    public function getMtaStatus(MtaStatusService $mta): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $mta->probe(),
        ]);
    }

    /**
     * The DKIM key of a sending domain: the DNS record to publish and whether it has been
     * seen. Mail of the domain's NMI mailboxes is signed once it has.
     */
    public function getDomainDkim(Request $request, DomainConfiguration $domain): JsonResponse
    {
        $this->authorizeDomain($request, $domain);

        return response()->json([
            'success' => true,
            'data' => $this->formatDomainDkim($domain),
        ]);
    }

    /**
     * Generate the domain's DKIM key. With `replace` an existing key is thrown away:
     * mail goes out unsigned until the new record is published and verified.
     */
    public function generateDomainDkim(Request $request, DomainConfiguration $domain): JsonResponse
    {
        $this->authorizeDomain($request, $domain);

        try {
            $this->dkimManager->generateForDomain($domain, $request->boolean('replace'));
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'message' => __('nmi.dkim_generated'),
            'data' => $this->formatDomainDkim($domain->refresh()),
        ]);
    }

    /**
     * Look the domain's DKIM record up in DNS
     */
    public function verifyDomainDkim(Request $request, DomainConfiguration $domain): JsonResponse
    {
        $this->authorizeDomain($request, $domain);

        $verified = $this->dkimManager->verifyForDomain($domain);

        return response()->json([
            'success' => true,
            'message' => $verified ? __('nmi.dkim_verified') : __('nmi.dkim_not_verified'),
            'data' => $this->formatDomainDkim($domain->refresh()),
        ]);
    }

    private function formatDomainDkim(DomainConfiguration $domain): array
    {
        $record = $this->dkimManager->getDomainDnsInstructions($domain);

        return [
            'domain' => $domain->domain,
            'has_key' => $record !== null,
            'selector' => $record['selector'] ?? null,
            'dns_name' => $record['name'] ?? null,
            'dns_value' => $record['value'] ?? null,
            'verified' => $domain->nmiDkimReady(),
            'verified_at' => $domain->nmi_dkim_verified_at?->toIso8601String(),
            'generated_at' => $domain->nmi_dkim_generated_at?->toIso8601String(),
            // Dedicated IPs of the domain that carry a key of their own (used while the
            // domain's key is not verified)
            'ip_keys' => $domain->dedicatedIps()
                ->whereNotNull('dkim_selector')
                ->pluck('id')
                ->all(),
        ];
    }

    private function authorizeDomain(Request $request, DomainConfiguration $domain): void
    {
        if ($domain->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized access to this domain');
        }
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
            'hostname' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        // Determine IP version (the column is an enum of 'ipv4' and 'ipv6')
        $ipVersion = filter_var($validated['ip_address'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? 'ipv6' : 'ipv4';

        DedicatedIpAddress::forgetRemoved($validated['ip_address']);

        $ip = DedicatedIpAddress::create([
            'ip_pool_id' => $pool->id,
            'ip_address' => $validated['ip_address'],
            'hostname' => $validated['hostname'],
            'ip_version' => $ipVersion,
            'provider' => DedicatedIpAddress::PROVIDER_MANUAL,
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

        // An address ordered through a provider's API is given back first: otherwise it
        // would stay on the bill. `keep_at_provider` removes it from NetSendo only.
        if (! $request->boolean('keep_at_provider')) {
            try {
                $this->provisioningService->release($request->user()->id, $ip);
            } catch (IpProviderException $e) {
                return response()->json([
                    'success' => false,
                    'release_failed' => true,
                    'message' => __('nmi.ip_release_failed', ['reason' => $e->getMessage()]),
                ], 422);
            }
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
     * Order an address for one of the account's servers at a provider and add it to the pool
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
            'provider' => ['required', 'string', Rule::in(array_keys(IpProvisioningService::PROVIDERS))],
            'server_id' => ['required', 'string', 'max:64'],
            // The name the address will answer to: its PTR record and the MTA's HELO
            'hostname' => ['required', 'string', 'max:253', 'regex:/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\\.)+[a-z]{2,63}$/i'],
        ], [], ['hostname' => __('nmi.provision.hostname'), 'server_id' => __('nmi.provision.server')]);

        try {
            $result = $this->provisioningService->provision(
                $request->user()->id,
                $pool,
                $validated['provider'],
                $validated['server_id'],
                $validated['hostname']
            );
        } catch (IpProviderException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('nmi.ip_provisioned'),
            'data' => [
                ...$this->formatIpForResponse($result['ip']),
                'reverse_dns' => $result['reverse_dns'],
            ],
        ], 201);
    }

    /**
     * The account's servers at a provider: the address goes to the one the MTA runs on
     */
    public function getProviderServers(Request $request, string $provider): JsonResponse
    {
        abort_unless(isset(IpProvisioningService::PROVIDERS[$provider]), 404);

        try {
            $servers = $this->provisioningService->servers($request->user()->id, $provider);
        } catch (IpProviderException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'servers' => $servers,
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
     * Connect a provider (the key is checked with the provider before it is stored) or,
     * with an empty key, disconnect it
     */
    public function saveProviderSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', Rule::in(array_keys(IpProvisioningService::PROVIDERS))],
            'api_key' => ['nullable', 'string', 'max:255'],
            'enabled' => ['boolean'],
        ]);

        $user = $request->user();
        $account = null;

        if (empty($validated['api_key'])) {
            $this->provisioningService->disconnect($user->id, $validated['provider']);
        } else {
            try {
                $account = $this->provisioningService->connect($user->id, $validated['provider'], $validated['api_key']);
            } catch (IpProviderException $e) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }
        }

        return response()->json([
            'success' => true,
            'message' => __('nmi.provider_saved'),
            'data' => [
                'providers' => IpProviderSetting::getForUser($user->id),
                'account' => $account,
            ],
        ]);
    }
}
