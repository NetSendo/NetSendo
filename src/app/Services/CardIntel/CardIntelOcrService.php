<?php

namespace App\Services\CardIntel;

use App\Models\CardIntelScan;
use App\Models\CardIntelExtraction;
use App\Services\AI\AiService;
use App\Models\AiIntegration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CardIntelOcrService
{
    protected AiService $aiService;

    public function __construct(AiService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Extract text and structured fields from a business card image.
     *
     * @param CardIntelScan $scan The scan record with file_path
     * @return array{raw_text: string, fields: array, confidence: array}
     * @throws \Exception If OCR fails
     */
    public function extractFromScan(CardIntelScan $scan): array
    {
        $imagePath = Storage::disk('public')->path($scan->file_path);

        if (!file_exists($imagePath)) {
            throw new \Exception("Image file not found: {$scan->file_path}");
        }

        return $this->extractFromImage($imagePath);
    }

    /**
     * Extract text and structured fields from an image file.
     *
     * @param string $imagePath Absolute path to the image file
     * @return array{raw_text: string, fields: array, confidence: array}
     */
    public function extractFromImage(string $imagePath): array
    {
        // Get AI integration with vision capability
        $integration = $this->getVisionIntegration();

        if (!$integration) {
            throw new \Exception('No AI integration with vision capability available. Please configure OpenAI (GPT-4o) or Google Gemini.');
        }

        // Read image and encode to base64
        $imageData = file_get_contents($imagePath);
        $base64Image = base64_encode($imageData);
        $mimeType = mime_content_type($imagePath);

        // Build the OCR prompt
        $prompt = $this->getOcrPrompt();

        try {
            // Use AI vision to analyze the image
            $response = $this->callVisionApi($integration, $base64Image, $mimeType, $prompt);

            // Parse the response
            return $this->parseOcrResponse($response);

        } catch (\Exception $e) {
            Log::error('CardIntel OCR failed', [
                'image_path' => $imagePath,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('OCR extraction failed: ' . $e->getMessage());
        }
    }

    /**
     * Get AI integration with vision capability.
     */
    protected function getVisionIntegration(): ?AiIntegration
    {
        // Prefer GPT-4o or Gemini for vision
        $visionProviders = ['openai', 'gemini'];

        return AiIntegration::active()
            ->whereIn('provider', $visionProviders)
            ->whereNotNull('api_key')
            ->first();
    }

    /**
     * Call the AI Vision API.
     */
    protected function callVisionApi(
        AiIntegration $integration,
        string $base64Image,
        string $mimeType,
        string $prompt
    ): string {
        $provider = $this->aiService->getProvider($integration);

        // Check if provider supports vision
        if (!method_exists($provider, 'generateWithImage')) {
            // Fallback: use standard text generation with image description prompt
            return $provider->generateText($prompt);
        }

        return $provider->generateWithImage($prompt, $base64Image, $mimeType, [
            'max_tokens' => 4000,
            'temperature' => 0.3, // Low temperature for accuracy
        ]);
    }

    /**
     * Get the OCR extraction prompt.
     */
    protected function getOcrPrompt(): string
    {
        return <<<PROMPT
Analizujesz zdjęcie wizytówki biznesowej. Wydobądź wszystkie widoczne informacje kontaktowe.

WAŻNE INSTRUKCJE:
1. Wydobądź TYLKO informacje widoczne na wizytówce
2. Dla każdego pola oceń pewność ekstrakcji (0.0-1.0)
3. Jeśli pole nie jest widoczne lub nieczytelne, ustaw wartość null i confidence 0
4. Normalizuj numery telefonów (usuń spacje, zostaw +48)
5. Normalizuj email (małe litery)
6. Dla NIP/REGON usuń spacje i myślniki

Odpowiedz WYŁĄCZNIE w formacie JSON (bez markdown, bez komentarzy):

{
  "raw_text": "cały widoczny tekst z wizytówki, linia po linii",
  "fields": {
    "first_name": "imię lub null",
    "last_name": "nazwisko lub null",
    "company": "nazwa firmy lub null",
    "email": "email@example.com lub null",
    "phone": "+48123456789 lub null",
    "website": "www.example.com lub null",
    "nip": "1234567890 lub null",
    "regon": "123456789 lub null",
    "position": "stanowisko lub null"
  },
  "confidence": {
    "first_name": 0.95,
    "last_name": 0.95,
    "company": 0.90,
    "email": 0.99,
    "phone": 0.85,
    "website": 0.80,
    "nip": 0.0,
    "regon": 0.0,
    "position": 0.70
  }
}
PROMPT;
    }

    /**
     * Parse the OCR response from AI.
     */
    protected function parseOcrResponse(string $response): array
    {
        // Try to extract JSON from the response
        $response = trim($response);

        // Remove markdown code blocks if present
        if (str_starts_with($response, '```')) {
            $response = preg_replace('/^```(?:json)?\n?/', '', $response);
            $response = preg_replace('/\n?```$/', '', $response);
        }

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('CardIntel OCR: Failed to parse JSON response', [
                'response' => substr($response, 0, 500),
                'error' => json_last_error_msg(),
            ]);

            // Return empty results with raw text
            return [
                'raw_text' => $response,
                'fields' => CardIntelExtraction::DEFAULT_FIELDS,
                'confidence' => [],
            ];
        }

        // Validate and normalize the response
        return [
            'raw_text' => $data['raw_text'] ?? '',
            'fields' => $this->normalizeFields($data['fields'] ?? []),
            'confidence' => $this->normalizeConfidence($data['confidence'] ?? []),
        ];
    }

    /**
     * Normalize extracted fields.
     */
    protected function normalizeFields(array $fields): array
    {
        $normalized = CardIntelExtraction::DEFAULT_FIELDS;

        foreach ($normalized as $key => $_) {
            $value = $fields[$key] ?? null;

            if ($value === null || $value === '' || $value === 'null') {
                $normalized[$key] = null;
                continue;
            }

            $normalized[$key] = match($key) {
                'email' => strtolower(trim($value)),
                'phone' => $this->normalizePhone($value),
                'nip', 'regon' => preg_replace('/\D/', '', $value),
                'website' => $this->normalizeWebsite($value),
                default => trim($value),
            };
        }

        return $normalized;
    }

    /**
     * Normalize phone number.
     */
    protected function normalizePhone(string $phone): string
    {
        // Remove all non-digit characters except +
        $phone = preg_replace('/[^\d+]/', '', $phone);

        // Add Polish prefix if not present
        if (!str_starts_with($phone, '+') && strlen($phone) === 9) {
            $phone = '+48' . $phone;
        }

        return $phone;
    }

    /**
     * Normalize website URL.
     */
    protected function normalizeWebsite(string $url): string
    {
        $url = trim(strtolower($url));

        // Remove common prefixes for storage, we'll add them back when needed
        $url = preg_replace('/^https?:\/\//', '', $url);
        $url = preg_replace('/^www\./', '', $url);

        // Remove trailing slash
        $url = rtrim($url, '/');

        return $url;
    }

    /**
     * Normalize confidence scores.
     */
    protected function normalizeConfidence(array $confidence): array
    {
        $normalized = [];

        foreach (CardIntelExtraction::DEFAULT_FIELDS as $key => $_) {
            $value = $confidence[$key] ?? 0;

            // Ensure value is between 0 and 1
            $normalized[$key] = max(0, min(1, (float) $value));
        }

        return $normalized;
    }

    /**
     * Create extraction record from OCR results.
     */
    public function createExtraction(CardIntelScan $scan, array $ocrResult): CardIntelExtraction
    {
        // Update scan with raw text
        $scan->update(['raw_text' => $ocrResult['raw_text']]);

        return CardIntelExtraction::create([
            'scan_id' => $scan->id,
            'fields_json' => $ocrResult['fields'],
            'confidence_json' => $ocrResult['confidence'],
            'normalized_json' => $ocrResult['fields'], // Initially same as fields
        ]);
    }
}
