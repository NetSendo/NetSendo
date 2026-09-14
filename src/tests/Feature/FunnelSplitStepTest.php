<?php

namespace Tests\Feature;

use App\Jobs\SendEmailJob;
use App\Models\ApiKey;
use App\Models\Funnel;
use App\Models\FunnelAbTest;
use App\Models\FunnelAbVariant;
use App\Models\FunnelStep;
use App\Models\FunnelSubscriber;
use App\Models\Tag;
use App\Services\Funnels\ABTestService;
use App\Services\Funnels\FunnelService;
use App\Services\Funnels\FunnelTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Concerns\BuildsFunnels;
use Tests\TestCase;

/**
 * The A/B (split) step: it drew a variant but every variant went on the step's
 * single next step, as nothing could give a variant a path of its own.
 */
class FunnelSplitStepTest extends TestCase
{
    use RefreshDatabase;
    use BuildsFunnels;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-14 10:00:00');
        Queue::fake();

        $this->setUpFunnelOwner();
    }

    private function node(string $id, string $type, array $data = []): array
    {
        return ['id' => $id, 'type' => $type, 'position' => ['x' => 0, 'y' => 0], 'data' => $data];
    }

    private function testFor(FunnelStep $split): FunnelAbTest
    {
        return FunnelAbTest::where('split_step_id', $split->id)->with('variants')->firstOrFail();
    }

    /**
     * Variant key => [name, weight, enrollments, conversions].
     */
    private function variantRows(FunnelStep $split): array
    {
        return $this->testFor($split)->variants
            ->mapWithKeys(fn (FunnelAbVariant $variant) => [$variant->variant_key => [$variant->name, $variant->weight, $variant->enrollments, $variant->conversions]])
            ->all();
    }

    private function tagsOf(string $email): array
    {
        return \App\Models\Subscriber::where('email', $email)->firstOrFail()->tags()->pluck('name')->all();
    }

    // ===== Engine =====

    public function test_each_variant_continues_on_its_own_path_as_drawn_by_weight(): void
    {
        $funnel = $this->makeFunnel();
        $pathA = $this->makeTagStep($funnel, 'path-a');
        $pathB = $this->makeTagStep($funnel, 'path-b');
        $split = $this->makeSplit($funnel, [
            ['key' => 'a', 'name' => 'Short subject', 'weight' => 100, 'next_step_id' => $pathA->id],
            ['key' => 'b', 'name' => 'Long subject', 'weight' => 0, 'next_step_id' => $pathB->id],
        ]);
        $this->makeChain($funnel, $split);

        $first = $this->enroll($funnel, $this->makeSubscriber('first@example.com'));

        $this->assertSame(['path-a'], $this->tagsOf('first@example.com'));
        $this->assertSame(FunnelSubscriber::STATUS_COMPLETED, $first->status);
        $this->assertSame('a', $this->historyEntry($first, 'ab_test_enrolled')['details']['variant_key']);

        // Reweighted after the test started: the test follows the step
        $split->update(['split_variants' => [
            ['key' => 'a', 'name' => 'Short subject', 'weight' => 0, 'next_step_id' => $pathA->id],
            ['key' => 'b', 'name' => 'Long subject', 'weight' => 100, 'next_step_id' => $pathB->id],
        ]]);

        $this->enroll($funnel, $this->makeSubscriber('second@example.com'));

        $this->assertSame(['path-b'], $this->tagsOf('second@example.com'));
        $this->assertSame([
            'a' => ['Short subject', 0, 1, 0],
            'b' => ['Long subject', 100, 1, 0],
        ], $this->variantRows($split));
        $this->assertSame($pathB->id, $this->testFor($split)->variants->firstWhere('variant_key', 'b')->next_step_id);
    }

    public function test_a_variant_without_a_path_of_its_own_continues_on_the_default_one(): void
    {
        $funnel = $this->makeFunnel();
        $default = $this->makeTagStep($funnel, 'default');
        $foreign = $this->makeTagStep($this->makeFunnel(['name' => 'Another funnel']), 'foreign');
        $split = $this->makeSplit($funnel, [
            ['key' => 'own', 'name' => 'A', 'weight' => 0, 'next_step_id' => $this->makeTagStep($funnel, 'own')->id],
            ['key' => 'none', 'name' => 'B', 'weight' => 100],
        ], ['next_step_id' => $default->id]);
        $this->makeChain($funnel, $split);

        $this->enroll($funnel, $this->makeSubscriber('none@example.com'));
        $this->assertSame(['default'], $this->tagsOf('none@example.com'));

        // A path to a step of another funnel is no path
        $split->update(['split_variants' => [
            ['key' => 'own', 'name' => 'A', 'weight' => 0],
            ['key' => 'none', 'name' => 'B', 'weight' => 100, 'next_step_id' => $foreign->id],
        ]]);

        $this->enroll($funnel, $this->makeSubscriber('foreign@example.com'));
        $this->assertSame(['default'], $this->tagsOf('foreign@example.com'));
        $this->assertNull($this->testFor($split)->variants->firstWhere('variant_key', 'none')->next_step_id);
    }

    public function test_subscribers_are_split_in_proportion_to_the_weights(): void
    {
        mt_srand(20260914);

        $funnel = $this->makeFunnel();
        $split = $this->makeSplit($funnel, [
            ['key' => 'a', 'name' => 'A', 'weight' => 75, 'next_step_id' => $this->makeTagStep($funnel, 'a')->id],
            ['key' => 'b', 'name' => 'B', 'weight' => 25, 'next_step_id' => $this->makeTagStep($funnel, 'b')->id],
        ]);
        $this->makeChain($funnel, $split);

        for ($i = 1; $i <= 200; $i++) {
            $this->enroll($funnel, $this->makeSubscriber("subscriber{$i}@example.com"));
        }

        $rows = $this->variantRows($split);
        $tagged = Tag::where('user_id', $this->user->id)->withCount('subscribers')->pluck('subscribers_count', 'name');

        $this->assertSame(200, $rows['a'][2] + $rows['b'][2]);
        $this->assertEqualsWithDelta(150, $rows['a'][2], 20);
        $this->assertSame($rows['a'][2], $tagged['a']);
        $this->assertSame($rows['b'][2], $tagged['b']);
    }

    public function test_a_goal_counts_for_the_variant_and_a_declared_winner_takes_everyone_onto_its_path(): void
    {
        $funnel = $this->makeFunnel();
        $goal = $this->makeStep($funnel, FunnelStep::TYPE_GOAL, [
            'goal_name' => 'Purchase',
            'goal_type' => FunnelStep::GOAL_PURCHASE,
            'next_step_id' => $this->makeTagStep($funnel, 'path-a')->id,
        ]);
        $split = $this->makeSplit($funnel, [
            ['key' => 'a', 'name' => 'A', 'weight' => 100, 'next_step_id' => $goal->id],
            ['key' => 'b', 'name' => 'B', 'weight' => 0, 'next_step_id' => $this->makeTagStep($funnel, 'path-b')->id],
        ]);
        $this->makeChain($funnel, $split);

        $this->enroll($funnel, $this->makeSubscriber('buyer@example.com'));

        $this->assertSame(['a' => ['A', 100, 1, 1], 'b' => ['B', 0, 0, 0]], $this->variantRows($split));

        // B converts far better: winner
        $test = $this->testFor($split);
        $test->variants->firstWhere('variant_key', 'a')->update(['enrollments' => 40, 'conversions' => 4]);
        $test->variants->firstWhere('variant_key', 'b')->update(['enrollments' => 40, 'conversions' => 20]);
        $winner = app(ABTestService::class)->checkForWinner($test->fresh('variants'));

        $this->assertSame('b', $winner?->variant_key);

        // Weights still favour A, the winner takes the subscriber anyway, outside the finished test
        $late = $this->enroll($funnel, $this->makeSubscriber('late@example.com'));

        $this->assertSame(['path-b'], $this->tagsOf('late@example.com'));
        $this->assertSame('b', $this->historyEntry($late, 'ab_test_winner_followed')['details']['variant_key']);
        $this->assertNull($this->historyEntry($late, 'ab_test_enrolled'));
        $this->assertSame(40, $this->variantRows($split)['b'][2]);
    }

    public function test_removing_the_winning_variant_reopens_the_test_for_the_variants_left(): void
    {
        $funnel = $this->makeFunnel();
        $variants = [
            ['key' => 'a', 'name' => 'A', 'weight' => 50, 'next_step_id' => $this->makeTagStep($funnel, 'path-a')->id],
            ['key' => 'b', 'name' => 'B', 'weight' => 50, 'next_step_id' => $this->makeTagStep($funnel, 'path-b')->id],
        ];
        $split = $this->makeSplit($funnel, $variants);
        $this->makeChain($funnel, $split);

        $test = app(ABTestService::class)->getOrCreateTest($split);
        $test->start()->complete($test->variants->firstWhere('variant_key', 'b'));

        $split->update(['split_variants' => [$variants[0], ['key' => 'c', 'name' => 'C', 'weight' => 0]]]);
        $enrollment = $this->enroll($funnel, $this->makeSubscriber());

        $test->refresh();
        $this->assertSame(FunnelAbTest::STATUS_RUNNING, $test->status);
        $this->assertNull($test->winner_variant_id);
        $this->assertSame(['a', 'c'], $test->variants->pluck('variant_key')->all());
        $this->assertSame('a', $this->historyEntry($enrollment, 'ab_test_enrolled')['details']['variant_key']);
        $this->assertSame(['path-a'], $this->tagsOf('jan@example.com'));
    }

    // ===== Builder =====

    public function test_the_builder_saves_a_path_per_variant_and_loads_it_back(): void
    {
        $service = app(FunnelService::class);
        $funnel = $service->create(['user_id' => $this->user->id, 'name' => 'Built']);
        $start = $funnel->steps()->first();

        $nodes = [
            $this->node((string) $start->id, 'start'),
            $this->node('new-1', 'split', ['split_variants' => [
                ['key' => 'k1', 'name' => 'A', 'weight' => 70],
                ['key' => 'k2', 'name' => 'B', 'weight' => 30],
                ['name' => 'C', 'weight' => 0],
            ]]),
            $this->node('new-2', 'email'),
            $this->node('new-3', 'end'),
            $this->node('new-4', 'action', ['action_type' => 'add_tag', 'action_config' => ['tag' => 'rest']]),
        ];

        $service->updateSteps($funnel, $nodes, [
            ['source' => (string) $start->id, 'target' => 'new-1', 'sourceHandle' => 'default'],
            ['source' => 'new-1', 'target' => 'new-2', 'sourceHandle' => 'variant-k1'],
            ['source' => 'new-1', 'target' => 'new-3', 'sourceHandle' => 'variant-k2'],
            ['source' => 'new-1', 'target' => 'new-4', 'sourceHandle' => 'default'],
            // A variant removed in the meantime
            ['source' => 'new-1', 'target' => 'new-3', 'sourceHandle' => 'variant-gone'],
        ]);

        $steps = $funnel->steps()->get()->keyBy('type');
        $split = $steps['split'];

        $this->assertSame([
            ['key' => 'k1', 'name' => 'A', 'weight' => 70, 'next_step_id' => $steps['email']->id],
            ['key' => 'k2', 'name' => 'B', 'weight' => 30, 'next_step_id' => $steps['end']->id],
            ['key' => 'v3', 'name' => 'C', 'weight' => 0, 'next_step_id' => null],
        ], $split->split_variants);
        $this->assertSame($steps['action']->id, $split->next_step_id);

        $builder = $service->prepareForBuilder($funnel->fresh('steps'));
        $splitNode = collect($builder['nodes'])->firstWhere('type', 'split');
        $splitEdges = collect($builder['edges'])->where('source', (string) $split->id)
            ->mapWithKeys(fn (array $edge) => [$edge['sourceHandle'] => $edge['target']])
            ->all();

        $this->assertSame([
            ['key' => 'k1', 'name' => 'A', 'weight' => 70],
            ['key' => 'k2', 'name' => 'B', 'weight' => 30],
            ['key' => 'v3', 'name' => 'C', 'weight' => 0],
        ], $splitNode['data']['split_variants']);
        $this->assertSame([
            'default' => (string) $steps['action']->id,
            'variant-k1' => (string) $steps['email']->id,
            'variant-k2' => (string) $steps['end']->id,
        ], $splitEdges);

        // Saved again with B disconnected
        $service->updateSteps(
            $funnel,
            $builder['nodes'],
            collect($builder['edges'])->reject(fn (array $edge) => $edge['sourceHandle'] === 'variant-k2')->values()->all()
        );

        $variants = collect($split->fresh()->getSplitVariants())->pluck('next_step_id', 'key')->all();
        $this->assertSame(['k1' => $steps['email']->id, 'k2' => null, 'v3' => null], $variants);
    }

    public function test_editing_the_variants_keeps_each_ones_results_and_removes_a_removed_ones(): void
    {
        $funnel = $this->makeFunnel();
        // Saved before variants had keys, with its test already counting
        $split = $this->makeSplit($funnel, [
            ['name' => 'A', 'weight' => 40],
            ['name' => 'B', 'weight' => 30],
            ['name' => 'C', 'weight' => 30],
        ]);
        $test = FunnelAbTest::create(['funnel_id' => $funnel->id, 'split_step_id' => $split->id, 'name' => 'Test', 'status' => FunnelAbTest::STATUS_RUNNING]);
        foreach (['A' => 5, 'B' => 7, 'C' => 9] as $name => $enrollments) {
            FunnelAbVariant::create(['ab_test_id' => $test->id, 'name' => $name, 'weight' => 30, 'enrollments' => $enrollments]);
        }

        $service = app(FunnelService::class);
        $builder = $service->prepareForBuilder($funnel->fresh('steps'));
        $node = $builder['nodes'][0];
        [$a, , $c] = $node['data']['split_variants'];

        $this->assertSame(['v1', 'v2', 'v3'], array_column($node['data']['split_variants'], 'key'));

        // B removed, C renamed and reweighted, a new D added in front
        $node['data']['split_variants'] = [['key' => 'd', 'name' => 'D', 'weight' => 10], $a, ['name' => 'C renamed', 'weight' => 50] + $c];
        $service->updateSteps($funnel, [$node], []);

        $this->assertSame([
            'v1' => ['A', 40, 5, 0],
            'v3' => ['C renamed', 50, 9, 0],
            'd' => ['D', 10, 0, 0],
        ], $this->variantRows($split));
    }

    // ===== API =====

    public function test_a_split_step_added_over_the_api_leads_each_variant_down_its_own_path(): void
    {
        $key = ApiKey::generate($this->user->id, 'Funnels', ['funnels:read', 'funnels:write'])['key'];
        $funnel = app(FunnelService::class)->create(['user_id' => $this->user->id, 'name' => 'API funnel']);
        $start = $funnel->steps()->first();
        $message = $this->makeEmail();

        $post = fn (array $payload) => $this->withHeaders(['Authorization' => "Bearer {$key}"])
            ->postJson("/api/v1/funnels/{$funnel->id}/steps", $payload);

        $split = $post([
            'type' => 'split',
            'name' => 'Subject test',
            'split_variants' => [['name' => 'Short'], ['key' => 'long', 'name' => 'Long', 'weight' => 0]],
        ])->assertCreated()->json('data');

        $this->assertSame([
            ['key' => 'v1', 'name' => 'Short', 'weight' => 50, 'next_step_id' => null],
            ['key' => 'long', 'name' => 'Long', 'weight' => 0, 'next_step_id' => null],
        ], $split['split_variants']);

        // By name, after the last step (the split)
        $shortTagId = $post(['type' => 'action', 'name' => 'Short tag', 'variant' => 'short', 'action_type' => 'add_tag', 'action_config' => ['tag' => 'short']])
            ->assertCreated()->json('data.id');
        // By key
        $longTagId = $post(['type' => 'action', 'name' => 'Long tag', 'after_step_id' => $split['id'], 'variant' => 'long', 'action_type' => 'add_tag', 'action_config' => ['tag' => 'long']])
            ->assertCreated()->json('data.id');
        // Inserted on the short path, before its tag
        $emailId = $post(['type' => 'email', 'name' => 'Short email', 'after_step_id' => $split['id'], 'variant' => 'v1', 'message_id' => $message->id])
            ->assertCreated()->json('data.id');

        $post(['type' => 'end', 'name' => 'End', 'after_step_id' => $split['id'], 'variant' => 'Medium'])
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'v1 (Short), long (Long)'));
        $post(['type' => 'split', 'name' => 'Too few', 'split_variants' => [['name' => 'Only']]])->assertUnprocessable();

        $splitStep = FunnelStep::find($split['id']);
        $this->assertSame($split['id'], $start->fresh()->next_step_id);
        $this->assertSame(['v1' => $emailId, 'long' => $longTagId], collect($splitStep->getSplitVariants())->pluck('next_step_id', 'key')->all());
        $this->assertNull($splitStep->next_step_id);
        $this->assertSame($shortTagId, FunnelStep::find($emailId)->next_step_id);

        $funnel->update(['status' => Funnel::STATUS_ACTIVE]);
        $enrollment = $this->enroll($funnel, $this->makeSubscriber());

        $this->assertSame(FunnelSubscriber::STATUS_COMPLETED, $enrollment->status);
        $this->assertSame(['short'], $this->tagsOf('jan@example.com'));
        Queue::assertPushed(SendEmailJob::class, 1);
    }

    public function test_a_split_steps_variants_and_paths_can_be_changed_and_a_step_on_a_path_deleted_over_the_api(): void
    {
        $key = ApiKey::generate($this->user->id, 'Funnels', ['funnels:read', 'funnels:write'])['key'];
        $funnel = $this->makeFunnel(['status' => Funnel::STATUS_DRAFT]);
        $end = $this->makeStep($funnel, FunnelStep::TYPE_END);
        $tagA = $this->makeTagStep($funnel, 'path-a', ['next_step_id' => $end->id]);
        $tagB = $this->makeTagStep($funnel, 'path-b');
        $split = $this->makeSplit($funnel, [
            ['key' => 'a', 'name' => 'A', 'weight' => 50, 'next_step_id' => $tagA->id],
            ['key' => 'b', 'name' => 'B', 'weight' => 50, 'next_step_id' => $tagB->id],
        ]);
        $this->makeChain($funnel, $split);
        $test = app(ABTestService::class)->getOrCreateTest($split);

        $api = fn (string $method, string $uri, array $payload = []) => $this->withHeaders(['Authorization' => "Bearer {$key}"])
            ->json($method, "/api/v1/funnels/{$funnel->id}/steps/{$uri}", $payload);

        // A renamed and without a path of its own now, B keeps its path, C added on the end step
        $api('PUT', $split->id, ['split_variants' => [
            ['key' => 'a', 'name' => 'A renamed', 'weight' => 0, 'next_step_id' => null],
            ['key' => 'b', 'name' => 'B', 'weight' => 60],
            ['key' => 'c', 'name' => 'C', 'weight' => 40, 'next_step_id' => $end->id],
        ]])->assertOk();

        $this->assertSame(['a' => null, 'b' => $tagB->id, 'c' => $end->id], collect($split->fresh()->getSplitVariants())->pluck('next_step_id', 'key')->all());
        $this->assertSame(['a' => ['A renamed', 0, 0, 0], 'b' => ['B', 60, 0, 0], 'c' => ['C', 40, 0, 0]], $this->variantRows($split));

        // Only a step of this funnel
        $foreign = $this->makeTagStep($this->makeFunnel(['name' => 'Another funnel']), 'foreign');
        $api('PUT', $split->id, ['split_variants' => [['key' => 'a', 'next_step_id' => $foreign->id], ['key' => 'b']]])
            ->assertJsonValidationErrors('split_variants.0.next_step_id');

        // B's path goes to its tag step; deleted, B continues where that step did
        $tagB->update(['next_step_id' => $end->id]);
        $api('DELETE', $tagB->id)->assertOk();

        $this->assertSame($end->id, collect($split->fresh()->getSplitVariants())->firstWhere('key', 'b')['next_step_id']);
        $this->assertSame($test->id, $this->testFor($split)->id);
    }

    // ===== Copies and statistics =====

    public function test_a_copy_of_the_funnel_and_one_made_from_its_template_keep_the_variant_paths(): void
    {
        $funnel = $this->makeFunnel();
        $split = $this->makeSplit($funnel, [
            ['key' => 'a', 'name' => 'A', 'weight' => 50, 'next_step_id' => $this->makeTagStep($funnel, 'path-a')->id],
            ['key' => 'b', 'name' => 'B', 'weight' => 50, 'next_step_id' => $this->makeTagStep($funnel, 'path-b')->id],
        ]);
        $this->makeChain($funnel, $split);

        $templates = app(FunnelTemplateService::class);
        $copies = [
            $funnel->duplicate(),
            $templates->createFromTemplate($templates->exportToTemplate($funnel, ['user_id' => $this->user->id]), ['user_id' => $this->user->id]),
        ];

        foreach ($copies as $copy) {
            $copySplit = $copy->steps()->where('type', FunnelStep::TYPE_SPLIT)->firstOrFail();
            $paths = collect($copySplit->getSplitVariants())->mapWithKeys(function (array $variant) use ($copy) {
                $target = FunnelStep::where('funnel_id', $copy->id)->find($variant['next_step_id']);

                return [$variant['key'] => $target?->action_config['tag']];
            });

            $this->assertSame(['a' => 'path-a', 'b' => 'path-b'], $paths->all());
        }
    }

    public function test_the_stats_page_shows_each_variants_results(): void
    {
        $funnel = $this->makeFunnel();
        $split = $this->makeSplit($funnel, [
            ['key' => 'a', 'name' => 'A', 'weight' => 100, 'next_step_id' => $this->makeTagStep($funnel, 'path-a')->id],
            ['key' => 'b', 'name' => 'B', 'weight' => 0],
        ]);
        $this->makeChain($funnel, $split);
        $this->enroll($funnel, $this->makeSubscriber());

        $this->withoutVite()
            ->actingAs($this->user)
            ->get(route('funnels.stats', $funnel))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('stats.ab_tests.0.variants.0.name', 'A')
                ->where('stats.ab_tests.0.variants.0.enrollments', 1)
                ->where('stats.ab_tests.0.variants.1.enrollments', 0));
    }
}
