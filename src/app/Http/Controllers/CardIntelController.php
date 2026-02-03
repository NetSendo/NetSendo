<?php

namespace App\Http\Controllers;

use App\Models\AiIntegration;
use App\Models\CardIntelScan;
use App\Models\CardIntelSettings;
use App\Models\CardIntelAction;
use App\Models\ContactIntelligenceRecord;
use App\Models\CrmContact;
use App\Models\Mailbox;
use App\Services\CardIntel\CardIntelService;
use App\Services\CardIntel\CardIntelScoringService;
use App\Services\AI\AiService;
use App\Services\Mail\MailProviderService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;


class CardIntelController extends Controller
{
    protected CardIntelService $cardIntelService;
    protected CardIntelScoringService $scoringService;

    public function __construct(
        CardIntelService $cardIntelService,
        CardIntelScoringService $scoringService
    ) {
        $this->cardIntelService = $cardIntelService;
        $this->scoringService = $scoringService;
    }

    /**
     * Display the CardIntel dashboard.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $settings = CardIntelSettings::getForUser($user->id);

        return Inertia::render('Crm/CardIntel/Index', [
            'stats' => $this->cardIntelService->getStats($user->id),
            'settings' => $settings,
            'recentScans' => CardIntelScan::forUser($user->id)
                ->with(['extraction', 'context'])
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(),
        ]);
    }

    /**
     * Upload and process a business card scan.
     */
    public function scan(Request $request): JsonResponse
    {
        // Extended MIME type validation for mobile devices
        // iOS/Android can send: image/jpg (instead of image/jpeg), application/octet-stream for camera photos
        $request->validate([
            'file' => 'required|file|mimetypes:image/jpeg,image/jpg,image/png,image/webp,image/heic,image/heif,application/pdf,application/octet-stream|max:10240',
            'mode' => 'nullable|in:manual,agent,auto',
        ]);

        // Additional validation for octet-stream - check file extension
        $file = $request->file('file');
        if ($file && $file->getMimeType() === 'application/octet-stream') {
            $extension = strtolower($file->getClientOriginalExtension());
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif', 'pdf'];
            if (!in_array($extension, $allowedExtensions)) {
                return response()->json([
                    'success' => false,
                    'message' => __('validation.mimetypes', ['attribute' => 'file', 'values' => 'JPG, PNG, WebP, HEIC, PDF']),
                ], 422);
            }
        }

        try {
            $scan = $this->cardIntelService->processUpload(
                $request->file('file'),
                $request->user()->id,
                $request->input('mode')
            );

            return response()->json([
                'success' => true,
                'message' => 'Wizytówka przetworzona pomyślnie',
                'scan' => $this->formatScanResponse($scan),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Błąd przetwarzania: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Display the scan queue (pending reviews).
     */
    public function queue(Request $request): Response
    {
        $user = $request->user();

        $scans = CardIntelScan::forUser($user->id)
            ->with(['extraction', 'context', 'enrichment'])
            ->whereIn('status', ['completed', 'pending', 'processing'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return Inertia::render('Crm/CardIntel/Queue', [
            'scans' => $scans,
            'stats' => $this->cardIntelService->getStats($user->id),
        ]);
    }

    /**
     * Display the Contact Intelligence Records (NetSendo Memory).
     */
    public function memory(Request $request): Response
    {
        $user = $request->user();

        $records = ContactIntelligenceRecord::forUser($user->id)
            ->with(['latestScan.context', 'crmContact'])
            ->orderBy('updated_at', 'desc')
            ->paginate(20);

        return Inertia::render('Crm/CardIntel/Memory', [
            'records' => $records,
            'stats' => [
                'total' => ContactIntelligenceRecord::forUser($user->id)->count(),
                'synced_to_crm' => ContactIntelligenceRecord::forUser($user->id)->syncedToCrm()->count(),
            ],
        ]);
    }

    /**
     * Display settings page.
     */
    public function settings(Request $request): Response
    {
        $user = $request->user();
        $settings = CardIntelSettings::getForUser($user->id);

        // Get available lists for dropdowns
        $emailLists = []; // TODO: Fetch from Lists model
        $smsLists = []; // TODO: Fetch from SMS Lists

        // Get user's mailboxes for sending settings
        $mailboxes = Mailbox::forUser($user->id)
            ->active()
            ->get(['id', 'name', 'from_email', 'is_default']);

        // Get vision-capable AI providers and their integration status
        $visionProviders = $this->getVisionProvidersStatus();

        return Inertia::render('Crm/CardIntel/Settings', [
            'settings' => $settings,
            'emailLists' => $emailLists,
            'smsLists' => $smsLists,
            'mailboxes' => $mailboxes,
            'modes' => CardIntelSettings::getAvailableModes(),
            'crmSyncModes' => CardIntelSettings::getAvailableCrmSyncModes(),
            'tones' => CardIntelSettings::getAvailableTones(),
            'visionProviders' => $visionProviders,
        ]);
    }


    /**
     * Update settings.
     */
    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'default_mode' => 'nullable|in:manual,agent,auto',
            'low_threshold' => 'nullable|integer|min:0|max:100',
            'high_threshold' => 'nullable|integer|min:0|max:100',
            'crm_sync_mode' => 'nullable|in:always,approve,high_only,never',
            'crm_min_score' => 'nullable|integer|min:0|max:100',
            'default_email_lists' => 'nullable|array',
            'default_sms_lists' => 'nullable|array',
            'list_add_mode' => 'nullable|in:always,approve,high_only',
            'enrichment_enabled' => 'nullable|boolean',
            'enrichment_only_medium_high' => 'nullable|boolean',
            'enrichment_timeout' => 'nullable|integer|min:5|max:30',
            'auto_send_enabled' => 'nullable|boolean',
            'auto_send_min_score' => 'nullable|integer|min:0|max:100',
            'auto_send_corporate_only' => 'nullable|boolean',
            'default_tone' => 'nullable|in:professional,friendly,formal',
            'show_all_context_levels' => 'nullable|boolean',
            'default_mailbox_id' => 'nullable|exists:mailboxes,id',
            'custom_ai_prompt' => 'nullable|string|max:2000',
            'allowed_html_tags' => 'nullable|string|max:500',
        ]);

        $settings = CardIntelSettings::getForUser($request->user()->id);
        $settings->update($validated);

        // Return JSON for pure AJAX requests (mode quick-switch from Index.vue)
        // Exclude Inertia requests which also use AJAX but expect redirects
        $isInertiaRequest = $request->header('X-Inertia') === 'true';
        if (!$isInertiaRequest && ($request->wantsJson() || $request->ajax())) {
            return response()->json([
                'success' => true,
                'message' => __('crm.cardintel.settings.saved'),
                'settings' => $settings->fresh(),
            ]);
        }

        return redirect()->back()->with('success', __('crm.cardintel.settings.saved'));
    }

    /**
     * Show a single scan with full details.
     */
    public function show(Request $request, CardIntelScan $scan): Response
    {
        // Authorization check
        if ($scan->user_id !== $request->user()->id) {
            abort(403);
        }

        $scan->load(['extraction', 'context', 'enrichment', 'actions', 'intelligenceRecord']);

        $settings = CardIntelSettings::getForUser($request->user()->id);

        return Inertia::render('Crm/CardIntel/Show', [
            'scan' => $this->formatScanResponse($scan),
            'settings' => $settings,
            'recommendations' => $this->cardIntelService->getRecommendations($scan),
        ]);
    }

    /**
     * Execute an action on a scan.
     */
    public function executeAction(Request $request, CardIntelScan $scan): JsonResponse
    {
        // Authorization check
        if ($scan->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'action' => 'required|in:save_memory,add_crm,add_email_list,add_sms_list,send_email,send_sms',
            'list_id' => 'nullable|integer',
            'message' => 'nullable|array',
        ]);

        $action = $request->input('action');

        try {
            $result = match($action) {
                'save_memory' => $this->cardIntelService->saveToMemory($scan),
                'add_crm' => $this->cardIntelService->addToCrm($scan),
                'add_email_list' => $this->cardIntelService->addToEmailList($scan, $request->input('list_id')),
                'add_sms_list' => $this->cardIntelService->addToSmsList($scan, $request->input('list_id')),
                'send_email' => $this->handleSendEmail($scan, $request->input('message')),
                'send_sms' => $this->handleSendSms($scan, $request->input('message')),
            };

            return response()->json([
                'success' => true,
                'message' => 'Akcja wykonana pomyślnie',
                'result' => $result,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Błąd: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Generate or regenerate a message draft.
     */
    public function generateMessage(Request $request, CardIntelScan $scan): JsonResponse
    {
        // Authorization check
        if ($scan->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'context_level' => 'nullable|in:LOW,MEDIUM,HIGH',
            'tone' => 'nullable|in:professional,friendly,formal',
            'all_versions' => 'nullable|boolean',
            'formality' => 'nullable|in:formal,informal',
            'gender' => 'nullable|in:auto,male,female',
        ]);

        try {
            if ($request->input('all_versions')) {
                $messages = $this->cardIntelService->generateAllVersions(
                    $scan,
                    $request->input('tone'),
                    $request->input('formality'),
                    $request->input('gender')
                );

                return response()->json([
                    'success' => true,
                    'messages' => $messages,
                ]);
            }

            $message = $this->cardIntelService->generateMessage(
                $scan,
                $request->input('context_level'),
                $request->input('tone'),
                $request->input('formality'),
                $request->input('gender')
            );

            return response()->json([
                'success' => true,
                'message' => $message,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Błąd generowania wiadomości: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Update extracted fields manually.
     */
    public function updateExtraction(Request $request, CardIntelScan $scan)
    {
        // Authorization check
        if ($scan->user_id !== $request->user()->id) {
            if ($request->header('X-Inertia')) {
                abort(403);
            }
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'fields' => 'required|array',
            'fields.first_name' => 'nullable|string|max:255',
            'fields.last_name' => 'nullable|string|max:255',
            'fields.company' => 'nullable|string|max:255',
            'fields.email' => 'nullable|email|max:255',
            'fields.phone' => 'nullable|string|max:50',
            'fields.website' => 'nullable|string|max:255',
            'fields.nip' => 'nullable|string|max:20',
            'fields.regon' => 'nullable|string|max:20',
            'fields.position' => 'nullable|string|max:255',
        ]);

        try {
            $extraction = $scan->extraction;

            if (!$extraction) {
                if ($request->header('X-Inertia')) {
                    return redirect()->back()->with('error', 'Brak danych ekstrakcji');
                }
                return response()->json([
                    'success' => false,
                    'message' => 'Brak danych ekstrakcji',
                ], 404);
            }

            // Update fields
            $extraction->updateFields($request->input('fields'));

            // Re-score after update
            $this->cardIntelService->rescoreScan($scan);

            // Return Inertia redirect for Inertia requests
            if ($request->header('X-Inertia')) {
                return redirect()->back()->with('success', 'Dane zaktualizowane');
            }

            // Return JSON for pure AJAX requests
            return response()->json([
                'success' => true,
                'message' => 'Dane zaktualizowane',
                'extraction' => $extraction->fresh(),
                'context' => $scan->context->fresh(),
            ]);

        } catch (\Exception $e) {
            if ($request->header('X-Inertia')) {
                return redirect()->back()->with('error', 'Błąd aktualizacji: ' . $e->getMessage());
            }
            return response()->json([
                'success' => false,
                'message' => 'Błąd aktualizacji: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * API: Get dashboard stats.
     */
    public function apiStats(Request $request): JsonResponse
    {
        return response()->json([
            'stats' => $this->cardIntelService->getStats($request->user()->id),
        ]);
    }

    /**
     * Format scan for API response.
     */
    protected function formatScanResponse(CardIntelScan $scan): array
    {
        return [
            'id' => $scan->id,
            'file_url' => $scan->file_url,
            'raw_text' => $scan->raw_text,
            'status' => $scan->status,
            'mode' => $scan->mode,
            'error_message' => $scan->error_message,
            'created_at' => $scan->created_at->toIso8601String(),
            'extraction' => $scan->extraction ? [
                'fields' => $scan->extraction->fields,
                'fields_with_confidence' => $scan->extraction->fields_with_confidence,
                'average_confidence' => $scan->extraction->average_confidence,
            ] : null,
            'context' => $scan->context ? [
                'context_level' => $scan->context->context_level,
                'quality_score' => $scan->context->quality_score,
                'signals' => $scan->context->signals_with_labels,
                'reasoning' => $scan->context->reasoning_bullets,
                'level_color' => $scan->context->level_color,
                'level_label' => $scan->context->level_label,
            ] : null,
            'enrichment' => $scan->enrichment ? [
                'website_summary' => $scan->enrichment->website_summary,
                'firmographics' => $scan->enrichment->firmographics,
                'b2b_b2c_guess' => $scan->enrichment->b2b_b2c_guess,
                'use_case_hypothesis' => $scan->enrichment->use_case_hypothesis,
                'has_data' => $scan->enrichment->hasAnyData(),
            ] : null,
            'actions' => $scan->actions->map(fn($a) => [
                'id' => $a->id,
                'action_type' => $a->action_type,
                'type_label' => $a->type_label,
                'status' => $a->status,
                'status_label' => $a->status_label,
                'executed_at' => $a->executed_at?->toIso8601String(),
            ]),
        ];
    }

    /**
     * Handle email sending with CRM integration.
     *
     * Ensures contact exists in CRM, sends email, and logs activity.
     */
    protected function handleSendEmail(CardIntelScan $scan, ?array $message): array
    {
        $extraction = $scan->extraction;
        if (!$extraction || empty($message)) {
            throw new \Exception('Brak danych do wysłania wiadomości.');
        }

        $fields = $extraction->fields;
        $recipientEmail = $fields['email'] ?? null;
        if (!$recipientEmail) {
            throw new \Exception('Brak adresu email kontaktu.');
        }

        // 1. Ensure contact exists in CRM
        $crmContact = $this->ensureContactInCrm($scan);
        if (!$crmContact) {
            throw new \Exception('Nie udało się utworzyć kontaktu w CRM.');
        }

        // 2. Get mailbox (from settings or default)
        $settings = CardIntelSettings::getForUser($scan->user_id);
        $mailbox = null;

        if ($settings->default_mailbox_id) {
            $mailbox = Mailbox::forUser($scan->user_id)
                ->active()
                ->find($settings->default_mailbox_id);
        }

        if (!$mailbox) {
            $mailProviderService = app(MailProviderService::class);
            $mailbox = $mailProviderService->getBestMailbox($scan->user_id, 'system');
        }

        if (!$mailbox) {
            throw new \Exception('Brak skonfigurowanej skrzynki pocztowej.');
        }

        // 3. Prepare email content
        $subject = $message['subject'] ?? 'Wiadomość od ' . config('app.name');
        $greeting = $message['greeting'] ?? '';
        $body = $message['body'] ?? '';
        $preheader = $message['preheader'] ?? '';

        // Build full HTML email
        $htmlBody = $this->buildEmailHtml($greeting, $body, $preheader);
        $textBody = strip_tags(str_replace(['</p>', '<br>', '<br/>'], "\n", $body));

        // 4. Send email
        try {
            $mailProviderService = app(MailProviderService::class);
            $provider = $mailProviderService->getProvider($mailbox);

            $result = $provider->send(
                to: $recipientEmail,
                subject: $subject,
                html: $htmlBody,
                text: $textBody
            );

            if (!$result['success']) {
                throw new \Exception($result['error'] ?? 'Błąd wysyłania emaila.');
            }

            // Increment mailbox sent count
            $mailbox->incrementSentCount();

            // 5. Log activity to CRM contact
            $recipientName = trim(($fields['first_name'] ?? '') . ' ' . ($fields['last_name'] ?? ''));
            $crmContact->logActivity('email_sent', "Wysłano email: {$subject}", [
                'source' => 'cardintel',
                'subject' => $subject,
                'preheader' => $preheader,
                'mailbox_name' => $mailbox->name,
                'mailbox_id' => $mailbox->id,
                'recipient_email' => $recipientEmail,
                'recipient_name' => $recipientName ?: null,
                'scan_id' => $scan->id,
            ]);

            // 6. Log CardIntel action
            CardIntelAction::createForScan($scan, CardIntelAction::TYPE_SEND_EMAIL, [
                'crm_contact_id' => $crmContact->id,
                'mailbox_id' => $mailbox->id,
                'subject' => $subject,
            ])->markAsCompleted();

            Log::info('CardIntel email sent', [
                'scan_id' => $scan->id,
                'crm_contact_id' => $crmContact->id,
                'recipient' => $recipientEmail,
            ]);

            return [
                'success' => true,
                'crm_contact_id' => $crmContact->id,
            ];

        } catch (\Exception $e) {
            Log::error('CardIntel email send failed', [
                'scan_id' => $scan->id,
                'error' => $e->getMessage(),
            ]);

            CardIntelAction::createForScan($scan, CardIntelAction::TYPE_SEND_EMAIL)
                ->markAsFailed($e->getMessage());

            throw $e;
        }
    }

    /**
     * Ensure the scan contact exists in CRM.
     */
    protected function ensureContactInCrm(CardIntelScan $scan): ?CrmContact
    {
        $extraction = $scan->extraction;
        if (!$extraction) {
            return null;
        }

        $fields = $extraction->fields;
        $email = $fields['email'] ?? null;

        if (!$email) {
            return null;
        }

        // Check if CRM contact already exists via CIR
        $cir = $scan->intelligenceRecord;
        if ($cir && $cir->crm_contact_id) {
            return CrmContact::find($cir->crm_contact_id);
        }

        // Try to find existing contact by email
        $existingContact = CrmContact::whereHas('subscriber', function ($q) use ($email, $scan) {
            $q->where('email', $email)->where('user_id', $scan->user_id);
        })->first();

        if ($existingContact) {
            // Link CIR if exists
            if ($cir) {
                $cir->markAsSyncedToCrm($existingContact->id);
            }
            return $existingContact;
        }

        // Create new CRM contact
        return $this->cardIntelService->addToCrm($scan);
    }

    /**
     * Build full HTML email with greeting and preheader.
     */
    protected function buildEmailHtml(string $greeting, string $body, string $preheader = ''): string
    {
        $preheaderHtml = $preheader
            ? '<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;">' . e($preheader) . '</div>'
            : '';

        $greetingHtml = $greeting
            ? '<p style="margin-bottom: 16px;">' . e($greeting) . '</p>'
            : '';

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #333;">
    {$preheaderHtml}
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        {$greetingHtml}
        {$body}
    </div>
</body>
</html>
HTML;
    }

    /**
     * Handle SMS sending (placeholder).
     */
    protected function handleSendSms(CardIntelScan $scan, ?array $message): bool
    {
        // TODO: Integrate with existing SMS sending system

        return true;
    }

    /**
     * Get vision-capable AI providers and their integration status.
     *
     * @return array List of providers that support vision with their status
     */
    protected function getVisionProvidersStatus(): array
    {
        // Providers that support vision/image analysis
        $visionCapable = [
            'openai' => [
                'name' => 'OpenAI',
                'models' => ['GPT-4o', 'GPT-4o-mini', 'GPT-4-turbo'],
                'description' => 'Modele GPT-4o obsługują rozpoznawanie obrazów',
                'recommended' => true,
            ],
            'gemini' => [
                'name' => 'Google Gemini',
                'models' => ['Gemini 2.5 Pro', 'Gemini 2.5 Flash', 'Gemini 2.0'],
                'description' => 'Wszystkie modele Gemini obsługują wizję',
                'recommended' => true,
            ],
        ];

        // Check which are integrated
        $integrations = AiIntegration::active()
            ->whereIn('provider', array_keys($visionCapable))
            ->get()
            ->keyBy('provider');

        $result = [];

        foreach ($visionCapable as $provider => $info) {
            $integration = $integrations->get($provider);
            $result[] = [
                'provider' => $provider,
                'name' => $info['name'],
                'models' => $info['models'],
                'description' => $info['description'],
                'recommended' => $info['recommended'],
                'integrated' => $integration !== null && $integration->hasApiKey(),
                'active' => $integration !== null && $integration->is_active,
                'lastTested' => $integration?->last_tested_at?->toIso8601String(),
                'testStatus' => $integration?->last_test_status,
            ];
        }

        // Check if any vision provider is available
        $hasVisionProvider = collect($result)->contains(fn($p) => $p['integrated'] && $p['active']);

        return [
            'providers' => $result,
            'hasVisionProvider' => $hasVisionProvider,
            'message' => $hasVisionProvider
                ? 'CardIntel gotowy do skanowania wizytówek'
                : 'Skonfiguruj OpenAI lub Google Gemini do skanowania wizytówek',
        ];
    }
}
