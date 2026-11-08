<?php

namespace App\Services\Brain\Knowledge;

use App\Models\KnowledgeChunk;
use App\Models\KnowledgeEntry;
use Illuminate\Support\Facades\DB;

/**
 * Keeps the chunks and vectors of knowledge entries in step with their text and the chosen model.
 * Callers check the GOLD licence (Feature::SemanticKnowledge) first.
 */
class KnowledgeIndexer
{
    public function __construct(
        protected KnowledgeChunker $chunker,
        protected KnowledgeEmbedder $embedder,
    ) {}

    public function isCurrent(KnowledgeEntry $entry, EmbeddingTarget $target): bool
    {
        return KnowledgeChunk::where('knowledge_entry_id', $entry->id)
            ->where('embedding_model', $target->key())
            ->where('content_hash', $entry->contentHash())
            ->exists();
    }

    /**
     * Embed one entry again unless its chunks already match its text and the model.
     *
     * @return int the number of chunks embedded (0 when it was up to date)
     *
     * @throws \RuntimeException when the provider fails; the old chunks are kept then
     */
    public function indexEntry(KnowledgeEntry $entry, EmbeddingTarget $target): int
    {
        if ($this->isCurrent($entry, $target)) {
            return 0;
        }

        $chunks = $this->chunker->split($entry->content ?? '');
        if ($chunks === []) {
            $entry->chunks()->delete();

            return 0;
        }

        // The title travels with every chunk, so a chunk from the middle is still found by topic
        $texts = array_map(fn ($chunk) => $entry->title . "\n\n" . $chunk, $chunks);
        $vectors = $this->embedder->embedDocuments($target, $texts);

        $hash = $entry->contentHash();
        $now = now();
        $rows = [];
        foreach ($chunks as $index => $chunk) {
            $vector = $vectors[$index] ?? [];
            $rows[] = [
                'knowledge_entry_id' => $entry->id,
                'user_id' => $entry->user_id,
                'chunk_index' => $index,
                'content' => $chunk,
                'embedding' => KnowledgeEmbedder::pack($vector),
                'embedding_model' => $target->key(),
                'dimensions' => count($vector),
                'content_hash' => $hash,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::transaction(function () use ($entry, $rows) {
            $entry->chunks()->delete();
            foreach (array_chunk($rows, 50) as $batch) {
                KnowledgeChunk::insert($batch);
            }
        });

        return count($rows);
    }

    /**
     * Embed the user's entries that are missing or outdated, oldest first, until the time is up.
     *
     * @return array{indexed: int, failed: int, remaining: int, error: ?string}
     */
    public function indexUser(int $userId, EmbeddingTarget $target, int $maxSeconds = 300): array
    {
        $started = microtime(true);
        $indexed = 0;
        $failed = 0;
        $error = null;

        foreach ($this->outdatedEntries($userId, $target) as $entry) {
            if (microtime(true) - $started > $maxSeconds) {
                break;
            }

            try {
                $this->indexEntry($entry, $target);
                $indexed++;
            } catch (\RuntimeException $e) {
                $failed++;
                $error = $e;
                // A provider that refuses one entry will refuse the next ones too
                if ($failed >= 3) {
                    break;
                }
            }
        }

        return [
            'indexed' => $indexed,
            'failed' => $failed,
            'remaining' => $this->outdatedEntries($userId, $target)->count(),
            'error' => $error?->getMessage(),
        ];
    }

    /**
     * Entries without chunks for this model, or whose text changed since.
     *
     * @return \Illuminate\Support\LazyCollection<int, KnowledgeEntry>
     */
    public function outdatedEntries(int $userId, EmbeddingTarget $target)
    {
        $current = $this->currentHashes($userId, $target);

        return KnowledgeEntry::where('user_id', $userId)
            ->orderBy('id')
            ->lazyById(100)
            ->filter(fn (KnowledgeEntry $entry) => ($current[$entry->id] ?? null) !== $entry->contentHash());
    }

    /**
     * How far the user's knowledge base is embedded with this model.
     *
     * @return array{entries: int, indexed: int, chunks: int}
     */
    public function status(int $userId, ?EmbeddingTarget $target): array
    {
        $entries = KnowledgeEntry::where('user_id', $userId)->count();

        if (!$target) {
            return ['entries' => $entries, 'indexed' => 0, 'chunks' => 0];
        }

        $current = $this->currentHashes($userId, $target);
        $indexed = KnowledgeEntry::where('user_id', $userId)
            ->whereIn('id', array_keys($current))
            ->get(['id', 'title', 'content'])
            ->filter(fn (KnowledgeEntry $entry) => $current[$entry->id] === $entry->contentHash())
            ->count();

        return [
            'entries' => $entries,
            'indexed' => $indexed,
            'chunks' => KnowledgeChunk::where('user_id', $userId)->where('embedding_model', $target->key())->count(),
        ];
    }

    /** @return array<int, string> entry id => content hash of its chunks for this model */
    private function currentHashes(int $userId, EmbeddingTarget $target): array
    {
        return KnowledgeChunk::where('user_id', $userId)
            ->where('embedding_model', $target->key())
            ->distinct()
            ->pluck('content_hash', 'knowledge_entry_id')
            ->all();
    }
}
