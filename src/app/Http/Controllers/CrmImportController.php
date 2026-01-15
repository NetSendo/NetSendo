<?php

namespace App\Http\Controllers;

use App\Models\CrmContact;
use App\Models\CrmCompany;
use App\Models\Subscriber;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\DB;
use League\Csv\Reader;

class CrmImportController extends Controller
{
    /**
     * Display the import form.
     */
    public function index(): Response
    {
        return Inertia::render('Crm/Import/Index');
    }

    /**
     * Preview CSV file and show column mapping.
     */
    public function preview(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240', // 10MB max
        ]);

        $file = $request->file('file');
        $csv = Reader::createFromPath($file->getRealPath(), 'r');
        $csv->setHeaderOffset(0);

        $headers = $csv->getHeader();
        $records = iterator_to_array($csv->getRecords());
        $sampleRows = array_slice($records, 0, 5);

        // Available fields for mapping
        $availableFields = [
            ['value' => 'email', 'label' => 'Email', 'required' => true],
            ['value' => 'first_name', 'label' => 'Imię', 'required' => false],
            ['value' => 'last_name', 'label' => 'Nazwisko', 'required' => false],
            ['value' => 'phone', 'label' => 'Telefon', 'required' => false],
            ['value' => 'company_name', 'label' => 'Nazwa firmy', 'required' => false],
            ['value' => 'position', 'label' => 'Stanowisko', 'required' => false],
            ['value' => 'source', 'label' => 'Źródło', 'required' => false],
            ['value' => 'status', 'label' => 'Status CRM', 'required' => false],
            ['value' => 'skip', 'label' => '-- Pomiń --', 'required' => false],
        ];

        // Auto-detect mapping
        $suggestedMapping = [];
        foreach ($headers as $header) {
            $headerLower = strtolower(trim($header));

            if (str_contains($headerLower, 'email') || str_contains($headerLower, 'e-mail')) {
                $suggestedMapping[$header] = 'email';
            } elseif (str_contains($headerLower, 'imię') || str_contains($headerLower, 'first') || $headerLower === 'name') {
                $suggestedMapping[$header] = 'first_name';
            } elseif (str_contains($headerLower, 'nazwisko') || str_contains($headerLower, 'last')) {
                $suggestedMapping[$header] = 'last_name';
            } elseif (str_contains($headerLower, 'telefon') || str_contains($headerLower, 'phone') || str_contains($headerLower, 'tel')) {
                $suggestedMapping[$header] = 'phone';
            } elseif (str_contains($headerLower, 'firma') || str_contains($headerLower, 'company')) {
                $suggestedMapping[$header] = 'company_name';
            } elseif (str_contains($headerLower, 'stanowisko') || str_contains($headerLower, 'position') || str_contains($headerLower, 'title')) {
                $suggestedMapping[$header] = 'position';
            } elseif (str_contains($headerLower, 'źródło') || str_contains($headerLower, 'source')) {
                $suggestedMapping[$header] = 'source';
            } else {
                $suggestedMapping[$header] = 'skip';
            }
        }

        // Store file path in session for import
        $tempPath = $file->store('crm-imports', 'local');

        return response()->json([
            'headers' => $headers,
            'sampleRows' => $sampleRows,
            'totalRows' => count($records),
            'availableFields' => $availableFields,
            'suggestedMapping' => $suggestedMapping,
            'tempPath' => $tempPath,
        ]);
    }

    /**
     * Execute the import.
     */
    public function import(Request $request): RedirectResponse
    {
        $userId = auth()->user()->admin_user_id ?? auth()->id();

        $validated = $request->validate([
            'temp_path' => 'required|string',
            'mapping' => 'required|array',
            'duplicate_action' => 'required|in:update,skip',
            'default_status' => 'required|in:lead,prospect,client,dormant',
        ]);

        $filePath = storage_path('app/' . $validated['temp_path']);

        if (!file_exists($filePath)) {
            return redirect()->back()->with('error', 'Plik tymczasowy wygasł. Proszę przesłać plik ponownie.');
        }

        $csv = Reader::createFromPath($filePath, 'r');
        $csv->setHeaderOffset(0);
        $records = iterator_to_array($csv->getRecords());

        $mapping = $validated['mapping'];
        $duplicateAction = $validated['duplicate_action'];
        $defaultStatus = $validated['default_status'];

        // Reverse mapping: field => header
        $fieldToHeader = [];
        foreach ($mapping as $header => $field) {
            if ($field !== 'skip') {
                $fieldToHeader[$field] = $header;
            }
        }

        if (!isset($fieldToHeader['email'])) {
            return redirect()->back()->with('error', 'Mapowanie musi zawierać pole email.');
        }

        $stats = [
            'added' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        $companyCache = [];

        DB::beginTransaction();

        try {
            foreach ($records as $row) {
                $email = trim($row[$fieldToHeader['email']] ?? '');

                if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $stats['skipped']++;
                    continue;
                }

                // Check for existing subscriber
                $existingSubscriber = Subscriber::where('user_id', $userId)
                    ->where('email', $email)
                    ->first();

                // Check for existing CRM contact
                $existingContact = $existingSubscriber
                    ? CrmContact::where('subscriber_id', $existingSubscriber->id)->first()
                    : null;

                if ($existingContact) {
                    if ($duplicateAction === 'skip') {
                        $stats['skipped']++;
                        continue;
                    }
                    // Update existing
                    $this->updateContact($existingContact, $existingSubscriber, $row, $fieldToHeader, $companyCache, $userId);
                    $stats['updated']++;
                } else {
                    // Create new
                    $this->createContact($existingSubscriber, $row, $fieldToHeader, $companyCache, $userId, $defaultStatus);
                    $stats['added']++;
                }
            }

            DB::commit();

            // Clean up temp file
            @unlink($filePath);

            return redirect()->route('crm.contacts.index')
                ->with('success', "Import zakończony: {$stats['added']} dodanych, {$stats['updated']} zaktualizowanych, {$stats['skipped']} pominiętych.");

        } catch (\Exception $e) {
            DB::rollBack();
            @unlink($filePath);

            return redirect()->back()->with('error', 'Błąd podczas importu: ' . $e->getMessage());
        }
    }

    /**
     * Create a new contact from import row.
     */
    private function createContact(?Subscriber $existingSubscriber, array $row, array $fieldToHeader, array &$companyCache, int $userId, string $defaultStatus): void
    {
        // Create subscriber if not exists
        if (!$existingSubscriber) {
            $existingSubscriber = Subscriber::create([
                'user_id' => $userId,
                'email' => trim($row[$fieldToHeader['email']]),
                'first_name' => isset($fieldToHeader['first_name']) ? trim($row[$fieldToHeader['first_name']] ?? '') : null,
                'last_name' => isset($fieldToHeader['last_name']) ? trim($row[$fieldToHeader['last_name']] ?? '') : null,
                'phone' => isset($fieldToHeader['phone']) ? trim($row[$fieldToHeader['phone']] ?? '') : null,
                'source' => 'crm_import',
            ]);
        }

        // Handle company
        $companyId = null;
        if (isset($fieldToHeader['company_name'])) {
            $companyName = trim($row[$fieldToHeader['company_name']] ?? '');
            if (!empty($companyName)) {
                $companyId = $this->getOrCreateCompany($companyName, $companyCache, $userId);
            }
        }

        // Create CRM contact
        CrmContact::create([
            'subscriber_id' => $existingSubscriber->id,
            'user_id' => $userId,
            'owner_id' => auth()->id(),
            'crm_company_id' => $companyId,
            'status' => isset($fieldToHeader['status']) && in_array($row[$fieldToHeader['status']] ?? '', ['lead', 'prospect', 'client', 'dormant'])
                ? $row[$fieldToHeader['status']]
                : $defaultStatus,
            'source' => isset($fieldToHeader['source']) ? trim($row[$fieldToHeader['source']] ?? 'import') : 'import',
            'position' => isset($fieldToHeader['position']) ? trim($row[$fieldToHeader['position']] ?? '') : null,
        ]);
    }

    /**
     * Update existing contact from import row.
     */
    private function updateContact(CrmContact $contact, Subscriber $subscriber, array $row, array $fieldToHeader, array &$companyCache, int $userId): void
    {
        // Update subscriber
        $subscriberData = [];
        if (isset($fieldToHeader['first_name']) && !empty($row[$fieldToHeader['first_name']])) {
            $subscriberData['first_name'] = trim($row[$fieldToHeader['first_name']]);
        }
        if (isset($fieldToHeader['last_name']) && !empty($row[$fieldToHeader['last_name']])) {
            $subscriberData['last_name'] = trim($row[$fieldToHeader['last_name']]);
        }
        if (isset($fieldToHeader['phone']) && !empty($row[$fieldToHeader['phone']])) {
            $subscriberData['phone'] = trim($row[$fieldToHeader['phone']]);
        }
        if (!empty($subscriberData)) {
            $subscriber->update($subscriberData);
        }

        // Update contact
        $contactData = [];
        if (isset($fieldToHeader['position']) && !empty($row[$fieldToHeader['position']])) {
            $contactData['position'] = trim($row[$fieldToHeader['position']]);
        }
        if (isset($fieldToHeader['company_name'])) {
            $companyName = trim($row[$fieldToHeader['company_name']] ?? '');
            if (!empty($companyName)) {
                $contactData['crm_company_id'] = $this->getOrCreateCompany($companyName, $companyCache, $userId);
            }
        }
        if (!empty($contactData)) {
            $contact->update($contactData);
        }
    }

    /**
     * Get or create a company by name.
     */
    private function getOrCreateCompany(string $name, array &$cache, int $userId): int
    {
        $key = strtolower($name);

        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $company = CrmCompany::where('user_id', $userId)
            ->whereRaw('LOWER(name) = ?', [$key])
            ->first();

        if (!$company) {
            $company = CrmCompany::create([
                'user_id' => $userId,
                'name' => $name,
            ]);
        }

        $cache[$key] = $company->id;
        return $company->id;
    }
}
