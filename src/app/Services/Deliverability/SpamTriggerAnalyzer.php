<?php

namespace App\Services\Deliverability;

class SpamTriggerAnalyzer
{
    /**
     * Spam trigger words (case-insensitive)
     */
    private const SPAM_WORDS = [
        // Polish
        'za darmo', 'gratis', 'pilne', 'natychmiast', 'wygraj', 'nagroda',
        'kliknij tutaj', 'kliknij teraz', 'nie przegap', 'ostatnia szansa',
        'promocja', 'rabat', 'zniżka', 'bezpłatnie', 'darmowe',
        'zarabiaj', 'miliony', 'tysiące', 'bogactwo', 'sukces finansowy',
        'gwarancja zwrotu', 'bez ryzyka', 'stuprocentowo', '100%',
        'ekskluzywna oferta', 'tylko dla ciebie', 'specjalna oferta',

        // English
        'free', 'winner', 'won', 'prize', 'urgent', 'immediately',
        'click here', 'click now', 'act now', 'limited time',
        'congratulations', 'promotion', 'discount', 'sale',
        'earn money', 'millions', 'thousands', 'rich', 'wealth',
        'guarantee', 'risk free', 'no risk', '100%',
        'exclusive offer', 'just for you', 'special offer',
        'buy now', 'order now', 'subscribe now', 'sign up now',

        // German
        'kostenlos', 'gewinner', 'preis', 'dringend', 'sofort',
        'klicken sie hier', 'jetzt kaufen', 'rabatt', 'angebot',
    ];

    /**
     * Suspicious TLD patterns
     */
    private const SUSPICIOUS_TLDS = [
        '.tk', '.ml', '.ga', '.cf', '.gq', '.xyz', '.top', '.work', '.click',
    ];

    /**
     * URL shortener domains
     */
    private const URL_SHORTENERS = [
        'bit.ly', 'tinyurl.com', 'goo.gl', 't.co', 'ow.ly', 'is.gd',
        'buff.ly', 'adf.ly', 'tiny.cc', 'j.mp', 'rb.gy', 'cutt.ly',
    ];

    /**
     * Analyze content for spam triggers
     */
    public function analyze(string $subject, string $htmlContent): ContentAnalysisResult
    {
        $result = new ContentAnalysisResult();

        // Combine subject and content for analysis
        $textContent = $this->stripHtml($htmlContent);
        $fullText = $subject . ' ' . $textContent;

        // Check spam words
        $result->spamWords = $this->detectSpamWords($fullText);

        // Check subject specific issues
        $result->subjectIssues = $this->analyzeSubject($subject);

        // Check HTML structure
        $result->htmlIssues = $this->analyzeHtml($htmlContent);

        // Check links
        $result->linkIssues = $this->analyzeLinks($htmlContent);

        // Check formatting
        $result->formattingIssues = $this->analyzeFormatting($subject, $textContent);

        // Calculate content score (0-40 points, deducted from 100)
        $result->contentScore = $this->calculateContentScore($result);

        return $result;
    }

    /**
     * Detect spam words in text
     */
    private function detectSpamWords(string $text): array
    {
        $found = [];
        $lowerText = mb_strtolower($text);

        foreach (self::SPAM_WORDS as $word) {
            if (str_contains($lowerText, $word)) {
                $found[] = [
                    'word' => $word,
                    'severity' => $this->getWordSeverity($word),
                    'message_key' => 'deliverability.spam.word_detected',
                ];
            }
        }

        return $found;
    }

    /**
     * Get severity of spam word
     */
    private function getWordSeverity(string $word): string
    {
        $highSeverity = ['free', 'za darmo', 'winner', 'wygraj', 'urgent', 'pilne', '100%'];
        $mediumSeverity = ['click here', 'kliknij tutaj', 'buy now', 'kup teraz'];

        if (in_array($word, $highSeverity)) {
            return 'high';
        }
        if (in_array($word, $mediumSeverity)) {
            return 'medium';
        }
        return 'low';
    }

    /**
     * Analyze subject line
     */
    private function analyzeSubject(string $subject): array
    {
        $issues = [];

        // Check length
        if (mb_strlen($subject) > 60) {
            $issues[] = [
                'code' => 'subject_too_long',
                'severity' => 'low',
                'message_key' => 'deliverability.subject.too_long',
            ];
        }

        if (mb_strlen($subject) < 5) {
            $issues[] = [
                'code' => 'subject_too_short',
                'severity' => 'medium',
                'message_key' => 'deliverability.subject.too_short',
            ];
        }

        // Check ALL CAPS
        $capsRatio = $this->getCapsRatio($subject);
        if ($capsRatio > 0.5 && mb_strlen($subject) > 10) {
            $issues[] = [
                'code' => 'subject_all_caps',
                'severity' => 'high',
                'message_key' => 'deliverability.subject.all_caps',
            ];
        }

        // Check excessive punctuation
        $exclamations = substr_count($subject, '!');
        if ($exclamations >= 2) {
            $issues[] = [
                'code' => 'subject_exclamations',
                'severity' => 'high',
                'message_key' => 'deliverability.subject.exclamations',
            ];
        }

        $questions = substr_count($subject, '?');
        if ($questions >= 3) {
            $issues[] = [
                'code' => 'subject_questions',
                'severity' => 'medium',
                'message_key' => 'deliverability.subject.questions',
            ];
        }

        // Check for RE: or FW: (fake reply)
        if (preg_match('/^(re:|fw:|fwd:)/i', $subject)) {
            $issues[] = [
                'code' => 'subject_fake_reply',
                'severity' => 'high',
                'message_key' => 'deliverability.subject.fake_reply',
            ];
        }

        return $issues;
    }

    /**
     * Analyze HTML structure
     */
    private function analyzeHtml(string $html): array
    {
        $issues = [];

        $textContent = $this->stripHtml($html);
        $htmlLength = strlen($html);
        $textLength = strlen($textContent);

        // Check text/HTML ratio
        if ($htmlLength > 0) {
            $ratio = $textLength / $htmlLength;
            if ($ratio < 0.2) {
                $issues[] = [
                    'code' => 'html_ratio_low',
                    'severity' => 'medium',
                    'message_key' => 'deliverability.html.ratio_low',
                ];
            }
        }

        // Check for dark patterns (hidden text)
        if (preg_match('/display\s*:\s*none/i', $html)) {
            $issues[] = [
                'code' => 'html_hidden_text',
                'severity' => 'high',
                'message_key' => 'deliverability.html.hidden_text',
            ];
        }

        // Check for tiny fonts
        if (preg_match('/font-size\s*:\s*[0-5](px|pt)/i', $html)) {
            $issues[] = [
                'code' => 'html_tiny_font',
                'severity' => 'high',
                'message_key' => 'deliverability.html.tiny_font',
            ];
        }

        // Check image-to-text ratio
        preg_match_all('/<img\s/i', $html, $images);
        $imageCount = count($images[0]);
        if ($imageCount > 0 && $textLength < 100) {
            $issues[] = [
                'code' => 'html_image_heavy',
                'severity' => 'medium',
                'message_key' => 'deliverability.html.image_heavy',
            ];
        }

        return $issues;
    }

    /**
     * Analyze links in content
     */
    private function analyzeLinks(string $html): array
    {
        $issues = [];

        preg_match_all('/href=["\']([^"\']+)["\']/i', $html, $matches);
        $urls = $matches[1] ?? [];

        foreach ($urls as $url) {
            $parsed = parse_url($url);
            $host = $parsed['host'] ?? '';

            // Check for URL shorteners
            foreach (self::URL_SHORTENERS as $shortener) {
                if (str_contains(strtolower($host), $shortener)) {
                    $issues[] = [
                        'code' => 'link_shortener',
                        'severity' => 'medium',
                        'url' => $url,
                        'message_key' => 'deliverability.links.shortener',
                    ];
                    break;
                }
            }

            // Check for suspicious TLDs
            foreach (self::SUSPICIOUS_TLDS as $tld) {
                if (str_ends_with(strtolower($host), $tld)) {
                    $issues[] = [
                        'code' => 'link_suspicious_tld',
                        'severity' => 'medium',
                        'url' => $url,
                        'message_key' => 'deliverability.links.suspicious_tld',
                    ];
                    break;
                }
            }

            // Check for IP-based URLs
            if (preg_match('/^(\d{1,3}\.){3}\d{1,3}$/', $host)) {
                $issues[] = [
                    'code' => 'link_ip_address',
                    'severity' => 'high',
                    'url' => $url,
                    'message_key' => 'deliverability.links.ip_address',
                ];
            }
        }

        // Check link count
        if (count($urls) > 20) {
            $issues[] = [
                'code' => 'too_many_links',
                'severity' => 'medium',
                'message_key' => 'deliverability.links.too_many',
            ];
        }

        return $issues;
    }

    /**
     * Analyze text formatting
     */
    private function analyzeFormatting(string $subject, string $text): array
    {
        $issues = [];
        $fullText = $subject . ' ' . $text;

        // Check overall caps ratio
        $capsRatio = $this->getCapsRatio($fullText);
        if ($capsRatio > 0.3 && mb_strlen($fullText) > 50) {
            $issues[] = [
                'code' => 'too_many_caps',
                'severity' => 'medium',
                'message_key' => 'deliverability.formatting.caps',
            ];
        }

        // Check for excessive symbols
        $symbolCount = preg_match_all('/[!?€$%@#*]/', $fullText);
        if ($symbolCount > 10) {
            $issues[] = [
                'code' => 'too_many_symbols',
                'severity' => 'low',
                'message_key' => 'deliverability.formatting.symbols',
            ];
        }

        return $issues;
    }

    /**
     * Calculate content score
     */
    private function calculateContentScore(ContentAnalysisResult $result): int
    {
        $score = 40; // Max content contribution

        // Deduct for spam words
        foreach ($result->spamWords as $word) {
            $deduction = match($word['severity']) {
                'high' => 5,
                'medium' => 3,
                default => 1,
            };
            $score -= $deduction;
        }

        // Deduct for subject issues
        foreach ($result->subjectIssues as $issue) {
            $deduction = match($issue['severity']) {
                'high' => 5,
                'medium' => 3,
                default => 1,
            };
            $score -= $deduction;
        }

        // Deduct for HTML issues
        foreach ($result->htmlIssues as $issue) {
            $deduction = match($issue['severity']) {
                'high' => 5,
                'medium' => 3,
                default => 1,
            };
            $score -= $deduction;
        }

        // Deduct for link issues
        foreach ($result->linkIssues as $issue) {
            $deduction = match($issue['severity']) {
                'high' => 5,
                'medium' => 3,
                default => 1,
            };
            $score -= $deduction;
        }

        // Deduct for formatting issues
        foreach ($result->formattingIssues as $issue) {
            $deduction = match($issue['severity']) {
                'high' => 5,
                'medium' => 3,
                default => 1,
            };
            $score -= $deduction;
        }

        return max(0, $score);
    }

    /**
     * Strip HTML tags
     */
    private function stripHtml(string $html): string
    {
        // Remove style and script tags completely
        $html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);
        $html = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html);

        return strip_tags($html);
    }

    /**
     * Calculate caps ratio
     */
    private function getCapsRatio(string $text): float
    {
        $letters = preg_replace('/[^a-zA-Z]/', '', $text);
        if (empty($letters)) {
            return 0;
        }

        $caps = preg_replace('/[^A-Z]/', '', $text);
        return mb_strlen($caps) / mb_strlen($letters);
    }
}

/**
 * Result of content analysis
 */
class ContentAnalysisResult
{
    public array $spamWords = [];
    public array $subjectIssues = [];
    public array $htmlIssues = [];
    public array $linkIssues = [];
    public array $formattingIssues = [];
    public int $contentScore = 40;

    public function getAllIssues(): array
    {
        return array_merge(
            array_map(fn($w) => ['type' => 'spam_word', ...$w], $this->spamWords),
            array_map(fn($i) => ['type' => 'subject', ...$i], $this->subjectIssues),
            array_map(fn($i) => ['type' => 'html', ...$i], $this->htmlIssues),
            array_map(fn($i) => ['type' => 'link', ...$i], $this->linkIssues),
            array_map(fn($i) => ['type' => 'formatting', ...$i], $this->formattingIssues)
        );
    }

    public function getCriticalIssues(): array
    {
        return array_filter($this->getAllIssues(), fn($i) => ($i['severity'] ?? '') === 'high');
    }
}
