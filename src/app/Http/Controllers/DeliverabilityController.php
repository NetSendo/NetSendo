<?php

namespace App\Http\Controllers;

use App\Models\DomainConfiguration;
use App\Models\InboxSimulation;
use App\Services\Deliverability\DomainVerificationService;
use App\Services\Deliverability\InboxPassportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DeliverabilityController extends Controller
{
    public function __construct(
        private DomainVerificationService $domainService,
        private InboxPassportService $inboxPassportService
    ) {}

    /**
     * Display the Deliverability Shield dashboard
     */
    public function index(): Response
    {
        $user = Auth::user();

        $domains = DomainConfiguration::forUser($user->id)
            ->with('mailbox')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($domain) {
                return [
                    'id' => $domain->id,
                    'domain' => $domain->domain,
                    'cname_verified' => $domain->cname_verified,
                    'overall_status' => $domain->overall_status,
                    'status_info' => $domain->getStatusInfo(),
                    'spf_status' => $domain->spf_status,
                    'dkim_status' => $domain->dkim_status,
                    'dmarc_status' => $domain->dmarc_status,
                    'dmarc_policy' => $domain->dmarc_policy,
                    'last_check_at' => $domain->last_check_at?->diffForHumans(),
                    'mailbox_name' => $domain->mailbox?->name,
                    'created_at' => $domain->created_at->format('Y-m-d'),
                ];
            });

        $recentSimulations = InboxSimulation::forUser($user->id)
            ->with('domainConfiguration')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($sim) {
                return [
                    'id' => $sim->id,
                    'subject' => $sim->subject,
                    'inbox_score' => $sim->inbox_score,
                    'score_info' => $sim->getScoreInfo(),
                    'predicted_folder' => $sim->predicted_folder,
                    'folder_info' => $sim->getFolderInfo(),
                    'domain' => $sim->domainConfiguration?->domain,
                    'created_at' => $sim->created_at->diffForHumans(),
                ];
            });

        // Summary stats
        $stats = [
            'total_domains' => $domains->count(),
            'verified_domains' => $domains->where('cname_verified', true)->count(),
            'critical_domains' => $domains->where('overall_status', 'critical')->count(),
            'avg_inbox_score' => InboxSimulation::forUser($user->id)
                ->recent(7)
                ->avg('inbox_score') ?? 0,
        ];

        return Inertia::render('Deliverability/Index', [
            'domains' => $domains,
            'recentSimulations' => $recentSimulations,
            'stats' => $stats,
            'isGold' => $user->license?->plan === 'GOLD',
            'licenseRoute' => route('license.index'),
        ]);
    }

    /**
     * Show DMARC Wiz - Add domain page
     */
    public function createDomain(): Response
    {
        $user = Auth::user();

        // Get existing domains for reference
        $existingDomains = DomainConfiguration::forUser($user->id)
            ->pluck('domain')
            ->toArray();

        return Inertia::render('Deliverability/DmarcWiz', [
            'existingDomains' => $existingDomains,
            'verifyTarget' => parse_url(config('app.url'), PHP_URL_HOST),
        ]);
    }

    /**
     * Store new domain configuration
     */
    public function addDomain(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'domain' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9][a-zA-Z0-9-]{0,61}[a-zA-Z0-9]?\.[a-zA-Z]{2,}$/',
                Rule::unique('domain_configurations')
                    ->where('user_id', $user->id)
                    ->whereNull('deleted_at'),
            ],
            'mailbox_id' => 'nullable|exists:mailboxes,id',
        ], [
            'domain.regex' => __('deliverability.validation.domain_format'),
            'domain.unique' => __('deliverability.validation.domain_exists'),
        ]);

        // Normalize domain
        $domain = strtolower(trim($validated['domain']));

        // Create domain configuration
        $config = DomainConfiguration::create([
            'user_id' => $user->id,
            'domain' => $domain,
            'mailbox_id' => $validated['mailbox_id'] ?? null,
            'overall_status' => DomainConfiguration::OVERALL_PENDING,
        ]);

        // Generate CNAME instruction
        $cnameInstruction = $this->domainService->generateCnameInstruction($config);

        return redirect()->route('deliverability.domains.show', $config)
            ->with('success', __('deliverability.messages.domain_added'))
            ->with('cname', $cnameInstruction);
    }

    /**
     * Show domain details and CNAME instruction
     */
    public function showDomain(DomainConfiguration $domain): Response
    {
        $this->authorize('view', $domain);

        $cnameInstruction = $this->domainService->generateCnameInstruction($domain);
        $humanStatus = $this->domainService->getHumanReadableStatus($domain);

        // Get recent simulations for this domain
        $simulations = InboxSimulation::where('domain_configuration_id', $domain->id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($sim) {
                return [
                    'id' => $sim->id,
                    'subject' => $sim->subject,
                    'inbox_score' => $sim->inbox_score,
                    'score_info' => $sim->getScoreInfo(),
                    'predicted_folder' => $sim->predicted_folder,
                    'folder_info' => $sim->getFolderInfo(),
                    'created_at' => $sim->created_at->diffForHumans(),
                ];
            });

        return Inertia::render('Deliverability/DomainStatus', [
            'domain' => [
                'id' => $domain->id,
                'domain' => $domain->domain,
                'cname_verified' => $domain->cname_verified,
                'cname_verified_at' => $domain->cname_verified_at?->format('Y-m-d H:i'),
                'overall_status' => $domain->overall_status,
                'status_info' => $domain->getStatusInfo(),
                'spf_status' => $domain->spf_status,
                'dkim_status' => $domain->dkim_status,
                'dmarc_status' => $domain->dmarc_status,
                'dmarc_policy' => $domain->dmarc_policy,
                'dns_records' => $domain->dns_records,
                'check_history' => array_slice($domain->check_history ?? [], 0, 5),
                'last_check_at' => $domain->last_check_at?->diffForHumans(),
                'next_check_at' => $domain->next_check_at?->diffForHumans(),
                'alerts_enabled' => $domain->alerts_enabled,
            ],
            'cnameInstruction' => $cnameInstruction,
            'humanStatus' => $humanStatus,
            'simulations' => $simulations,
        ]);
    }

    /**
     * Verify CNAME record
     */
    public function verifyCname(DomainConfiguration $domain)
    {
        $this->authorize('update', $domain);

        $verified = $this->domainService->verifyCname($domain);

        if ($verified) {
            // Trigger DNS check immediately
            $this->domainService->checkDnsRecords($domain);

            return back()->with('success', __('deliverability.messages.cname_verified'));
        }

        return back()->with('error', __('deliverability.messages.cname_not_found'));
    }

    /**
     * Refresh DNS status
     */
    public function refreshStatus(DomainConfiguration $domain)
    {
        $this->authorize('update', $domain);

        if (!$domain->cname_verified) {
            // Try to verify CNAME first
            $this->domainService->verifyCname($domain);
        }

        if ($domain->cname_verified) {
            $this->domainService->checkDnsRecords($domain);
        }

        $domain->refresh();

        return back()->with('success', __('deliverability.messages.status_refreshed'));
    }

    /**
     * Delete domain configuration
     */
    public function removeDomain(DomainConfiguration $domain)
    {
        $this->authorize('delete', $domain);

        $domain->delete();

        return redirect()->route('deliverability.index')
            ->with('success', __('deliverability.messages.domain_removed'));
    }

    /**
     * Toggle alerts for domain
     */
    public function toggleAlerts(DomainConfiguration $domain)
    {
        $this->authorize('update', $domain);

        $domain->update([
            'alerts_enabled' => !$domain->alerts_enabled,
        ]);

        return back()->with('success', __('deliverability.messages.alerts_updated'));
    }

    /**
     * Show InboxPassport simulation page
     */
    public function showSimulator(): Response
    {
        $user = Auth::user();

        $domains = DomainConfiguration::forUser($user->id)
            ->verified()
            ->get(['id', 'domain', 'overall_status']);

        return Inertia::render('Deliverability/InboxPassport', [
            'domains' => $domains,
        ]);
    }

    /**
     * Run inbox simulation
     */
    public function simulateInbox(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'domain_id' => 'required|exists:domain_configurations,id',
            'subject' => 'required|string|max:255',
            'content' => 'required|string|max:100000',
            'message_id' => 'nullable|exists:messages,id',
        ]);

        $domain = DomainConfiguration::findOrFail($validated['domain_id']);
        $this->authorize('view', $domain);

        $simulation = $this->inboxPassportService->simulate(
            $user,
            $domain,
            $validated['subject'],
            $validated['content'],
            $validated['message_id'] ?? null
        );

        return redirect()->route('deliverability.simulations.show', $simulation)
            ->with('success', __('deliverability.messages.simulation_complete'));
    }

    /**
     * Show simulation result
     */
    public function showSimulation(InboxSimulation $simulation): Response
    {
        $this->authorize('view', $simulation);

        return Inertia::render('Deliverability/SimulationResult', [
            'simulation' => [
                'id' => $simulation->id,
                'subject' => $simulation->subject,
                'from_email' => $simulation->from_email,
                'inbox_score' => $simulation->inbox_score,
                'score_info' => $simulation->getScoreInfo(),
                'score_category' => $simulation->getScoreCategory(),
                'predicted_folder' => $simulation->predicted_folder,
                'folder_info' => $simulation->getFolderInfo(),
                'provider_predictions' => $simulation->provider_predictions,
                'domain_analysis' => $simulation->domain_analysis,
                'content_analysis' => $simulation->content_analysis,
                'issues' => $simulation->issues,
                'recommendations' => $simulation->recommendations,
                'score_breakdown' => $simulation->score_breakdown,
                'analyzed_at' => $simulation->analyzed_at->format('Y-m-d H:i'),
                'domain' => $simulation->domainConfiguration?->domain,
            ],
        ]);
    }

    /**
     * Show simulation history
     */
    public function simulationHistory(): Response
    {
        $user = Auth::user();

        $simulations = InboxSimulation::forUser($user->id)
            ->with('domainConfiguration')
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->through(function ($sim) {
                return [
                    'id' => $sim->id,
                    'subject' => $sim->subject,
                    'inbox_score' => $sim->inbox_score,
                    'score_info' => $sim->getScoreInfo(),
                    'predicted_folder' => $sim->predicted_folder,
                    'folder_info' => $sim->getFolderInfo(),
                    'domain' => $sim->domainConfiguration?->domain,
                    'created_at' => $sim->created_at->format('Y-m-d H:i'),
                ];
            });

        return Inertia::render('Deliverability/SimulationHistory', [
            'simulations' => $simulations,
        ]);
    }
}
