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
     * Transform issues array to include translated messages
     */
    private function transformIssues(array $issues): array
    {
        return array_map(function ($issue) {
            if (isset($issue['message_key']) && !isset($issue['message'])) {
                $context = $issue['context'] ?? [];
                $issue['message'] = __($issue['message_key'], $context);
            }
            return $issue;
        }, $issues);
    }

    /**
     * Transform recommendations array to include translated messages
     */
    private function transformRecommendations(array $recommendations): array
    {
        return array_map(function ($rec) {
            if (isset($rec['message_key']) && !isset($rec['message'])) {
                $rec['message'] = __($rec['message_key']);
            }
            return $rec;
        }, $recommendations);
    }

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
            'isLocalhost' => DomainVerificationService::isLocalhostEnvironment(),
        ]);
    }

    /**
     * Store new domain configuration
     */
    public function addDomain(Request $request)
    {
        $user = $request->user();

        // Normalize domain before validation
        $request->merge([
            'domain' => strtolower(trim($request->input('domain', ''))),
        ]);

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

        // Domain already normalized
        $domain = $validated['domain'];

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
            'isLocalhost' => DomainVerificationService::isLocalhostEnvironment(),
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

        // Use forceDelete to permanently remove the record
        // This allows re-adding the same domain later (soft delete would conflict with unique constraint)
        $domain->forceDelete();

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
     * Get optimal DMARC record generator data
     */
    public function getDmarcGenerator(DomainConfiguration $domain): \Illuminate\Http\JsonResponse
    {
        $this->authorize('view', $domain);

        $generator = $this->domainService->generateOptimalDmarcRecord($domain);

        return response()->json([
            'success' => true,
            'domain' => $domain->domain,
            'generator' => $generator,
            'translations' => [
                'initial_explanation' => __('deliverability.dmarc_generator.initial_explanation'),
                'recommended_explanation' => __('deliverability.dmarc_generator.recommended_explanation'),
                'minimal_explanation' => __('deliverability.dmarc_generator.minimal_explanation'),
                'upgrade_notice' => __('deliverability.dmarc_generator.upgrade_notice'),
                'copy_success' => __('deliverability.messages.copied_to_clipboard'),
            ],
        ]);
    }

    /**
     * Get optimal SPF record generator data
     */
    public function getSpfGenerator(DomainConfiguration $domain): \Illuminate\Http\JsonResponse
    {
        $this->authorize('view', $domain);

        $generator = $this->domainService->generateOptimalSpfRecord($domain);

        return response()->json([
            'success' => true,
            'domain' => $domain->domain,
            'generator' => $generator,
            'translations' => [
                'optimal_explanation' => __('deliverability.spf_generator.optimal_explanation'),
                'softfail_explanation' => __('deliverability.spf_generator.softfail_explanation'),
                'lookup_warning' => __('deliverability.spf_generator.lookup_warning'),
                'copy_success' => __('deliverability.messages.copied_to_clipboard'),
            ],
        ]);
    }

    /**
     * Get both DMARC and SPF generators for domain status page
     */
    public function getDnsGenerators(DomainConfiguration $domain): \Illuminate\Http\JsonResponse
    {
        $this->authorize('view', $domain);

        $dmarcGenerator = $this->domainService->generateOptimalDmarcRecord($domain);
        $spfGenerator = $this->domainService->generateOptimalSpfRecord($domain);

        return response()->json([
            'success' => true,
            'domain' => $domain->domain,
            'dmarc' => $dmarcGenerator,
            'spf' => $spfGenerator,
            'translations' => [
                // DMARC translations
                'dmarc_initial_explanation' => __('deliverability.dmarc_generator.initial_explanation'),
                'dmarc_recommended_explanation' => __('deliverability.dmarc_generator.recommended_explanation'),
                'dmarc_minimal_explanation' => __('deliverability.dmarc_generator.minimal_explanation'),
                'dmarc_upgrade_notice' => __('deliverability.dmarc_generator.upgrade_notice'),
                // SPF translations
                'spf_optimal_explanation' => __('deliverability.spf_generator.optimal_explanation'),
                'spf_softfail_explanation' => __('deliverability.spf_generator.softfail_explanation'),
                'spf_lookup_warning' => __('deliverability.spf_generator.lookup_warning'),
                // Common
                'copy_success' => __('deliverability.messages.copied_to_clipboard'),
                'dns_instructions_title' => __('deliverability.dns_generator.instructions_title'),
                'dns_step1' => __('deliverability.dns_generator.step1'),
                'dns_step2' => __('deliverability.dns_generator.step2'),
                'dns_step3' => __('deliverability.dns_generator.step3'),
            ],
        ]);
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
                'issues' => $this->transformIssues($simulation->issues ?? []),
                'recommendations' => $this->transformRecommendations($simulation->recommendations ?? []),
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

    /**
     * Quick inbox simulation for message editor (returns JSON)
     */
    public function quickSimulateInbox(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'content' => 'required|string|max:100000',
            'mailbox_id' => 'nullable|exists:mailboxes,id',
        ]);

        // Get mailbox to check provider
        $mailbox = null;
        if (!empty($validated['mailbox_id'])) {
            $mailbox = \App\Models\Mailbox::find($validated['mailbox_id']);
        }

        // If no mailbox selected, use default
        if (!$mailbox) {
            $mailbox = \App\Models\Mailbox::getDefaultFor($user->id);
        }

        // Handle provider-specific logic
        $provider = $mailbox?->provider;

        // Gmail: Google manages SPF/DKIM, no need to check user's domain
        if ($provider === \App\Models\Mailbox::PROVIDER_GMAIL) {
            $contentAnalysis = $this->inboxPassportService->analyzeContentOnly(
                $validated['subject'],
                $validated['content'],
                'gmail' // Pass provider for context
            );

            return response()->json([
                'success' => true,
                'has_domain' => true, // Gmail domain is managed by Google
                'provider' => 'gmail',
                'provider_info' => __('deliverability.messages.gmail_managed_dns'),
                'inbox_score' => $contentAnalysis['score'],
                'predicted_folder' => $contentAnalysis['predicted_folder'],
                'provider_predictions' => $contentAnalysis['provider_predictions'] ?? null,
                'issues' => $contentAnalysis['issues'],
                'recommendations' => $contentAnalysis['recommendations'],
                'score_breakdown' => $contentAnalysis['score_breakdown'],
            ]);
        }

        // SendGrid/SMTP: Find domain configuration from from_email
        $domain = null;

        if ($mailbox && $mailbox->from_email) {
            $emailDomain = substr(strrchr($mailbox->from_email, '@'), 1);

            // Try to find domain configuration for this email domain
            $domain = DomainConfiguration::forUser($user->id)
                ->where('domain', $emailDomain)
                ->verified()
                ->first();

            // If not verified, try unverified
            if (!$domain) {
                $domain = DomainConfiguration::forUser($user->id)
                    ->where('domain', $emailDomain)
                    ->first();
            }
        }

        // If no domain found for the mailbox's email, return content-only analysis with warning
        if (!$domain) {
            $contentAnalysis = $this->inboxPassportService->analyzeContentOnly(
                $validated['subject'],
                $validated['content'],
                $provider // Pass provider for context
            );

            $message = $mailbox && $mailbox->from_email
                ? __('deliverability.messages.domain_not_configured', [
                    'domain' => substr(strrchr($mailbox->from_email, '@'), 1)
                ])
                : __('deliverability.messages.no_domain_warning');

            return response()->json([
                'success' => true,
                'has_domain' => false,
                'provider' => $provider,
                'inbox_score' => $contentAnalysis['score'],
                'predicted_folder' => $contentAnalysis['predicted_folder'],
                'provider_predictions' => null,
                'issues' => $contentAnalysis['issues'],
                'recommendations' => $contentAnalysis['recommendations'],
                'score_breakdown' => $contentAnalysis['score_breakdown'],
                'message' => $message,
            ]);
        }

        // Run full simulation with domain
        $simulation = $this->inboxPassportService->simulate(
            $user,
            $domain,
            $validated['subject'],
            $validated['content'],
            null // No message_id for quick simulation
        );

        return response()->json([
            'success' => true,
            'has_domain' => true,
            'domain' => $domain->domain,
            'provider' => $provider,
            'simulation_id' => $simulation->id,
            'inbox_score' => $simulation->inbox_score,
            'score_info' => $simulation->getScoreInfo(),
            'predicted_folder' => $simulation->predicted_folder,
            'folder_info' => $simulation->getFolderInfo(),
            'provider_predictions' => $simulation->provider_predictions,
            'issues' => $this->transformIssues($simulation->issues ?? []),
            'recommendations' => $this->transformRecommendations($simulation->recommendations ?? []),
            'score_breakdown' => $simulation->score_breakdown,
        ]);
    }
}
