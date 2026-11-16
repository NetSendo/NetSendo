<?php

namespace App\Jobs;

use App\Licensing\Feature;
use App\Models\KnowledgeEntry;
use App\Services\Brain\Knowledge\KnowledgeEmbedder;
use App\Services\Brain\Knowledge\KnowledgeIndexer;
use App\Services\LicenseService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Embeds one knowledge entry after it was added or changed (semantic knowledge base, GOLD).
 * What fails here is picked up by the hourly brain:index-knowledge sweep.
 */
class IndexKnowledgeEntry implements ShouldQueue
{
    use Queueable;

    public $tries = 3;

    /** Below the queue's retry_after (90 s by default), so a slow run is not started twice. */
    public $timeout = 85;

    public function __construct(
        public int $entryId,
    ) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(LicenseService $license, KnowledgeEmbedder $embedder, KnowledgeIndexer $indexer): void
    {
        if (!$license->allows(Feature::SemanticKnowledge)) {
            return;
        }

        $entry = KnowledgeEntry::find($this->entryId);
        if (!$entry) {
            return;
        }

        $target = $embedder->resolve($entry->user_id);
        if (!$target) {
            return;
        }

        try {
            $indexer->indexEntry($entry, $target);
        } catch (\RuntimeException $e) {
            Log::warning('Knowledge entry could not be embedded', [
                'entry_id' => $entry->id,
                'model' => $target->key(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
