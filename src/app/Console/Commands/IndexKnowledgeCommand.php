<?php

namespace App\Console\Commands;

use App\Jobs\IndexKnowledgeBase;
use App\Licensing\Feature;
use App\Models\KnowledgeEntry;
use App\Services\Brain\Knowledge\KnowledgeEmbedder;
use App\Services\Brain\Knowledge\KnowledgeIndexer;
use App\Services\LicenseService;
use Illuminate\Console\Command;

/**
 * Embeds knowledge entries that are missing or outdated (semantic knowledge base, GOLD):
 * entries saved while the provider was down, before GOLD or before an embedding provider was
 * added. Scheduled hourly; queues a job per user unless --sync is given.
 */
class IndexKnowledgeCommand extends Command
{
    protected $signature = 'brain:index-knowledge
        {--user= : Only this user ID}
        {--sync : Embed now in this process instead of queueing}';

    protected $description = 'Embed Brain knowledge entries for semantic search (GOLD)';

    public function handle(LicenseService $license, KnowledgeEmbedder $embedder, KnowledgeIndexer $indexer): int
    {
        if (!$license->allows(Feature::SemanticKnowledge)) {
            $this->warn('The semantic knowledge base needs the GOLD licence.');

            return self::SUCCESS;
        }

        $userIds = KnowledgeEntry::query()
            ->when($this->option('user'), fn ($query, $userId) => $query->where('user_id', (int) $userId))
            ->distinct()
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            $target = $embedder->resolve($userId);
            if (!$target) {
                $this->line("User {$userId}: no AI integration that can embed (OpenAI, Gemini, OpenRouter, Ollama).");
                continue;
            }

            if ($this->option('sync')) {
                $result = $indexer->indexUser($userId, $target, PHP_INT_MAX);
                $this->line("User {$userId} ({$target->key()}): {$result['indexed']} embedded, {$result['failed']} failed, {$result['remaining']} left."
                    . ($result['error'] ? " Last error: {$result['error']}" : ''));
                continue;
            }

            if ($indexer->outdatedEntries($userId, $target)->isNotEmpty()) {
                IndexKnowledgeBase::dispatch($userId);
                $this->line("User {$userId} ({$target->key()}): queued.");
            }
        }

        return self::SUCCESS;
    }
}
