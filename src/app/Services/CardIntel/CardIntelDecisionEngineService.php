<?php

namespace App\Services\CardIntel;

use App\Models\CardIntelScan;
use App\Models\CardIntelContext;
use App\Models\CardIntelSettings;
use App\Services\AI\AiService;
use Illuminate\Support\Facades\Log;

class CardIntelDecisionEngineService
{
    protected AiService $aiService;

    public function __construct(AiService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Generate a message draft for a scan.
     */
    public function generateMessage(
        CardIntelScan $scan,
        ?string $contextLevel = null,
        ?string $tone = null,
        ?string $formality = null,
        ?string $gender = null
    ): array {
        $extraction = $scan->extraction;
        $context = $scan->context;
        $enrichment = $scan->enrichment;
        $settings = CardIntelSettings::getForUser($scan->user_id);

        if (!$extraction) {
            throw new \Exception('No extraction found for scan');
        }

        // Use provided or detected context level
        $contextLevel ??= $context?->context_level ?? CardIntelContext::LEVEL_LOW;
        $tone ??= $settings->default_tone;
        $formality ??= 'formal';

        // Detect gender from first name if auto
        $firstName = $extraction->fields['first_name'] ?? '';
        if ($gender === 'auto' || $gender === null) {
            $gender = $this->detectGenderFromName($firstName);
        }

        $prompt = $this->buildPrompt($extraction, $enrichment, $contextLevel, $tone, $formality, $gender);

        $integration = $this->aiService->getDefaultIntegration();

        if (!$integration) {
            throw new \Exception('No AI integration configured');
        }

        try {
            $response = $this->aiService->generateContent($prompt, $integration, [
                'max_tokens' => 4000,
                'temperature' => 0.7,
            ]);

            return $this->parseMessageResponse($response, $contextLevel);

        } catch (\Exception $e) {
            Log::error('CardIntel message generation failed', [
                'scan_id' => $scan->id,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Message generation failed: ' . $e->getMessage());
        }
    }

    /**
     * Generate all three context level versions at once.
     */
    public function generateAllVersions(CardIntelScan $scan, ?string $tone = null, ?string $formality = null, ?string $gender = null): array
    {
        $versions = [];

        foreach ([CardIntelContext::LEVEL_LOW, CardIntelContext::LEVEL_MEDIUM, CardIntelContext::LEVEL_HIGH] as $level) {
            try {
                $versions[$level] = $this->generateMessage($scan, $level, $tone, $formality, $gender);
            } catch (\Exception $e) {
                $versions[$level] = [
                    'subject' => '',
                    'body' => 'Nie udało się wygenerować wiadomości: ' . $e->getMessage(),
                    'context_level' => $level,
                    'error' => true,
                ];
            }
        }

        return $versions;
    }

    /**
     * Detect gender from Polish first name.
     */
    protected function detectGenderFromName(string $firstName): string
    {
        $firstName = mb_strtolower(trim($firstName));

        if (empty($firstName)) {
            return 'male'; // default
        }

        // Common Polish female name endings
        $femaleEndings = ['a', 'ia', 'ja', 'cia', 'sia', 'nia', 'la', 'ela', 'ola'];

        // Exceptions - male names ending in 'a'
        $maleNamesEndingInA = [
            'kuba', 'barnaba', 'kosma', 'bonawentura', 'boryna', 'sasza', 'dyzma',
            'jarema', 'kosma', 'cezaria', 'nikita', 'ezra', 'nehemia'
        ];

        // Check exceptions first
        if (in_array($firstName, $maleNamesEndingInA)) {
            return 'male';
        }

        // Check if name ends with female suffix
        foreach ($femaleEndings as $ending) {
            if (str_ends_with($firstName, $ending)) {
                return 'female';
            }
        }

        return 'male';
    }

    /**
     * Build the message generation prompt.
     */
    protected function buildPrompt(
        $extraction,
        $enrichment,
        string $contextLevel,
        string $tone,
        string $formality,
        string $gender
    ): string {
        $fields = $extraction->fields;

        $firstName = $fields['first_name'] ?? '';
        $lastName = $fields['last_name'] ?? '';
        $fullName = trim("$firstName $lastName");
        $company = $fields['company'] ?? '';
        $position = $fields['position'] ?? '';
        $email = $fields['email'] ?? '';

        // Build base context
        $contactInfo = "DANE KONTAKTU:\n";
        $contactInfo .= "- Imię i nazwisko: $fullName\n";
        if ($company) $contactInfo .= "- Firma: $company\n";
        if ($position) $contactInfo .= "- Stanowisko: $position\n";
        $contactInfo .= "- Email: $email\n";

        // Build enrichment context (for MEDIUM/HIGH)
        $enrichmentInfo = "";
        if ($enrichment && in_array($contextLevel, [CardIntelContext::LEVEL_MEDIUM, CardIntelContext::LEVEL_HIGH])) {
            if ($enrichment->website_summary) {
                $enrichmentInfo .= "\nO FIRMIE:\n{$enrichment->website_summary}\n";
            }
            if ($enrichment->b2b_b2c_guess && $enrichment->b2b_b2c_guess !== 'UNKNOWN') {
                $enrichmentInfo .= "Typ biznesu: {$enrichment->b2b_b2c_guess}\n";
            }
            if ($contextLevel === CardIntelContext::LEVEL_HIGH && $enrichment->use_case_hypothesis) {
                $enrichmentInfo .= "\nHIPOTEZA USE-CASE:\n{$enrichment->use_case_hypothesis}\n";
            }
        }

        // Tone description
        $toneDesc = match($tone) {
            CardIntelSettings::TONE_PROFESSIONAL => 'profesjonalny, biznesowy, rzeczowy',
            CardIntelSettings::TONE_FRIENDLY => 'przyjazny, ciepły, ale nadal profesjonalny',
            CardIntelSettings::TONE_FORMAL => 'formalny, oficjalny, uprzejmy',
            default => 'profesjonalny, biznesowy',
        };

        // Context level instructions
        $levelInstructions = match($contextLevel) {
            CardIntelContext::LEVEL_LOW => <<<EOT
POZIOM KONTEKSTU: NISKI
STYL: Neutralny i krótki. Nie masz dużo informacji o firmie.
CEL: Nawiązanie kontaktu bez konkretnych propozycji.
ZASADY:
- NIE zakładaj branży ani potrzeb
- NIE wspominaj o szczegółach firmy (nie znasz ich)
- Ogólne zaproszenie do rozmowy
- Max 3-4 zdania w treści
EOT,
            CardIntelContext::LEVEL_MEDIUM => <<<EOT
POZIOM KONTEKSTU: ŚREDNI
STYL: Branżowy, pokazujesz znajomość sektora.
CEL: Showing relevance to their industry.
ZASADY:
- Możesz odnosić się do branży jeśli znasz
- Wspomnij ogólne wyzwania sektora
- Unikaj zbyt szczegółowych założeń
- Max 5-6 zdań w treści
EOT,
            CardIntelContext::LEVEL_HIGH => <<<EOT
POZIOM KONTEKSTU: WYSOKI
STYL: Konkretny, odniesienie do firmyi jej potrzeb.
CEL: Pokazanie, że rozumiesz ich biznes.
ZASADY:
- Odnieś się do konkretnej działalności firmy
- Użyj hipotezy use-case jako punktu wyjścia
- Pokaż wartość, którą możesz dostarczyć
- Max 6-8 zdań w treści
EOT,
            default => '',
        };

        // Get settings for custom instructions and allowed HTML tags
        $customInstructions = "";
        $allowedHtmlTags = "p,br,strong,em,ul,ol,li,a,h3,h4";
        if ($extraction->scan && $extraction->scan->user_id) {
            $settings = CardIntelSettings::getForUser($extraction->scan->user_id);
            if (!empty($settings->custom_ai_prompt)) {
                $customInstructions = "\n\nDODATKOWE INSTRUKCJE OD UŻYTKOWNIKA:\n{$settings->custom_ai_prompt}\n";
            }
            if (!empty($settings->allowed_html_tags)) {
                $allowedHtmlTags = $settings->allowed_html_tags;
            }
        }

        // Build personalization instructions
        $formalityDesc = $formality === 'informal'
            ? 'NIEFORMALNA (na Ty) - zwracaj się "Cześć", "Witaj", używaj form "Ty", "Ci", "Twój"'
            : 'FORMALNA (na Pan/Pani) - zwracaj się "Szanowny Panie/Szanowna Pani", używaj form "Pan", "Pana", "Pani"';

        $genderDesc = $gender === 'female'
            ? 'KOBIETA - używaj form żeńskich: "chciałabyś", "mogłabyś", "zainteresowana", "Pani"'
            : 'MĘŻCZYZNA - używaj form męskich: "chciałbyś", "mógłbyś", "zainteresowany", "Pan"';

        $personalizationInstructions = <<<EOT

PERSONALIZACJA:
FORMA ZWRACANIA SIĘ: {$formalityDesc}
PŁEĆ ODBIORCY: {$genderDesc}

PRZYKŁADY POPRAWNYCH FORM:
- Formalna + mężczyzna: "Szanowny Panie Janie, czy byłby Pan zainteresowany..."
- Formalna + kobieta: "Szanowna Pani Anno, czy byłaby Pani zainteresowana..."
- Nieformalna + mężczyzna: "Cześć Janie! Zastanawiałem się, czy chciałbyś..."
- Nieformalna + kobieta: "Cześć Anno! Zastanawiałem się, czy chciałabyś..."

ZAWZE stosuj odpowiednią odmianę czasowników i zaimków zgodną z płcią!
EOT;

        $prompt = <<<PROMPT
Wygeneruj email biznesowy do nowego kontaktu.

{$contactInfo}
{$enrichmentInfo}

TON: {$toneDesc}
{$personalizationInstructions}

{$levelInstructions}
{$customInstructions}

FORMATOWANIE HTML:
Treść wiadomości (body) MUSI być sformatowana w HTML. Dozwolone tagi: <{$allowedHtmlTags}>
- Używaj <p> do akapitów
- Używaj <strong> lub <em> do wyróżnień
- Możesz użyć <ul>/<ol> i <li> dla list
- Linki formatuj jako <a href="url">tekst</a>
- NIE używaj tagów <html>, <head>, <body> - tylko treść wewnętrzna

FORMAT ODPOWIEDZI (JSON):
{
  "subject": "Temat emaila (krótki, angażujący)",
  "preheader": "Krótki podgląd widoczny w skrzynce odbiorczej (max 100 znaków)",
  "greeting": "Szanowny Panie [Imię]" lub odpowiednie zgodne z formalnością i płcią,
  "body": "<p>Treść emaila w HTML bez pozdrowień końcowych</p>"
}

WAŻNE:
- Pisz po polsku
- Nie używaj pustych frazesów
- Nie zaczynaj od "Mam nadzieję, że ten mail zastaje..."
- Zacznij od sedna
- Podpis zostanie dodany automatycznie - NIE dodawaj go
- ZAWSZE dodaj preheader - krótkie podsumowanie zachęcające do otwarcia
- BEZWZGLĘDNIE przestrzegaj wybranej formy zwracania się i odmiany zgodnej z płcią!
PROMPT;

        return $prompt;
    }

    /**
     * Parse the AI response into structured message data.
     */
    protected function parseMessageResponse(string $response, string $contextLevel): array
    {
        // Clean response
        $response = trim($response);
        if (str_starts_with($response, '```')) {
            $response = preg_replace('/^```(?:json)?\n?/', '', $response);
            $response = preg_replace('/\n?```$/', '', $response);
        }

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // Fallback: try to extract subject and body manually
            return [
                'subject' => 'Propozycja współpracy',
                'body' => $response,
                'greeting' => 'Dzień dobry,',
                'context_level' => $contextLevel,
            ];
        }

        return [
            'subject' => $data['subject'] ?? 'Propozycja współpracy',
            'body' => $data['body'] ?? '',
            'greeting' => $data['greeting'] ?? 'Dzień dobry,',
            'context_level' => $contextLevel,
        ];
    }

    /**
     * Determine the recommended communication channel.
     */
    public function getRecommendedChannel(CardIntelScan $scan): string
    {
        $extraction = $scan->extraction;

        if (!$extraction) {
            return 'email';
        }

        $fields = $extraction->fields;

        // Prefer email if available
        if (!empty($fields['email'])) {
            return 'email';
        }

        // Fall back to SMS if only phone
        if (!empty($fields['phone'])) {
            return 'sms';
        }

        return 'email';
    }

    /**
     * Get recommendations based on context.
     */
    public function getRecommendations(CardIntelScan $scan): array
    {
        $context = $scan->context;
        $settings = CardIntelSettings::getForUser($scan->user_id);

        $recommendations = [];

        if (!$context) {
            return [
                ['action' => 'rescore', 'label' => 'Ponów ocenę kontekstu'],
            ];
        }

        // Always recommend saving to memory
        $recommendations[] = [
            'action' => 'save_memory',
            'label' => 'Zapisz do pamięci',
            'priority' => 'high',
        ];

        // CRM recommendation based on settings
        if ($context->isHigh() || $settings->crm_sync_mode === CardIntelSettings::CRM_SYNC_ALWAYS) {
            $recommendations[] = [
                'action' => 'add_crm',
                'label' => 'Dodaj do CRM',
                'priority' => $context->isHigh() ? 'high' : 'medium',
            ];
        }

        // List recommendation
        if ($context->quality_score >= 50) {
            $recommendations[] = [
                'action' => 'add_list',
                'label' => 'Dodaj do listy',
                'priority' => 'medium',
            ];
        }

        // Message recommendation
        $channel = $this->getRecommendedChannel($scan);
        $recommendations[] = [
            'action' => "send_$channel",
            'label' => $channel === 'email' ? 'Wyślij email' : 'Wyślij SMS',
            'priority' => $context->isHigh() ? 'high' : 'low',
        ];

        return $recommendations;
    }
}
