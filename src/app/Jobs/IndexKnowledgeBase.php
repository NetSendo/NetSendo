<?php

namespace App\Jobs;

use App\Licensing\Feature;
use App\Services\Brain\Knowledge\KnowledgeEmbedder;
use App\Services\Brain\Knowledge\KnowledgeIndexer;
use App\Services\LicenseService;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Embeds a user's whole knowledge base: after another model was chosen or on "Re-index".
 * Entries already embedded with the model are skipped, so running it twice costs nothing.
 *
 * One run stays under the queue's retry_after (90 s by default) and queues the next run while
 * entries are left and the last one made progress.
 */
class IndexKnowledgeBase implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Queueable;

    public $tries = 1;

    public $timeout = 85;

    private const SECONDS_PER_RUN = 60;

    public function __construct(
        public int $userId,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->userId;
    }

    public function handle(LicenseService $license, KnowledgeEmbedder $embedder, KnowledgeIndexer $indexer): void
    {
        if (!$license->allows(Feature::SemanticKnowledge)) {
            return;
        }

        $target = $embedder->resolve($this->userId);
        if (!$target) {
            return;
        }

        $result = $indexer->indexUser($this->userId, $target, self::SECONDS_PER_RUN);

        if ($result['failed'] > 0) {
            Log::warning('Knowledge base partly embedded', ['user_id' => $this->userId, 'model' => $target->key()] + $result);
        }

        if ($result['remaining'] > 0 && $result['indexed'] > 0) {
            self::dispatch($this->userId);
        }
    }
}
