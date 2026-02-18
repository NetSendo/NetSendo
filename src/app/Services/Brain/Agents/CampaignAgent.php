<?php

namespace App\Services\Brain\Agents;

use App\Models\AiActionPlan;
use App\Models\AiActionPlanStep;
use App\Models\ContactList;
use App\Models\Message;
use App\Models\User;
use App\Services\AI\AiService;
use App\Services\Brain\KnowledgeBaseService;
use App\Services\CampaignArchitectService;
use App\Services\CampaignAdvisorService;
use Illuminate\Support\Facades\Log;

class CampaignAgent extends BaseAgent
{
    public function __construct(
        protected AiService $aiService,
        protected KnowledgeBaseService $knowledgeBase,
        protected CampaignArchitectService $architectService,
        protected CampaignAdvisorService $advisorService,
    ) {
        parent::__construct($aiService, $knowledgeBase);
    }

    public function getName(): string
    {
        return 'campaign';
    }

    public function getLabel(): string
    {
        return __('brain.campaign.label');
    }

    public function getCapabilities(): array
    {
        return [
            'create_campaign',
            'plan_drip_sequence',
            'analyze_campaign_results',
            'optimize_send_time',
            'suggest_audience',
            'schedule_campaign',
        ];
    }

    /**
     * Check if we need more information before creating a campaign plan.
     */
    public function needsMoreInfo(array $intent, User $user, string $knowledgeContext = ''): bool
    {
        $params = $intent['parameters'] ?? [];
        // If user already provided details via the info-gathering step, no more info needed
        if (!empty($params['user_details'])) {
            return false;
        }
        // Need at least topic/goal/product info to create a meaningful campaign
        return empty($params['topic']) && empty($params['goal']) && empty($params['product']);
    }

    /**
     * Generate campaign-specific questions for the user.
     */
    public function getInfoQuestions(array $intent, User $user, string $knowledgeContext = ''): string
    {
        // Fetch available lists for context
        $lists = ContactList::where('user_id', $user->id)->withCount('subscribers')->get();
        $listsInfo = $lists->map(fn($l) => "• {$l->name} ({$l->subscribers_count} subskrybentów)")->join("\n");

        $response = "📧 **Tworzenie kampanii — potrzebuję szczegółów:**\n\n"
            . "1. **Cel kampanii** — co chcesz osiągnąć? (np. promocja produktu, newsletter, welcome email)\n"
            . "2. **Temat/produkt** — o czym ma być wiadomość?\n"
            . "3. **Ton komunikacji** — formalny, przyjazny, promujący?\n"
            . "4. **Grupa docelowa** — do kogo wysłać?\n";

        if ($listsInfo) {
            $response .= "\n📋 **Twoje listy:**\n{$listsInfo}\n";
        }

        $response .= "\n5. **Kiedy wysłać?** — natychmiast, zaplanować na konkretny termin?\n\n"
            . "Podaj tyle szczegółów ile możesz, a ja przygotuję profesjonalny plan kampanii.";

        return $response;
    }

    /**
     * Create a plan for campaign-related actions.
     */
    public function plan(array $intent, User $user, string $knowledgeContext = ''): ?AiActionPlan
    {
        $intentDesc = $intent['intent'];
        $paramsJson = json_encode($intent['parameters'] ?? []);

        $prompt = <<<PROMPT
Jesteś ekspertem email marketingu. Użytkownik chce wykonać następującą akcję:
Intencja: {$intentDesc}
Parametry: {$paramsJson}

{$knowledgeContext}

Stwórz szczegółowy plan kampanii. Odpowiedz w JSON:
{
  "title": "krótki tytuł planu",
  "description": "opis co plan osiągnie",
  "steps": [
    {
      "action_type": "typ_akcji",
      "title": "tytuł kroku",
      "description": "opis kroku",
      "config": {}
    }
  ]
}

Dostępne action_types:
- select_audience: wybierz grupę docelową (config: {list_ids: [], segment_criteria: {}})
- generate_content: wygeneruj treść wiadomości (config: {type: "email"|"sms", tone: "", topic: ""})
- create_message: stwórz wiadomość w systemie (config: {subject: "", content_ref: "step_N"})
- schedule_send: zaplanuj wysyłkę (config: {send_at: "datetime|immediate", list_id: N})
- create_automation: stwórz automatyzację (config: {trigger: "", actions: []})
- analyze_results: analizuj wyniki po wysyłce (config: {campaign_id: N, wait_hours: 24})
PROMPT;

        try {
            $response = $this->callAi($prompt, ['max_tokens' => 2000, 'temperature' => 0.3], $user, 'campaign');
            $data = $this->parseJson($response);

            if (!$data || empty($data['steps'])) {
                return null;
            }

            return $this->createPlan(
                $user,
                $intent['intent'] ?? 'campaign',
                $data['title'] ?? __('brain.campaign.plan_title'),
                $data['description'] ?? null,
                $data['steps']
            );
        } catch (\Exception $e) {
            Log::error('CampaignAgent plan failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Execute a campaign plan.
     */
    public function execute(AiActionPlan $plan, User $user): array
    {
        $steps = $plan->steps()->orderBy('step_order')->get();
        $stepReports = [];
        $hasErrors = false;

        foreach ($steps as $step) {
            try {
                $result = $this->executeStep($step, $user);
                $detail = $result['message'] ?? '';
                $stepReports[] = "  {$step->step_order}. ✅ **{$step->title}**" . ($detail ? "\n     ↳ {$detail}" : '');
            } catch (\Exception $e) {
                $hasErrors = true;
                $stepReports[] = "  {$step->step_order}. ❌ **{$step->title}**\n     ↳ {$e->getMessage()}";
            }
        }

        $completedCount = count(array_filter($stepReports, fn($r) => str_contains($r, '✅')));
        $icon = $hasErrors ? '⚠️' : '✅';
        $report = "{$icon} **{$plan->title}**\n";

        if ($plan->description) {
            $report .= "{$plan->description}\n";
        }

        $report .= "\n📋 **Wykonane kroki** ({$completedCount}/{$plan->total_steps}):\n"
            . implode("\n", $stepReports);

        return [
            'type' => 'execution_result',
            'message' => $report,
        ];
    }

    /**
     * Execute a specific campaign step action.
     */
    protected function executeStepAction(AiActionPlanStep $step, User $user): array
    {
        return match ($step->action_type) {
            'select_audience' => $this->executeSelectAudience($step, $user),
            'generate_content' => $this->executeGenerateContent($step, $user),
            'create_message' => $this->executeCreateMessage($step, $user),
            'schedule_send' => $this->executeScheduleSend($step, $user),
            default => ['status' => 'completed', 'message' => "Action '{$step->action_type}' noted"],
        };
    }

    /**
     * Advise on campaign actions (manual mode).
     */
    public function advise(array $intent, User $user, string $knowledgeContext = ''): array
    {
        $intentDesc = $intent['intent'];
        $paramsJson = json_encode($intent['parameters'] ?? []);

        $prompt = <<<PROMPT
Jesteś ekspertem email marketingu. Użytkownik pracuje w trybie manualnym i potrzebuje porady.
Intencja: {$intentDesc}
Parametry: {$paramsJson}

{$knowledgeContext}

Podaj szczegółowe instrukcje krok po kroku, jak użytkownik powinien to zrobić ręcznie w panelu NetSendo.
Uwzględnij best practices i wskazówki optymalizacyjne.
Odpowiedz w czytelnym formacie z emoji.
PROMPT;

        $response = $this->callAi($prompt, ['max_tokens' => 2000, 'temperature' => 0.5], $user, 'campaign');

        return [
            'type' => 'advice',
            'message' => $response,
        ];
    }

    // === Step Executors ===

    protected function executeSelectAudience(AiActionPlanStep $step, User $user): array
    {
        $config = $step->config;
        $listIds = $config['list_ids'] ?? [];

        if (empty($listIds)) {
            // Auto-select best lists based on criteria
            $lists = ContactList::where('user_id', $user->id)
                ->withCount('subscribers')
                ->orderByDesc('subscribers_count')
                ->limit(3)
                ->get();

            return [
                'status' => 'completed',
                'selected_lists' => $lists->pluck('id')->toArray(),
                'total_subscribers' => $lists->sum('subscribers_count'),
                'message' => __('brain.campaign.audience_selected', ['count' => $lists->count(), 'subscribers' => $lists->sum('subscribers_count')]),
            ];
        }

        $lists = ContactList::whereIn('id', $listIds)
            ->where('user_id', $user->id)
            ->get();

        return [
            'status' => 'completed',
            'selected_lists' => $lists->pluck('id')->toArray(),
            'total_subscribers' => $lists->sum('subscribers_count'),
        ];
    }

    protected function executeGenerateContent(AiActionPlanStep $step, User $user): array
    {
        $config = $step->config;
        $knowledgeContext = $this->knowledgeBase->getContext($user, 'message');

        $type = $config['type'] ?? 'email';
        $tone = $config['tone'] ?? 'profesjonalny';
        $topic = $config['topic'] ?? '';

        $prompt = <<<PROMPT
Wygeneruj treść {$type} wiadomości marketingowej.

Temat/cel: {$topic}
Ton komunikacji: {$tone}

{$knowledgeContext}

Odpowiedz w JSON:
{
  "subject": "temat wiadomości (jeśli email)",
  "preview_text": "tekst podglądu (jeśli email)",
  "content": "treść wiadomości (HTML dla email, tekst dla SMS)",
  "cta_text": "tekst przycisku CTA",
  "variants": [
    {"subject": "alternatywny temat 1"},
    {"subject": "alternatywny temat 2"}
  ]
}
PROMPT;

        $response = $this->callAi($prompt, ['max_tokens' => 3000, 'temperature' => 0.7], $user, 'content_generation');
        $data = $this->parseJson($response);

        return [
            'status' => 'completed',
            'generated_content' => $data,
        ];
    }

    protected function executeCreateMessage(AiActionPlanStep $step, User $user): array
    {
        $config = $step->config;

        // Look for generated content from a previous step
        $plan = $step->plan;
        $contentStep = $plan->steps()
            ->where('action_type', 'generate_content')
            ->where('status', 'completed')
            ->first();

        $content = $contentStep?->result['generated_content'] ?? null;
        $subject = $config['subject'] ?? $content['subject'] ?? __('brain.campaign.default_message');
        $body = $content['content'] ?? '<p>Treść wiadomości</p>';

        $message = Message::create([
            'user_id' => $user->id,
            'name' => $subject,
            'subject' => $subject,
            'preheader' => $content['preview_text'] ?? '',
            'content' => $body,
            'type' => 'email',
            'status' => 'draft',
        ]);

        return [
            'status' => 'completed',
            'message_id' => $message->id,
            'message' => __('brain.campaign.message_created', ['subject' => $subject, 'id' => $message->id]),
        ];
    }

    protected function executeScheduleSend(AiActionPlanStep $step, User $user): array
    {
        // For safety, we create as draft — actual sending requires manual confirmation
        return [
            'status' => 'completed',
            'message' => __('brain.campaign.schedule_ready'),
            'note' => 'Auto-send disabled for safety — manual scheduling required',
        ];
    }
}
