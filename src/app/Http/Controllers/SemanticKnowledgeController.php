<?php

namespace App\Http\Controllers;

use App\Jobs\IndexKnowledgeBase;
use App\Licensing\Feature;
use App\Models\AiBrainSettings;
use App\Models\AiIntegration;
use App\Models\KnowledgeEntry;
use App\Services\Brain\Knowledge\KnowledgeEmbedder;
use App\Services\Brain\Knowledge\KnowledgeIndexer;
use App\Services\LicenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Settings and state of the semantic knowledge base (GOLD) on the Brain settings page.
 * Reading the state is open on SILVER; choosing the model and re-indexing need GOLD
 * (`licensed:semantic_knowledge` on the routes).
 */
class SemanticKnowledgeController extends Controller
{
    public function __construct(
        protected KnowledgeEmbedder $embedder,
        protected KnowledgeIndexer $indexer,
        protected LicenseService $license,
    ) {}

    /**
     * GET /brain/api/knowledge/semantic
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json($this->state($request->user()->id));
    }

    /**
     * Choose the integration and model that embed the knowledge base. The model is tried with
     * one short text first, so a wrong name or key is refused here and not in the background.
     * PUT /brain/api/knowledge/semantic
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'embedding_integration_id' => 'nullable|integer',
            'embedding_model' => 'nullable|string|max:191',
        ]);

        $userId = $request->user()->id;
        $integrationId = $validated['embedding_integration_id'] ?? null;
        // Without a chosen integration the first one that can embed works with its default model
        $model = $integrationId ? (trim((string) ($validated['embedding_model'] ?? '')) ?: null) : null;

        $integration = $integrationId
            ? $this->embedder->candidates()->firstWhere('id', $integrationId)
            : $this->embedder->candidates()->first();
        if (!$integration) {
            return response()->json([
                'message' => __('brain.knowledge_semantic.no_provider'),
            ], 422);
        }

        $provider = $this->embedder->provider($integration);
        try {
            $vector = $provider?->embed(['NetSendo'], $model ?: $provider->embeddingModels()[0], [
                'purpose' => 'query',
                'dimensions' => KnowledgeEmbedder::DIMENSIONS,
                'timeout' => 20,
            ])[0] ?? [];
            if ($vector === []) {
                throw new \RuntimeException('The provider returned an empty vector.');
            }
        } catch (\Throwable $e) {
            return response()->json([
                'message' => __('brain.knowledge_semantic.test_failed', ['error' => mb_substr($e->getMessage(), 0, 300)]),
            ], 422);
        }

        $before = $this->embedder->resolve($userId)?->key();

        AiBrainSettings::getForUser($userId)->update([
            'embedding_integration_id' => $integrationId,
            'embedding_model' => $model,
        ]);

        // A new model means new vectors; the old ones are replaced entry by entry
        $target = $this->embedder->resolve($userId);
        if ($target && ($before !== $target->key() || $this->indexer->outdatedEntries($userId, $target)->isNotEmpty())) {
            IndexKnowledgeBase::dispatch($userId);
        }

        return response()->json($this->state($userId));
    }

    /**
     * Embed every entry that is missing or outdated, in the background.
     * POST /brain/api/knowledge/semantic/reindex
     */
    public function reindex(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        if (!$this->embedder->resolve($userId)) {
            return response()->json([
                'message' => __('brain.knowledge_semantic.no_provider'),
            ], 422);
        }

        IndexKnowledgeBase::dispatch($userId);

        return response()->json($this->state($userId) + ['queued' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function state(int $userId): array
    {
        $settings = AiBrainSettings::where('user_id', $userId)->first();
        $target = $this->embedder->resolve($userId);

        return [
            'licensed' => $this->license->allows(Feature::SemanticKnowledge),
            'integration_id' => $settings?->embedding_integration_id,
            'model' => $settings?->embedding_model,
            'active' => $target ? [
                'integration_id' => $target->integration->id,
                'name' => $target->integration->name ?: $target->integration->provider,
                'provider' => $target->integration->provider,
                'model' => $target->model,
            ] : null,
            'integrations' => $this->embedder->candidates()->map(fn (AiIntegration $integration) => [
                'id' => $integration->id,
                'name' => $integration->name ?: $integration->provider,
                'provider' => $integration->provider,
                'models' => $this->embedder->provider($integration)?->embeddingModels() ?? [],
            ])->values(),
            'status' => $this->indexer->status($userId, $target),
            'content_limit' => KnowledgeEntry::contentLimit(),
        ];
    }
}
