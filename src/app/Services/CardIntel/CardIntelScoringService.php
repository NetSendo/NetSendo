<?php

namespace App\Services\CardIntel;

use App\Models\CardIntelScan;
use App\Models\CardIntelExtraction;
use App\Models\CardIntelContext;
use App\Models\CardIntelSettings;
use Illuminate\Support\Facades\Log;

class CardIntelScoringService
{
    /**
     * Free email providers that reduce context score.
     */
    protected const FREE_EMAIL_PROVIDERS = [
        'gmail.com', 'googlemail.com',
        'yahoo.com', 'yahoo.pl',
        'outlook.com', 'hotmail.com', 'live.com',
        'wp.pl', 'o2.pl', 'interia.pl', 'onet.pl', 'tlen.pl',
        'protonmail.com', 'proton.me',
        'icloud.com', 'me.com',
        'aol.com', 'mail.com', 'yandex.com',
    ];

    /**
     * Score an extraction and create context record.
     */
    public function scoreExtraction(
        CardIntelExtraction $extraction,
        ?CardIntelSettings $settings = null
    ): CardIntelContext {
        $fields = $extraction->fields;
        $confidence = $extraction->confidence;

        $signals = $this->calculateSignals($fields, $confidence);
        $qualityScore = $this->calculateQualityScore($signals, $confidence);

        // Get thresholds from settings or use defaults
        $settings ??= CardIntelSettings::getForUser($extraction->scan->user_id);
        $contextLevel = $settings->getContextLevel($qualityScore);

        $reasoning = $this->generateReasoning($signals, $qualityScore, $contextLevel);

        return CardIntelContext::updateOrCreate(
            ['scan_id' => $extraction->scan_id],
            [
                'context_level' => $contextLevel,
                'quality_score' => $qualityScore,
                'signals_json' => $signals,
                'reasoning_short' => $reasoning,
            ]
        );
    }

    /**
     * Calculate all signals for scoring.
     */
    protected function calculateSignals(array $fields, array $confidence): array
    {
        $signals = [];

        // Required fields presence
        $signals['has_email'] = !empty($fields['email']);
        $signals['has_phone'] = !empty($fields['phone']);
        $signals['has_website'] = !empty($fields['website']);
        $signals['has_nip_or_regon'] = !empty($fields['nip']) || !empty($fields['regon']);
        $signals['has_company'] = !empty($fields['company']);
        $signals['has_position'] = !empty($fields['position']);

        // Name completeness
        $hasFirstName = !empty($fields['first_name']);
        $hasLastName = !empty($fields['last_name']);
        $signals['complete_name'] = $hasFirstName && $hasLastName;

        // Corporate email check
        if ($signals['has_email']) {
            $signals['corporate_email'] = $this->isCorporateEmail($fields['email']);
        } else {
            $signals['corporate_email'] = false;
        }

        // Company vs personal name check
        if ($signals['has_company']) {
            $signals['company_not_personal'] = $this->isCompanyNotPersonal(
                $fields['company'],
                $fields['first_name'],
                $fields['last_name']
            );
        } else {
            $signals['company_not_personal'] = false;
        }

        // NIP validation (basic format check, not registry lookup)
        if (!empty($fields['nip'])) {
            $signals['nip_valid_format'] = $this->isValidNipFormat($fields['nip']);
        } else {
            $signals['nip_valid_format'] = false;
        }

        // High confidence data
        $avgConfidence = array_sum($confidence) / max(1, count(array_filter($confidence)));
        $signals['high_confidence_data'] = $avgConfidence >= 0.7;

        return $signals;
    }

    /**
     * Calculate quality score (0-100) based on signals and confidence.
     */
    protected function calculateQualityScore(array $signals, array $confidence): int
    {
        $score = 0;

        // Points for each signal (total 100 possible)
        $weights = [
            'has_email' => 15,          // Email is critical
            'has_phone' => 10,
            'has_website' => 10,
            'has_nip_or_regon' => 10,
            'has_company' => 10,
            'has_position' => 5,
            'complete_name' => 10,
            'corporate_email' => 15,    // Corporate email is very valuable
            'company_not_personal' => 5,
            'nip_valid_format' => 5,
            'high_confidence_data' => 5,
        ];

        foreach ($weights as $signal => $weight) {
            if ($signals[$signal] ?? false) {
                $score += $weight;
            }
        }

        // Apply confidence penalty (reduce score if confidence is low)
        $avgConfidence = array_sum($confidence) / max(1, count(array_filter($confidence)));
        if ($avgConfidence < 0.5) {
            $score = (int) ($score * 0.8); // 20% penalty for low confidence
        }

        return min(100, max(0, $score));
    }

    /**
     * Check if email is from a corporate domain.
     */
    protected function isCorporateEmail(string $email): bool
    {
        $parts = explode('@', strtolower($email));
        $domain = $parts[1] ?? '';

        return !in_array($domain, self::FREE_EMAIL_PROVIDERS);
    }

    /**
     * Check if company name is not just a personal name.
     */
    protected function isCompanyNotPersonal(string $company, ?string $firstName, ?string $lastName): bool
    {
        $companyLower = strtolower(trim($company));

        // Check if company contains business entity indicators
        $businessIndicators = [
            'sp. z o.o.', 'spółka z o.o.', 's.a.', 'spółka akcyjna',
            'sp.j.', 'sp.k.', 'sp.p.',
            'pphu', 'phu', 'fhu', 'zphu',
            'firma', 'przedsiębiorstwo', 'zakład',
            'studio', 'agencja', 'biuro', 'kancelaria',
            'grupa', 'holdings', 'consulting', 'solutions',
            'tech', 'soft', 'it', 'media', 'marketing',
            'limited', 'ltd', 'inc', 'corp', 'gmbh',
        ];

        foreach ($businessIndicators as $indicator) {
            if (str_contains($companyLower, $indicator)) {
                return true;
            }
        }

        // Check if company is just person's name
        if ($firstName && $lastName) {
            $fullName = strtolower(trim("$firstName $lastName"));
            $reverseName = strtolower(trim("$lastName $firstName"));

            if ($companyLower === $fullName || $companyLower === $reverseName) {
                return false; // Company is same as person's name
            }
        }

        // If company has multiple words, likely a business name
        $wordCount = str_word_count($company);
        return $wordCount > 2;
    }

    /**
     * Validate NIP format (Polish tax ID).
     */
    protected function isValidNipFormat(string $nip): bool
    {
        $nip = preg_replace('/\D/', '', $nip);

        if (strlen($nip) !== 10) {
            return false;
        }

        // NIP checksum validation
        $weights = [6, 5, 7, 2, 3, 4, 5, 6, 7];
        $sum = 0;

        for ($i = 0; $i < 9; $i++) {
            $sum += (int) $nip[$i] * $weights[$i];
        }

        $checksum = $sum % 11;

        return $checksum === (int) $nip[9];
    }

    /**
     * Generate human-readable reasoning for the score.
     */
    protected function generateReasoning(array $signals, int $score, string $level): string
    {
        $positive = [];
        $negative = [];

        // Map signals to Polish descriptions
        $descriptions = [
            'has_email' => ['Ma adres email', 'Brak adresu email'],
            'has_phone' => ['Ma numer telefonu', 'Brak numeru telefonu'],
            'has_website' => ['Ma stronę www', 'Brak strony www'],
            'has_nip_or_regon' => ['Ma NIP lub REGON', 'Brak NIP/REGON'],
            'has_company' => ['Podana nazwa firmy', 'Brak nazwy firmy'],
            'has_position' => ['Podane stanowisko', 'Brak stanowiska'],
            'complete_name' => ['Pełne imię i nazwisko', 'Niepełne dane osobowe'],
            'corporate_email' => ['Email firmowy', 'Email prywatny (gmail/wp)'],
            'company_not_personal' => ['Firma ≠ osoba fizyczna', 'Firma = osoba fizyczna'],
            'nip_valid_format' => ['NIP prawidłowy format', ''],
            'high_confidence_data' => ['Dane wysokiej pewności', 'Niska pewność danych'],
        ];

        foreach ($signals as $key => $value) {
            if (!isset($descriptions[$key])) {
                continue;
            }

            [$positiveText, $negativeText] = $descriptions[$key];

            if ($value && $positiveText) {
                $positive[] = "• " . $positiveText;
            } elseif (!$value && $negativeText) {
                $negative[] = "• " . $negativeText;
            }
        }

        // Combine positive and negative, limit to 6 items
        $bullets = array_merge(
            array_slice($positive, 0, 4),
            array_slice($negative, 0, 2)
        );

        return implode("\n", array_slice($bullets, 0, 6));
    }

    /**
     * Re-score a scan after manual field updates.
     */
    public function rescoreScan(CardIntelScan $scan): CardIntelContext
    {
        $extraction = $scan->extraction;

        if (!$extraction) {
            throw new \Exception('No extraction found for scan');
        }

        $settings = CardIntelSettings::getForUser($scan->user_id);

        return $this->scoreExtraction($extraction, $settings);
    }
}
