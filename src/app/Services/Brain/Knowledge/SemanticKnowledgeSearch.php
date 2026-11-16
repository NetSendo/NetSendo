<?php

namespace App\Services\Brain\Knowledge;

use App\Licensing\Feature;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeEntry;
use App\Services\LicenseService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Finds the knowledge chunks that match a question (semantic knowledge base, GOLD).
 *
 * MySQL Community cannot compare vectors, so the user's vectors are read in batches and
 * compared in PHP (a dot product of unit vectors is their cosine similarity): about 60 ms
 * for 5,000 chunks. Words that MySQL's full-text index finds in an entry, the task's
 * categories and verified entries add a small bonus, so exact names and numbers still win.
 */
class SemanticKnowledgeSearch
{
    private const CATEGORY_BONUS = 0.02;

    private const VERIFIED_BONUS = 0.01;

    private const KEYWORD_BONUS = 0.04;

    /** Chunks scoring below this share of the best chunk are left out. */
    private const RELATIVE_FLOOR = 0.6;

    public function __construct(
        protected KnowledgeEmbedder $embedder,
        protected LicenseService $license,
    ) {}

    /**
     * Chunks matching the question, best first, at most $perEntry from one entry.
     * Null when semantic search cannot run (no GOLD, no embedding provider, nothing embedded
     * yet, the provider failed): the caller then reads the knowledge base the SILVER way.
     *
     * @param  list<string>  $categories  categories of the task at hand (a small bonus)
     * @return Collection<int, array{entry: KnowledgeEntry, chunk_index: int, chunks_total: int, content: string, score: float}>|null
     */
    public function search(
        int $userId,
        string $query,
        int $limit = 8,
        array $categories = [],
        int $perEntry = 2,
        ?string $category = null,
    ): ?Collection {
        $query = trim(mb_substr($query, 0, 2000));
        if ($query === '' || !$this->license->allows(Feature::SemanticKnowledge)) {
            return null;
        }

        try {
            $target = $this->embedder->resolve($userId);
            if (!$target || !KnowledgeChunk::where('user_id', $userId)->where('embedding_model', $target->key())->exists()) {
                return null;
            }

            $queryVector = $this->embedder->embedQuery($target, $query);
        } catch (\Throwable $e) {
            Log::warning('Semantic knowledge search unavailable, using categories', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $scores = $this->similarities($userId, $target, $queryVector, $category);
        if ($scores === []) {
            return collect();
        }

        // Bonuses only reorder close results: look at the best few dozen chunks
        arsort($scores);
        $candidates = array_slice($scores, 0, max(50, $limit * 6), true);
        $chunkRows = KnowledgeChunk::whereIn('id', array_keys($candidates))
            ->get(['id', 'knowledge_entry_id', 'chunk_index'])
            ->keyBy('id');
        $entries = KnowledgeEntry::whereIn('id', $chunkRows->pluck('knowledge_entry_id')->unique())
            ->get()
            ->keyBy('id');
        $keywordHits = $this->keywordHits($userId, $query);

        $ranked = [];
        foreach ($candidates as $chunkId => $similarity) {
            $row = $chunkRows[$chunkId] ?? null;
            $entry = $row ? ($entries[$row->knowledge_entry_id] ?? null) : null;
            if (!$entry) {
                continue;
            }

            $score = $similarity
                + (in_array($entry->category, $categories, true) ? self::CATEGORY_BONUS : 0)
                + ($entry->is_verified ? self::VERIFIED_BONUS : 0)
                + (isset($keywordHits[$entry->id]) ? self::KEYWORD_BONUS : 0);

            $ranked[] = ['chunk_id' => $chunkId, 'entry' => $entry, 'chunk_index' => $row->chunk_index, 'score' => $score];
        }
        usort($ranked, fn ($a, $b) => $b['score'] <=> $a['score']);

        // Far weaker matches than the best one are noise, not context
        $floor = ($ranked[0]['score'] ?? 0) * self::RELATIVE_FLOOR;

        $picked = [];
        $perEntryCount = [];
        foreach ($ranked as $hit) {
            if ($picked !== [] && $hit['score'] < $floor) {
                break;
            }
            $entryId = $hit['entry']->id;
            if (($perEntryCount[$entryId] ?? 0) >= $perEntry) {
                continue;
            }
            $perEntryCount[$entryId] = ($perEntryCount[$entryId] ?? 0) + 1;
            $picked[] = $hit;
            if (count($picked) >= $limit) {
                break;
            }
        }

        $contents = KnowledgeChunk::whereIn('id', array_column($picked, 'chunk_id'))->pluck('content', 'id');
        $totals = KnowledgeChunk::whereIn('knowledge_entry_id', array_keys($perEntryCount))
            ->where('embedding_model', $target->key())
            ->selectRaw('knowledge_entry_id, COUNT(*) as total')
            ->groupBy('knowledge_entry_id')
            ->pluck('total', 'knowledge_entry_id');

        return collect($picked)->map(fn ($hit) => [
            'entry' => $hit['entry'],
            'chunk_index' => (int) $hit['chunk_index'],
            'chunks_total' => (int) ($totals[$hit['entry']->id] ?? 1),
            'content' => (string) ($contents[$hit['chunk_id']] ?? ''),
            'score' => round($hit['score'], 4),
        ]);
    }

    /**
     * Cosine similarity of every active chunk of the user to the question.
     *
     * @param  list<float>  $queryVector
     * @return array<int, float> chunk id => similarity
     */
    private function similarities(int $userId, EmbeddingTarget $target, array $queryVector, ?string $category): array
    {
        $dimensions = count($queryVector);
        $scores = [];

        DB::table('knowledge_chunks')
            ->join('knowledge_entries', 'knowledge_entries.id', '=', 'knowledge_chunks.knowledge_entry_id')
            ->where('knowledge_chunks.user_id', $userId)
            ->where('knowledge_chunks.embedding_model', $target->key())
            ->where('knowledge_chunks.dimensions', $dimensions)
            ->where('knowledge_entries.is_active', true)
            ->when($category, fn ($query) => $query->where('knowledge_entries.category', $category))
            ->select('knowledge_chunks.id', 'knowledge_chunks.embedding')
            ->chunkById(500, function ($rows) use (&$scores, $queryVector, $dimensions) {
                foreach ($rows as $row) {
                    $vector = unpack('g*', $row->embedding);
                    $dot = 0.0;
                    for ($i = 0; $i < $dimensions; $i++) {
                        $dot += $queryVector[$i] * $vector[$i + 1];
                    }
                    $scores[$row->id] = $dot;
                }
            }, 'knowledge_chunks.id', 'id');

        return $scores;
    }

    /**
     * Entries MySQL's full-text index finds for the question (none on other databases).
     *
     * @return array<int, true>
     */
    private function keywordHits(int $userId, string $query): array
    {
        if (DB::getDriverName() !== 'mysql') {
            return [];
        }

        try {
            return KnowledgeEntry::where('user_id', $userId)
                ->active()
                ->whereRaw('MATCH(title, content) AGAINST(? IN NATURAL LANGUAGE MODE)', [$query])
                ->limit(10)
                ->pluck('id')
                ->mapWithKeys(fn ($id) => [$id => true])
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
