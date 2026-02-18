<?php

namespace App\Services\Brain;

use App\Models\KnowledgeEntry;
use App\Models\User;
use App\Services\AI\AiService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class KnowledgeBaseService
{
    public function __construct(
        protected AiService $aiService,
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
        return KnowledgeEntry::create([
            'user_id' => $user->id,
            'category' => $category,
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
     */
    public function search(User $user, string $query, ?string $category = null, int $limit = 10): Collection
    {
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
     */
    public function getContext(User $user, string $taskType, int $maxEntries = 5): string
    {
        $categoryMap = [
            'campaign' => ['company', 'products', 'brand_voice', 'audience', 'best_practices', 'goals'],
            'message' => ['brand_voice', 'products', 'company', 'templates', 'audience'],
            'list' => ['audience', 'products', 'goals'],
            'analysis' => ['insights', 'best_practices', 'goals', 'competitors'],
            'general' => ['company', 'products', 'brand_voice', 'goals'],
        ];

        $categories = $categoryMap[$taskType] ?? $categoryMap['general'];
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

        // Format for AI context
        $context = "--- BAZA WIEDZY ---\n";
        foreach ($entries as $entry) {
            $verified = $entry->is_verified ? '✓' : '~';
            $context .= "[{$verified}] [{$entry->category}] {$entry->title}: {$entry->content}\n";
        }

        return $context;
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
Analizujesz konwersację między użytkownikiem a asystentem AI w kontekście email marketingu.
Wyodrębnij z tej konwersacji NOWE informacje, które warto zapamiętać w bazie wiedzy.

KATEGORIE:
- company: informacje o firmie użytkownika
- products: produkty, usługi, oferta
- brand_voice: ton komunikacji, styl pisania, preferowane zwroty
- audience: informacje o grupie docelowej
- best_practices: wnioski, co działa, co nie
- insights: dane / spostrzeżenia z kampanii
- goals: cele biznesowe, KPI

KONWERSACJA:
{$conversationContent}

Odpowiedz w JSON jako tablica obiektów:
[
  {"category": "...", "title": "krótki tytuł", "content": "treść informacji", "tags": ["tag1"]},
  ...
]

Jeśli nie ma wartościowych informacji do wyodrębnienia, odpowiedz: []
WAŻNE: Wyodrębniaj TYLKO konkretne, użyteczne informacje. Nie powtarzaj ogólnych rzeczy.
PROMPT;

        try {
            $response = $this->aiService->generateContent(
                AiService::prependDateContext($prompt),
                $integration,
                ['max_tokens' => 2000, 'temperature' => 0.3]
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
