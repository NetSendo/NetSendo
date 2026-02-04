<?php

namespace App\Services\CardIntel;

use App\Models\CardIntelScan;
use App\Models\CardIntelExtraction;
use App\Models\CardIntelContext;
use App\Models\CardIntelEnrichment;
use App\Models\CardIntelAction;
use App\Models\CardIntelSettings;
use App\Models\ContactIntelligenceRecord;
use App\Models\CrmContact;
use App\Models\Subscriber;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CardIntelService
{
    protected CardIntelStorageService $storageService;
    protected CardIntelOcrService $ocrService;
    protected CardIntelScoringService $scoringService;
    protected CardIntelEnrichmentService $enrichmentService;
    protected CardIntelDecisionEngineService $decisionEngine;

    public function __construct(
        CardIntelStorageService $storageService,
        CardIntelOcrService $ocrService,
        CardIntelScoringService $scoringService,
        CardIntelEnrichmentService $enrichmentService,
        CardIntelDecisionEngineService $decisionEngine
    ) {
        $this->storageService = $storageService;
        $this->ocrService = $ocrService;
        $this->scoringService = $scoringService;
        $this->enrichmentService = $enrichmentService;
        $this->decisionEngine = $decisionEngine;
    }

    /**
     * Process an uploaded business card image.
     * This is the main entry point for the CardIntel workflow.
     */
    public function processUpload(UploadedFile $file, int $userId, ?string $mode = null): CardIntelScan
    {
        $settings = CardIntelSettings::getForUser($userId);
        $mode ??= $settings->default_mode;

        try {
            // 1. Store the file
            $storageInfo = $this->storageService->store($file, $userId);

            // 2. Create scan record
            $scan = CardIntelScan::create([
                'user_id' => $userId,
                'file_path' => $storageInfo['file_path'],
                'file_url' => $storageInfo['file_url'],
                'status' => CardIntelScan::STATUS_PROCESSING,
                'mode' => $mode,
            ]);

            // 3. Process the scan (OCR, scoring, enrichment)
            $this->processScan($scan, $settings);

            return $scan->fresh(['extraction', 'context', 'enrichment', 'actions']);

        } catch (\Exception $e) {
            Log::error('CardIntel upload processing failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            if (isset($scan)) {
                $scan->markAsFailed($e->getMessage());
            }

            throw $e;
        }
    }

    /**
     * Process a scan through the full pipeline.
     */
    protected function processScan(CardIntelScan $scan, CardIntelSettings $settings): void
    {
        try {
            // 1. OCR Extraction
            $ocrResult = $this->ocrService->extractFromScan($scan);
            $extraction = $this->ocrService->createExtraction($scan, $ocrResult);

            // 2. Context Scoring
            $context = $this->scoringService->scoreExtraction($extraction, $settings);

            // 3. Enrichment (if applicable)
            if ($settings->shouldEnrich($context->context_level)) {
                $this->enrichmentService->enrichScan($scan, $settings);
            }

            // 4. Mark as completed
            $scan->markAsCompleted();

            // 5. Handle auto-mode actions
            if ($scan->isAutoMode()) {
                $this->handleAutoModeActions($scan, $settings);
            }

        } catch (\Exception $e) {
            $scan->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    /**
     * Handle automatic actions in auto mode.
     */
    protected function handleAutoModeActions(CardIntelScan $scan, CardIntelSettings $settings): void
    {
        $context = $scan->context;

        if (!$context) {
            return;
        }

        // Always save to memory
        $this->saveToMemory($scan);

        // Auto-sync to CRM if settings allow
        if ($settings->shouldAutoSyncToCrm($context->quality_score)) {
            $this->addToCrm($scan);
        }

        // Auto-add to lists if settings allow
        if ($settings->shouldAutoAddToLists($context->quality_score)) {
            $this->addToDefaultLists($scan, $settings);
        }

        // Auto-send only if guardrails pass
        if ($settings->qualifiesForAutoSend($context)) {
            // Generate message and queue for sending
            // Note: Actual sending will require separate confirmation in most cases
            $this->queueAutoSend($scan, $settings);
        }
    }

    /**
     * Save scan to ContactIntelligenceRecord (NetSendo Memory).
     */
    public function saveToMemory(CardIntelScan $scan): ?ContactIntelligenceRecord
    {
        $cir = ContactIntelligenceRecord::findOrCreateForScan($scan);

        if ($cir) {
            CardIntelAction::createForScan($scan, CardIntelAction::TYPE_SAVE_MEMORY, [
                'cir_id' => $cir->id,
            ])->markAsCompleted();
        }

        return $cir;
    }

    /**
     * Add scan contact to CRM.
     */
    public function addToCrm(CardIntelScan $scan): ?CrmContact
    {
        $extraction = $scan->extraction;

        if (!$extraction || !$extraction->hasMinimumFields()) {
            return null;
        }

        $fields = $extraction->fields;

        try {
            return DB::transaction(function () use ($scan, $fields) {
                // Create or find subscriber first
                $subscriber = Subscriber::firstOrCreate(
                    [
                        'user_id' => $scan->user_id,
                        'email' => $fields['email'],
                    ],
                    [
                        'first_name' => $fields['first_name'],
                        'last_name' => $fields['last_name'],
                        'phone' => $fields['phone'],
                        'status' => 'subscribed',
                        'source' => 'cardintel',
                    ]
                );

                // Create CRM contact
                $contact = CrmContact::create([
                    'subscriber_id' => $subscriber->id,
                    'user_id' => $scan->user_id,
                    'status' => 'lead',
                    'source' => 'cardintel',
                    'position' => $fields['position'],
                ]);

                // Link CIR to CRM contact
                $cir = $scan->intelligenceRecord;
                if ($cir) {
                    $cir->markAsSyncedToCrm($contact->id);
                }

                // Log action
                CardIntelAction::createForScan($scan, CardIntelAction::TYPE_ADD_CRM, [
                    'crm_contact_id' => $contact->id,
                    'subscriber_id' => $subscriber->id,
                ])->markAsCompleted();

                return $contact;
            });

        } catch (\Exception $e) {
            Log::warning('CardIntel add to CRM failed', [
                'scan_id' => $scan->id,
                'error' => $e->getMessage(),
            ]);

            CardIntelAction::createForScan($scan, CardIntelAction::TYPE_ADD_CRM)
                ->markAsFailed($e->getMessage());

            return null;
        }
    }

    /**
     * Add to default email/SMS lists.
     */
    public function addToDefaultLists(CardIntelScan $scan, CardIntelSettings $settings): array
    {
        $added = ['email' => [], 'sms' => []];

        // Add to email lists
        if (!empty($settings->default_email_lists)) {
            foreach ($settings->default_email_lists as $listId) {
                $result = $this->addToEmailList($scan, $listId);
                if ($result) {
                    $added['email'][] = $listId;
                }
            }
        }

        // Add to SMS lists
        if (!empty($settings->default_sms_lists)) {
            foreach ($settings->default_sms_lists as $listId) {
                $result = $this->addToSmsList($scan, $listId);
                if ($result) {
                    $added['sms'][] = $listId;
                }
            }
        }

        return $added;
    }

    /**
     * Add to a specific email list.
     */
    public function addToEmailList(CardIntelScan $scan, int $listId): bool
    {
        $extraction = $scan->extraction;

        if (!$extraction || empty($extraction->fields['email'])) {
            Log::warning('CardIntel addToEmailList: No email in extraction', [
                'scan_id' => $scan->id,
                'list_id' => $listId,
            ]);
            return false;
        }

        $fields = $extraction->fields;

        try {
            return DB::transaction(function () use ($scan, $fields, $listId) {
                // Find or create subscriber
                $subscriber = Subscriber::firstOrCreate(
                    [
                        'user_id' => $scan->user_id,
                        'email' => $fields['email'],
                    ],
                    [
                        'first_name' => $fields['first_name'] ?? null,
                        'last_name' => $fields['last_name'] ?? null,
                        'phone' => $fields['phone'] ?? null,
                        'status' => 'subscribed',
                        'source' => 'cardintel',
                        'is_active_global' => true,
                        'subscribed_at' => now(),
                    ]
                );

                // Find the contact list
                $contactList = \App\Models\ContactList::where('id', $listId)
                    ->where('user_id', $scan->user_id)
                    ->first();

                if (!$contactList) {
                    Log::warning('CardIntel addToEmailList: List not found', [
                        'scan_id' => $scan->id,
                        'list_id' => $listId,
                    ]);
                    return false;
                }

                // Check if already attached to this list
                if (!$subscriber->contactLists()->where('contact_list_id', $listId)->exists()) {
                    // Attach subscriber to list via pivot table
                    $subscriber->contactLists()->attach($listId, [
                        'status' => 'active',
                        'source' => 'cardintel',
                        'subscribed_at' => now(),
                    ]);
                }

                // Log action
                CardIntelAction::createForScan($scan, CardIntelAction::TYPE_ADD_EMAIL_LIST, [
                    'list_id' => $listId,
                    'list_name' => $contactList->name,
                    'subscriber_id' => $subscriber->id,
                ])->markAsCompleted();

                return true;
            });

        } catch (\Exception $e) {
            Log::error('CardIntel addToEmailList failed', [
                'scan_id' => $scan->id,
                'list_id' => $listId,
                'error' => $e->getMessage(),
            ]);

            CardIntelAction::createForScan($scan, CardIntelAction::TYPE_ADD_EMAIL_LIST, [
                'list_id' => $listId,
            ])->markAsFailed($e->getMessage());

            return false;
        }
    }

    /**
     * Add to a specific SMS list.
     */
    public function addToSmsList(CardIntelScan $scan, int $listId): bool
    {
        // TODO: Implement SMS list addition

        CardIntelAction::createForScan($scan, CardIntelAction::TYPE_ADD_SMS_LIST, [
            'list_id' => $listId,
        ])->markAsCompleted();

        return true;
    }

    /**
     * Queue auto-send (for auto mode).
     */
    protected function queueAutoSend(CardIntelScan $scan, CardIntelSettings $settings): void
    {
        // Generate message for current context level
        $message = $this->decisionEngine->generateMessage($scan, null, $settings->default_tone);

        // Create pending action (will be executed by job/queue)
        CardIntelAction::createForScan($scan, CardIntelAction::TYPE_SEND_EMAIL, [
            'subject' => $message['subject'],
            'body' => $message['body'],
            'context_level' => $message['context_level'],
            'auto_generated' => true,
        ]);

        // Note: Actual sending is handled separately with proper queue
    }

    /**
     * Rescore a scan after manual field updates.
     */
    public function rescoreScan(CardIntelScan $scan): CardIntelContext
    {
        return $this->scoringService->rescoreScan($scan);
    }

    /**
     * Generate message for a scan.
     */
    public function generateMessage(
        CardIntelScan $scan,
        ?string $contextLevel = null,
        ?string $tone = null,
        ?string $formality = null,
        ?string $gender = null
    ): array {
        $message = $this->decisionEngine->generateMessage($scan, $contextLevel, $tone, $formality, $gender);

        // Log action
        CardIntelAction::createForScan($scan, CardIntelAction::TYPE_GENERATE_MESSAGE, [
            'context_level' => $message['context_level'],
        ])->markAsCompleted();

        return $message;
    }

    /**
     * Generate all message versions (LOW, MEDIUM, HIGH) for a scan.
     */
    public function generateAllVersions(CardIntelScan $scan, ?string $tone = null, ?string $formality = null, ?string $gender = null): array
    {
        return $this->decisionEngine->generateAllVersions($scan, $tone, $formality, $gender);
    }

    /**
     * Get queue of scans pending review.
     */
    public function getReviewQueue(int $userId, int $limit = 20): \Illuminate\Database\Eloquent\Collection
    {
        return CardIntelScan::forUser($userId)
            ->needsReview()
            ->with(['extraction', 'context', 'enrichment'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get recommendations for a scan.
     */
    public function getRecommendations(CardIntelScan $scan): array
    {
        return $this->decisionEngine->getRecommendations($scan);
    }

    /**
     * Get dashboard statistics.
     */
    public function getStats(int $userId): array
    {
        $scans = CardIntelScan::forUser($userId);

        return [
            'total_scans' => $scans->count(),
            'pending_review' => (clone $scans)->needsReview()->count(),
            'completed' => (clone $scans)->completed()->count(),
            'failed' => (clone $scans)->failed()->count(),
            'contacts_created' => ContactIntelligenceRecord::forUser($userId)->count(),
            'synced_to_crm' => ContactIntelligenceRecord::forUser($userId)->syncedToCrm()->count(),
        ];
    }
}
