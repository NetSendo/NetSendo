<?php

namespace App\Services\Brain;

use App\Models\AiBrainSettings;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WebResearchService — Internet research capabilities for the Brain.
 *
 * Provides:
 *  - Perplexity AI deep research (with citations)
 *  - SerpAPI Google Search (quick web results)
 *  - Combined research for company intelligence, trends, and content
 */
class WebResearchService
{
    private const PERPLEXITY_API_URL = 'https://api.perplexity.ai/chat/completions';
    private const SERPAPI_URL = 'https://serpapi.com/search.json';

    /**
     * Search the web using SerpAPI (Google Search).
     *
     * @return array{results: array, knowledge_graph: ?array, related_questions: ?array}
     */
    public function searchWeb(string $query, User $user, string $type = 'general', int $limit = 5): array
    {
        $settings = AiBrainSettings::getForUser($user->id);

        if (!$settings->serpapi_api_key) {
            return ['results' => [], 'error' => 'SerpAPI key not configured'];
        }

        try {
            $params = [
                'q' => $query,
                'api_key' => $settings->serpapi_api_key,
                'num' => $limit,
                'hl' => $this->resolveSearchLanguage($settings, $user),
            ];

            // Add type-specific params
            match ($type) {
                'news' => $params['tbm'] = 'nws',
                'images' => $params['tbm'] = 'isch',
                default => null,
            };

            $response = Http::timeout(15)->get(self::SERPAPI_URL, $params);

            if (!$response->successful()) {
                Log::warning('SerpAPI request failed', [
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 500),
                ]);
                return ['results' => [], 'error' => 'SerpAPI request failed: ' . $response->status()];
            }

            $data = $response->json();

            return [
                'results' => $this->formatSerpResults($data, $limit),
                'knowledge_graph' => $data['knowledge_graph'] ?? null,
                'related_questions' => $data['related_questions'] ?? null,
                'search_metadata' => [
                    'total_results' => $data['search_information']['total_results'] ?? null,
                    'time_taken_displayed' => $data['search_information']['time_taken_displayed'] ?? null,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('SerpAPI search failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);
            return ['results' => [], 'error' => $e->getMessage()];
        }
    }

    /**
     * Deep research using Perplexity AI (with internet access and citations).
     *
     * @return array{answer: string, citations: array, model: string}
     */
    public function deepResearch(string $query, User $user, string $context = ''): array
    {
        $settings = AiBrainSettings::getForUser($user->id);

        if (!$settings->perplexity_api_key) {
            return ['answer' => '', 'citations' => [], 'error' => 'Perplexity API key not configured'];
        }

        $langCode = $settings->resolveLanguage($user);
        $languageName = AiBrainSettings::getLanguageName($langCode);

        $systemPrompt = "You are a research assistant for a marketing & CRM platform called NetSendo. "
            . "Provide accurate, up-to-date information with specific data points, statistics, and actionable insights. "
            . "Focus on email marketing, SMS marketing, CRM, sales, and marketing automation topics when relevant. "
            . "Always cite your sources. Respond in {$languageName}.";

        $userMessage = $query;
        if ($context) {
            $userMessage = "Context: {$context}\n\nResearch query: {$query}";
        }

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $settings->perplexity_api_key,
                    'Content-Type' => 'application/json',
                ])
                ->post(self::PERPLEXITY_API_URL, [
                    'model' => 'sonar',
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userMessage],
                    ],
                    'max_tokens' => 4000,
                    'temperature' => 0.2,
                ]);

            if (!$response->successful()) {
                Log::warning('Perplexity API request failed', [
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 500),
                ]);
                return ['answer' => '', 'citations' => [], 'error' => 'Perplexity request failed: ' . $response->status()];
            }

            $data = $response->json();
            $answer = $data['choices'][0]['message']['content'] ?? '';
            $citations = $data['citations'] ?? [];

            return [
                'answer' => $answer,
                'citations' => $citations,
                'model' => $data['model'] ?? 'sonar',
                'usage' => [
                    'prompt_tokens' => $data['usage']['prompt_tokens'] ?? 0,
                    'completion_tokens' => $data['usage']['completion_tokens'] ?? 0,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Perplexity research failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);
            return ['answer' => '', 'citations' => [], 'error' => $e->getMessage()];
        }
    }

    /**
     * Research a company — combines SerpAPI + Perplexity for intelligence.
     */
    public function researchCompany(string $companyName, User $user): array
    {
        $results = ['company' => $companyName, 'sources' => []];

        // Step 1: Quick Google search
        $serpResults = $this->searchWeb("{$companyName} company", $user, 'general', 5);
        $results['web_results'] = $serpResults['results'];
        $results['knowledge_graph'] = $serpResults['knowledge_graph'] ?? null;

        // Step 2: Deep research with Perplexity
        $research = $this->deepResearch(
            "Research the company '{$companyName}'. Provide: industry, size, key products/services, website, recent news, and competitive positioning.",
            $user
        );
        $results['analysis'] = $research['answer'];
        $results['sources'] = $research['citations'];

        return $results;
    }

    /**
     * Research market/industry trends.
     */
    public function researchTrends(string $topic, User $user): array
    {
        $results = ['topic' => $topic];

        // Google search for latest trends
        $serpResults = $this->searchWeb("{$topic} trends 2026", $user, 'news', 5);
        $results['news'] = $serpResults['results'];

        // Deep analysis with Perplexity
        $research = $this->deepResearch(
            "What are the latest trends in '{$topic}'? Include statistics, key developments, predictions, and actionable recommendations for a marketing professional.",
            $user
        );
        $results['analysis'] = $research['answer'];
        $results['sources'] = $research['citations'];

        return $results;
    }

    /**
     * Research content ideas for a specific topic/product.
     */
    public function researchContentIdeas(string $topic, User $user, string $contentType = 'email'): array
    {
        $research = $this->deepResearch(
            "Generate creative {$contentType} marketing content ideas for '{$topic}'. "
            . "Include subject lines, key messaging angles, best practices, and examples of successful campaigns in this space.",
            $user
        );

        return [
            'ideas' => $research['answer'],
            'sources' => $research['citations'],
        ];
    }

    /**
     * Enrich company data for CRM — returns structured data.
     */
    public function enrichCompanyData(string $companyName, User $user, ?string $website = null): array
    {
        $query = "Find the following information about the company '{$companyName}'";
        if ($website) {
            $query .= " (website: {$website})";
        }
        $query .= ": website URL, industry, company size (employees), headquarters location, founding year, and a brief description. "
            . "Respond in JSON format: {\"website\": \"\", \"industry\": \"\", \"size\": \"\", \"location\": \"\", \"founded\": \"\", \"description\": \"\"}";

        $research = $this->deepResearch($query, $user);

        // Try to parse structured data from the response
        $structured = $this->parseJsonFromResearch($research['answer']);

        return [
            'structured' => $structured,
            'raw_analysis' => $research['answer'],
            'sources' => $research['citations'],
        ];
    }

    /**
     * Test Perplexity API connection.
     */
    public function testPerplexity(string $apiKey): array
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post(self::PERPLEXITY_API_URL, [
                    'model' => 'sonar',
                    'messages' => [
                        ['role' => 'user', 'content' => 'Hello, respond with one word: OK'],
                    ],
                    'max_tokens' => 10,
                ]);

            if ($response->successful()) {
                return ['success' => true, 'message' => 'Perplexity API connected successfully.'];
            }

            $error = $response->json('error.message') ?? $response->body();
            return ['success' => false, 'message' => 'Perplexity API error: ' . mb_substr($error, 0, 200)];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Connection failed: ' . $e->getMessage()];
        }
    }

    /**
     * Test SerpAPI connection.
     */
    public function testSerpApi(string $apiKey): array
    {
        try {
            $response = Http::timeout(10)->get(self::SERPAPI_URL, [
                'q' => 'test',
                'api_key' => $apiKey,
                'num' => 1,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['search_metadata'])) {
                    return ['success' => true, 'message' => 'SerpAPI connected successfully.'];
                }
            }

            $error = $response->json('error') ?? $response->body();
            return ['success' => false, 'message' => 'SerpAPI error: ' . mb_substr((string) $error, 0, 200)];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Connection failed: ' . $e->getMessage()];
        }
    }

    /**
     * Check if research is available for a user.
     */
    public function isAvailable(User $user): array
    {
        $settings = AiBrainSettings::getForUser($user->id);

        return [
            'perplexity' => !empty($settings->perplexity_api_key),
            'serpapi' => !empty($settings->serpapi_api_key),
            'any' => !empty($settings->perplexity_api_key) || !empty($settings->serpapi_api_key),
        ];
    }

    /**
     * Format a research result as context for AI prompts.
     */
    public function formatAsContext(array $researchResult): string
    {
        $context = "--- WEB RESEARCH RESULTS ---\n";

        if (!empty($researchResult['analysis'])) {
            $context .= "ANALYSIS:\n{$researchResult['analysis']}\n\n";
        }

        if (!empty($researchResult['web_results'])) {
            $context .= "WEB SOURCES:\n";
            foreach ($researchResult['web_results'] as $result) {
                $context .= "• [{$result['title']}]({$result['link']})\n";
                if (!empty($result['snippet'])) {
                    $context .= "  {$result['snippet']}\n";
                }
            }
            $context .= "\n";
        }

        if (!empty($researchResult['sources'])) {
            $context .= "CITATIONS:\n";
            foreach ($researchResult['sources'] as $i => $url) {
                $context .= "[" . ($i + 1) . "] {$url}\n";
            }
        }

        return $context;
    }

    // === Private helpers ===

    private function formatSerpResults(array $data, int $limit): array
    {
        $results = [];

        $organicResults = $data['organic_results'] ?? [];
        foreach (array_slice($organicResults, 0, $limit) as $item) {
            $results[] = [
                'title' => $item['title'] ?? '',
                'link' => $item['link'] ?? '',
                'snippet' => $item['snippet'] ?? '',
                'position' => $item['position'] ?? null,
                'displayed_link' => $item['displayed_link'] ?? '',
            ];
        }

        return $results;
    }

    private function resolveSearchLanguage(AiBrainSettings $settings, User $user): string
    {
        $langCode = $settings->resolveLanguage($user);

        // Map to SerpAPI language codes
        return match ($langCode) {
            'pl' => 'pl',
            'de' => 'de',
            'es' => 'es',
            'fr' => 'fr',
            'it' => 'it',
            'pt' => 'pt',
            default => 'en',
        };
    }

    private function parseJsonFromResearch(string $text): ?array
    {
        // Try to extract JSON from the response
        if (preg_match('/```(?:json)?\s*\n?(.*?)\n?```/s', $text, $matches)) {
            $text = $matches[1];
        }

        // Try to find JSON object in the text
        if (preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s', $text, $matches)) {
            $data = json_decode($matches[0], true);
            if (is_array($data)) {
                return $data;
            }
        }

        $data = json_decode(trim($text), true);
        return is_array($data) ? $data : null;
    }
}
