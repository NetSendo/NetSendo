<?php

namespace App\Services\Brain\Agents;

use App\Models\AiActionPlan;
use App\Models\AiActionPlanStep;
use App\Models\Message;
use App\Models\Template;
use App\Models\User;
use App\Services\TemplateAiService;
use Illuminate\Support\Facades\Log;

class MessageAgent extends BaseAgent
{
    public function getName(): string
    {
        return 'message';
    }

    public function getLabel(): string
    {
        return __('brain.message.label');
    }

    public function getCapabilities(): array
    {
        return [
            'generate_email_content',
            'generate_sms_content',
            'create_subject_line_variants',
            'improve_existing_content',
            'create_email_template',
            'translate_content',
        ];
    }

    /**
     * Create a plan for message content operations.
     */
    public function plan(array $intent, User $user, string $knowledgeContext = ''): ?AiActionPlan
    {
        $intentDesc = $intent['intent'];
        $paramsJson = json_encode($intent['parameters'] ?? []);

        $prompt = <<<PROMPT
Jesteś ekspertem copywritingu email/SMS. Użytkownik chce:
Intencja: {$intentDesc}
Parametry: {$paramsJson}

{$knowledgeContext}

Stwórz plan tworzenia treści. Odpowiedz w JSON:
{
  "title": "tytuł planu",
  "description": "opis",
  "steps": [
    {
      "action_type": "typ",
      "title": "tytuł kroku",
      "description": "opis",
      "config": {}
    }
  ]
}

Dostępne action_types:
- generate_subject: wygeneruj warianty tematu (config: {topic: "", count: 5, tone: ""})
- generate_body: wygeneruj treść (config: {type: "email"|"sms", topic: "", tone: "", length: ""})
- create_message: zapisz wiadomość jako szkic (config: {subject: "", type: "email"|"sms"})
- generate_ab_variants: wygeneruj warianty A/B (config: {original_subject: "", count: 3})
- improve_content: popraw istniejącą treść (config: {message_id: N, improvements: []})
PROMPT;

        try {
            $response = $this->callAi($prompt, ['max_tokens' => 1500, 'temperature' => 0.3]);
            $data = $this->parseJson($response);

            if (!$data || empty($data['steps'])) {
                return null;
            }

            return $this->createPlan(
                $user,
                $intent['intent'] ?? 'message_content',
                $data['title'] ?? __('brain.message.plan_title'),
                $data['description'] ?? null,
                $data['steps']
            );
        } catch (\Exception $e) {
            Log::error('MessageAgent plan failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Execute message content plan.
     */
    public function execute(AiActionPlan $plan, User $user): array
    {
        $steps = $plan->steps()->orderBy('step_order')->get();
        $generatedContent = [];
        $messages = [];

        foreach ($steps as $step) {
            try {
                $result = $this->executeStep($step, $user);
                $generatedContent[$step->action_type] = $result;
                $messages[] = $result['message'] ?? "✅ {$step->title}";
            } catch (\Exception $e) {
                $messages[] = "❌ {$step->title}: {$e->getMessage()}";
            }
        }

        return [
            'type' => 'execution_result',
            'message' => __('brain.message.content_ready') . "\n\n" . implode("\n", $messages),
            'generated_content' => $generatedContent,
        ];
    }

    /**
     * Execute specific message step actions.
     */
    protected function executeStepAction(AiActionPlanStep $step, User $user): array
    {
        return match ($step->action_type) {
            'generate_subject' => $this->executeGenerateSubject($step, $user),
            'generate_body' => $this->executeGenerateBody($step, $user),
            'create_message' => $this->executeCreateMessage($step, $user),
            'generate_ab_variants' => $this->executeGenerateAbVariants($step, $user),
            'improve_content' => $this->executeImproveContent($step, $user),
            default => ['status' => 'completed', 'message' => "Noted: {$step->action_type}"],
        };
    }

    /**
     * Advise on message creation.
     */
    public function advise(array $intent, User $user, string $knowledgeContext = ''): array
    {
        $prompt = <<<PROMPT
Poradź użytkownikowi w tworzeniu treści email/SMS. Tryb manualny.

Intencja: {$intent['intent']}
{$knowledgeContext}

Podaj:
1. Wskazówki copywritingowe
2. Przykładowe tematy wiadomości
3. Strukturę treści
4. Best practices dla email/SMS marketingu
5. Wskazówki personalizacyjne

Odpowiedz z emoji i formatowaniem.
PROMPT;

        $response = $this->callAi($prompt, ['max_tokens' => 2000, 'temperature' => 0.6]);

        return [
            'type' => 'advice',
            'message' => $response,
        ];
    }

    // === Step Executors ===

    protected function executeGenerateSubject(AiActionPlanStep $step, User $user): array
    {
        $config = $step->config;
        $knowledgeContext = $this->knowledgeBase->getContext($user, 'message');

        $topic = $config['topic'] ?? '';
        $count = $config['count'] ?? 5;
        $tone = $config['tone'] ?? 'profesjonalny';

        $prompt = <<<PROMPT
Wygeneruj {$count} wariantów tematu emaila.

Temat/cel: {$topic}
Ton: {$tone}

{$knowledgeContext}

Odpowiedz w JSON:
{
  "subjects": [
    {"subject": "...", "emoji": true/false, "type": "benefit|curiosity|urgency|question|personalization"},
    ...
  ]
}

Zasady:
- Maksymalnie 50 znaków w temacie
- Jeden temat z emoji, reszta bez
- Różne techniki copywritingowe
- Unikaj spamowych słów
PROMPT;

        $response = $this->callAi($prompt, ['max_tokens' => 1000, 'temperature' => 0.8]);
        $data = $this->parseJson($response);
        $subjects = $data['subjects'] ?? [];

        $subjectList = collect($subjects)->map(fn($s, $i) => ($i + 1) . ". " . $s['subject'])->join("\n");

        return [
            'status' => 'completed',
            'subjects' => $subjects,
            'message' => __('brain.message.subjects_generated', ['count' => $count]) . "\n{$subjectList}",
        ];
    }

    protected function executeGenerateBody(AiActionPlanStep $step, User $user): array
    {
        $config = $step->config;
        $knowledgeContext = $this->knowledgeBase->getContext($user, 'message');

        $type = $config['type'] ?? 'email';
        $topic = $config['topic'] ?? '';
        $tone = $config['tone'] ?? 'profesjonalny';
        $length = $config['length'] ?? 'medium';

        if ($type === 'sms') {
            $prompt = <<<PROMPT
Wygeneruj treść SMS marketingowego (max 160 znaków).

Temat: {$topic}
Ton: {$tone}

{$knowledgeContext}

Odpowiedz w JSON: {"content": "treść SMS", "characters": N}
PROMPT;
        } else {
            $prompt = <<<PROMPT
Wygeneruj treść emaila marketingowego w HTML.

Temat: {$topic}
Ton: {$tone}
Długość: {$length}

{$knowledgeContext}

Odpowiedz w JSON:
{
  "subject": "temat",
  "preview_text": "tekst podglądu (max 90 znaków)",
  "html_content": "<html z treścią maila>",
  "plain_text": "wersja tekstowa",
  "cta_text": "tekst przycisku"
}

Zasady:
- Responsywny HTML
- Wyraźne CTA
- Krótkie akapity
- Personalizacja: użyj {{first_name}} jako placeholder
PROMPT;
        }

        $response = $this->callAi($prompt, ['max_tokens' => 3000, 'temperature' => 0.7]);
        $data = $this->parseJson($response);

        return [
            'status' => 'completed',
            'content' => $data,
            'message' => __('brain.message.body_generated', ['type' => $type]) . ($data['subject'] ?? ''),
        ];
    }

    protected function executeCreateMessage(AiActionPlanStep $step, User $user): array
    {
        $config = $step->config;
        $type = $config['type'] ?? 'email';

        // Look for generated content from previous steps
        $plan = $step->plan;
        $bodyStep = $plan->steps()
            ->where('action_type', 'generate_body')
            ->where('status', 'completed')
            ->first();

        $content = $bodyStep?->result['content'] ?? null;
        $subject = $config['subject'] ?? $content['subject'] ?? __('brain.message.default_message');

        $message = Message::create([
            'user_id' => $user->id,
            'name' => $subject,
            'subject' => $subject,
            'preheader' => $content['preview_text'] ?? '',
            'content' => $content['html_content'] ?? $content['content'] ?? '',
            'type' => $type,
            'status' => 'draft',
        ]);

        return [
            'status' => 'completed',
            'message_id' => $message->id,
            'message' => __('brain.message.message_saved', ['subject' => $subject, 'id' => $message->id]),
        ];
    }

    protected function executeGenerateAbVariants(AiActionPlanStep $step, User $user): array
    {
        $config = $step->config;
        $original = $config['original_subject'] ?? '';
        $count = $config['count'] ?? 3;

        $prompt = <<<PROMPT
Wygeneruj {$count} wariantów A/B dla tematu emaila.

Oryginalny temat: {$original}

Odpowiedz w JSON:
{
  "variants": [
    {"subject": "...", "hypothesis": "dlaczego ten wariant może być lepszy"},
    ...
  ]
}

Każdy wariant powinien testować inną zmienną (CTA, personalizacja, urgency, etc.)
PROMPT;

        $response = $this->callAi($prompt, ['max_tokens' => 1000, 'temperature' => 0.8]);
        $data = $this->parseJson($response);

        $variants = $data['variants'] ?? [];
        $variantList = collect($variants)->map(fn($v, $i) => ($i + 1) . ". {$v['subject']}\n   ↳ {$v['hypothesis']}")->join("\n");

        return [
            'status' => 'completed',
            'variants' => $variants,
            'message' => __('brain.message.ab_variants') . "\n{$variantList}",
        ];
    }

    protected function executeImproveContent(AiActionPlanStep $step, User $user): array
    {
        $config = $step->config;
        $messageId = $config['message_id'] ?? null;

        if (!$messageId) {
            return ['status' => 'completed', 'message' => __('brain.message.no_message_id')];
        }

        $message = Message::where('id', $messageId)->where('user_id', $user->id)->first();
        if (!$message) {
            return ['status' => 'completed', 'message' => __('brain.message.message_not_found', ['id' => $messageId])];
        }

        $improvements = implode(', ', $config['improvements'] ?? ['better CTA', 'more engaging']);

        $prompt = <<<PROMPT
Popraw treść emaila marketingowego.

AKTUALNY TEMAT: {$message->subject}
AKTUALNA TREŚĆ: {$message->content}

Wymagane ulepszenia: {$improvements}

Odpowiedz w JSON:
{
  "improved_subject": "...",
  "improved_content": "...",
  "changes_made": ["zmiana 1", "zmiana 2"]
}
PROMPT;

        $response = $this->callAi($prompt, ['max_tokens' => 3000, 'temperature' => 0.5]);
        $data = $this->parseJson($response);

        if ($data) {
            $message->update([
                'subject' => $data['improved_subject'] ?? $message->subject,
                'content' => $data['improved_content'] ?? $message->content,
            ]);
        }

        $changes = implode(', ', $data['changes_made'] ?? []);

        return [
            'status' => 'completed',
            'message' => __('brain.message.message_improved', ['changes' => $changes]),
        ];
    }
}
