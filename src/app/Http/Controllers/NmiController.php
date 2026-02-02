<?php

namespace App\Http\Controllers;

use App\Models\DedicatedIpAddress;
use App\Models\IpPool;
use App\Services\Nmi\BlacklistMonitorService;
use App\Services\Nmi\DkimKeyManager;
use App\Services\Nmi\IpWarmingService;
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
        private BlacklistMonitorService $blacklistService
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
            'nmiEnabled' => config('netsendo.nmi.enabled', false),
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
            'ptr_verified' => $ip->ptr_verified,
            'is_active' => $ip->is_active,
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
}
