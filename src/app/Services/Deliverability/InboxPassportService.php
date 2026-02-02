<?php

namespace App\Services\Deliverability;

use App\Models\DomainConfiguration;
use App\Models\InboxSimulation;
use App\Models\User;
use App\Services\AI\AIService;
use Illuminate\Support\Facades\Log;

class InboxPassportService
{
    public function __construct(
        private DomainVerificationService $domainService,
        private SpamTriggerAnalyzer $spamAnalyzer,
        private ?AIService $aiService = null
    ) {}

    /**
     * Run full inbox simulation
     */
    public function simulate(
        User $user,
        DomainConfiguration $domain,
        string $subject,
        string $htmlContent,
        ?int $messageId = null
    ): InboxSimulation {
        // Analyze domain
        $domainAnalysis = $this->analyzeDomain($domain);

        // Analyze content
        $contentAnalysis = $this->spamAnalyzer->analyze($subject, $htmlContent);

        // Calculate scores
        $scoreBreakdown = $this->calculateScoreBreakdown($domainAnalysis, $contentAnalysis);
        $inboxScore = $this->calculateFinalScore($scoreBreakdown);

        // Predict folder
        $predictedFolder = $this->predictFolder($inboxScore, $domainAnalysis, $contentAnalysis);

        // Generate provider predictions
        $providerPredictions = $this->generateProviderPredictions(
            $inboxScore,
            $domainAnalysis,
            $contentAnalysis
        );

        // Collect issues
        $issues = $this->collectIssues($domainAnalysis, $contentAnalysis);

        // Generate recommendations
        $recommendations = $this->generateRecommendations($issues, $domainAnalysis, $contentAnalysis);

        // Create simulation record
        $simulation = InboxSimulation::create([
            'user_id' => $user->id,
            'domain_configuration_id' => $domain->id,
            'message_id' => $messageId,
            'subject' => $subject,
            'content_preview' => mb_substr(strip_tags($htmlContent), 0, 500),
            'from_email' => $domain->mailbox?->from_email,
            'inbox_score' => $inboxScore,
            'predicted_folder' => $predictedFolder,
            'provider_predictions' => $providerPredictions,
            'domain_analysis' => $domainAnalysis,
            'content_analysis' => [
                'spam_words_count' => count($contentAnalysis->spamWords),
                'subject_issues' => $contentAnalysis->subjectIssues,
                'html_issues' => $contentAnalysis->htmlIssues,
                'link_issues' => $contentAnalysis->linkIssues,
                'content_score' => $contentAnalysis->contentScore,
            ],
            'issues' => $issues,
            'recommendations' => $recommendations,
            'score_breakdown' => $scoreBreakdown,
            'is_test' => $messageId === null,
            'analyzed_at' => now(),
        ]);

        return $simulation;
    }

    /**
     * Analyze domain configuration
     */
    private function analyzeDomain(DomainConfiguration $domain): array
    {
        $score = 30; // Max domain contribution
        $issues = [];

        // Check CNAME verification
        if (!$domain->cname_verified) {
            $score -= 10;
            $issues[] = [
                'code' => 'domain_not_verified',
                'severity' => 'critical',
                'message_key' => 'deliverability.domain.not_verified',
            ];
        }

        // Check SPF
        if ($domain->spf_status === DomainConfiguration::STATUS_CRITICAL) {
            $score -= 8;
            $issues[] = [
                'code' => 'spf_critical',
                'severity' => 'critical',
                'message_key' => 'deliverability.domain.spf_critical',
            ];
        } elseif ($domain->spf_status === DomainConfiguration::STATUS_WARNING) {
            $score -= 4;
            $issues[] = [
                'code' => 'spf_warning',
                'severity' => 'warning',
                'message_key' => 'deliverability.domain.spf_warning',
            ];
        }

        // Check DKIM
        if ($domain->dkim_status === DomainConfiguration::STATUS_CRITICAL) {
            $score -= 8;
            $issues[] = [
                'code' => 'dkim_critical',
                'severity' => 'critical',
                'message_key' => 'deliverability.domain.dkim_critical',
            ];
        } elseif ($domain->dkim_status === DomainConfiguration::STATUS_WARNING) {
            $score -= 4;
            $issues[] = [
                'code' => 'dkim_warning',
                'severity' => 'warning',
                'message_key' => 'deliverability.domain.dkim_warning',
            ];
        }

        // Check DMARC
        if ($domain->dmarc_status === DomainConfiguration::STATUS_CRITICAL) {
            $score -= 10;
            $issues[] = [
                'code' => 'dmarc_critical',
                'severity' => 'critical',
                'message_key' => 'deliverability.domain.dmarc_critical',
            ];
        } elseif ($domain->dmarc_status === DomainConfiguration::STATUS_WARNING) {
            $score -= 5;
            $issues[] = [
                'code' => 'dmarc_warning',
                'severity' => 'warning',
                'message_key' => 'deliverability.domain.dmarc_warning',
            ];
        }

        // Check DMARC policy
        if ($domain->dmarc_policy === DomainConfiguration::DMARC_NONE) {
            $score -= 3;
            $issues[] = [
                'code' => 'dmarc_policy_none',
                'severity' => 'warning',
                'message_key' => 'deliverability.domain.dmarc_policy_none',
            ];
        }

        return [
            'score' => max(0, $score),
            'max_score' => 30,
            'issues' => $issues,
            'overall_status' => $domain->overall_status,
            'dmarc_policy' => $domain->dmarc_policy,
        ];
    }

    /**
     * Calculate score breakdown
     */
    private function calculateScoreBreakdown(array $domainAnalysis, ContentAnalysisResult $contentAnalysis): array
    {
        return [
            'domain' => [
                'score' => $domainAnalysis['score'],
                'max' => 30,
                'weight' => 0.3,
            ],
            'content' => [
                'score' => $contentAnalysis->contentScore,
                'max' => 40,
                'weight' => 0.4,
            ],
            'reputation' => [
                'score' => 30, // Default base reputation (can be enhanced with IP reputation checks)
                'max' => 30,
                'weight' => 0.3,
            ],
        ];
    }

    /**
     * Calculate final inbox score
     */
    private function calculateFinalScore(array $breakdown): int
    {
        $totalScore = 0;

        foreach ($breakdown as $category) {
            // Normalize score to percentage
            $percentage = ($category['score'] / $category['max']) * 100;
            // Apply weight
            $totalScore += $percentage * $category['weight'];
        }

        return (int) round($totalScore);
    }

    /**
     * Predict which folder email will land in
     */
    private function predictFolder(int $score, array $domainAnalysis, ContentAnalysisResult $contentAnalysis): string
    {
        // Critical domain issues = spam
        if ($domainAnalysis['overall_status'] === DomainConfiguration::OVERALL_CRITICAL) {
            return InboxSimulation::FOLDER_SPAM;
        }

        // Very high spam triggers = spam
        if (count($contentAnalysis->getCriticalIssues()) >= 3) {
            return InboxSimulation::FOLDER_SPAM;
        }

        // Score-based prediction
        if ($score >= 80) {
            return InboxSimulation::FOLDER_INBOX;
        } elseif ($score >= 60) {
            // Might land in promotions if content is promotional
            return $this->hasPromotionalContent($contentAnalysis)
                ? InboxSimulation::FOLDER_PROMOTIONS
                : InboxSimulation::FOLDER_INBOX;
        } elseif ($score >= 40) {
            return InboxSimulation::FOLDER_PROMOTIONS;
        }

        return InboxSimulation::FOLDER_SPAM;
    }

    /**
     * Check if content appears promotional
     */
    private function hasPromotionalContent(ContentAnalysisResult $analysis): bool
    {
        $promotionalWords = ['promocja', 'rabat', 'zniżka', 'sale', 'discount', 'offer', 'oferta'];

        foreach ($analysis->spamWords as $word) {
            if (in_array($word['word'] ?? '', $promotionalWords)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate provider-specific predictions
     */
    private function generateProviderPredictions(
        int $baseScore,
        array $domainAnalysis,
        ContentAnalysisResult $contentAnalysis
    ): array {
        // Gmail is stricter with domain configuration
        $gmailScore = $baseScore;
        if ($domainAnalysis['dmarc_policy'] === DomainConfiguration::DMARC_NONE) {
            $gmailScore -= 10;
        }

        // Outlook focuses more on content
        $outlookScore = $baseScore;
        if (count($contentAnalysis->spamWords) > 3) {
            $outlookScore -= 5;
        }

        // Yahoo is generally more lenient
        $yahooScore = min(100, $baseScore + 5);

        return [
            'gmail' => [
                'score' => max(0, $gmailScore),
                'folder' => $this->scoreTofolder($gmailScore),
            ],
            'outlook' => [
                'score' => max(0, $outlookScore),
                'folder' => $this->scoreTofolder($outlookScore),
            ],
            'yahoo' => [
                'score' => max(0, $yahooScore),
                'folder' => $this->scoreTofolder($yahooScore),
            ],
        ];
    }

    /**
     * Convert score to folder prediction
     */
    private function scoreTofolder(int $score): string
    {
        if ($score >= 75) return InboxSimulation::FOLDER_INBOX;
        if ($score >= 50) return InboxSimulation::FOLDER_PROMOTIONS;
        return InboxSimulation::FOLDER_SPAM;
    }

    /**
     * Collect all issues
     */
    private function collectIssues(array $domainAnalysis, ContentAnalysisResult $contentAnalysis): array
    {
        $issues = [];

        // Domain issues
        foreach ($domainAnalysis['issues'] as $issue) {
            $issues[] = [
                'type' => 'domain',
                ...$issue,
            ];
        }

        // Content issues
        foreach ($contentAnalysis->getAllIssues() as $issue) {
            $issues[] = $issue;
        }

        // Sort by severity
        usort($issues, function ($a, $b) {
            $severityOrder = ['critical' => 0, 'high' => 1, 'warning' => 2, 'medium' => 3, 'low' => 4];
            $aSeverity = $severityOrder[$a['severity'] ?? 'low'] ?? 5;
            $bSeverity = $severityOrder[$b['severity'] ?? 'low'] ?? 5;
            return $aSeverity - $bSeverity;
        });

        return $issues;
    }

    /**
     * Generate actionable recommendations
     */
    private function generateRecommendations(
        array $issues,
        array $domainAnalysis,
        ContentAnalysisResult $contentAnalysis
    ): array {
        $recommendations = [];

        // Domain recommendations
        if ($domainAnalysis['overall_status'] !== DomainConfiguration::OVERALL_SAFE) {
            $recommendations[] = [
                'priority' => 'high',
                'category' => 'domain',
                'message_key' => 'deliverability.recommendations.fix_domain',
                'action_key' => 'deliverability.actions.go_to_domain_settings',
            ];
        }

        if ($domainAnalysis['dmarc_policy'] === DomainConfiguration::DMARC_NONE) {
            $recommendations[] = [
                'priority' => 'medium',
                'category' => 'domain',
                'message_key' => 'deliverability.recommendations.upgrade_dmarc',
            ];
        }

        // Content recommendations
        if (!empty($contentAnalysis->spamWords)) {
            $recommendations[] = [
                'priority' => 'high',
                'category' => 'content',
                'message_key' => 'deliverability.recommendations.remove_spam_words',
                'details' => array_slice(array_column($contentAnalysis->spamWords, 'word'), 0, 5),
            ];
        }

        if (!empty($contentAnalysis->subjectIssues)) {
            $recommendations[] = [
                'priority' => 'high',
                'category' => 'subject',
                'message_key' => 'deliverability.recommendations.improve_subject',
            ];
        }

        if (!empty($contentAnalysis->htmlIssues)) {
            $recommendations[] = [
                'priority' => 'medium',
                'category' => 'html',
                'message_key' => 'deliverability.recommendations.fix_html',
            ];
        }

        if (!empty($contentAnalysis->linkIssues)) {
            $recommendations[] = [
                'priority' => 'medium',
                'category' => 'links',
                'message_key' => 'deliverability.recommendations.fix_links',
            ];
        }

        // General recommendations
        if (empty($issues)) {
            $recommendations[] = [
                'priority' => 'info',
                'category' => 'general',
                'message_key' => 'deliverability.recommendations.looks_good',
            ];
        }

        return $recommendations;
    }

    /**
     * Analyze content only (without domain) for quick simulations
     */
    public function analyzeContentOnly(string $subject, string $htmlContent): array
    {
        // Analyze content
        $contentAnalysis = $this->spamAnalyzer->analyze($subject, $htmlContent);

        // Calculate content score (content is 40% of full score, but we scale to 100)
        $contentScore = (int) round(($contentAnalysis->contentScore / 40) * 70); // Max 70 without domain

        // Collect content issues
        $issues = [];

        if (!empty($contentAnalysis->spamWords)) {
            foreach (array_slice($contentAnalysis->spamWords, 0, 5) as $word) {
                $issues[] = [
                    'code' => 'spam_word',
                    'severity' => 'warning',
                    'message_key' => 'deliverability.content.spam_word',
                    'context' => ['word' => $word],
                ];
            }
        }

        foreach ($contentAnalysis->subjectIssues as $issue) {
            $issues[] = [
                'code' => $issue['code'],
                'severity' => $issue['severity'] ?? 'warning',
                'message_key' => $issue['message_key'],
            ];
        }

        foreach ($contentAnalysis->htmlIssues as $issue) {
            $issues[] = [
                'code' => $issue['code'],
                'severity' => $issue['severity'] ?? 'warning',
                'message_key' => $issue['message_key'],
            ];
        }

        foreach ($contentAnalysis->linkIssues as $issue) {
            $issues[] = [
                'code' => $issue['code'],
                'severity' => $issue['severity'] ?? 'warning',
                'message_key' => $issue['message_key'],
            ];
        }

        // Predict folder based on content only
        $predictedFolder = InboxSimulation::FOLDER_INBOX;
        if (count($contentAnalysis->getCriticalIssues()) >= 3) {
            $predictedFolder = InboxSimulation::FOLDER_SPAM;
        } elseif ($contentScore < 50) {
            $predictedFolder = InboxSimulation::FOLDER_SPAM;
        } elseif ($contentScore < 65 || $this->hasPromotionalContent($contentAnalysis)) {
            $predictedFolder = InboxSimulation::FOLDER_PROMOTIONS;
        }

        // Generate recommendations
        $recommendations = [];

        if (!empty($contentAnalysis->spamWords)) {
            $recommendations[] = [
                'priority' => 'high',
                'category' => 'content',
                'message_key' => 'deliverability.recommendations.remove_spam_words',
            ];
        }

        if (!empty($contentAnalysis->subjectIssues)) {
            $recommendations[] = [
                'priority' => 'medium',
                'category' => 'subject',
                'message_key' => 'deliverability.recommendations.improve_subject',
            ];
        }

        // Add domain recommendation since no domain is configured
        $recommendations[] = [
            'priority' => 'high',
            'category' => 'domain',
            'message_key' => 'deliverability.recommendations.add_domain',
        ];

        return [
            'score' => $contentScore,
            'predicted_folder' => $predictedFolder,
            'issues' => $issues,
            'recommendations' => $recommendations,
            'score_breakdown' => [
                'content' => [
                    'score' => $contentAnalysis->contentScore,
                    'max' => 40,
                    'weight' => 0.7,
                ],
                'domain' => [
                    'score' => 0,
                    'max' => 30,
                    'weight' => 0.3,
                    'note' => 'no_domain_configured',
                ],
            ],
        ];
    }
}
