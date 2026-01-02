<?php

namespace App\Services;

use App\Models\AiIntegration;
use App\Services\AI\AiService;

class TemplateAiService
{
    protected AiService $aiService;

    public function __construct(AiService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Get an AI integration (specific or default)
     */
    protected function getIntegration(?int $integrationId = null): ?AiIntegration
    {
        if ($integrationId) {
            return AiIntegration::where('id', $integrationId)
                ->where('is_active', true)
                ->first();
        }

        return $this->aiService->getDefaultIntegration();
    }

    /**
     * Check if AI is available
     */
    public function isAvailable(): bool
    {
        return $this->getIntegration() !== null;
    }

    /**
     * Generate content for a specific block type
     */
    public function generateContent(
        string $prompt,
        string $blockType,
        string $tone = 'casual',
        ?int $integrationId = null,
        ?string $modelId = null
    ): string {
        $integration = $this->getIntegration($integrationId);

        if (!$integration) {
            throw new \Exception('Brak skonfigurowanej integracji AI. Skonfiguruj ją w Ustawieniach.');
        }

        $toneInstructions = match ($tone) {
            'formal' => 'Pisz w stylu formalnym, profesjonalnym, używając uprzejmych form.',
            'casual' => 'Pisz w stylu luźnym, przyjaznym, bezpośrednim do odbiorcy.',
            'persuasive' => 'Pisz w stylu perswazyjnym, zachęcającym do działania, z mocnymi argumentami.',
            default => 'Pisz w stylu neutralnym.',
        };

        $blockInstructions = match ($blockType) {
            'text' => 'Wygeneruj treść tekstową odpowiednią do wiadomości email. Użyj krótkich paragrafów.',
            'header' => 'Wygeneruj krótki, chwytliwy nagłówek lub slogan.',
            'button' => 'Wygeneruj krótki, zachęcający tekst na przycisk CTA (max 3-4 słowa).',
            'product' => 'Wygeneruj opis produktu: tytuł i krótki opis zachęcający do zakupu.',
            'footer' => 'Wygeneruj profesjonalną treść stopki emaila z informacjami o firmie.',
            default => 'Wygeneruj treść odpowiednią do wiadomości email.',
        };

        $dateContext = AiService::getDateContext();

        $systemPrompt = <<<PROMPT
{$dateContext}

Jesteś ekspertem od email marketingu. Tworzysz treści do szablonów wiadomości email.

Zasady:
1. {$toneInstructions}
2. {$blockInstructions}
3. Pisz po polsku (chyba że użytkownik poprosi o inny język).
4. Treść powinna być zwięzła i angażująca.
5. Odpowiedź w formacie HTML (używaj <p>, <strong>, <em> itp.).
6. NIE używaj nagłówków HTML (h1, h2) w odpowiedzi.
7. Do personalizacji używaj wstawek w formacie [[nazwa_pola]], np. [[first_name]], [[email]].
   NIGDY nie używaj nawiasów klamrowych {}, tylko podwójne kwadratowe [[]].

WAŻNE: Odpowiadaj TYLKO treścią HTML, bez żadnych komentarzy czy wyjaśnień.
PROMPT;

        $fullPrompt = $systemPrompt . "\n\nZadanie użytkownika: " . $prompt;

        return $this->aiService->generateContent($fullPrompt, $integration, [
            'max_tokens' => $integration->max_tokens_small ?: 8000,
            'temperature' => 0.7,
            'model' => $modelId,
        ]);
    }

    /**
     * Generate entire email section
     */
    public function generateSection(
        string $description,
        string $sectionType = 'promotional',
        ?int $integrationId = null,
        ?string $modelId = null
    ): array {
        $integration = $this->getIntegration($integrationId);

        if (!$integration) {
            throw new \Exception('Brak skonfigurowanej integracji AI.');
        }

        $sectionTemplates = match ($sectionType) {
            'promotional' => 'sekcja promocyjna z nagłówkiem, tekstem opisującym ofertę i przyciskiem CTA',
            'welcome' => 'sekcja powitalna dla nowego subskrybenta z ciepłym przywitaniem',
            'product' => 'sekcja prezentująca produkt ze zdjęciem, opisem i ceną',
            'newsletter' => 'sekcja newslettera z głównym artykułem lub informacją',
            default => 'sekcja emailowa',
        };

        $dateContext = AiService::getDateContext();

        $prompt = <<<PROMPT
{$dateContext}

Wygeneruj treść dla sekcji emaila: {$sectionTemplates}

Opis od użytkownika: {$description}

Odpowiedz w formacie JSON z następującą strukturą:
{
    "headline": "Krótki nagłówek sekcji",
    "text": "Główna treść w HTML (krótkie paragrafy)",
    "buttonText": "Tekst przycisku CTA",
    "buttonUrl": "#"
}

Odpowiedź TYLKO JSON, bez żadnych dodatkowych wyjaśnień.
PROMPT;

        $response = $this->aiService->generateContent($prompt, $integration, [
            'max_tokens' => $integration->max_tokens_small ?: 8000,
            'temperature' => 0.7,
            'model' => $modelId,
        ]);

        // Parse JSON from response
        $json = $this->extractJson($response);

        return $json ?? [
            'headline' => 'Nagłówek sekcji',
            'text' => '<p>' . $response . '</p>',
            'buttonText' => 'Dowiedz się więcej',
            'buttonUrl' => '#',
        ];
    }

    /**
     * Improve existing text with AI
     */
    public function improveText(string $text, string $tone = 'casual', string $action = 'improve'): string
    {
        $integration = $this->getIntegration();

        if (!$integration) {
            throw new \Exception('Brak skonfigurowanej integracji AI.');
        }

        $toneInstructions = match ($tone) {
            'formal' => 'formalny i profesjonalny',
            'casual' => 'luźny i przyjazny',
            'persuasive' => 'perswazyjny i zachęcający do działania',
            default => 'neutralny',
        };

        $actionInstructions = match ($action) {
            'improve' => 'Popraw i ulepsz poniższy tekst, zachowując jego sens.',
            'shorten' => 'Skróć poniższy tekst, zachowując najważniejsze informacje.',
            'expand' => 'Rozwiń poniższy tekst, dodając więcej szczegółów.',
            'fix_grammar' => 'Popraw błędy gramatyczne i stylistyczne w poniższym tekście.',
            default => 'Ulepsz poniższy tekst.',
        };

        $dateContext = AiService::getDateContext();

        $prompt = <<<PROMPT
{$dateContext}

{$actionInstructions}
Użyj tonu: {$toneInstructions}.
Odpowiedź w formacie HTML (używaj <p>, <strong>, <em>).
Odpowiedz TYLKO ulepszonym tekstem, bez żadnych komentarzy.

Tekst do przetworzenia:
{$text}
PROMPT;

        return $this->aiService->generateContent($prompt, $integration, [
            'max_tokens' => $integration->max_tokens_small ?: 8000,
            'temperature' => 0.6,
        ]);
    }

    /**
     * Build placeholder section for AI prompts
     * Includes standard placeholders and any custom ones provided
     *
     * @param array $customPlaceholders Custom placeholders selected by user
     * @return string Formatted placeholder section for prompt
     */
    protected function buildPlaceholderSection(array $customPlaceholders = []): string
    {
        $lines = [];
        $lines[] = "   Używaj wstawek w formacie [[nazwa_pola]] do personalizacji treści.";
        $lines[] = "   NIGDY nie używaj nawiasów klamrowych {}, tylko podwójne kwadratowe [[]]!";
        $lines[] = "";
        $lines[] = "   DOSTĘPNE WSTAWKI:";

        // Standard placeholders (always available)
        $standardPlaceholders = [
            ['name' => 'first_name', 'label' => 'Imię', 'description' => 'Imię odbiorcy'],
            ['name' => 'last_name', 'label' => 'Nazwisko', 'description' => 'Nazwisko odbiorcy'],
            ['name' => 'email', 'label' => 'Email', 'description' => 'Adres email odbiorcy'],
            ['name' => 'phone', 'label' => 'Telefon', 'description' => 'Numer telefonu'],
            ['name' => 'unsubscribe_link', 'label' => 'Link wypisania', 'description' => 'Link do wypisania z listy'],
        ];

        foreach ($standardPlaceholders as $p) {
            $desc = $p['description'] ?? $p['label'];
            $lines[] = "   - [[{$p['name']}]] - {$desc}";
        }

        // Add custom placeholders if any
        if (!empty($customPlaceholders)) {
            $lines[] = "";
            $lines[] = "   POLA NIESTANDARDOWE (dodatkowe):";
            foreach ($customPlaceholders as $p) {
                $desc = $p['description'] ?? $p['label'] ?? $p['name'];
                $lines[] = "   - [[{$p['name']}]] - {$desc}";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Generate message content with two modes: text fragment or full template
     *
     * @param string $prompt User's request
     * @param string $mode 'text' for fragment, 'template' for full email
     * @param string|null $currentContent Current HTML content for context
     * @param string $tone Tone of the content
     * @param bool $withFormatting Whether to include HTML formatting
     * @param int|null $integrationId Optional specific integration to use
     * @param string|null $modelId Optional specific model to use
     * @param array $placeholders Available placeholders for personalization
     * @return string Generated HTML content
     */
    public function generateMessageContent(
        string $prompt,
        string $mode = 'text',
        ?string $currentContent = null,
        string $tone = 'casual',
        bool $withFormatting = true,
        ?int $integrationId = null,
        ?string $modelId = null,
        array $placeholders = []
    ): string {
        $integration = $this->getIntegration($integrationId);

        if (!$integration) {
            throw new \Exception('Brak skonfigurowanej integracji AI. Skonfiguruj ją w Ustawieniach.');
        }

        $toneInstructions = match ($tone) {
            'formal' => 'Pisz w stylu formalnym, profesjonalnym, używając uprzejmych form.',
            'casual' => 'Pisz w stylu luźnym, przyjaznym, bezpośrednim do odbiorcy.',
            'persuasive' => 'Pisz w stylu perswazyjnym, zachęcającym do działania, z mocnymi argumentami.',
            default => 'Pisz w stylu neutralnym.',
        };

        // Build dynamic placeholder section
        $placeholderSection = $this->buildPlaceholderSection($placeholders);

        // Conditional formatting guide based on user preference
        if ($withFormatting) {
            $htmlTagsGuide = <<<TAGS
Dozwolone znaczniki HTML:
- Nagłówki: <h1>, <h2>, <h3>, <h4> (używaj do tytułów i sekcji)
- Tekst: <p> (paragrafy), <strong> (pogrubienie), <em> (kursywa), <u> (podkreślenie)
- Listy: <ul>, <ol>, <li> (wypunktowania i numeracje)
- Linki: <a href="URL">tekst</a>
- Przyciski CTA: <a href="URL" style="display:inline-block;padding:12px 24px;background:#4F46E5;color:white;text-decoration:none;border-radius:6px;">Tekst przycisku</a>
- Separatory: <hr>
- Obrazy: <img src="URL" alt="opis" style="max-width:100%;">
- Tabele: <table>, <tr>, <td>, <th> (do układu lub danych)
- Kontenery: <div> z inline styles dla układu

NIE używaj: <script>, <style> (jako tag), <iframe>, <form>
TAGS;
        } else {
            // Plain text mode - no HTML formatting
            $htmlTagsGuide = <<<TAGS
WAŻNE: Odpowiadaj CZYSTYM TEKSTEM bez żadnego formatowania HTML.
- Nie używaj znaczników HTML
- Pisz czysty tekst bez tagów
- Rozdzielaj paragrafy pustymi liniami
TAGS;
        }

        if ($mode === 'template') {
            // Full template generation/modification mode
            $contextInfo = '';
            if ($currentContent && strlen(strip_tags($currentContent)) > 10) {
                $contextInfo = "\n\nAKTUALNA TREŚĆ WIADOMOŚCI DO MODYFIKACJI:\n" . mb_substr($currentContent, 0, 5000);
            }

            $dateContext = AiService::getDateContext();

            $systemPrompt = <<<PROMPT
{$dateContext}

Jesteś ekspertem od email marketingu i tworzenia szablonów HTML dla profesjonalnych wiadomości email.

TWOJA ROLA: Tworzysz szablony email, które:
- Wyglądają świetnie na WSZYSTKICH klientach poczty (Gmail, Outlook, Yahoo, Apple Mail, itp.)
- Są w pełni RESPONSYWNE - dopasowują się do urządzeń mobilnych
- Wspierają tryb DARK MODE i LIGHT MODE
- Używają inline CSS (wymagane przez klientów poczty)

ZADANIE: Wygeneruj lub zmodyfikuj cały szablon wiadomości email.

ZASADY PROJEKTOWANIA:
1. {$toneInstructions}
2. RESPONSYWNOŚĆ:
   - Używaj szerokości max-width: 600px dla głównego kontenera
   - Tekst 16-18px na desktop, 18-20px na mobile (większa czcionka!)
   - Przyciski CTA min. 48px wysokości (łatwe klikanie na mobile)
   - Obrazy max-width: 100% i height: auto

3. DARK MODE:
   - Używaj kolorów, które działają w obu trybach
   - Preferuj ciemniejsze tła (#1a1a2e, #16213e) z jasnym tekstem
   - Lub jasne tła z ciemnym tekstem (kontrast min. 4.5:1)

4. KOMPATYBILNOŚĆ Z KLIENTAMI POCZTY:
   - Wszystkie style inline (style="...")
   - Używaj table-based layout dla starszych Outlooka
   - Unikaj: position, float, flexbox, grid (słabe wsparcie)
   - Preferuj: tables, inline-block, margin, padding

5. STRUKTURA SZABLONU:
   - Nagłówek z logo lub tytułem
   - Treść główna z akapitami
   - Wyraźny przycisk CTA (wezwanie do działania)
   - Stopka z informacjami kontaktowymi

6. TYPOGRAFIA:
   - Fonty web-safe: Arial, Helvetica, Georgia, Times New Roman
   - Line-height: 1.5-1.6 dla czytelności
   - Nagłówki: font-weight: bold

7. PERSONALIZACJA (wstawki):
{$placeholderSection}

8. ZAWSZE generuj KOMPLETNY, PEŁNY szablon - bez obcinania treści.

{$htmlTagsGuide}

PRZYKŁAD RESPONSYWNEGO PRZYCISKU CTA:
<a href="#" style="display:inline-block;padding:16px 32px;background:#4F46E5;color:white;text-decoration:none;border-radius:8px;font-weight:bold;font-size:18px;">Tekst przycisku</a>

WAŻNE:
- Odpowiadaj TYLKO kodem HTML szablonu, bez żadnych komentarzy czy wyjaśnień.
- Wygeneruj CAŁĄ treść od początku do końca, nie przerywaj w połowie.
- Jeśli modyfikujesz istniejącą treść, zachowaj jej strukturę i dodaj/zmień tylko to, o co prosi użytkownik.
{$contextInfo}
PROMPT;

            $maxTokens = $integration->max_tokens_large ?? 50000; // Large for full templates
        } else {
            // Text fragment mode
            $contextInfo = '';
            if ($currentContent && strlen(strip_tags($currentContent)) > 10) {
                $plainContent = strip_tags($currentContent);
                $contextInfo = "\n\nKONTEKST (obecna treść wiadomości, dla odniesienia):\n" . mb_substr($plainContent, 0, 1000);
            }

            $dateContext = AiService::getDateContext();

            $systemPrompt = <<<PROMPT
{$dateContext}

Jesteś ekspertem od email marketingu. Tworzysz treści do wiadomości email.

ZADANIE: Wygeneruj fragment tekstu HTML do wstawienia w wiadomość email.

ZASADY:
1. {$toneInstructions}
2. Treść powinna być zwięzła i angażująca.
3. Pisz po polsku (chyba że użytkownik poprosi o inny język).
4. Formatuj treść używając odpowiednich znaczników HTML.
5. Generuj KOMPLETNĄ odpowiedź, nie przerywaj w połowie zdania.
6. PERSONALIZACJA (wstawki):
{$placeholderSection}

{$htmlTagsGuide}

WAŻNE: Odpowiadaj TYLKO treścią HTML, bez żadnych komentarzy czy wyjaśnień.
{$contextInfo}
PROMPT;

            $maxTokens = $integration->max_tokens_small ?: 8000; // Small for text fragments
        }

        $fullPrompt = $systemPrompt . "\n\nZadanie użytkownika: " . $prompt;

        return $this->aiService->generateContent($fullPrompt, $integration, [
            'max_tokens' => $maxTokens,
            'temperature' => 0.7,
            'model' => $modelId,
        ]);
    }

    /**
     * Generate SMS content with AI
     *
     * @param string $prompt User's request/description
     * @param int $count Number of suggestions to generate (1 or 3)
     * @param string $tone Tone of the content
     * @param int|null $integrationId Optional specific integration to use
     * @param string|null $modelId Optional specific model to use
     * @param array $placeholders Available placeholders for personalization
     * @return array Array of generated SMS content suggestions
     */
    public function generateSmsContent(
        string $prompt,
        int $count = 1,
        string $tone = 'casual',
        ?int $integrationId = null,
        ?string $modelId = null,
        array $placeholders = []
    ): array {
        $integration = $this->getIntegration($integrationId);

        if (!$integration) {
            throw new \Exception('Brak skonfigurowanej integracji AI. Skonfiguruj ją w Ustawieniach.');
        }

        $toneInstructions = match ($tone) {
            'formal' => 'Pisz w stylu formalnym, profesjonalnym i uprzejmym.',
            'casual' => 'Pisz w stylu luźnym, przyjaznym i bezpośrednim.',
            'persuasive' => 'Pisz w stylu perswazyjnym, zachęcającym do działania.',
            default => 'Pisz w stylu neutralnym.',
        };

        // Build placeholder section for SMS
        $placeholderSection = $this->buildSmsPlaceholderSection($placeholders);

        $countInstruction = $count > 1
            ? "Wygeneruj DOKŁADNIE {$count} RÓŻNE propozycje treści SMS."
            : "Wygeneruj 1 propozycję treści SMS.";

        $dateContext = AiService::getDateContext();

        $systemPrompt = <<<PROMPT
{$dateContext}

Jesteś ekspertem od SMS marketingu. Tworzysz krótkie, skuteczne wiadomości SMS.

ZADANIE: {$countInstruction}

ZASADY DLA KAŻDEJ PROPOZYCJI:
1. {$toneInstructions}
2. MAKSYMALNIE 160 znaków (jeden segment SMS) - to BEZWZGLĘDNY limit!
3. Treść MUSI być czystym tekstem - BEZ HTML, BEZ formatowania
4. Pisz po polsku (chyba że użytkownik poprosi o inny język)
5. Krótko i na temat - każde słowo musi mieć znaczenie
6. Zachęć do działania (CTA) jeśli pasuje do kontekstu
7. Unikaj znaków specjalnych (emotikony zwiększają koszt SMS)

{$placeholderSection}

FORMAT ODPOWIEDZI:
Zwróć TYLKO tablicę JSON z propozycjami - bez żadnego dodatkowego tekstu.

Dla 1 propozycji:
["Treść wiadomości SMS tutaj"]

Dla 3 propozycji:
["Pierwsza propozycja SMS", "Druga propozycja SMS", "Trzecia propozycja SMS"]

WAŻNE: Każda propozycja to osobny string w tablicy. Odpowiadaj TYLKO JSON array.
PROMPT;

        $fullPrompt = $systemPrompt . "\n\nOpis od użytkownika: " . $prompt;

        $response = $this->aiService->generateContent($fullPrompt, $integration, [
            'max_tokens' => $integration->max_tokens_small ?: 8000,
            'temperature' => 0.8,
            'model' => $modelId,
        ]);

        $results = $this->extractJsonArray($response);

        if (!empty($results) && is_array($results)) {
            // Ensure we have strings in the array
            return array_filter(array_map(function($item) {
                if (is_string($item)) {
                    return trim($item);
                }
                if (is_array($item) && isset($item['content'])) {
                    return trim($item['content']);
                }
                return null;
            }, $results));
        }

        // Fallback: return the raw response as single suggestion
        return [trim(strip_tags($response))];
    }

    /**
     * Build placeholder section for SMS AI prompts
     * Similar to email but adapted for SMS context
     *
     * @param array $customPlaceholders Custom placeholders selected by user
     * @return string Formatted placeholder section for prompt
     */
    protected function buildSmsPlaceholderSection(array $customPlaceholders = []): string
    {
        $lines = [];
        $lines[] = "PERSONALIZACJA (wstawki):";
        $lines[] = "   Używaj wstawek w formacie [[nazwa_pola]] do personalizacji.";
        $lines[] = "   NIGDY nie używaj nawiasów klamrowych {}, tylko podwójne kwadratowe [[]]!";
        $lines[] = "";
        $lines[] = "   DOSTĘPNE WSTAWKI:";

        // Standard placeholders for SMS
        $standardPlaceholders = [
            ['name' => 'first_name', 'description' => 'Imię odbiorcy'],
            ['name' => 'last_name', 'description' => 'Nazwisko odbiorcy'],
            ['name' => 'phone', 'description' => 'Numer telefonu'],
        ];

        foreach ($standardPlaceholders as $p) {
            $lines[] = "   - [[{$p['name']}]] - {$p['description']}";
        }

        // Add custom placeholders if any
        if (!empty($customPlaceholders)) {
            $lines[] = "";
            $lines[] = "   POLA NIESTANDARDOWE:";
            foreach ($customPlaceholders as $p) {
                $desc = $p['description'] ?? $p['label'] ?? $p['name'];
                $lines[] = "   - [[{$p['name']}]] - {$desc}";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Generate email subject lines with preheaders based on content
     *
     * @param string $emailContent Email HTML content
     * @param int $count Number of suggestions
     * @param string|null $userHint Optional user hint/requirements for the subject
     * @param int|null $integrationId Optional specific integration to use
     * @param string|null $modelId Optional specific model to use
     * @return array Array of objects with 'subject' and 'preheader' fields
     */
    public function generateSubjectLine(
        string $emailContent,
        int $count = 3,
        ?string $userHint = null,
        ?int $integrationId = null,
        ?string $modelId = null
    ): array
    {
        $integration = $this->getIntegration($integrationId);

        if (!$integration) {
            throw new \Exception('Brak skonfigurowanej integracji AI.');
        }

        // Strip HTML tags for analysis
        $plainContent = strip_tags($emailContent);
        $plainContent = mb_substr($plainContent, 0, 1000); // Limit content

        $hintSection = '';
        if ($userHint && strlen(trim($userHint)) > 0) {
            $hintSection = "\n\nWskazówka od użytkownika dotycząca tematu:\n{$userHint}\n\nUwzględnij tę wskazówkę przy generowaniu tematów.";
        }

        $dateContext = AiService::getDateContext();

        $prompt = <<<PROMPT
{$dateContext}

Jesteś ekspertem od email marketingu. Tworzysz chwytliwe tematy wiadomości email wraz z preheaderami.

ZADANIE:
Wygeneruj DOKŁADNIE {$count} RÓŻNYCH propozycji tematów email (subject line) wraz z preheaderem dla każdego tematu.

WYMAGANIA DLA KAŻDEGO TEMATU (subject):
✅ Maksymalnie 60 znaków
✅ Chwytliwy i zachęcający do otwarcia
✅ DODAJ 1-2 emotikony (emoji) na początku lub końcu - to zwiększa open rate!
✅ Bez clickbaitu i fałszywych obietnic
✅ Każdy temat MUSI się znacząco różnić od pozostałych

WYMAGANIA DLA KAŻDEGO PREHEADERA:
✅ Maksymalnie 100 znaków
✅ BEZ EMOTIKONÓW (emoji) - preheader musi być tylko tekstem
✅ Uzupełnia temat, dodaje kontekst lub zachętę
✅ Krótkie zdanie lub fraza
{$hintSection}

TREŚĆ EMAILA DO ANALIZY:
{$plainContent}

FORMAT ODPOWIEDZI:
Zwróć TYLKO i WYŁĄCZNIE tablicę JSON obiektów - bez żadnego dodatkowego tekstu, komentarzy ani formatowania markdown.

Oczekiwany format (dokładnie tak):
[{"subject": "🎁 Temat z emoji", "preheader": "Preheader bez emoji tutaj"}, {"subject": "Drugi temat ✨", "preheader": "Drugi preheader"}]

TWOJA ODPOWIEDŹ (tylko JSON array):
PROMPT;

        \Log::info('Subject generation prompt', [
            'count' => $count,
            'plainContent_length' => strlen($plainContent),
        ]);

        $response = $this->aiService->generateContent($prompt, $integration, [
            'max_tokens' => $integration->max_tokens_small ?: 8000,
            'temperature' => 0.9,
            'model' => $modelId,
        ]);

        \Log::info('Subject generation response', ['response' => $response]);

        $results = $this->extractJsonArray($response);

        // Handle both old format (string array) and new format (object array)
        if (!empty($results)) {
            // Check if first element is a string (old format) or object (new format)
            if (is_string($results[0])) {
                // Convert old format to new format
                return array_map(function($subject) {
                    return [
                        'subject' => $subject,
                        'preheader' => '',
                    ];
                }, $results);
            }
            // New format - ensure proper structure
            return array_map(function($item) {
                if (is_array($item) && isset($item['subject'])) {
                    return [
                        'subject' => $item['subject'],
                        'preheader' => $item['preheader'] ?? '',
                    ];
                }
                return [
                    'subject' => is_string($item) ? $item : 'Sprawdź naszą ofertę!',
                    'preheader' => '',
                ];
            }, $results);
        }

        return [['subject' => 'Sprawdź naszą ofertę!', 'preheader' => '']];
    }

    /**
     * Generate product description for e-commerce block
     */
    public function generateProductDescription(array $productData): array
    {
        $integration = $this->getIntegration();

        if (!$integration) {
            throw new \Exception('Brak skonfigurowanej integracji AI.');
        }

        $productName = $productData['name'] ?? 'Produkt';
        $category = $productData['category'] ?? '';
        $features = $productData['features'] ?? '';

        $dateContext = AiService::getDateContext();

        $prompt = <<<PROMPT
{$dateContext}

Wygeneruj krótki, sprzedażowy opis produktu dla emaila.

Produkt: {$productName}
Kategoria: {$category}
Cechy: {$features}

Odpowiedz w formacie JSON:
{
    "title": "Chwytliwy tytuł produktu (max 50 znaków)",
    "description": "Krótki opis zachęcający do zakupu (max 100 słów, format HTML)",
    "callToAction": "Tekst przycisku (max 20 znaków)"
}

Odpowiedź TYLKO JSON.
PROMPT;

        $response = $this->aiService->generateContent($prompt, $integration, [
            'max_tokens' => $integration->max_tokens_small ?: 8000,
            'temperature' => 0.7,
        ]);

        $result = $this->extractJson($response);

        return $result ?? [
            'title' => $productName,
            'description' => '<p>Odkryj nasz nowy produkt!</p>',
            'callToAction' => 'Kup teraz',
        ];
    }

    /**
     * Suggest design improvements for template
     */
    public function suggestDesignImprovements(array $templateStructure): array
    {
        $integration = $this->getIntegration();

        if (!$integration) {
            return [];
        }

        $blocks = $templateStructure['blocks'] ?? [];
        $blockTypes = array_column($blocks, 'type');
        $blockSummary = implode(', ', $blockTypes);

        $dateContext = AiService::getDateContext();

        $prompt = <<<PROMPT
{$dateContext}

Przeanalizuj strukturę emaila i zaproponuj ulepszenia designu.

Obecne bloki w szablonie: {$blockSummary}
Liczba bloków: {count($blocks)}

Zaproponuj max 3 konkretne ulepszenia w formacie JSON:
[
    {"type": "suggestion|warning|tip", "message": "Opis sugestii"}
]

Sugestie powinny dotyczyć:
- Brakujących elementów (np. brak CTA, brak stopki)
- Lepszej struktury (np. za długi email)
- Dobrych praktyk email marketingu

Odpowiedź TYLKO JSON array.
PROMPT;

        $response = $this->aiService->generateContent($prompt, $integration, [
            'max_tokens' => $integration->max_tokens_small ?: 8000,
            'temperature' => 0.5,
        ]);

        return $this->extractJsonArray($response) ?: [];
    }

    /**
     * Extract JSON object from AI response
     */
    protected function extractJson(string $response): ?array
    {
        // Try to find JSON in the response
        if (preg_match('/\{[\s\S]*\}/', $response, $matches)) {
            $json = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $json;
            }
        }

        return null;
    }

    /**
     * Extract JSON array from AI response
     */
    protected function extractJsonArray(string $response): ?array
    {
        // Strip markdown code blocks if present
        $cleanResponse = preg_replace('/```(?:json)?\s*/i', '', $response);
        $cleanResponse = preg_replace('/```\s*/i', '', $cleanResponse);
        $cleanResponse = trim($cleanResponse);

        \Log::info('extractJsonArray attempting parse', [
            'original_length' => strlen($response),
            'cleaned_length' => strlen($cleanResponse),
            'first_100_chars' => substr($cleanResponse, 0, 100),
        ]);

        // First try: direct json_decode on cleaned response
        $json = json_decode($cleanResponse, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
            \Log::info('extractJsonArray direct decode success', ['count' => count($json)]);
            return $json;
        }

        // Second try: find first [ and corresponding ]
        $startPos = strpos($cleanResponse, '[');
        if ($startPos !== false) {
            $depth = 0;
            $endPos = null;
            for ($i = $startPos; $i < strlen($cleanResponse); $i++) {
                if ($cleanResponse[$i] === '[') $depth++;
                if ($cleanResponse[$i] === ']') $depth--;
                if ($depth === 0) {
                    $endPos = $i;
                    break;
                }
            }

            if ($endPos !== null) {
                $jsonStr = substr($cleanResponse, $startPos, $endPos - $startPos + 1);
                \Log::info('extractJsonArray extracted json string', ['jsonStr' => $jsonStr]);

                $json = json_decode($jsonStr, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
                    \Log::info('extractJsonArray bracket matching success', ['count' => count($json)]);
                    return $json;
                }
                \Log::warning('extractJsonArray JSON decode failed', [
                    'error' => json_last_error_msg(),
                    'jsonStr' => substr($jsonStr, 0, 200),
                ]);
            }
        }

        \Log::warning('extractJsonArray no valid JSON found');
        return null;
    }
}
