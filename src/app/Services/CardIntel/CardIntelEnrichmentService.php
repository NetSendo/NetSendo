<?php

namespace App\Services\CardIntel;

use App\Models\CardIntelScan;
use App\Models\CardIntelExtraction;
use App\Models\CardIntelEnrichment;
use App\Models\CardIntelSettings;
use App\Services\PolishCompanyLookupService;
use App\Services\AI\AiService;
use App\Models\AiIntegration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CardIntelEnrichmentService
{
    protected PolishCompanyLookupService $nipService;
    protected AiService $aiService;

    public function __construct(
        PolishCompanyLookupService $nipService,
        AiService $aiService
    ) {
        $this->nipService = $nipService;
        $this->aiService = $aiService;
    }

    /**
     * Enrich a scan with external data.
     */
    public function enrichScan(CardIntelScan $scan, ?CardIntelSettings $settings = null): CardIntelEnrichment
    {
        $extraction = $scan->extraction;
        $context = $scan->context;

        if (!$extraction) {
            throw new \Exception('No extraction found for scan');
        }

        $settings ??= CardIntelSettings::getForUser($scan->user_id);

        // Check if we should enrich based on context level
        if (!$settings->shouldEnrich($context?->context_level ?? 'LOW')) {
            // Create empty enrichment record
            return $this->createEnrichment($scan, []);
        }

        $fields = $extraction->fields;
        $result = [
            'website_summary' => null,
            'firmographics' => null,
            'language' => 'pl',
            'b2b_b2c_guess' => null,
            'use_case_hypothesis' => null,
        ];

        // 1. NIP/REGON lookup
        if (!empty($fields['nip'])) {
            $result['firmographics'] = $this->lookupNip($fields['nip']);
        } elseif (!empty($fields['regon'])) {
            $result['firmographics'] = $this->lookupRegon($fields['regon']);
        }

        // 2. Website scraping and summary
        if (!empty($fields['website'])) {
            $result['website_summary'] = $this->scrapeAndSummarize(
                $fields['website'],
                $settings->enrichment_timeout
            );
        }

        // 3. AI analysis for B2B/B2C guess and use case
        if ($result['website_summary'] || $result['firmographics']) {
            $aiAnalysis = $this->generateAiAnalysis($fields, $result);
            $result['b2b_b2c_guess'] = $aiAnalysis['b2b_b2c'] ?? 'UNKNOWN';
            $result['use_case_hypothesis'] = $aiAnalysis['use_case'] ?? null;
        }

        return $this->createEnrichment($scan, $result);
    }

    /**
     * Lookup company by NIP.
     */
    protected function lookupNip(string $nip): ?array
    {
        try {
            $data = $this->nipService->lookupByNip($nip);

            if ($data) {
                return [
                    'name' => $data['name'] ?? null,
                    'nip' => $data['nip'] ?? null,
                    'regon' => $data['regon'] ?? null,
                    'address' => $data['address'] ?? null,
                    'vat_status' => $data['vat_status'] ?? null,
                    'krs' => $data['krs'] ?? null,
                    'nip_verified' => true,
                    'source' => 'biala_lista',
                ];
            }
        } catch (\Exception $e) {
            Log::warning('CardIntel NIP lookup failed', [
                'nip' => $nip,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Lookup company by REGON.
     */
    protected function lookupRegon(string $regon): ?array
    {
        try {
            $data = $this->nipService->lookupByRegon($regon);

            if ($data) {
                return [
                    'name' => $data['name'] ?? null,
                    'nip' => $data['nip'] ?? null,
                    'regon' => $data['regon'] ?? null,
                    'address' => $data['address'] ?? null,
                    'vat_status' => $data['vat_status'] ?? null,
                    'krs' => $data['krs'] ?? null,
                    'regon_verified' => true,
                    'source' => 'biala_lista',
                ];
            }
        } catch (\Exception $e) {
            Log::warning('CardIntel REGON lookup failed', [
                'regon' => $regon,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Scrape website and generate AI summary.
     */
    protected function scrapeAndSummarize(string $website, int $timeout = 10): ?string
    {
        // Normalize URL
        $url = $website;
        if (!str_starts_with($url, 'http')) {
            $url = 'https://' . $url;
        }

        try {
            // Fetch website content
            $response = Http::timeout($timeout)
                ->withUserAgent('Mozilla/5.0 (compatible; NetSendo/1.0)')
                ->get($url);

            if (!$response->successful()) {
                return null;
            }

            $html = $response->body();

            // Extract text content
            $text = $this->extractTextFromHtml($html);

            if (strlen($text) < 100) {
                return null;
            }

            // Generate AI summary
            return $this->generateWebsiteSummary($text, $url);

        } catch (\Exception $e) {
            Log::warning('CardIntel website scrape failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Extract plain text from HTML.
     */
    protected function extractTextFromHtml(string $html): string
    {
        // Remove script and style elements
        $html = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);

        // Remove HTML tags
        $text = strip_tags($html);

        // Normalize whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        // Limit length
        return mb_substr($text, 0, 5000);
    }

    /**
     * Generate website summary using AI.
     */
    protected function generateWebsiteSummary(string $text, string $url): ?string
    {
        $integration = $this->aiService->getDefaultIntegration();

        if (!$integration) {
            return null;
        }

        $prompt = <<<PROMPT
Przeanalizuj treść strony internetowej i napisz krótkie streszczenie działalności firmy (2-4 zdania).

ZASADY:
- Opisz TYLKO działalność biznesową firmy
- NIE podawaj danych osobowych ani kontaktowych
- Napisz w języku polskim, profesjonalnym stylem
- Skup się na: czym się firma zajmuje, dla kogo, w jakiej branży

URL: {$url}

TREŚĆ STRONY:
{$text}

STRESZCZENIE:
PROMPT;

        try {
            $summary = $this->aiService->generateContent($prompt, $integration, [
                'max_tokens' => 300,
                'temperature' => 0.3,
            ]);

            return trim($summary);

        } catch (\Exception $e) {
            Log::warning('CardIntel AI summary failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Generate AI analysis for B2B/B2C guess and use case hypothesis.
     */
    protected function generateAiAnalysis(array $fields, array $enrichmentData): array
    {
        $integration = $this->aiService->getDefaultIntegration();

        if (!$integration) {
            return ['b2b_b2c' => 'UNKNOWN', 'use_case' => null];
        }

        $context = [];

        if (!empty($fields['company'])) {
            $context[] = "Firma: " . $fields['company'];
        }
        if (!empty($fields['position'])) {
            $context[] = "Stanowisko: " . $fields['position'];
        }
        if (!empty($enrichmentData['website_summary'])) {
            $context[] = "O firmie: " . $enrichmentData['website_summary'];
        }
        if (!empty($enrichmentData['firmographics']['name'])) {
            $context[] = "Pełna nazwa: " . $enrichmentData['firmographics']['name'];
        }

        $contextStr = implode("\n", $context);

        $prompt = <<<PROMPT
Na podstawie danych o firmie, określ:

1. TYP BIZNESU: Czy firma działa głównie w modelu B2B, B2C, czy MIXED?
2. HIPOTEZA USE-CASE: Jaki problem biznesowy może mieć ta firma i czego może szukać?

DANE:
{$contextStr}

Odpowiedz WYŁĄCZNIE w formacie JSON:
{
  "b2b_b2c": "B2B" lub "B2C" lub "MIXED" lub "UNKNOWN",
  "use_case": "krótka hipoteza (1-2 zdania) lub null"
}
PROMPT;

        try {
            $response = $this->aiService->generateContent($prompt, $integration, [
                'max_tokens' => 200,
                'temperature' => 0.4,
            ]);

            // Parse JSON response
            $response = trim($response);
            if (str_starts_with($response, '```')) {
                $response = preg_replace('/^```(?:json)?\n?/', '', $response);
                $response = preg_replace('/\n?```$/', '', $response);
            }

            $data = json_decode($response, true);

            return [
                'b2b_b2c' => $data['b2b_b2c'] ?? 'UNKNOWN',
                'use_case' => $data['use_case'] ?? null,
            ];

        } catch (\Exception $e) {
            Log::warning('CardIntel AI analysis failed', [
                'error' => $e->getMessage(),
            ]);
            return ['b2b_b2c' => 'UNKNOWN', 'use_case' => null];
        }
    }

    /**
     * Create enrichment record.
     */
    protected function createEnrichment(CardIntelScan $scan, array $result): CardIntelEnrichment
    {
        return CardIntelEnrichment::updateOrCreate(
            ['scan_id' => $scan->id],
            [
                'website_summary' => $result['website_summary'] ?? null,
                'firmographics_json' => $result['firmographics'] ?? null,
                'language' => $result['language'] ?? 'pl',
                'b2b_b2c_guess' => $result['b2b_b2c_guess'] ?? null,
                'use_case_hypothesis' => $result['use_case_hypothesis'] ?? null,
            ]
        );
    }
}
