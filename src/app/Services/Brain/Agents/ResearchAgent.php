<?php

namespace App\Services\Brain\Agents;

use App\Models\AiActionPlan;
use App\Models\AiActionPlanStep;
use App\Models\User;
use App\Services\Brain\KnowledgeBaseService;
use App\Services\Brain\WebResearchService;
use Illuminate\Support\Facades\Log;

class ResearchAgent extends BaseAgent
{
    public function __construct(
        protected \App\Services\AI\AiService $aiService,
        protected KnowledgeBaseService $knowledgeBase,
        protected WebResearchService $webResearch,
    ) {
        parent::__construct($aiService, $knowledgeBase);
    }

    public function getName(): string
    {
        return 'research';
    }

    public function getLabel(): string
    {
        return __('brain.research.agent_label');
    }

    public function getCapabilities(): array
    {
        return [
            'web_search',
            'deep_research',
            'competitor_analysis',
            'market_trends',
            'company_research',
            'content_research',
        ];
    }

    /**
     * Check if more info is needed before researching.
     */
    public function needsMoreInfo(array $intent, User $user, string $knowledgeContext = ''): bool
    {
        $params = $intent['parameters'] ?? [];
        if (!empty($params['user_details'])) {
            return false;
        }
        // Need at least a query/topic to research
        return empty($params['query']) && empty($params['topic']) && empty($params['company']);
    }

    /**
     * Generate research-specific questions for the user.
     */
    public function getInfoQuestions(array $intent, User $user, string $knowledgeContext = ''): string
    {
        $langInstruction = $this->getLanguageInstruction($user);

        return "🔍 **Research — I need some details:**\n\n"
            . "1. **What do you want to research?** — topic, company, competitor, trend?\n"
            . "2. **Specific focus** — any particular aspect you're interested in?\n"
            . "3. **Purpose** — how will you use this research? (campaign, strategy, CRM enrichment?)\n\n"
            . "Provide as much detail as you can and I will conduct thorough internet research.\n\n{$langInstruction}";
    }

    /**
     * Create a research plan.
     */
    public function plan(array $intent, User $user, string $knowledgeContext = ''): ?AiActionPlan
    {
        $intentDesc = $intent['intent'];
        $paramsJson = json_encode($intent['parameters'] ?? []);

        $langInstruction = $this->getLanguageInstruction($user);

        // Check what research capabilities are available
        $availability = $this->webResearch->isAvailable($user);
        $availableTools = [];
        if ($availability['serpapi']) {
            $availableTools[] = 'web_search — search the web via Google (config: {query: "", type: "general|news"})';
        }
        if ($availability['perplexity']) {
            $availableTools[] = 'deep_research — AI-powered deep research with citations (config: {query: "", context: ""})';
        }
        $availableTools[] = 'company_research — research a specific company (config: {company: "", website: ""})';
        $availableTools[] = 'trend_analysis — analyze market/industry trends (config: {topic: ""})';
        $availableTools[] = 'content_research — research content ideas (config: {topic: "", type: "email|sms"})';
        $availableTools[] = 'save_to_knowledge — save findings to knowledge base (config: {category: "", title: ""})';

        $toolsList = implode("\n- ", $availableTools);

        $prompt = <<<PROMPT
You are an internet research expert. The user wants to research something:
Intent: {$intentDesc}
Parameters: {$paramsJson}

{$knowledgeContext}

{$langInstruction}

Create a research plan. Respond in JSON:
{
  "title": "short plan title",
  "description": "description of what the research will cover",
  "steps": [
    {
      "action_type": "action_type",
      "title": "step title",
      "description": "step description",
      "config": {}
    }
  ]
}

Available action_types:
- {$toolsList}

Create 2-4 focused research steps. Always end with save_to_knowledge if findings are valuable.
PROMPT;

        try {
            $response = $this->callAi($prompt, ['max_tokens' => 2000, 'temperature' => 0.3], $user, 'research');
            $data = $this->parseJson($response);

            if (!$data || empty($data['steps'])) {
                return null;
            }

            return $this->createPlan(
                $user,
                $intent['intent'] ?? 'research',
                $data['title'] ?? __('brain.research.plan_title'),
                $data['description'] ?? null,
                $data['steps']
            );
        } catch (\Exception $e) {
            Log::error('ResearchAgent plan failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Execute a research plan.
     */
    public function execute(AiActionPlan $plan, User $user): array
    {
        $steps = $plan->steps()->orderBy('step_order')->get();
        $messages = [];
        $researchContext = '';

        foreach ($steps as $step) {
            try {
                // Pass accumulated research context to each step
                $step->config = array_merge($step->config ?? [], ['_research_context' => $researchContext]);
                $result = $this->executeStep($step, $user);

                if (!empty($result['message'])) {
                    $messages[] = $result['message'];
                }

                // Accumulate research context for subsequent steps
                if (!empty($result['research_data'])) {
                    $researchContext .= "\n" . $result['research_data'];
                }
            } catch (\Exception $e) {
                $messages[] = "⚠️ {$step->title}: {$e->getMessage()}";
            }
        }

        return [
            'type' => 'execution_result',
            'message' => implode("\n\n---\n\n", $messages) ?: __('brain.research.done'),
        ];
    }

    /**
     * Execute a specific research step action.
     */
    protected function executeStepAction(AiActionPlanStep $step, User $user): array
    {
        return match ($step->action_type) {
            'web_search' => $this->executeWebSearch($step, $user),
            'deep_research' => $this->executeDeepResearch($step, $user),
            'company_research' => $this->executeCompanyResearch($step, $user),
            'trend_analysis' => $this->executeTrendAnalysis($step, $user),
            'content_research' => $this->executeContentResearch($step, $user),
            'save_to_knowledge' => $this->executeSaveToKnowledge($step, $user),
            default => ['status' => 'completed', 'message' => "Action '{$step->action_type}' noted"],
        };
    }

    /**
     * Advise on research (manual mode).
     */
    public function advise(array $intent, User $user, string $knowledgeContext = ''): array
    {
        $langInstruction = $this->getLanguageInstruction($user);
        $availability = $this->webResearch->isAvailable($user);

        $paramsJson = json_encode($intent['parameters'] ?? []);
        $perplexityStatus = $availability['perplexity'] ? 'YES' : 'NOT CONFIGURED';
        $serpapiStatus = $availability['serpapi'] ? 'YES' : 'NOT CONFIGURED';

        $prompt = <<<PROMPT
You are a research expert. The user needs research advice.
Intent: {$intent['intent']}
Parameters: {$paramsJson}

Research tools available:
- Perplexity AI (deep research): {$perplexityStatus}
- SerpAPI (Google Search): {$serpapiStatus}

{$knowledgeContext}

{$langInstruction}

Provide detailed advice on how to research this topic, including specific search queries to use,
sources to check, and how to apply findings to their marketing/CRM strategy.
Use emoji for readability.
PROMPT;

        $response = $this->callAi($prompt, ['max_tokens' => 2000, 'temperature' => 0.5], $user, 'research');

        return [
            'type' => 'advice',
            'message' => $response,
        ];
    }

    // === Step Executors ===

    protected function executeWebSearch(AiActionPlanStep $step, User $user): array
    {
        $config = $step->config;
        $query = $config['query'] ?? '';
        $type = $config['type'] ?? 'general';

        if (empty($query)) {
            return ['status' => 'failed', 'message' => __('brain.research.query_missing')];
        }

        $results = $this->webResearch->searchWeb($query, $user, $type);

        if (!empty($results['error'])) {
            return ['status' => 'failed', 'message' => "Search error: {$results['error']}"];
        }

        if (empty($results['results'])) {
            return ['status' => 'completed', 'message' => __('brain.research.no_results', ['query' => $query])];
        }

        $msg = "🔍 **Web Search: \"{$query}\"**\n\n";
        foreach ($results['results'] as $i => $result) {
            $msg .= ($i + 1) . ". **[{$result['title']}]({$result['link']})**\n";
            if (!empty($result['snippet'])) {
                $msg .= "   {$result['snippet']}\n";
            }
            $msg .= "\n";
        }

        if (!empty($results['knowledge_graph'])) {
            $kg = $results['knowledge_graph'];
            $msg .= "\n📊 **Knowledge Graph:**\n";
            if (!empty($kg['title'])) $msg .= "• **{$kg['title']}**";
            if (!empty($kg['description'])) $msg .= " — {$kg['description']}";
            $msg .= "\n";
        }

        return [
            'status' => 'completed',
            'message' => $msg,
            'research_data' => $this->webResearch->formatAsContext($results),
        ];
    }

    protected function executeDeepResearch(AiActionPlanStep $step, User $user): array
    {
        $config = $step->config;
        $query = $config['query'] ?? '';
        $context = $config['context'] ?? $config['_research_context'] ?? '';

        if (empty($query)) {
            return ['status' => 'failed', 'message' => __('brain.research.query_missing')];
        }

        $result = $this->webResearch->deepResearch($query, $user, $context);

        if (!empty($result['error'])) {
            return ['status' => 'failed', 'message' => "Research error: {$result['error']}"];
        }

        $msg = "🔬 **Deep Research: \"{$query}\"**\n\n";
        $msg .= $result['answer'];

        if (!empty($result['citations'])) {
            $msg .= "\n\n📚 **Sources:**\n";
            foreach ($result['citations'] as $i => $url) {
                $msg .= "[" . ($i + 1) . "] {$url}\n";
            }
        }

        return [
            'status' => 'completed',
            'message' => $msg,
            'research_data' => $result['answer'],
        ];
    }

    protected function executeCompanyResearch(AiActionPlanStep $step, User $user): array
    {
        $config = $step->config;
        $company = $config['company'] ?? '';

        if (empty($company)) {
            return ['status' => 'failed', 'message' => __('brain.research.company_missing')];
        }

        $result = $this->webResearch->researchCompany($company, $user);

        $msg = "🏢 **Company Research: \"{$company}\"**\n\n";
        $msg .= $result['analysis'] ?? __('brain.research.no_data');

        if (!empty($result['sources'])) {
            $msg .= "\n\n📚 **Sources:**\n";
            foreach ($result['sources'] as $i => $url) {
                $msg .= "[" . ($i + 1) . "] {$url}\n";
            }
        }

        return [
            'status' => 'completed',
            'message' => $msg,
            'research_data' => $result['analysis'] ?? '',
        ];
    }

    protected function executeTrendAnalysis(AiActionPlanStep $step, User $user): array
    {
        $config = $step->config;
        $topic = $config['topic'] ?? '';

        if (empty($topic)) {
            return ['status' => 'failed', 'message' => __('brain.research.topic_missing')];
        }

        $result = $this->webResearch->researchTrends($topic, $user);

        $msg = "📈 **Trend Analysis: \"{$topic}\"**\n\n";
        $msg .= $result['analysis'] ?? __('brain.research.no_data');

        if (!empty($result['news'])) {
            $msg .= "\n\n📰 **Latest News:**\n";
            foreach ($result['news'] as $i => $news) {
                $msg .= ($i + 1) . ". [{$news['title']}]({$news['link']})\n";
            }
        }

        return [
            'status' => 'completed',
            'message' => $msg,
            'research_data' => $result['analysis'] ?? '',
        ];
    }

    protected function executeContentResearch(AiActionPlanStep $step, User $user): array
    {
        $config = $step->config;
        $topic = $config['topic'] ?? '';
        $type = $config['type'] ?? 'email';

        if (empty($topic)) {
            return ['status' => 'failed', 'message' => __('brain.research.topic_missing')];
        }

        $result = $this->webResearch->researchContentIdeas($topic, $user, $type);

        $msg = "✍️ **Content Research: \"{$topic}\" ({$type})**\n\n";
        $msg .= $result['ideas'] ?? __('brain.research.no_data');

        return [
            'status' => 'completed',
            'message' => $msg,
            'research_data' => $result['ideas'] ?? '',
        ];
    }

    protected function executeSaveToKnowledge(AiActionPlanStep $step, User $user): array
    {
        $config = $step->config;
        $category = $config['category'] ?? 'insights';
        $title = $config['title'] ?? __('brain.research.default_kb_title');
        $researchContext = $config['_research_context'] ?? '';

        if (empty($researchContext)) {
            return ['status' => 'completed', 'message' => __('brain.research.nothing_to_save')];
        }

        // Summarize research for knowledge base
        $langInstruction = $this->getLanguageInstruction($user);
        $prompt = <<<PROMPT
Summarize the following research findings into a concise knowledge base entry (max 500 words).
Keep the most important facts, statistics, and actionable insights.

Research data:
{$researchContext}

{$langInstruction}

Write a clear, structured summary.
PROMPT;

        try {
            $summary = $this->callAi($prompt, ['max_tokens' => 800, 'temperature' => 0.3], $user, 'research');

            $entry = $this->knowledgeBase->addEntry(
                $user,
                $category,
                $title,
                $summary,
                'web_research',
                'research_agent',
                ['research', 'auto-generated']
            );

            return [
                'status' => 'completed',
                'message' => "💾 " . __('brain.research.saved_to_kb', ['title' => $title, 'id' => $entry->id]),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'failed',
                'message' => __('brain.research.save_failed', ['error' => $e->getMessage()]),
            ];
        }
    }
}
