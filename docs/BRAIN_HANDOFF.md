# NetSendo Brain — Handoff Document

> **Last updated:** 2026-02-18  
> **Status:** Phase 1 ✅ | Phase 2 ✅ | Phase 3.1 ✅ | Phase 3.2 ✅ | Phase 4 ⏳ pending

This document provides full context for the next agent continuing development of the NetSendo Brain AI system.

---

## Project Overview

**NetSendo Brain** is a multi-agent AI marketing assistant embedded into the NetSendo email/SMS marketing platform. It allows users to interact via chat (web, API, Telegram) to manage campaigns, subscribers, CRM, analytics, and segmentation using natural language.

The system uses a **plan → approve → execute** pattern with three work modes:

- **Autonomous** — AI plans and executes automatically
- **Semi-Auto** — AI proposes plan, user approves, then AI executes
- **Manual** — AI only advises, user acts manually

---

## Architecture

```
src/app/Services/Brain/
├── AgentOrchestrator.php         # Central brain: intent classification, agent routing
├── ConversationManager.php       # Multi-channel conversation handling
├── ModeController.php            # Work mode + approval flow management
├── KnowledgeBaseService.php      # Knowledge base CRUD + context injection
├── Agents/
│   ├── BaseAgent.php             # Abstract base: plan/execute/advise contract
│   ├── CampaignAgent.php         # Phase 1 — campaign planning & scheduling
│   ├── ListAgent.php             # Phase 1 — list management & cleanup
│   ├── MessageAgent.php          # Phase 1 — email/SMS content generation
│   ├── CrmAgent.php              # Phase 2 — contacts, deals, tasks, pipeline
│   ├── AnalyticsAgent.php        # Phase 2 — read-only stats & reports
│   └── SegmentationAgent.php     # Phase 2 — tags, scoring segments, automation
└── Telegram/
    ├── TelegramBotService.php    # Telegram webhook processing & commands
    └── TelegramAuthService.php   # Account linking via codes
```

### Key Models (8 Brain-specific)

| Model                   | Table                      | Purpose                                        |
| ----------------------- | -------------------------- | ---------------------------------------------- |
| `AiBrainSettings`       | `ai_brain_settings`        | Per-user config (work mode, temperature, etc.) |
| `AiConversation`        | `ai_conversations`         | Multi-channel conversations                    |
| `AiConversationMessage` | `ai_conversation_messages` | Messages within conversations                  |
| `KnowledgeEntry`        | `knowledge_entries`        | User + AI-generated knowledge base             |
| `AiActionPlan`          | `ai_action_plans`          | Action plans with steps                        |
| `AiActionPlanStep`      | `ai_action_plan_steps`     | Individual plan steps                          |
| `AiPendingApproval`     | `ai_pending_approvals`     | Approval queue (24h expiry)                    |
| `AiExecutionLog`        | `ai_execution_logs`        | Execution audit trail                          |

### BaseAgent Contract

Every agent extends `BaseAgent` and must implement:

```php
abstract public function getName(): string;           // e.g. 'crm'
abstract public function getLabel(): string;           // e.g. '👥 CRM Agent'
abstract public function getCapabilities(): array;     // e.g. ['manage_contacts', ...]
abstract public function plan(array $intent, User $user, string $knowledgeContext = ''): ?AiActionPlan;
abstract public function execute(AiActionPlan $plan, User $user): array;
abstract public function advise(array $intent, User $user, string $knowledgeContext = ''): array;
```

Agents typically also override `executeStepAction()` to dispatch step types via `match()`.

### AgentOrchestrator Flow

1. User message → `classifyIntent()` (AI prompt + keyword fallback)
2. Intent → select agent from registered agents map
3. Based on work mode:
   - **Autonomous:** `agent->plan()` → `agent->execute()`
   - **Semi-Auto:** `agent->plan()` → create pending approval → wait
   - **Manual:** `agent->advise()`
4. Response returned via API/Telegram

---

## Completed Work

### Phase 1 ✅ — Core Brain

- 8 database migrations + Eloquent models
- Core services: `AgentOrchestrator`, `ConversationManager`, `ModeController`, `KnowledgeBaseService`
- 3 agents: `CampaignAgent`, `ListAgent`, `MessageAgent`
- Telegram integration (webhook, commands, account linking)
- 15 API endpoints via `BrainController`
- Telegram bot token stored encrypted in DB

### Phase 2 ✅ — Extended Agents

- `CrmAgent` — 9 action types (search/create contacts, deals, tasks, pipeline, scoring, companies)
- `AnalyticsAgent` — 6 action types (campaign stats, subscriber stats, reports, comparisons, trends, AI usage) — strictly read-only
- `SegmentationAgent` — 6 action types (tag distribution, score segments, tag management, AI suggestions, automation stats)
- `AgentOrchestrator` updated: 6 registered agents, updated classification prompt, keyword fallbacks
- Fixed heredoc syntax bug (`{json_encode()}` in heredocs) across all 6 agents

### Current Agent Registry (6 agents)

```
campaign:     📧 Campaign Agent
list:         📋 List Agent
message:      ✉️ Message Agent
crm:          👥 CRM Agent
analytics:    📊 Analytics Agent
segmentation: 🎯 Segmentation Agent
```

---

## Phase 3 — Frontend & Dashboard

### 3.1 Frontend — Brain Chat UI (Vue/Inertia) ✅ COMPLETED

- `Brain/Index.vue` — Main chat interface with conversation list, message bubbles, typing indicator, action plan preview, welcome screen with suggestions
- `Brain/Settings.vue` — Work mode toggle (autonomous/semi-auto/manual), Telegram integration (connect/test/disconnect), Knowledge base CRUD
- `BrainPageController.php` — Inertia page controller for `/brain` and `/brain/settings`
- `Sidebar.vue` — "NetSendo Brain" group added after CRM with AI badge
- Translations: `brain.*` keys (~65 per locale) added to all 4 locale files

### 3.2 Dashboard Status Widget ✅ COMPLETED

- `BrainWidget.vue` — Self-contained widget in Dashboard right sidebar (above HealthScoreWidget)
- Fetches live data via AJAX (`GET /brain/api/status`) — work mode, knowledge count, Telegram status
- Quick chat input, "Open Chat" and "Settings" links
- `BrainController::dashboardStatus()` — lightweight JSON endpoint
- Telegram Marketplace integration page added
- Fixed Brain Settings 401 errors by adding session-authenticated web routes (`/brain/api/*`)
- Full translations in PL, EN, DE, ES

---

## ⚠️ Known Gaps / Bugs

### Telegram Bot Token — No Input Field in Settings UI

The backend fully supports `telegram_bot_token` in `AiBrainSettings` (encrypted, fillable, accepted by `BrainController::updateSettings()`), but **there is no input field for it in `Brain/Settings.vue`**.

The current Telegram integration UI only has:

- Generate link code → `/connect <code>` flow
- Test connection / Disconnect buttons

**The user has no way to enter their Telegram bot token through the UI.** It can only be set via API (`PUT /api/v1/brain/settings` with `telegram_bot_token` field).

**Action required:** Add a text input field for `telegram_bot_token` in the Telegram Integration Card in `Settings.vue`, **before** the connect/disconnect flow. This should be the first setup step — without a bot token, the connect flow won't work.

---

## Phase 4 — Claude Integration & Opus 4.6 Orchestrator (⏳ NEXT)

### 4.1 Anthropic / Claude API Integration

Replace or augment the current AI provider with **Claude (Anthropic)** as the primary intelligence engine. Two approaches to evaluate:

**Option A: Direct Anthropic API Integration**

- Add `AnthropicProvider` to the existing AI provider system (alongside `OpenAiProvider`, `GeminiProvider`)
- Use Anthropic Messages API (`POST https://api.anthropic.com/v1/messages`)
- API key stored per-user in settings (like other AI providers)
- Model selection: Opus 4.6 for orchestration, Sonnet for lighter sub-agent tasks

**Option B: Claude via Existing AI Settings**

- Use the existing `AiService` abstraction — users configure their own Anthropic API key in AI Settings
- Leverage existing `AiIntegration` model for key storage
- Brain automatically selects the best available model per task complexity

**Package Authorization:**

- User subscription tier (SILVER/GOLD) determines which Claude models are available
- Tier enforcement: GOLD = Opus 4.6 access, SILVER = Sonnet only
- Token budget tracking per user per billing period

### 4.2 Opus 4.6 as Master Brain Orchestrator

Refactor `AgentOrchestrator` so that **Opus 4.6 is the central intelligence** that:

1. **Understands the full request** — receives user message + conversation context + knowledge base
2. **Decomposes complex tasks** — breaks multi-step requests into sub-tasks
3. **Delegates to specialist agents** — routes each sub-task to the appropriate agent
4. **Coordinates execution** — manages the workflow, handles dependencies between agents
5. **Synthesizes results** — aggregates agent responses into a coherent final answer

```
User Message
    ↓
Opus 4.6 (Master Brain)
    ├── Analyze intent (multi-label)
    ├── Create execution plan
    ├── Delegate: CampaignAgent (Sonnet) → sub-task 1
    ├── Delegate: SegmentationAgent (Sonnet) → sub-task 2
    ├── Wait for results
    └── Synthesize final response
```

**Key changes to `AgentOrchestrator`:**

- Replace simple `classifyIntent()` → single agent routing with Opus-powered multi-step planning
- Agents receive narrower, pre-parsed instructions from Opus (not raw user messages)
- Sub-agents can use lighter models (Sonnet) for cost efficiency
- Opus handles cross-agent dependencies (e.g., "segment audience first, then create campaign")

### 4.3 i18n — Agent Response Localization

Agent responses (success/error messages) are currently hardcoded in Polish. Extract to Laravel `__()` helpers:

- Create `src/lang/{locale}/brain.php` for each locale
- Reference: `docs/TRANSLATIONS.md` → "NetSendo Brain — Agent Labels & Responses" section has full translation table

### 4.4 Testing

No tests exist for the Brain yet. Priority areas:

- Unit tests for each agent's `plan()` / `executeStepAction()`
- Feature tests for `BrainController` API endpoints
- Integration tests for `AgentOrchestrator` intent classification

### 4.5 Async Execution (Job Queues)

- Dispatch `ExecuteBrainPlanJob` for autonomous/approved plans
- Real-time progress via Laravel Reverb (WebSocket) — already configured
- Push notifications for Telegram channel

### 4.6 Cross-Agent Workflows (enabled by Opus orchestrator)

- "Create a segment of hot leads, then send them a campaign" → SegmentationAgent + CampaignAgent
- Opus 4.6 handles the chaining logic natively as the master orchestrator

---

## Important Technical Notes

### Heredoc Pattern

All agent `plan()` and `advise()` methods use heredoc strings for AI prompts. **Never use function calls inside heredocs.** Always extract to a variable first:

```php
// ✅ Correct
$paramsJson = json_encode($intent['parameters'] ?? []);
$prompt = <<<PROMPT
Parametry: {$paramsJson}
PROMPT;

// ❌ Wrong — will cause fatal error
$prompt = <<<PROMPT
Parametry: {json_encode($intent['parameters'] ?? [])}
PROMPT;
```

### Docker Environment

```bash
# Run app commands
docker compose -f docker-compose.dev.yml exec -T app php artisan <command>

# Dev server
docker compose -f docker-compose.dev.yml exec -u dev app npm run dev

# Verify agent registration
docker compose -f docker-compose.dev.yml exec -T app php -r '
require "/var/www/vendor/autoload.php";
$app = require_once "/var/www/bootstrap/app.php";
$app->make("Illuminate\Contracts\Console\Kernel")->bootstrap();
$o = app("App\Services\Brain\AgentOrchestrator");
foreach ($o->getAgents() as $name => $agent) {
    echo "$name: " . $agent->getLabel() . "\n";
}
'
```

### Key Files to Know

| File                                                 | Purpose                                               |
| ---------------------------------------------------- | ----------------------------------------------------- |
| `src/app/Http/Controllers/BrainController.php`       | All 15 Brain API endpoints                            |
| `src/app/Http/Controllers/TelegramController.php`    | Telegram webhook handler                              |
| `src/app/Http/Controllers/GlobalStatsController.php` | Data source for AnalyticsAgent                        |
| `src/routes/api.php`                                 | API route definitions (search for `brain`)            |
| `CHANGELOG.md`                                       | All changes documented under `[Unreleased]`           |
| `docs/TRANSLATIONS.md`                               | Translation key conventions + Brain translation table |

### AI Service

Agents call AI via `$this->callAi($prompt, $options)` which uses `AiService::generateContent()`. The AI integration (API key, model) is configured per-user in the admin panel. `AiService::prependDateContext()` automatically adds current date/time to prompts.

### Existing AI Providers

| Provider   | Class                | Models           | Vision |
| ---------- | -------------------- | ---------------- | ------ |
| OpenAI     | `OpenAiProvider`     | GPT-4o, etc.     | ✅     |
| Google     | `GeminiProvider`     | Gemini Pro/Flash | ✅     |
| Ollama     | `OllamaProvider`     | Local models     | ❌     |
| OpenRouter | via `OpenAiProvider` | Various          | Varies |

> **Phase 4 TODO:** Add `AnthropicProvider` for Claude Opus 4.6 / Sonnet. The provider must implement `AiProviderInterface` — see existing providers for the contract.
