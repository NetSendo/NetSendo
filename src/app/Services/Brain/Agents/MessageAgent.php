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

        $langInstruction = $this->getLanguageInstruction($user);

        $prompt = <<<PROMPT
You are an email/SMS copywriting expert. The user wants:
Intent: {$intentDesc}
Parameters: {$paramsJson}

{$knowledgeContext}

{$langInstruction}

Create a content creation plan. Respond in JSON:
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
- generate_subject: generate subject line variants (config: {topic: "", count: 5, tone: ""})
- generate_body: generate content (config: {type: "email"|"sms", topic: "", tone: "", length: ""})
- create_message: save message as draft (config: {subject: "", type: "email"|"sms"})
- generate_ab_variants: generate A/B variants (config: {original_subject: "", count: 3})
- improve_content: improve existing content (config: {message_id: N, improvements: []})
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
        $langInstruction = $this->getLanguageInstruction($user);

        $prompt = <<<PROMPT
Advise the user on creating email/SMS content. Manual mode.

Intent: {$intent['intent']}
{$knowledgeContext}

{$langInstruction}

Provide:
1. Copywriting tips
2. Example message subjects
3. Content structure
4. Best practices for email/SMS marketing
5. Personalization tips

Respond with emoji and formatting.
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

        $langInstruction = $this->getLanguageInstruction($user);

        $prompt = <<<PROMPT
Generate {$count} email subject line variants.

Topic/goal: {$topic}
Tone: {$tone}

{$knowledgeContext}

{$langInstruction}

Respond in JSON:
{
  "subjects": [
    {"subject": "...", "emoji": true/false, "type": "benefit|curiosity|urgency|question|personalization"},
    ...
  ]
}

Rules:
- Maximum 50 characters per subject
- One subject with emoji, rest without
- Use different copywriting techniques
- Avoid spammy words
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
            $langInstruction = $this->getLanguageInstruction($user);

            $prompt = <<<PROMPT
Generate SMS marketing content (max 160 characters).

Topic: {$topic}
Tone: {$tone}

{$knowledgeContext}

{$langInstruction}

Respond in JSON: {"content": "SMS content", "characters": N}
PROMPT;
        } else {
            $langInstruction = $this->getLanguageInstruction($user);

            $prompt = <<<PROMPT
Generate marketing email content in HTML.

Topic: {$topic}
Tone: {$tone}
Length: {$length}

{$knowledgeContext}

{$langInstruction}

Respond in JSON:
{
  "subject": "subject",
  "preview_text": "preview text (max 90 characters)",
  "html_content": "<html email content>",
  "plain_text": "plain text version",
  "cta_text": "button text"
}

Rules:
- Responsive HTML
- Clear CTA
- Short paragraphs
- Personalization: use {{first_name}} as placeholder
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

        $langInstruction = $this->getLanguageInstruction($user);

        $prompt = <<<PROMPT
Generate {$count} A/B variants for the email subject line.

Original subject: {$original}

{$langInstruction}

Respond in JSON:
{
  "variants": [
    {"subject": "...", "hypothesis": "why this variant might perform better"},
    ...
  ]
}

Each variant should test a different variable (CTA, personalization, urgency, etc.)
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

        $langInstruction = $this->getLanguageInstruction($user);

        $prompt = <<<PROMPT
Improve marketing email content.

CURRENT SUBJECT: {$message->subject}
CURRENT CONTENT: {$message->content}

Required improvements: {$improvements}

{$langInstruction}

Respond in JSON:
{
  "improved_subject": "...",
  "improved_content": "...",
  "changes_made": ["change 1", "change 2"]
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
