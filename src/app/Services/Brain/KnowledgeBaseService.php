<?php

namespace App\Services\Brain;

use App\Models\KnowledgeEntry;
use App\Models\User;
use App\Services\AI\AiService;
use App\Services\Brain\Knowledge\SemanticKnowledgeSearch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class KnowledgeBaseService
{
    /** Categories Brain reads for each kind of task (the SILVER way; a small bonus in semantic search). */
    private const CATEGORY_MAP = [
        'campaign' => ['company', 'products', 'brand_voice', 'audience', 'best_practices', 'goals', 'style_preference', 'performance_pattern'],
        'message' => ['brand_voice', 'products', 'company', 'templates', 'audience', 'style_preference', 'performance_pattern'],
        'list' => ['audience', 'products', 'goals'],
        'analysis' => ['insights', 'best_practices', 'goals', 'competitors', 'performance_pattern'],
        'general' => ['company', 'products', 'brand_voice', 'goals'],
    ];

    /** Who the company is and how it speaks: kept in every semantic context of a task that uses them. */
    private const CORE_CATEGORIES = ['brand_voice', 'company', 'style_preference'];

    private const SEMANTIC_CHUNKS = 8;

    private const CORE_ENTRY_CHARS = 1500;

    /** Upper bound of the semantic knowledge context in characters (about 2,500 tokens). */
    private const SEMANTIC_BUDGET = 10000;

    public function __construct(
        protected AiService $aiService,
        protected SemanticKnowledgeSearch $semanticSearch,
    ) {}

    /**
     * Add a knowledge entry manually (from user).
     */
    public function addEntry(
        User $user,
        string $category,
        string $title,
        string $content,
        string $source = 'user',
        ?string $sourceReference = null,
        array $tags = [],
    ): KnowledgeEntry {
        // An unknown category (e.g. one web research made up) would hide the entry from Brain,
        // which reads the knowledge base by category: keep it as a tag instead
        $known = KnowledgeEntry::normalizeCategory($category);
        if ($known !== $category && $category !== '' && !in_array($category, $tags, true)) {
            $tags[] = $category;
        }

        return KnowledgeEntry::create([
            'user_id' => $user->id,
            'category' => $known,
            'title' => $title,
            'content' => $content,
            'source' => $source,
            'source_reference' => $sourceReference,
            'tags' => $tags,
            'confidence' => $source === 'user' ? 1.0 : 0.7,
            'is_verified' => $source === 'user',
        ]);
    }

    /**
     * Search knowledge base for a user.
     *
     * With the semantic knowledge base (GOLD) entries come by meaning, best first; otherwise,
     * or when it cannot run, by MySQL full-text search and then LIKE.
     */
    public function search(User $user, string $query, ?string $category = null, int $limit = 10): Collection
    {
        $hits = $this->semanticSearch->search($user->id, $query, $limit, [], 1, $category);
        if ($hits !== null && $hits->isNotEmpty()) {
            return $hits->pluck('entry')->values();
        }

        $q = KnowledgeEntry::forUser($user->id)->active();

        if ($category) {
            $q->forCategory($category);
        }

        // Try full-text search first
        try {
            $results = $q->search($query)->byRelevance()->limit($limit)->get();
            if ($results->isNotEmpty()) {
                return $results;
            }
        } catch (\Exception $e) {
            // Full-text search may fail, fall back to LIKE
        }

        // Fallback: LIKE search
        return KnowledgeEntry::forUser($user->id)
            ->active()
            ->when($category, fn($q) => $q->forCategory($category))
            ->where(function ($q) use ($query) {
                $q->where('title', 'LIKE', "%{$query}%")
                  ->orWhere('content', 'LIKE', "%{$query}%");
            })
            ->byRelevance()
            ->limit($limit)
            ->get();
    }

    /**
     * Get knowledge context for a specific task type.
     * Returns a formatted string ready to inject into AI prompts.
     *
     * Given the task's text ($query: the user's message, the topic of the e-mail, the goal),
     * the semantic knowledge base (GOLD) supplies the chunks that match it. Otherwise, or when
     * that cannot run, Brain reads the best entries of the task's categories (SILVER).
     */
    public function getContext(User $user, string $taskType, int $maxEntries = 5, ?string $query = null): string
    {
        $categories = self::CATEGORY_MAP[$taskType] ?? self::CATEGORY_MAP['general'];

        if (filled($query)) {
            $semantic = $this->semanticContext($user, $categories, $query);
            if ($semantic !== null) {
                return $semantic;
            }
        }

        $entries = collect();

        foreach ($categories as $category) {
            $found = KnowledgeEntry::forUser($user->id)
                ->active()
                ->forCategory($category)
                ->byRelevance()
                ->limit(3)
                ->get();

            $entries = $entries->merge($found);
        }

        // Limit total entries
        $entries = $entries->unique('id')->take($maxEntries);

        if ($entries->isEmpty()) {
            return '';
        }

        // Record usage
        $entries->each(fn($entry) => $entry->recordUsage());

        // Format for AI context. Whole entries go in, but no longer than SILVER allows: longer
        // ones were written for the semantic knowledge base, which sends only their matching chunks
        $context = "--- KNOWLEDGE BASE ---\n";
        foreach ($entries as $entry) {
            $verified = $entry->is_verified ? '✓' : '~';
            $content = $this->cut($entry->content, KnowledgeEntry::CONTENT_LIMIT);
            $context .= "[{$verified}] [{$entry->category}] {$entry->title}: {$content}\n";
        }

        return $context;
    }

    /**
     * The start of the task's core entries (brand voice, company) and the chunks that match
     * the query; null when semantic search cannot run, so the caller reads by category.
     *
     * @param  list<string>  $categories
     */
    protected function semanticContext(User $user, array $categories, string $query): ?string
    {
        $hits = $this->semanticSearch->search($user->id, $query, self::SEMANTIC_CHUNKS, $categories);
        if ($hits === null || $hits->isEmpty()) {
            return null;
        }

        $coreCategories = array_values(array_intersect(self::CORE_CATEGORIES, $categories));
        $core = $coreCategories === [] ? collect() : KnowledgeEntry::forUser($user->id)
            ->active()
            ->whereIn('category', $coreCategories)
            ->byRelevance()
            ->limit(2)
            ->get();

        $context = "--- KNOWLEDGE BASE ---\n";
        $used = collect();

        foreach ($core as $entry) {
            $verified = $entry->is_verified ? '✓' : '~';
            $context .= "[{$verified}] [{$entry->category}] {$entry->title}: {$this->cut($entry->content, self::CORE_ENTRY_CHARS)}\n";
            $used->put($entry->id, $entry);
        }

        foreach ($hits as $hit) {
            $entry = $hit['entry'];
            // The start of a core entry is already in
            if ($used->has($entry->id) && $hit['chunk_index'] === 0 && $core->contains('id', $entry->id)) {
                continue;
            }

            $verified = $entry->is_verified ? '✓' : '~';
            $part = $hit['chunks_total'] > 1 ? ' (' . ($hit['chunk_index'] + 1) . "/{$hit['chunks_total']})" : '';
            $line = "[{$verified}] [{$entry->category}] {$entry->title}{$part}: {$hit['content']}\n";

            if (mb_strlen($context) + mb_strlen($line) > self::SEMANTIC_BUDGET) {
                break;
            }

            $context .= $line;
            $used->put($entry->id, $entry);
        }

        $used->each(fn (KnowledgeEntry $entry) => $entry->recordUsage());

        return $context;
    }

    private function cut(?string $text, int $limit): string
    {
        $text = (string) $text;

        return mb_strlen($text) > $limit ? rtrim(mb_substr($text, 0, $limit)) . '…' : $text;
    }

    /**
     * Auto-enrich knowledge base from conversation content.
     * AI extracts useful information and creates knowledge entries.
     */
    public function autoEnrich(User $user, string $conversationContent, ?string $sourceReference = null): array
    {
        $integration = $this->aiService->getDefaultIntegration();
        if (!$integration) {
            return [];
        }

        $prompt = <<<PROMPT
You are analyzing a conversation between a user and an AI assistant in the context of email marketing.
Extract NEW information from this conversation that is worth remembering in the knowledge base.

CATEGORIES:
- company: information about the user's company
- products: products, services, offerings
- brand_voice: tone of communication, writing style, preferred phrases
- audience: information about the target audience
- best_practices: conclusions, what works, what doesn't
- insights: data / observations from campaigns
- goals: business goals, KPIs

CONVERSATION:
{$conversationContent}

Respond in JSON as an array of objects:
[
  {"category": "...", "title": "short title", "content": "information content", "tags": ["tag1"]},
  ...
]

If there is no valuable information to extract, respond: []
IMPORTANT: Extract ONLY specific, useful information. Do not repeat general knowledge.
PROMPT;

        try {
            $response = BrainAi::generate(
                $user,
                'kb_auto_enrich',
                AiService::prependDateContext($prompt),
                $integration,
                ['max_tokens' => 4000, 'temperature' => 0.3]
            );

            $entries = $this->parseEnrichmentResponse($response);
            $created = [];

            foreach ($entries as $entry) {
                $created[] = $this->addEntry(
                    user: $user,
                    category: $entry['category'],
                    title: $entry['title'],
                    content: $entry['content'],
                    source: 'ai_enrichment',
                    sourceReference: $sourceReference,
                    tags: $entry['tags'] ?? [],
                );
            }

            return $created;
        } catch (\Exception $e) {
            Log::warning('Knowledge auto-enrichment failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Extract style preferences from an executed action plan.
     * Called after successful plan execution to learn from user-approved content.
     */
    public function extractStylePreferences(User $user, \App\Models\AiActionPlan $plan): void
    {
        // Only extract from campaign/message plans
        $relevantAgents = ['campaign', 'message'];
        if (!in_array($plan->agent_type, $relevantAgents)) {
            return;
        }

        // Avoid duplicate extractions
        $existing = KnowledgeEntry::forUser($user->id)
            ->active()
            ->forCategory('style_preference')
            ->where('source_reference', 'plan:' . $plan->id)
            ->exists();

        if ($existing) {
            return;
        }

        // Gather plan details
        $steps = $plan->steps->map(fn($s) => [
            'action' => $s->action_type,
            'config' => $s->config,
            'result' => mb_substr(json_encode($s->result ?? [], JSON_UNESCAPED_UNICODE) ?: '', 0, 500),
        ])->toArray();

        $planSummary = json_encode($steps, JSON_UNESCAPED_UNICODE);

        $integration = $this->aiService->getDefaultIntegration();
        if (!$integration) {
            return;
        }

        try {
            $prompt = <<<PROMPT
Analyze this executed marketing plan and extract the user's content preferences and style patterns.

Plan type: {$plan->agent_type}
Title: {$plan->title}
Description: {$plan->description}
Steps: {$planSummary}

Extract specific, reusable observations about:
- Writing tone (formal/casual/humorous)
- Subject line style (short/long, emojis, questions)
- Content structure (paragraphs, bullets, CTA placement)
- Preferred topics or angles
- Any unique patterns

Respond as a single compact paragraph (max 200 words) summarizing the style preferences.
If there's nothing notable to extract, respond with: SKIP
PROMPT;

            $response = BrainAi::generate(
                $user,
                'style_extraction',
                AiService::prependDateContext($prompt),
                $integration,
                ['max_tokens' => 500, 'temperature' => 0.3]
            );

            $response = trim($response);
            if ($response === 'SKIP' || strlen($response) < 20) {
                return;
            }

            $this->addEntry(
                user: $user,
                category: 'style_preference',
                title: "Style from: {$plan->title}",
                content: $response,
                source: 'ai_enrichment',
                sourceReference: 'plan:' . $plan->id,
                tags: ['auto_extracted', $plan->agent_type],
            );
        } catch (\Exception $e) {
            Log::warning('Style preference extraction failed', [
                'plan_id' => $plan->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Extract performance patterns from a high-performing campaign snapshot.
     * Called from PerformanceTracker when a campaign performs above benchmark.
     */
    public function extractPerformancePatterns(
        User $user,
        \App\Models\AiPerformanceSnapshot $snapshot,
    ): void {
        // Only extract from above-benchmark campaigns
        $comparison = $snapshot->benchmark_comparison ?? [];
        $isAbove = ($comparison['open_rate'] ?? '') === 'above'
            || ($comparison['click_rate'] ?? '') === 'above';

        if (!$isAbove) {
            return;
        }

        // Avoid duplicate extractions
        $existing = KnowledgeEntry::forUser($user->id)
            ->active()
            ->forCategory('performance_pattern')
            ->where('source_reference', 'snapshot:' . $snapshot->id)
            ->exists();

        if ($existing) {
            return;
        }

        // Build a concise pattern summary without AI (rule-based, fast)
        $parts = [];
        $parts[] = "Campaign: {$snapshot->campaign_name}";
        $parts[] = "Sent: {$snapshot->sent_count}, OR: {$snapshot->open_rate}%, CTR: {$snapshot->click_rate}%";

        if (($comparison['open_rate'] ?? '') === 'above') {
            $parts[] = "Open rate ABOVE benchmark";
        }
        if (($comparison['click_rate'] ?? '') === 'above') {
            $parts[] = "Click rate ABOVE benchmark";
        }

        $lessons = $snapshot->lessons_learned;
        if (!empty($lessons['what_worked'])) {
            $worked = is_array($lessons['what_worked'])
                ? implode('; ', $lessons['what_worked'])
                : $lessons['what_worked'];
            $parts[] = "What worked: {$worked}";
        }

        $content = implode('. ', $parts);

        $this->addEntry(
            user: $user,
            category: 'performance_pattern',
            title: "Top campaign: {$snapshot->campaign_name}",
            content: $content,
            source: 'performance_tracker',
            sourceReference: 'snapshot:' . $snapshot->id,
            tags: ['auto_extracted', 'top_performer'],
        );
    }

    /**
     * Get summary of knowledge base for a user (dashboard widget).
     */
    public function getSummary(User $user): array
    {
        $entries = KnowledgeEntry::forUser($user->id)->active();

        return [
            'total_entries' => (clone $entries)->count(),
            'verified_entries' => (clone $entries)->verified()->count(),
            'by_category' => (clone $entries)
                ->selectRaw('category, COUNT(*) as count')
                ->groupBy('category')
                ->pluck('count', 'category')
                ->toArray(),
            'recent' => (clone $entries)
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(['id', 'title', 'category', 'source', 'created_at']),
            'most_used' => (clone $entries)
                ->where('usage_count', '>', 0)
                ->orderByDesc('usage_count')
                ->limit(5)
                ->get(['id', 'title', 'category', 'usage_count']),
        ];
    }

    /**
     * Get all entries for a user grouped by category.
     */
    public function getAllGrouped(User $user): Collection
    {
        return KnowledgeEntry::forUser($user->id)
            ->active()
            ->byRelevance()
            ->get()
            ->groupBy('category');
    }

    /**
     * Parse the AI enrichment response.
     */
    private function parseEnrichmentResponse(string $response): array
    {
        // Try to extract JSON from the response
        $response = trim($response);

        // Remove markdown code blocks if present
        if (preg_match('/```(?:json)?\s*\n?(.*?)\n?```/s', $response, $matches)) {
            $response = $matches[1];
        }

        $data = json_decode($response, true);

        if (!is_array($data)) {
            return [];
        }

        // Validate entries
        $validCategories = array_keys(KnowledgeEntry::CATEGORIES);
        return array_filter($data, function ($entry) use ($validCategories) {
            return isset($entry['category'], $entry['title'], $entry['content'])
                && in_array($entry['category'], $validCategories)
                && strlen($entry['content']) > 10;
        });
    }
}
