<?php

namespace App\Services\Brain\Agents;

use App\Models\AiActionPlan;
use App\Models\AiActionPlanStep;
use App\Models\CrmCompany;
use App\Models\CrmContact;
use App\Models\CrmDeal;
use App\Models\CrmPipeline;
use App\Models\CrmStage;
use App\Models\CrmTask;
use App\Models\Subscriber;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class CrmAgent extends BaseAgent
{
    public function getName(): string
    {
        return 'crm';
    }

    public function getLabel(): string
    {
        return __('brain.crm.label');
    }

    public function getCapabilities(): array
    {
        return [
            'manage_contacts',
            'manage_deals',
            'manage_tasks',
            'pipeline_overview',
            'contact_scoring',
            'create_company',
        ];
    }

    /**
     * Create a plan for CRM-related actions.
     */
    public function plan(array $intent, User $user, string $knowledgeContext = ''): ?AiActionPlan
    {
        $intentDesc = $intent['intent'];
        $paramsJson = json_encode($intent['parameters'] ?? []);

        $prompt = <<<PROMPT
Jesteś ekspertem CRM i zarządzania kontaktami. Użytkownik chce wykonać następującą akcję:
Intencja: {$intentDesc}
Parametry: {$paramsJson}

{$knowledgeContext}

Stwórz szczegółowy plan CRM. Odpowiedz w JSON:
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
- search_contacts: szukaj kontaktów (config: {query: "", status: "lead|prospect|client", min_score: N})
- create_contact: stwórz kontakt CRM z subskrybenta (config: {email: "", source: "", status: "lead"})
- update_contact_status: zmień status kontaktu (config: {contact_id: N, new_status: "prospect|client"})
- create_deal: stwórz deal w pipeline (config: {name: "", value: N, contact_id: N, pipeline_id: N})
- move_deal_stage: przenieś deal na etap (config: {deal_id: N, stage_name: ""})
- create_task: stwórz zadanie CRM (config: {title: "", type: "call|email|meeting|follow_up", priority: "low|medium|high", contact_id: N, due_days: N})
- score_analysis: analizuj scoring leadów (config: {min_score: N, status: ""})
- pipeline_summary: pokaż podsumowanie pipeline (config: {pipeline_id: N|null})
- create_company: stwórz firmę (config: {name: "", website: "", industry: ""})
PROMPT;

        try {
            $response = $this->callAi($prompt, ['max_tokens' => 2000, 'temperature' => 0.3]);
            $data = $this->parseJson($response);

            if (!$data || empty($data['steps'])) {
                return null;
            }

            return $this->createPlan(
                $user,
                $intent['intent'] ?? 'crm',
                $data['title'] ?? __('brain.crm.plan_title'),
                $data['description'] ?? null,
                $data['steps']
            );
        } catch (\Exception $e) {
            Log::error('CrmAgent plan failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Execute a CRM plan.
     */
    public function execute(AiActionPlan $plan, User $user): array
    {
        $steps = $plan->steps()->orderBy('step_order')->get();
        $results = [];

        foreach ($steps as $step) {
            try {
                $result = $this->executeStep($step, $user);
                $results[] = $result;
            } catch (\Exception $e) {
                return [
                    'type' => 'error',
                    'message' => __('brain.crm.step_error', ['step' => $step->step_order, 'title' => $step->title, 'error' => $e->getMessage()]),
                ];
            }
        }

        $completedCount = count(array_filter($results));
        return [
            'type' => 'execution_result',
            'message' => __('brain.crm.plan_completed', ['completed' => $completedCount, 'total' => $plan->total_steps]),
        ];
    }

    /**
     * Execute a specific CRM step action.
     */
    protected function executeStepAction(AiActionPlanStep $step, User $user): array
    {
        return match ($step->action_type) {
            'search_contacts' => $this->executeSearchContacts($step, $user),
            'create_contact' => $this->executeCreateContact($step, $user),
            'update_contact_status' => $this->executeUpdateContactStatus($step, $user),
            'create_deal' => $this->executeCreateDeal($step, $user),
            'move_deal_stage' => $this->executeMoveDealStage($step, $user),
            'create_task' => $this->executeCreateTask($step, $user),
            'score_analysis' => $this->executeScoreAnalysis($step, $user),
            'pipeline_summary' => $this->executePipelineSummary($step, $user),
            'create_company' => $this->executeCreateCompany($step, $user),
            default => ['status' => 'completed', 'message' => "Action '{$step->action_type}' noted"],
        };
    }

    /**
     * Advise on CRM actions (manual mode).
     */
    public function advise(array $intent, User $user, string $knowledgeContext = ''): array
    {
        $intentDesc = $intent['intent'];
        $paramsJson = json_encode($intent['parameters'] ?? []);

        $prompt = <<<PROMPT
Jesteś ekspertem CRM. Użytkownik pracuje w trybie manualnym i potrzebuje porady.
Intencja: {$intentDesc}
Parametry: {$paramsJson}

{$knowledgeContext}

Podaj szczegółowe instrukcje krok po kroku, jak użytkownik powinien to zrobić ręcznie w panelu NetSendo CRM.
Uwzględnij best practices zarządzania kontaktami i pipeline sprzedażowym.
Odpowiedz w czytelnym formacie z emoji.
PROMPT;

        $response = $this->callAi($prompt, ['max_tokens' => 2000, 'temperature' => 0.5]);

        return [
            'type' => 'advice',
            'message' => $response,
        ];
    }

    // === Step Executors ===

    protected function executeSearchContacts(AiActionPlanStep $step, User $user): array
    {
        $config = $step->config;
        $query = CrmContact::forUser($user->id)->with('subscriber');

        if (!empty($config['status'])) {
            $query->withStatus($config['status']);
        }

        if (!empty($config['min_score'])) {
            $query->where('score', '>=', $config['min_score']);
        }

        if (!empty($config['query'])) {
            $searchTerm = $config['query'];
            $query->whereHas('subscriber', function ($q) use ($searchTerm) {
                $q->where('email', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('first_name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('last_name', 'LIKE', "%{$searchTerm}%");
            });
        }

        $contacts = $query->orderByDesc('score')->limit(20)->get();

        $summary = $contacts->map(fn($c) => "{$c->full_name} ({$c->email}) — score: {$c->score}, status: {$c->status}")->join("\n");

        return [
            'status' => 'completed',
            'count' => $contacts->count(),
            'contacts' => $contacts->pluck('id')->toArray(),
            'message' => __('brain.crm.contacts_found', ['count' => $contacts->count()]) . "\n{$summary}",
        ];
    }

    protected function executeCreateContact(AiActionPlanStep $step, User $user): array
    {
        $config = $step->config;
        $email = $config['email'] ?? null;

        if (empty($email)) {
            return ['status' => 'failed', 'message' => __('brain.crm.email_missing')];
        }

        // Find or create subscriber first
        $subscriber = Subscriber::where('user_id', $user->id)
            ->where('email', $email)
            ->first();

        if (!$subscriber) {
            return ['status' => 'failed', 'message' => __('brain.crm.subscriber_not_found', ['email' => $email])];
        }

        // Check if CRM contact already exists
        $existing = CrmContact::where('subscriber_id', $subscriber->id)->first();
        if ($existing) {
            return [
                'status' => 'completed',
                'contact_id' => $existing->id,
                'message' => __('brain.crm.contact_exists', ['email' => $email, 'id' => $existing->id]),
            ];
        }

        $contact = CrmContact::createFromSubscriber($subscriber, [
            'source' => $config['source'] ?? 'ai_brain',
            'status' => $config['status'] ?? 'lead',
        ]);

        return [
            'status' => 'completed',
            'contact_id' => $contact->id,
            'message' => __('brain.crm.contact_created', ['name' => $contact->full_name, 'id' => $contact->id]),
        ];
    }

    protected function executeUpdateContactStatus(AiActionPlanStep $step, User $user): array
    {
        $config = $step->config;
        $contactId = $config['contact_id'] ?? null;
        $newStatus = $config['new_status'] ?? null;

        if (!$contactId || !$newStatus) {
            return ['status' => 'failed', 'message' => __('brain.crm.missing_contact_status')];
        }

        $contact = CrmContact::forUser($user->id)->findOrFail($contactId);
        $oldStatus = $contact->status;
        $contact->update(['status' => $newStatus]);

        $contact->logActivity('status_changed', __('brain.crm.status_changed_log', ['old' => $oldStatus, 'new' => $newStatus]));

        return [
            'status' => 'completed',
            'message' => __('brain.crm.status_changed', ['name' => $contact->full_name, 'old' => $oldStatus, 'new' => $newStatus]),
        ];
    }

    protected function executeCreateDeal(AiActionPlanStep $step, User $user): array
    {
        $config = $step->config;

        // Get or create default pipeline
        $pipelineId = $config['pipeline_id'] ?? null;
        $pipeline = $pipelineId
            ? CrmPipeline::forUser($user->id)->findOrFail($pipelineId)
            : CrmPipeline::getDefaultForUser($user->id);

        if (!$pipeline) {
            return ['status' => 'failed', 'message' => __('brain.crm.no_pipeline')];
        }

        $firstStage = $pipeline->getFirstStage();
        if (!$firstStage) {
            return ['status' => 'failed', 'message' => __('brain.crm.no_stages')];
        }

        $deal = CrmDeal::create([
            'user_id' => $user->id,
            'crm_pipeline_id' => $pipeline->id,
            'crm_stage_id' => $firstStage->id,
            'crm_contact_id' => $config['contact_id'] ?? null,
            'crm_company_id' => $config['company_id'] ?? null,
            'owner_id' => $user->id,
            'name' => $config['name'] ?? __('brain.crm.deal_default_name'),
            'value' => $config['value'] ?? 0,
            'currency' => $config['currency'] ?? 'PLN',
            'expected_close_date' => now()->addDays($config['close_days'] ?? 30),
            'status' => 'open',
        ]);

        return [
            'status' => 'completed',
            'deal_id' => $deal->id,
            'message' => __('brain.crm.deal_created', ['name' => $deal->name, 'value' => $deal->formatted_value, 'pipeline' => $pipeline->name, 'stage' => $firstStage->name]),
        ];
    }

    protected function executeMoveDealStage(AiActionPlanStep $step, User $user): array
    {
        $config = $step->config;
        $dealId = $config['deal_id'] ?? null;
        $stageName = $config['stage_name'] ?? null;

        if (!$dealId || !$stageName) {
            return ['status' => 'failed', 'message' => __('brain.crm.missing_deal_stage')];
        }

        $deal = CrmDeal::forUser($user->id)->findOrFail($dealId);

        $newStage = CrmStage::where('crm_pipeline_id', $deal->crm_pipeline_id)
            ->where('name', 'LIKE', "%{$stageName}%")
            ->first();

        if (!$newStage) {
            return ['status' => 'failed', 'message' => __('brain.crm.stage_not_found', ['stage' => $stageName])];
        }

        $deal->moveToStage($newStage, $user->id);

        return [
            'status' => 'completed',
            'message' => __('brain.crm.deal_moved', ['name' => $deal->name, 'stage' => $newStage->name]),
        ];
    }

    protected function executeCreateTask(AiActionPlanStep $step, User $user): array
    {
        $config = $step->config;

        $task = CrmTask::create([
            'user_id' => $user->id,
            'owner_id' => $user->id,
            'crm_contact_id' => $config['contact_id'] ?? null,
            'crm_deal_id' => $config['deal_id'] ?? null,
            'title' => $config['title'] ?? __('brain.crm.task_default_title'),
            'type' => $config['type'] ?? 'follow_up',
            'priority' => $config['priority'] ?? 'medium',
            'due_date' => now()->addDays($config['due_days'] ?? 2),
            'notes' => $config['notes'] ?? null,
        ]);

        return [
            'status' => 'completed',
            'task_id' => $task->id,
            'message' => __('brain.crm.task_created', ['title' => $task->title, 'priority' => $task->priority, 'due_date' => $task->due_date->format('d.m.Y')]),
        ];
    }

    protected function executeScoreAnalysis(AiActionPlanStep $step, User $user): array
    {
        $config = $step->config;
        $minScore = $config['min_score'] ?? 0;

        $contacts = CrmContact::forUser($user->id)
            ->where('score', '>=', $minScore)
            ->orderByDesc('score')
            ->limit(20)
            ->get();

        $hotLeads = CrmContact::forUser($user->id)->hotLeads()->count();
        $avgScore = CrmContact::forUser($user->id)->avg('score') ?? 0;
        $totalContacts = CrmContact::forUser($user->id)->count();

        $topContacts = $contacts->take(5)->map(fn($c) =>
            "  • {$c->full_name} — score: {$c->score} ({$c->status}) trend: {$c->getScoreTrend()}"
        )->join("\n");

        $summary = __('brain.crm.score_header') . "\n\n";
        $summary .= __('brain.crm.score_total', ['count' => $totalContacts]) . "\n";
        $summary .= __('brain.crm.score_avg', ['avg' => round($avgScore, 1)]) . "\n";
        $summary .= __('brain.crm.score_hot', ['count' => $hotLeads]) . "\n\n";
        $summary .= __('brain.crm.score_top5') . "\n{$topContacts}";

        return [
            'status' => 'completed',
            'total_contacts' => $totalContacts,
            'hot_leads' => $hotLeads,
            'avg_score' => round($avgScore, 1),
            'message' => $summary,
        ];
    }

    protected function executePipelineSummary(AiActionPlanStep $step, User $user): array
    {
        $config = $step->config;
        $pipelineId = $config['pipeline_id'] ?? null;

        $pipeline = $pipelineId
            ? CrmPipeline::forUser($user->id)->with(['stages', 'deals'])->findOrFail($pipelineId)
            : CrmPipeline::forUser($user->id)->where('is_default', true)->with(['stages', 'deals'])->first();

        if (!$pipeline) {
            return ['status' => 'completed', 'message' => __('brain.crm.no_pipeline_display')];
        }

        $summary = __('brain.crm.pipeline_header', ['name' => $pipeline->name]) . "\n\n";

        foreach ($pipeline->stages as $stage) {
            $deals = CrmDeal::where('crm_stage_id', $stage->id)
                ->where('status', 'open')
                ->get();

            $totalValue = $deals->sum('value');
            $count = $deals->count();

            $emoji = $stage->is_won ? '🎉' : ($stage->is_lost ? '❌' : '📌');
            $summary .= "{$emoji} **{$stage->name}**: {$count} deals";
            if ($totalValue > 0) {
                $summary .= " — " . number_format($totalValue, 2, ',', ' ') . " PLN";
            }
            $summary .= "\n";
        }

        $totalOpen = CrmDeal::where('crm_pipeline_id', $pipeline->id)->open()->count();
        $totalValue = CrmDeal::where('crm_pipeline_id', $pipeline->id)->open()->sum('value');
        $summary .= "\n" . __('brain.crm.pipeline_total_open', ['count' => $totalOpen, 'value' => number_format($totalValue, 2, ',', ' ')]);

        return [
            'status' => 'completed',
            'pipeline_id' => $pipeline->id,
            'message' => $summary,
        ];
    }

    protected function executeCreateCompany(AiActionPlanStep $step, User $user): array
    {
        $config = $step->config;

        $company = CrmCompany::create([
            'user_id' => $user->id,
            'name' => $config['name'] ?? __('brain.crm.company_default_name'),
            'website' => $config['website'] ?? null,
            'industry' => $config['industry'] ?? null,
            'notes' => $config['notes'] ?? null,
        ]);

        return [
            'status' => 'completed',
            'company_id' => $company->id,
            'message' => __('brain.crm.company_created', ['name' => $company->name, 'id' => $company->id]),
        ];
    }
}
