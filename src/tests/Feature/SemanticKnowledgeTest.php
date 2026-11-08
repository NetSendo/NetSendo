<?php

namespace Tests\Feature;

use App\Jobs\IndexKnowledgeBase;
use App\Models\AiBrainSettings;
use App\Models\AiIntegration;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeEntry;
use App\Models\User;
use App\Services\Brain\Knowledge\KnowledgeEmbedder;
use App\Services\Brain\KnowledgeBaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Concerns\ActivatesGold;
use Tests\TestCase;

/**
 * The semantic knowledge base (GOLD): entries are split into chunks and embedded, and Brain
 * gets the chunks that match the task. SILVER reads the knowledge base by category, and
 * anything that stops semantic search falls back to that.
 */
class SemanticKnowledgeTest extends TestCase
{
    use RefreshDatabase;
    use ActivatesGold;

    private User $user;

    private AiIntegration $openai;

    /** What the fake OpenAI embeddings endpoint answers with. */
    private int $embeddingStatus = 200;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->openai = AiIntegration::create([
            'provider' => 'openai',
            'name' => 'OpenAI',
            'api_key' => 'sk-test',
            'is_active' => true,
        ]);

        Http::fake([
            'api.openai.com/v1/embeddings' => function (Request $request) {
                if ($this->embeddingStatus !== 200) {
                    return Http::response(['error' => ['message' => 'The model does not exist']], $this->embeddingStatus);
                }

                $data = [];
                foreach ($request['input'] as $index => $text) {
                    $data[] = ['index' => $index, 'embedding' => self::vectorFor($text)];
                }

                return Http::response(['data' => $data]);
            },
        ]);
    }

    /**
     * A tiny embedding model: one axis per topic, so the test knows what should match.
     */
    private static function vectorFor(string $text): array
    {
        $text = mb_strtolower($text);

        return [
            preg_match('/price|cost|297/', $text) ? 1.0 : 0.0,
            preg_match('/shipping|delivery|courier/', $text) ? 1.0 : 0.0,
            preg_match('/voice|tone/', $text) ? 1.0 : 0.0,
            0.05,
        ];
    }

    private function kb(): KnowledgeBaseService
    {
        return app(KnowledgeBaseService::class);
    }

    private function seedEntries(): array
    {
        return [
            'price' => $this->kb()->addEntry($this->user, 'products', 'Course price', 'The course costs 297 EUR and includes lifetime access.'),
            'shipping' => $this->kb()->addEntry($this->user, 'products', 'Shipping', 'Physical books ship by courier within two days.'),
            'voice' => $this->kb()->addEntry($this->user, 'brand_voice', 'Voice', 'We write in a warm, direct tone and use first names.'),
        ];
    }

    public function test_silver_does_not_embed_and_reads_the_knowledge_base_by_category(): void
    {
        $this->seedEntries();

        $context = $this->kb()->getContext($this->user, 'message', query: 'How much is the course? price');

        Http::assertNothingSent();
        $this->assertSame(0, KnowledgeChunk::count());
        // The SILVER context: whole entries of the task's categories, matching or not
        $this->assertStringContainsString('Shipping', $context);
        $this->assertStringContainsString('Course price', $context);
    }

    public function test_gold_embeds_entries_in_chunks_with_shortened_unit_vectors(): void
    {
        $this->activateGold();

        $long = implode("\n\n", array_map(
            fn ($i) => "Module {$i}: " . str_repeat("Lesson about pricing strategy {$i}. ", 12),
            range(1, 12),
        ));
        $entry = $this->kb()->addEntry($this->user, 'products', 'Course modules', $long);

        $chunks = KnowledgeChunk::where('knowledge_entry_id', $entry->id)->orderBy('chunk_index')->get();
        $this->assertGreaterThan(1, $chunks->count());
        $this->assertSame('openai:text-embedding-3-small', $chunks->first()->embedding_model);
        $this->assertSame($entry->contentHash(), $chunks->first()->content_hash);

        $vector = KnowledgeEmbedder::unpack($chunks->first()->embedding);
        $this->assertCount(4, $vector);
        $this->assertEqualsWithDelta(1.0, sqrt(array_sum(array_map(fn ($v) => $v * $v, $vector))), 1e-5);

        Http::assertSent(fn (Request $request) => $request['model'] === 'text-embedding-3-small'
            && $request['dimensions'] === KnowledgeEmbedder::DIMENSIONS
            // The title travels with every chunk
            && str_starts_with($request['input'][0], "Course modules\n\n"));
    }

    public function test_gold_context_brings_the_chunks_that_match_the_task(): void
    {
        $this->activateGold();
        $entries = $this->seedEntries();

        $context = $this->kb()->getContext($this->user, 'message', query: 'What does the course cost?');

        $this->assertStringStartsWith('--- KNOWLEDGE BASE ---', $context);
        $this->assertStringContainsString('Course price', $context);
        // The brand voice is kept for writing tasks whatever the question
        $this->assertStringContainsString('warm, direct tone', $context);
        // The best match comes before unrelated entries
        $this->assertLessThan(
            strpos($context, 'Shipping') ?: PHP_INT_MAX,
            strpos($context, 'Course price'),
        );
        $this->assertSame(1, $entries['price']->fresh()->usage_count);
    }

    public function test_search_returns_entries_by_meaning_with_gold(): void
    {
        $this->activateGold();
        $entries = $this->seedEntries();

        $results = $this->kb()->search($this->user, 'when will the courier come', null, 2);

        $this->assertSame($entries['shipping']->id, $results->first()->id);
    }

    public function test_changed_text_drops_old_chunks_and_embeds_again(): void
    {
        $this->activateGold();
        $entry = $this->kb()->addEntry($this->user, 'products', 'Course price', 'The course costs 297 EUR.');
        $oldHash = KnowledgeChunk::where('knowledge_entry_id', $entry->id)->value('content_hash');

        $entry->update(['title' => 'Workbook', 'content' => 'Shipping of the printed workbook is free.']);

        $chunks = KnowledgeChunk::where('knowledge_entry_id', $entry->id)->get();
        $this->assertCount(1, $chunks);
        $this->assertNotSame($oldHash, $chunks->first()->content_hash);
        $this->assertSame([0.0, 1.0, 0.0], array_map(
            fn ($v) => round($v, 1),
            array_slice(KnowledgeEmbedder::unpack($chunks->first()->embedding), 0, 3),
        ));
    }

    public function test_a_provider_failure_falls_back_to_categories(): void
    {
        $this->activateGold();
        $this->seedEntries();

        $this->embeddingStatus = 503;

        $context = $this->kb()->getContext($this->user, 'message', query: 'something new about the price');

        $this->assertStringContainsString('Shipping', $context);
        $this->assertStringContainsString('Course price', $context);
    }

    public function test_an_entry_is_saved_even_when_the_provider_fails(): void
    {
        $this->activateGold();
        $this->embeddingStatus = 503;

        $this->actingAs($this->user)
            ->postJson('/brain/api/knowledge', ['category' => 'products', 'title' => 'Price', 'content' => 'It costs 297 EUR.'])
            ->assertCreated();

        $this->assertSame(1, KnowledgeEntry::count());
        $this->assertSame(0, KnowledgeChunk::count());

        // The sweep catches up once the provider answers again
        $this->embeddingStatus = 200;
        $this->artisan('brain:index-knowledge', ['--sync' => true])->assertSuccessful();
        $this->assertSame(1, KnowledgeChunk::count());
    }

    public function test_after_gold_ends_brain_reads_by_category_and_the_vectors_stay(): void
    {
        $this->activateGold();
        $this->seedEntries();
        $chunks = KnowledgeChunk::count();

        $this->activateGold(now()->subDay()->toDateString());
        $context = $this->kb()->getContext($this->user, 'message', query: 'What does the course cost?');

        $this->assertSame($chunks, KnowledgeChunk::count());
        $this->assertStringContainsString('Shipping', $context);
    }

    public function test_an_unknown_category_becomes_insights_and_is_kept_as_a_tag(): void
    {
        $entry = $this->kb()->addEntry($this->user, 'marketing_strategy', 'Market research', 'Competitors send three e-mails a week.', 'web_research', null, ['research']);

        $this->assertSame('insights', $entry->category);
        $this->assertSame(['research', 'marketing_strategy'], $entry->tags);
        $this->assertStringContainsString('Market research', $this->kb()->getContext($this->user, 'analysis'));
    }

    public function test_entries_can_be_longer_with_gold(): void
    {
        Queue::fake();
        $content = str_repeat('a', KnowledgeEntry::CONTENT_LIMIT + 1);
        $payload = ['category' => 'products', 'title' => 'Handbook', 'content' => $content];

        $this->actingAs($this->user)->postJson('/brain/api/knowledge', $payload)->assertStatus(422);

        $this->activateGold();
        $this->actingAs($this->user)->postJson('/brain/api/knowledge', $payload)->assertCreated();
    }

    public function test_choosing_the_model_needs_gold_and_a_working_model(): void
    {
        $this->actingAs($this->user)
            ->putJson('/brain/api/knowledge/semantic', ['embedding_integration_id' => $this->openai->id])
            ->assertStatus(402)
            ->assertJsonPath('feature', 'semantic_knowledge');

        $this->activateGold();
        $this->embeddingStatus = 404;
        $this->actingAs($this->user)
            ->putJson('/brain/api/knowledge/semantic', [
                'embedding_integration_id' => $this->openai->id,
                'embedding_model' => 'text-embedding-9',
            ])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => __('brain.knowledge_semantic.test_failed', ['error' => 'Embedding request failed (404): The model does not exist'])]);
        $this->assertNull(AiBrainSettings::where('user_id', $this->user->id)->value('embedding_model'));
    }

    public function test_a_new_model_is_saved_and_the_knowledge_base_embedded_again(): void
    {
        $this->activateGold();
        $this->seedEntries();
        Queue::fake();

        $this->actingAs($this->user)
            ->putJson('/brain/api/knowledge/semantic', [
                'embedding_integration_id' => $this->openai->id,
                'embedding_model' => 'text-embedding-3-large',
            ])
            ->assertOk()
            ->assertJsonPath('active.model', 'text-embedding-3-large')
            ->assertJsonPath('status.entries', 3)
            ->assertJsonPath('status.indexed', 0);

        Queue::assertPushed(IndexKnowledgeBase::class, fn ($job) => $job->userId === $this->user->id);

        // The queued job embeds everything with the new model
        (new IndexKnowledgeBase($this->user->id))->handle(app(\App\Services\LicenseService::class), app(KnowledgeEmbedder::class), app(\App\Services\Brain\Knowledge\KnowledgeIndexer::class));
        $this->assertSame(3, KnowledgeChunk::where('embedding_model', 'openai:text-embedding-3-large')->count());
        $this->assertSame(0, KnowledgeChunk::where('embedding_model', 'openai:text-embedding-3-small')->count());
    }

    public function test_the_state_is_readable_on_silver_and_reindex_needs_gold(): void
    {
        $this->actingAs($this->user)
            ->getJson('/brain/api/knowledge/semantic')
            ->assertOk()
            ->assertJsonPath('licensed', false)
            ->assertJsonPath('content_limit', KnowledgeEntry::CONTENT_LIMIT)
            ->assertJsonPath('integrations.0.provider', 'openai');

        $this->actingAs($this->user)->postJson('/brain/api/knowledge/semantic/reindex')->assertStatus(402);
    }

    public function test_the_hourly_sweep_embeds_entries_saved_before_gold(): void
    {
        $this->seedEntries();
        $this->assertSame(0, KnowledgeChunk::count());

        $this->activateGold();
        $this->artisan('brain:index-knowledge', ['--sync' => true])->assertSuccessful();

        $this->assertSame(3, KnowledgeChunk::count());
    }

    public function test_without_an_embedding_provider_brain_reads_by_category(): void
    {
        $this->activateGold();
        $this->openai->update(['is_active' => false]);
        AiIntegration::create(['provider' => 'anthropic', 'name' => 'Claude', 'api_key' => 'sk-ant', 'is_active' => true]);

        $this->seedEntries();
        $context = $this->kb()->getContext($this->user, 'message', query: 'price');

        Http::assertNothingSent();
        $this->assertStringContainsString('Shipping', $context);
        $this->actingAs($this->user)->postJson('/brain/api/knowledge/semantic/reindex')->assertStatus(422);
    }
}
