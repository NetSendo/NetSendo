<?php

namespace App\Services\Brain\Agents;

use App\Models\AiActionPlan;
use App\Models\AiActionPlanStep;
use App\Models\ContactList;
use App\Models\Subscriber;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class ListAgent extends BaseAgent
{
    public function getName(): string
    {
        return 'list';
    }

    public function getLabel(): string
    {
        return __('brain.list.label');
    }

    public function getCapabilities(): array
    {
        return [
            'create_list',
            'manage_subscribers',
            'clean_list',
            'segment_subscribers',
            'tag_subscribers',
            'import_subscribers',
            'list_stats',
        ];
    }

    /**
     * Create a plan for list operations.
     */
    public function plan(array $intent, User $user, string $knowledgeContext = ''): ?AiActionPlan
    {
        $langInstruction = $this->getLanguageInstruction($user);

        $intentDesc = $intent['intent'];
        $paramsJson = json_encode($intent['parameters'] ?? []);

        $prompt = <<<PROMPT
You are an email list management expert. The user wants:
Intent: {$intentDesc}
Parameters: {$paramsJson}

{$knowledgeContext}

Current user lists:
{$this->getUserListsSummary($user)}

{$langInstruction}

Create an action plan. Respond in JSON:
{
  "title": "plan title",
  "description": "description",
  "steps": [
    {
      "action_type": "type",
      "title": "step title",
      "description": "description",
      "config": {}
    }
  ]
}

Available action_types:
- create_list: create a new list (config: {name: "", description: ""})
- add_subscribers: add subscribers (config: {list_id: N, emails: [], source: ""})
- remove_subscribers: remove subscribers (config: {list_id: N, criteria: {}})
- tag_subscribers: tag subscribers (config: {tag_name: "", list_id: N, criteria: {}})
- clean_bounced: clean bounced/unsubscribed (config: {list_id: N})
- segment: create segment (config: {name: "", criteria: {}})
- show_stats: show statistics (config: {list_id: N})
PROMPT;

        try {
            $response = $this->callAi($prompt, ['max_tokens' => 2000, 'temperature' => 0.3]);
            $data = $this->parseJson($response);

            if (!$data || empty($data['steps'])) {
                return null;
            }

            return $this->createPlan(
                $user,
                $intent['intent'] ?? 'list_management',
                $data['title'] ?? __('brain.list.plan_title'),
                $data['description'] ?? null,
                $data['steps']
            );
        } catch (\Exception $e) {
            Log::error('ListAgent plan failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Execute list management plan.
     */
    public function execute(AiActionPlan $plan, User $user): array
    {
        $steps = $plan->steps()->orderBy('step_order')->get();
        $messages = [];

        foreach ($steps as $step) {
            try {
                $result = $this->executeStep($step, $user);
                $messages[] = $result['message'] ?? "✅ {$step->title}";
            } catch (\Exception $e) {
                $messages[] = "❌ {$step->title}: {$e->getMessage()}";
            }
        }

        return [
            'type' => 'execution_result',
            'message' => __('brain.list.management_done') . "\n\n" . implode("\n", $messages),
        ];
    }

    /**
     * Execute specific list step actions.
     */
    protected function executeStepAction(AiActionPlanStep $step, User $user): array
    {
        return match ($step->action_type) {
            'create_list' => $this->executeCreateList($step, $user),
            'clean_bounced' => $this->executeCleanBounced($step, $user),
            'tag_subscribers' => $this->executeTagSubscribers($step, $user),
            'show_stats' => $this->executeShowStats($step, $user),
            default => ['status' => 'completed', 'message' => "Noted: {$step->action_type}"],
        };
    }

    /**
     * Advise on list management.
     */
    public function advise(array $intent, User $user, string $knowledgeContext = ''): array
    {
        $listsSummary = $this->getUserListsSummary($user);

        $intentDesc = $intent['intent'];
        $paramsJson = json_encode($intent['parameters'] ?? []);

        $langInstruction = $this->getLanguageInstruction($user);

        $prompt = <<<PROMPT
Advise the user on email list management. Manual mode — provide instructions.

Intent: {$intentDesc}
Parameters: {$paramsJson}

Current lists:
{$listsSummary}

{$knowledgeContext}

{$langInstruction}

Provide specific step-by-step instructions to follow in the NetSendo panel.
PROMPT;

        $response = $this->callAi($prompt, ['max_tokens' => 2000, 'temperature' => 0.5]);

        return [
            'type' => 'advice',
            'message' => $response,
        ];
    }

    // === Step Executors ===

    protected function executeCreateList(AiActionPlanStep $step, User $user): array
    {
        $config = $step->config;

        $list = ContactList::create([
            'user_id' => $user->id,
            'name' => $config['name'] ?? __('brain.list.default_name'),
            'description' => $config['description'] ?? '',
        ]);

        return [
            'status' => 'completed',
            'list_id' => $list->id,
            'message' => __('brain.list.list_created', ['name' => $list->name, 'id' => $list->id]),
        ];
    }

    protected function executeCleanBounced(AiActionPlanStep $step, User $user): array
    {
        $config = $step->config;
        $query = Subscriber::where('user_id', $user->id)
            ->whereIn('status', ['bounced', 'complained']);

        if (isset($config['list_id'])) {
            $query->where('contact_list_id', $config['list_id']);
        }

        $count = $query->count();
        $query->update(['status' => 'unsubscribed']);

        return [
            'status' => 'completed',
            'cleaned_count' => $count,
            'message' => __('brain.list.cleaned', ['count' => $count]),
        ];
    }

    protected function executeTagSubscribers(AiActionPlanStep $step, User $user): array
    {
        $config = $step->config;
        $tagName = $config['tag_name'] ?? 'ai-tagged';

        $tag = Tag::firstOrCreate(
            ['name' => $tagName, 'user_id' => $user->id],
            ['name' => $tagName, 'user_id' => $user->id]
        );

        $query = Subscriber::where('user_id', $user->id);
        if (isset($config['list_id'])) {
            $query->where('contact_list_id', $config['list_id']);
        }

        $subscribers = $query->limit(1000)->get();
        $tagged = 0;

        foreach ($subscribers as $subscriber) {
            if (!$subscriber->tags->contains($tag->id)) {
                $subscriber->tags()->attach($tag->id);
                $tagged++;
            }
        }

        return [
            'status' => 'completed',
            'tagged_count' => $tagged,
            'message' => __('brain.list.tagged', ['count' => $tagged, 'tag' => $tagName]),
        ];
    }

    protected function executeShowStats(AiActionPlanStep $step, User $user): array
    {
        $config = $step->config;

        if (isset($config['list_id'])) {
            $list = ContactList::where('id', $config['list_id'])
                ->where('user_id', $user->id)
                ->withCount('subscribers')
                ->first();

            if ($list) {
                return [
                    'status' => 'completed',
                    'stats' => [
                        'name' => $list->name,
                        'subscribers' => $list->subscribers_count ?? 0,
                    ],
                    'message' => __('brain.list.stats_list', ['name' => $list->name, 'count' => $list->subscribers_count]),
                ];
            }
        }

        $lists = ContactList::where('user_id', $user->id)->withCount('subscribers')->get();
        $total = $lists->sum('subscribers_count');

        return [
            'status' => 'completed',
            'stats' => [
                'total_lists' => $lists->count(),
                'total_subscribers' => $total,
            ],
            'message' => __('brain.list.stats_total', ['lists' => $lists->count(), 'subscribers' => $total]),
        ];
    }

    /**
     * Get summary of user's lists for context.
     */
    protected function getUserListsSummary(User $user): string
    {
        $lists = ContactList::where('user_id', $user->id)
            ->withCount('subscribers')
            ->orderByDesc('subscribers_count')
            ->limit(10)
            ->get();

        if ($lists->isEmpty()) {
            return __('brain.list.no_lists');
        }

        return $lists->map(function ($list) {
            return "- [{$list->id}] {$list->name}: {$list->subscribers_count} subscribers";
        })->join("\n");
    }
}
