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

        $personalization = $this->getPersonalizationInstructions();

        $prompt = <<<PROMPT
You are an email/SMS copywriting expert. The user wants:
Intent: {$intentDesc}
Parameters: {$paramsJson}

{$knowledgeContext}

{$langInstruction}

You have access to the following personalization variables that can be used in email/SMS content:
{$personalization}

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
            $response = $this->callAi($prompt, ['max_tokens' => 3000, 'temperature' => 0.3]);
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

        $personalization = $this->getPersonalizationInstructions();

        $prompt = <<<PROMPT
Advise the user on creating email/SMS content. Manual mode.

Intent: {$intent['intent']}
{$knowledgeContext}

{$langInstruction}

The platform supports the following personalization variables:
{$personalization}

Provide:
1. Copywriting tips
2. Example message subjects
3. Content structure
4. Best practices for email/SMS marketing
5. Personalization tips — mention the available variables listed above with examples of how to use them effectively

Respond with emoji and formatting.
PROMPT;

        $response = $this->callAi($prompt, ['max_tokens' => 4000, 'temperature' => 0.6]);

        return [
            'type' => 'advice',
            'message' => $response,
        ];
    }

    // === Helpers ===

    /**
     * Get personalization instructions for AI prompts.
     * Lists all available NetSendo insert variables with correct syntax.
     */
    protected function getPersonalizationInstructions(): string
    {
        return <<<'VARS'
PERSONALIZATION VARIABLES (inserts):
Use double square brackets [[variable]] for personalization. NEVER use curly braces for field names.
Gender-dependent word forms use a SPECIAL syntax: {{male_form|female_form}}.

AVAILABLE VARIABLES:
  Subscriber data:
  - [[fname]] — First name (e.g. "Anna")
  - [[!fname]] — First name in VOCATIVE case / Polish declension (e.g. "Anno", "Grzegorzu")
  - [[lname]] — Last name
  - [[email]] — Email address
  - [[phone]] — Phone number
  - [[sex]] — Gender (M/F)

  Gender-dependent forms (special syntax with curly braces):
  - {{male_word|female_word}} — Inserts the correct word based on subscriber's gender
  - Examples: {{Drogi|Droga}}, {{Byłeś|Byłaś}}, {{zainteresowany|zainteresowana}}

  Links:
  - [[unsubscribe]] — Unsubscribe link
  - [[manage]] — Manage subscription preferences link

  Dates:
  - [[system-created]] — Account creation date
  - [[last-message]] — Last message date
  - [[list-created]] — List signup date

EXAMPLES:
  "{{Drogi|Droga}} [[!fname]], dziękujemy za subskrypcję!"
  "Cześć [[fname]]! {{Byłeś|Byłaś}} ostatnio u nas [[last-message]]."
  "[[fname]], mamy dla Ciebie coś specjalnego!"

IMPORTANT:
- Use [[fname]] or [[!fname]] for personalization — NEVER {{first_name}} or {first_name}
- Gender forms ONLY use curly braces: {{male|female}}
- All other variables use square brackets: [[variable_name]]
VARS;
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

        $personalization = $this->getPersonalizationInstructions();

        $prompt = <<<PROMPT
Generate {$count} email subject line variants.

Topic/goal: {$topic}
Tone: {$tone}

{$knowledgeContext}

{$langInstruction}

{$personalization}

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
- Use [[fname]] or [[!fname]] for at least one personalized subject variant
PROMPT;

        $response = $this->callAi($prompt, ['max_tokens' => 2000, 'temperature' => 0.8]);
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
            $personalization = $this->getPersonalizationInstructions();

            $prompt = <<<PROMPT
Generate marketing email content in HTML.

Topic: {$topic}
Tone: {$tone}
Length: {$length}

{$knowledgeContext}

{$langInstruction}

{$personalization}

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
- Use personalization variables from the list above (e.g. [[fname]], [[!fname]], {{male|female}} forms)
- NEVER use {{first_name}} or {first_name} — always use [[fname]] or [[!fname]]
PROMPT;
        }

        $response = $this->callAi($prompt, ['max_tokens' => 6000, 'temperature' => 0.7]);
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

        $personalization = $this->getPersonalizationInstructions();

        $prompt = <<<PROMPT
Generate {$count} A/B variants for the email subject line.

Original subject: {$original}

{$langInstruction}

{$personalization}

Respond in JSON:
{
  "variants": [
    {"subject": "...", "hypothesis": "why this variant might perform better"},
    ...
  ]
}

Each variant should test a different variable (CTA, personalization, urgency, etc.)
Consider using [[fname]] or [[!fname]] in at least one variant to test personalization impact.
PROMPT;

        $response = $this->callAi($prompt, ['max_tokens' => 2000, 'temperature' => 0.8]);
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

        $personalization = $this->getPersonalizationInstructions();

        $prompt = <<<PROMPT
Improve marketing email content.

CURRENT SUBJECT: {$message->subject}
CURRENT CONTENT: {$message->content}

Required improvements: {$improvements}

{$langInstruction}

{$personalization}

Respond in JSON:
{
  "improved_subject": "...",
  "improved_content": "...",
  "changes_made": ["change 1", "change 2"]
}

IMPORTANT: Preserve any existing personalization variables in the content (e.g. [[fname]], [[!fname]], {{...|...}}).
If the content lacks personalization, consider adding [[fname]] or [[!fname]] where natural.
PROMPT;

        $response = $this->callAi($prompt, ['max_tokens' => 6000, 'temperature' => 0.5]);
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
