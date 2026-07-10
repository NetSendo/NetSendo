# NetSendo Brain 2.0 — Autonomiczna Maszyna Przychodowa

> **Data:** 2026-07-10
> **Status:** Faza 0 ✅ (2026-07-10) | Faza 1 ✅ częściowo (ToolRegistry, ExecuteBrainPlanJob, Approval Center; ⏳ natywny tool-calling, dekompozycja crona na joby, "approve & remember") | Faza 2 ✅ (`revenue_events` + sync, atrybucja last-click, RevenueAgent, KPI RPM) | Faza 3 ✅ (DeliverabilityGuardService circuit breaker, DeliverabilityAgent, PlaybookService — 4 playbooki, FunnelAgent; ⏳ webinar funnel, RFM/VIP, pełna macierz triggerów) | Faza 4 ✅ (experiment_dimensions + win-rate per cecha, deterministyczny best-send-time, cele outcome-driven, feedback porażek; ⏳ embeddingi KB) | Faza 5 ✅ (2026-07-10: AllocationService — budżet uwagi alokowany Thompson samplingiem wg zmierzonego RPM per typ kampanii, wstrzykiwany do planera tygodniowego; P&L w WeeklyDigest — przychód vs koszt tokenów per okres; progi w config/brain.php z nadpisaniem per user)
>
> **WSZYSTKIE FAZY RDZENIA WDROŻONE.** Otwarte wątki kontynuacji: natywny tool-calling providerów, dekompozycja crona na joby, polityki "approve & remember", embeddingi KB, webinar funnel, RFM/VIP, pełna macierz triggerów automatyzacji.
> **Poprzednik:** `docs/BRAIN_HANDOFF.md` (Brain 1.0, Phase 1–3)
>
> **Faza 0 — wykonane:** D1 realna wysyłka + `PreSendSafetyService` (spam/limity/okno/supresja), D2 `start_ab_test`+`apply_ab_winner`, D3 tiery uprawnień w `ModeController` (destructive ⇒ zawsze approval, także w cron), D4 błąd zamiast cichych no-opów + usunięty martwy kod, D5 pełne tokeny (`BrainAi`, `AiCostEstimator`, `config/ai_pricing.php`), D6 migracja `2026_07_10_100000_fix_brain_schema_drift`, D12 poprawne mianowniki, dry-run (Settings toggle + badge w Monitorze). Szczegóły w `CHANGELOG.md` → Unreleased.
> **Uwaga wdrożeniowa:** wymagane `php artisan migrate` + rebuild frontendu (`npm run build`). Zalecany start w trybie dry-run przez 1–2 tygodnie.

Cel: przekształcić Brain z "asystenta czatowego z cronem" w **autonomiczny system e-mail marketingu zorientowany na przychód** — zamknięta pętla: *audiencja → oferta → wysyłka → pomiar przychodu → nauka → realokacja*.

---

## Część I — Diagnoza Brain 1.0 (stan faktyczny kodu)

### 1. Co działa

- **Dwie ścieżki wejścia**: interaktywna (`AgentOrchestrator::processMessage/streamConversation` — web chat, Telegram, API) oraz autonomiczna (`brain:run-cron` co 5 min, per-user interwał z `ai_brain_settings.cron_interval_minutes`).
- **7 agentów** (campaign, list, message, crm, analytics, segmentation, research) na kontrakcie `BaseAgent` (plan → approve → execute), 3 tryby pracy globalnie i per-agent.
- **Cykl cron** (`RunBrainCronCommand::processUserCron`): analiza sytuacji (LLM) → przegląd wyników kampanii → auto-cele → kalendarz tygodniowy → kontynuacja celów → egzekucja wpisów kalendarza → scoring zadań (`TaskScorer`, 5 wymiarów) → routing per-agent-mode → raport Telegram.
- **Pętla uczenia** istnieje: `PerformanceTracker` (snapshoty OR/CTR/bounce/unsub vs benchmarki) → `PerformanceLearner` (sygnały: najlepsze dni/godziny, wzorce) → wstrzyknięcie do promptów SituationAnalyzer/Calendar + 5. wymiar TaskScorer.
- **UI kompletne**: Chat (SSE streaming, głos, zatwierdzanie planów), Monitor (KPI, agenci, zadania, logi, cele, tokeny/koszty), Settings (tryby, strategia kampanii, routing modeli per-task, Telegram, baza wiedzy).
- **Warstwa LLM**: 6 providerów (`AnthropicProvider`, OpenAI, Gemini, Grok, OpenRouter, Ollama), routing modeli per typ zadania.

### 2. Krytyczne defekty (do naprawy zanim zbudujemy cokolwiek nowego)

| # | Defekt | Dowód w kodzie |
|---|--------|----------------|
| D1 | **Brain niczego realnie nie wysyła.** `schedule_send` ustawia tylko `Message.status`; `send_at='immediate'` zostawia **draft** (status nadpisywany na `scheduled` tylko przy poprawnej przyszłej dacie). Realna wysyłka (`SendEmailJob`/`SendSmsJob`) nigdy nie jest wywoływana przez agentów. | `CampaignAgent.php:505-581` (zwł. 550-560) |
| D2 | **A/B testy tylko tworzone, nigdy nie startowane** — brak akcji `start_ab_test`, zwycięzca nigdy nie jest aplikowany do wysyłki. | `CampaignAgent.php:585-682` |
| D3 | **Martwe guardraile.** `CRITICAL_ACTIONS` (`send_to_all`, `delete_list`…) porównywane z `agent_type`, nie z nazwą akcji — nigdy nie odpalą. Realnie destrukcyjne akcje (`clean_bounced` = masowe wypisanie, `delete_automation`, aktywacja automatyzacji) nie są chronione. | `ModeController.php:22-27, 65-83` |
| D4 | **Ciche no-opy.** Prompt reklamuje akcje bez executorów (`add_subscribers`, `remove_subscribers`, `segment`, `analyze_results`, `create_email_template`, `translate_content`); `default` w `match()` zwraca "Noted" i udaje sukces. | `ListAgent.php:132-139`, `CampaignAgent.php:294-303`, `MessageAgent.php:133-143` |
| D5 | **Liczenie tokenów fikcyjne.** `BaseAgent::callAi` → `generateText` bez usage — wszystkie plan/execute/classify logują 0/0 i nie obciążają `daily_token_limit`; streaming szacuje `strlen/4`; tabela cen w `estimateTokenCost` ma stare nazwy modeli → wszystko spada na fallback. | `BaseAgent.php:248`, `AgentOrchestrator.php:1028`, `BrainController.php:1164` |
| D6 | **Dryf schematu.** `cron_max_tasks` i `ai_campaign_calendar.executed_at` czytane/pisane, ale kolumny nie istnieją; `ai_pending_approvals.ai_action_plan_id` NOT NULL, a propozycje celów tworzą approvale bez planu. | `RunBrainCronCommand.php:225, 816-827, 656` |
| D7 | **Dwie niereconciliowane reprezentacje zadań**: interaktywna tworzy trwałe `AiActionPlan` (draft), cron operuje na efemerycznych tablicach — drafty są osierocone, nic ich nie wykonuje. | `SituationAnalyzer.php:274-296` vs `RunBrainCronCommand.php:199` |
| D8 | **Cele "kończą się" bez rezultatu.** Progress = `completed_plans/total_plans`; `success_criteria` nigdy nie są ewaluowane wobec metryk. Dopasowanie next-action/dedup przez substring/Jaccard — kruche. | `AiGoal.php:69-86`, `GoalPlanner.php:214-246` |
| D9 | **Pętla uczenia w praktyce bezzębna**: sygnały tylko jako tekst w promptach; `scorePerformanceAffinity` dopasowuje `str_contains` pełnych zdań — praktycznie nigdy nie trafia; `extractStylePreferences` wyłączone i z bugiem (`agent` vs `agent_type`); bounce/unsub parsowane przez `LIKE '%bounce%'` na error_message. | `PerformanceLearner.php:82-99`, `KnowledgeBaseService.php:197-221`, `PerformanceTracker.php:243-245` |
| D10 | **Wszystko synchroniczne.** Cykl cron = 10–15+ sekwencyjnych wywołań LLM w jednym procesie CLI z `withoutOverlapping` — przy wielu userach nie mieści się w oknie; brak jobów kolejkowych Brain. | `RunBrainCronCommand.php` całość |
| D11 | **Brak natywnego tool-callingu** — JSON-w-tekście + zdzieranie markdownu (`parseJson()` ×4 kopie); kolumny `tool_calls`/`tool_results` nieużywane; historia konwersacji spłaszczana do jednego `json_encode` stringa. | `BaseAgent.php:315`, `AgentOrchestrator.php:863, 1009` |
| D12 | **Metryki mylące**: open/click rate liczone do liczby aktywnych subskrybentów zamiast do wysłanych. | `AnalyticsAgent.php:129-130` |

### 3. Niewykorzystane dźwignie platformy (istnieją w NetSendo, Brain ich nie dotyka)

| Dźwignia | Stan platformy | Wartość dla maszyny przychodowej |
|---|---|---|
| **Przychód / transakcje** — Stripe/Polar/Tpay/NMI/WooCommerce, `FunnelGoalConversion.value`, pixel `purchase`, prowizje afiliacyjne | Istnieje, rozproszone, brak zunifikowanego modelu | **Krytyczna** — domyka pętlę wysyłka→pieniądze |
| **Lejki (Funnels)** — kroki email/sms/delay/condition/**split A/B**/goal(purchase!)/webhook, szablony, retry, revenue per lejek | Bardzo dojrzałe | **Krytyczna** — najpotężniejszy prymityw automatyzacji |
| **Deliverability** — `SpamTriggerAnalyzer`, `InboxPassportService`, SPF/DKIM/DMARC (`DomainVerificationService`), `MailboxReputationService`, `BounceProcessingService`, `SuppressionList` | Dojrzałe | **Wysoka** — bez tego autonomiczne wysyłki spalą reputację |
| **Pełna macierz automatyzacji** — 30+ triggerów (`cart_abandoned`, `purchase`, pixel, `score_threshold`, `deal_won`, inactivity, birthday…) i akcji (`start_funnel`, `add_score`…) | Dojrzałe | **Wysoka** — lifecycle marketing |
| **Lead scoring + sekwencje follow-up CRM** (`LeadScoringService`, `CrmFollowUpSequence`) | Dojrzałe | Średnio-wysoka |
| **Webinary / auto-webinary** (produkty, CTA, analityka przychodu) | Dojrzałe | Średnio-wysoka |
| **Audyt kampanii przed wysyłką** (`CampaignAuditorService`), `CampaignArchitectService` (wstrzyknięty do CampaignAgent, **nigdy nie wywołany**) | Istnieje | Wysoka, tania integracja |
| Biblioteka mediów, webhooki, marketplace afiliacyjny | Istnieją | Średnia/niska |

---

## Część II — Wizja Brain 2.0: Autonomiczna Maszyna Przychodowa

### Gwiazda północna

Jedna metryka nadrzędna: **przychód atrybuowany do działań Brain / tydzień** (minus koszty tokenów) — widoczna jako "P&L Mózgu". Wszystkie decyzje planera optymalizują oczekiwany **RPM (revenue per 1000 wysyłek)** przy twardych limitach bezpieczeństwa i deliverability.

### Pięć filarów architektury

```
┌─────────────────────────────────────────────────────────────┐
│  F5. EKONOMICZNY ALOKATOR (CFO)                             │
│  budżet uwagi (wysyłki/tydz.) → playbooki wg oczekiwanego   │
│  RPM (bandit) → tygodniowy P&L                              │
├─────────────────────────────────────────────────────────────┤
│  F4. SILNIK UCZENIA                                         │
│  każda wysyłka = eksperyment; A/B auto-start + auto-winner; │
│  embeddingi KB; cele rozliczane z metryk, nie z liczby      │
│  planów                                                     │
├─────────────────────────────────────────────────────────────┤
│  F3. PLAYBOOKI PRZYCHODOWE                                  │
│  welcome→oferta, porzucony koszyk, win-back, upsell,        │
│  webinar-funnel, VIP/RFM, lead-scoring→CRM                  │
├─────────────────────────────────────────────────────────────┤
│  F2. JĄDRO WYKONAWCZE (Tool Registry + kolejki)             │
│  natywny tool-calling, typowane narzędzia z tierami         │
│  uprawnień, realna wysyłka, asynchroniczne joby             │
├─────────────────────────────────────────────────────────────┤
│  F1. FUNDAMENT ZAUFANIA                                     │
│  pipeline bezpieczeństwa przed wysyłką, circuit breaker     │
│  deliverability, prawdziwe liczenie kosztów, audyt,         │
│  approval center                                            │
└─────────────────────────────────────────────────────────────┘
```

---

### F1. Fundament zaufania (bez tego autonomia = ryzyko)

**1.1 Pre-Send Safety Pipeline** — każda wysyłka inicjowana przez Brain przechodzi obowiązkowo:
1. `SuppressionList` + status subskrypcji (filtrowanie audiencji na poziomie egzekucji, nie promptu),
2. `SpamTriggerAnalyzer` na temacie i HTML (blokada przy critical issues),
3. `CampaignAuditorService` (audyt techniczny),
4. twardy limit wolumenu: `max_sends_per_week` + dzienne okno godzinowe **egzekwowane w kodzie** (dziś tylko doradczo w promptach),
5. sprawdzenie zdrowia domeny/skrzynki (`MailboxReputationService`, SPF/DKIM/DMARC status).

**1.2 Deliverability Circuit Breaker** — nowy serwis nasłuchujący metryk w czasie zbliżonym do rzeczywistego: bounce rate > próg lub skok unsub/spam → automatyczna pauza wszystkich wysyłek Brain + alert (Telegram/web/push). Progi w ustawieniach, nie hardcode.

**1.3 Naprawa guardraili** — tiery uprawnień per narzędzie (patrz F2): `read` / `write` / `send` / `destructive`. `destructive` (masowe wypisania, delete, aktywacja automatyzacji na dużą skalę) zawsze wymaga zatwierdzenia niezależnie od trybu. Usuwa martwy `CRITICAL_ACTIONS`.

**1.4 Prawdziwa księgowość** — `callAi` przechodzi na `generateTextWithUsage` wszędzie; tabela cen per model w DB (aktualizowalna), koszt tokenów wchodzi do P&L; `daily_token_limit` faktycznie egzekwowany dla wszystkich ścieżek.

**1.5 Approval Center 2.0 (web, nie tylko Telegram)** — kolejka zatwierdzeń w Monitorze: podgląd per-krok z parametrami, edycja kroku przed zatwierdzeniem, podgląd audiencji ("wyślemy do 1 243 osób z listy X"), przewidywany koszt, oraz **"zatwierdź i zapamiętaj"** → tworzy trwałą politykę (np. "newslettery do listy X < 2000 osób nie wymagają zgody"), przez co system z czasem zdobywa autonomię *zaufaniem, nie konfiguracją*.

**1.6 Tryb symulacji (dry-run)** — globalny przełącznik: Brain wykonuje pełny cykl, ale zamiast wysyłać, produkuje raport "co bym zrobił + oczekiwany efekt". Idealny na pierwsze 1–2 tygodnie po wdrożeniu i do regresji.

**1.7 Naprawy techniczne z listy defektów**: D1 (realny dispatch do pipeline'u wysyłkowego, `immediate` = wysyłka teraz), D2 (start_ab_test + auto-winner), D4 (usunięcie cichych no-opów — nieznana akcja = błąd planu), D6 (migracje brakujących kolumn, nullable FK w approvals), D12 (metryki liczone do wysłanych).

### F2. Jądro wykonawcze — Tool Registry + natywny tool-calling

**2.1 Centralny rejestr narzędzi** (jedno źródło prawdy zamiast dryfujących promptów i `match()`):

```php
Tool::define('campaign.send')
    ->schema([...])                 // typowane parametry (JSON Schema)
    ->tier(PermissionTier::SEND)    // read|write|send|destructive
    ->handler(SendCampaignHandler::class)
    ->costEstimator(...)            // szacunek wpływu (adresaci, tokeny)
    ->description(...);             // auto-generowane do promptu/tool spec
```

- Definicje narzędzi generują **natywne specyfikacje tool-use** (Anthropic Messages API `tools`, analogicznie dla OpenAI/Gemini) — koniec z JSON-w-tekście i `parseJson()`.
- Agent = **wiązka narzędzi + polityka + prompt systemowy**, nie klasa z własnym `match()`. Nowe zdolności dodaje się rejestracją narzędzia, nie edycją 4 plików.
- Walidacja parametrów na wejściu handlera; nieznane/niepoprawne wywołanie = jawny błąd kroku (koniec cichych "Noted").
- Kolumny `tool_calls`/`tool_results` w `ai_conversation_messages` wreszcie używane; historia konwersacji przekazywana jako natywna tablica `messages`.

**2.2 Jedna kolejka planów** — likwidacja podwójnej reprezentacji (D7): wszystko (chat, cron, kalendarz, cele, playbooki) tworzy `AiActionPlan` w jednym cyklu życia `draft → scored → approved → queued → executing → completed/failed`, wykonywane przez **joby kolejkowe** (`ExecuteBrainPlanJob`), z postępem live przez Reverb (WebSocket już skonfigurowany). Cron przestaje być monolitem — staje się cienkim "tickerem" wrzucającym joby: `AnalyzeSituationJob`, `ReviewPerformanceJob`, `PlanWeekJob`, `ExecuteDuePlansJob`. Skaluje się na wielu userów, odporność na timeouty providerów.

**2.3 Orkiestrator 2.0** — model klasy Opus jako planner (dekompozycja wielokrokowa, zależności między agentami), tańsze modele do egzekucji kroków (routing per-task już istnieje w ustawieniach — zostaje). Wątek "plan → wykonaj kroki → zbierz wyniki → synteza" na natywnym tool-callingu, z przekazywaniem wyników kroków przez jawny kontekst planu (koniec z fishowaniem po sibling-stepach).

### F3. Playbooki przychodowe (produktowe serce 2.0)

Playbook = skodyfikowana strategia zarabiania: szablon lejka + segment docelowy + KPI + guardraile + harmonogram. Brain instancjonuje, personalizuje treści, mierzy i optymalizuje. Startowy katalog:

| Playbook | Mechanika (istniejące prymitywy) | KPI |
|---|---|---|
| **Welcome → pierwsza oferta** | Funnel: seria powitalna 3–5 maili + goal(purchase) | konwersja do 1. zakupu |
| **Porzucony koszyk** | trigger `cart_abandoned` (pixel) → funnel 2–3 maile + rabat | odzyskany przychód |
| **Win-back / reaktywacja** | trigger `inactivity` → sekwencja + sunset (higiena listy) | reaktywowani, ochrona deliverability |
| **Post-purchase upsell/cross-sell** | trigger `purchase` → oferta komplementarna po N dniach | AOV, powtórne zakupy |
| **Webinar funnel** | rejestracja → przypomnienia → replay → oferta (`WebinarProduct`) | przychód z webinaru |
| **VIP / RFM** | segmentacja RFM (recency/frequency/monetary z transakcji) → ekskluzywne oferty do top-decyla | RPM segmentu VIP |
| **Lead scoring → CRM handoff** | `score_threshold` → deal + `CrmFollowUpSequence` + zadanie dla handlowca | pipeline value, deals won |
| **Newsletter engine** | kalendarz tygodniowy (istnieje) + optymalizacja czasu/tematu z F4 | OR/CTR/RPM, stały touch |

**Nowi agenci / wiązki narzędzi**: `FunnelAgent` (buduje/aktywuje lejki z szablonów), `DeliverabilityAgent` (monitoring + higiena), `RevenueAgent` (atrybucja, analiza ofert), rozszerzenie SegmentationAgent o pełną macierz triggerów.

### F4. Silnik uczenia — od "tekstu w promptach" do zamkniętej pętli

**4.1 Warstwa przychodu i atrybucji (fundament danych)**
- Zunifikowany model `RevenueEvent` (źródło: Stripe/Polar/Tpay/NMI/Woo/`FunnelGoalConversion`/pixel purchase/prowizje afiliacyjne) z normalizacją waluty i deduplikacją.
- Atrybucja: klik z wiadomości (`EmailClick`/`MessageTrackedLink`) → zakup w oknie X dni (last-click, okno konfigurowalne) + atrybucja lejkowa z `FunnelGoalConversion`. Wynik: **przychód per kampania / per segment / per playbook / per subskrybent (LTV)**.
- KPI Monitora rozszerzone: RPM, przychód 7/30d, LTV, revenue per playbook — obok OR/CTR.

**4.2 Wszystko jest eksperymentem**
- Każda wysyłka Brain rejestruje hipotezę (styl tematu, godzina, segment, oferta, długość) w ustrukturyzowanym `experiment_dimensions`.
- A/B: auto-start (naprawa D2), istotność z `AbTestStatisticsService`, **auto-aplikacja zwycięzcy** do reszty audiencji; wyniki zapisywane jako strukturalne fakty, nie zdania.
- `PerformanceLearner` 2.0: sygnały jako dane (najlepsza godzina → deterministyczne planowanie wysyłek o tej godzinie; najlepszy typ → wagi alokatora), a nie tylko proza w promptach.

**4.3 Baza wiedzy z embeddingami** — populacja istniejącej kolumny `content_embedding`, wyszukiwanie semantyczne zamiast `LIKE`; ponowne włączenie auto-enrichmentu w wersji kontrolowanej (wpisy AI trafiają jako `unverified` z niskim confidence, user może zatwierdzać hurtowo); naprawa `extractStylePreferences`.

**4.4 Cele rozliczane z rezultatów** — cel dostaje mierzalny target (`metric`, `target_value`, `deadline`), np. "OR listy X ≥ 25% do 31.08"; completion wyłącznie gdy metryka osiągnięta (weryfikacja w cyklu cron); dopasowania substring/Jaccard zastąpione jawnymi ID kroków dekompozycji. Porażki planów wracają do planera jako kontekst (dziś: zapisywane i nigdy nie czytane).

### F5. Ekonomiczny alokator ("CFO Mózgu")

- **Budżet uwagi**: tygodniowy limit wysyłek per lista (szanowanie skrzynek odbiorczych = aktywo, nie koszt).
- **Alokacja bandit** (Thompson sampling): rozdziela budżet wysyłek między playbooki/segmenty/tematy według oczekiwanego RPM z niepewnością — eksploracja nowych tematów vs eksploatacja sprawdzonych; zimny start z priorytetów LLM.
- **Tygodniowy P&L**: przychód atrybuowany − koszt tokenów − (opcjonalnie koszt SMS/prowizje), per playbook. `WeeklyDigest` 2.0 = raport CFO: co zarobiło, co przycięto, jakie decyzje alokacyjne podjęto i dlaczego, plus propozycje na kolejny tydzień.
- Wszystkie progi/benchmarki (hot-lead score≥50, okna przeglądu 24–168h, MIN_SNAPSHOTS, benchmarki branżowe) przenoszone z hardcode do ustawień strategii.

---

## Część III — Mapa drogowa

### Faza 0 — Naprawa fundamentów (1,5–2 tyg.) — *bez tego reszta nie ma sensu*
1. D1: realna wysyłka z `schedule_send` (dispatch do pipeline'u Message/SendEmailJob; `immediate` działa) — za Pre-Send Safety Pipeline (1.1 minimum: suppression + SpamTriggerAnalyzer + limit tygodniowy egzekwowany).
2. D2: `start_ab_test` + auto-winner.
3. D3: tiery uprawnień zamiast `CRITICAL_ACTIONS`; `clean_bounced`/automation-CRUD jako `destructive`.
4. D4: nieznana akcja = błąd, nie "Noted"; usunięcie martwego kodu (handleAutonomousTasks/handleSemiAutoTasks/getAcceptedPriorities).
5. D5: `generateTextWithUsage` wszędzie + tabela cen w DB.
6. D6: migracje `cron_max_tasks`, `executed_at`, nullable FK approvals.
7. D12: poprawne mianowniki metryk.
8. Tryb dry-run (1.6).

### Faza 1 — Jądro wykonawcze (2–3 tyg.)
1. Tool Registry + natywny tool-calling (Anthropic najpierw, potem OpenAI/Gemini).
2. Migracja agentów na wiązki narzędzi (bez zmiany zachowania).
3. Jedna kolejka planów + joby (`ExecuteBrainPlanJob`, dekompozycja crona na joby) + progres Reverb.
4. Approval Center 2.0 w Monitorze (per-krok, edycja, "zatwierdź i zapamiętaj").

### Faza 2 — Warstwa przychodu (2–3 tyg.)
1. `RevenueEvent` + konektory (Stripe/Polar/Tpay/Woo/FunnelGoalConversion/pixel).
2. Atrybucja klik→zakup + `RevenueAgent`.
3. KPI 2.0 w Monitorze (RPM, LTV, przychód per kampania) + P&L per tydzień.

### Faza 3 — Dźwignie i playbooki (3–4 tyg.)
1. `FunnelAgent` (szablony lejków → instancje) + `DeliverabilityAgent` (monitoring, circuit breaker 1.2).
2. Playbooki: welcome, cart-abandon, win-back, post-purchase (4 pierwsze).
3. Pełna macierz triggerów automatyzacji w narzędziach segmentacji.
4. Webinar funnel + RFM/VIP (druga tura).

### Faza 4 — Silnik uczenia (2–3 tyg.)
1. Eksperymenty na każdej wysyłce + strukturalne sygnały do planowania (deterministyczne best-time).
2. Embeddingi KB + kontrolowany auto-enrichment.
3. Cele outcome-driven (metric/target/deadline).

### Faza 5 — Autonomiczny CFO (2 tyg.)
1. Budżet uwagi + alokator bandit.
2. WeeklyDigest 2.0 (raport P&L + decyzje).
3. Progi/benchmarki do ustawień; polityki zaufania ("approve & remember") domykają przejście manual → semi → auto.

**Kolejność nieprzypadkowa**: najpierw system *umie i może* bezpiecznie wysyłać (F0/F1), potem *widzi pieniądze* (F2), potem *ma co optymalizować* (F3), potem *uczy się* (F4), na końcu *sam alokuje kapitał uwagi* (F5).

---

## Część IV — Miary sukcesu Brain 2.0

| Miara | Dziś | Cel 2.0 |
|---|---|---|
| Przychód atrybuowany do Brain / tydz. | niemierzalny (0) | mierzony, rosnący WoW |
| RPM (przychód / 1000 wysyłek) | brak | benchmark per playbook |
| Wysyłki faktycznie zrealizowane przez Brain | 0 (drafty) | 100% zaplanowanych, w oknie godzinowym |
| A/B testy z auto-winnerem | 0 | ≥1 / tydzień / aktywna lista |
| Koszt tokenów widoczny w P&L | zaniżony ~kilkukrotnie | pełny, per plan |
| Bounce/spam incydenty autonomiczne | brak ochrony | 0 (circuit breaker) |
| Czas cyklu cron per user | 10–15 sekwencyjnych LLM calli | joby równoległe, tick < 30 s |
| Cele zamknięte rezultatem (metryką) | 0% (liczone planami) | 100% celów z targetem liczbowym |

## Część V — Ryzyka i zasady

1. **Reputacja nadawcy > krótkoterminowy przychód** — circuit breaker i budżet uwagi mają pierwszeństwo przed alokatorem; sunset-policy dla nieaktywnych zamiast dociskania wolumenu.
2. **Zgody i suppression egzekwowane w kodzie**, nigdy w promptach.
3. **Autonomia stopniowana zaufaniem**: nowy playbook zawsze startuje w semi-auto; polityki "approve & remember" przenoszą go do auto po serii dobrych decyzji.
4. **Koszt LLM pod kontrolą**: planner na drogim modelu tylko raz per cykl; egzekucja kroków na tańszych (routing per-task już istnieje); cache kontekstu sytuacyjnego między krokami.
5. **Wszystko audytowalne**: każde wywołanie narzędzia w `ai_execution_logs` z pełnymi parametrami, tokenami i kosztem; dry-run do regresji przed każdym releasem playbooka.
