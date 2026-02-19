<?php

namespace App\Services\Brain\Skills;

use App\Models\AiActionPlan;
use App\Models\AiExecutionLog;
use App\Models\ContactList;
use App\Models\CrmContact;
use App\Models\CrmDeal;
use App\Models\User;

/**
 * MarketingSalesSkill — World-class email & SMS marketing/sales expertise
 * for the Brain orchestrator.
 *
 * Provides:
 *  - System prompt for orchestrator (intent classification & conversation)
 *  - Task categories for the Monitor task list
 *  - Dynamic task suggestions based on CRM data
 */
class MarketingSalesSkill
{
    /**
     * Core system prompt — marketing & sales expertise.
     * Appended to the orchestrator's intent classification and conversation prompts.
     */
    public static function getSystemPrompt(string $language = 'en'): string
    {
        $prompts = [
            'pl' => self::getPolishPrompt(),
            'en' => self::getEnglishPrompt(),
            'de' => self::getGermanPrompt(),
            'es' => self::getSpanishPrompt(),
        ];

        return $prompts[$language] ?? $prompts['en'];
    }

    /**
     * Task categories — defines the types of marketing/sales tasks the orchestrator can suggest.
     */
    public static function getTaskCategories(): array
    {
        return [
            [
                'id' => 'lead_nurturing',
                'icon' => '🌱',
                'label' => 'Lead Nurturing',
                'description' => 'Automatic lead nurturing sequences — welcome series, education, trust building',
                'priority' => 'high',
                'agent' => 'campaign',
            ],
            [
                'id' => 'drip_campaign',
                'icon' => '💧',
                'label' => 'Drip Campaign',
                'description' => 'Multi-step drip campaigns — timed sequences, follow-ups, reactivation',
                'priority' => 'high',
                'agent' => 'campaign',
            ],
            [
                'id' => 'promotional_blast',
                'icon' => '🎯',
                'label' => 'Promotional Campaign',
                'description' => 'One-time promotional campaigns — special offers, sales, events',
                'priority' => 'medium',
                'agent' => 'campaign',
            ],
            [
                'id' => 'sms_campaign',
                'icon' => '📱',
                'label' => 'SMS Marketing',
                'description' => 'SMS campaigns — short, direct messages, reminders, confirmations',
                'priority' => 'medium',
                'agent' => 'message',
            ],
            [
                'id' => 'ab_testing',
                'icon' => '🔬',
                'label' => 'A/B Testing',
                'description' => 'A/B tests — email subjects, content, CTAs, send time, segments',
                'priority' => 'medium',
                'agent' => 'campaign',
            ],
            [
                'id' => 'segmentation',
                'icon' => '🎯',
                'label' => 'Audience Segmentation',
                'description' => 'Smart segmentation — RFM, behaviors, scoring, interests',
                'priority' => 'high',
                'agent' => 'segmentation',
            ],
            [
                'id' => 'crm_pipeline',
                'icon' => '📊',
                'label' => 'CRM Pipeline',
                'description' => 'Pipeline management — follow-ups, lead scoring, stage progression',
                'priority' => 'high',
                'agent' => 'crm',
            ],
            [
                'id' => 'win_back',
                'icon' => '🔄',
                'label' => 'Reactivation',
                'description' => 'Win-back campaigns — recovering inactive contacts, re-engagement',
                'priority' => 'medium',
                'agent' => 'campaign',
            ],
            [
                'id' => 'analytics_report',
                'icon' => '📈',
                'label' => 'Report & Analysis',
                'description' => 'Results analysis — campaign KPIs, trends, optimization recommendations',
                'priority' => 'low',
                'agent' => 'analytics',
            ],
            [
                'id' => 'content_creation',
                'icon' => '✍️',
                'label' => 'Content Creation',
                'description' => 'Copywriting — sales emails, newsletters, SMS, landing pages',
                'priority' => 'medium',
                'agent' => 'message',
            ],
            [
                'id' => 'list_hygiene',
                'icon' => '🧹',
                'label' => 'List Hygiene',
                'description' => 'List cleaning — removing bounced, inactive, duplicates',
                'priority' => 'low',
                'agent' => 'list',
            ],
            [
                'id' => 'follow_up_sequence',
                'icon' => '📨',
                'label' => 'Follow-up Sequences',
                'description' => 'Automatic follow-ups after purchase, demo, material download',
                'priority' => 'high',
                'agent' => 'campaign',
            ],
        ];
    }

    /**
     * Generate dynamic task suggestions based on user's CRM data.
     */
    public static function getSuggestedTasks(User $user): array
    {
        $tasks = [];
        $now = now();

        // 1. Check for contacts without recent campaigns
        $totalContacts = ContactList::where('user_id', $user->id)
            ->withCount('subscribers')
            ->get();
        $totalSubscribers = $totalContacts->sum('subscribers_count');
        $listCount = $totalContacts->count();

        if ($totalSubscribers > 0) {
            // Check recent campaign activity
            $recentCampaigns = AiActionPlan::forUser($user->id)
                ->where('agent_type', 'campaign')
                ->where('created_at', '>=', $now->copy()->subDays(7))
                ->count();

            if ($recentCampaigns === 0 && $totalSubscribers >= 10) {
                $tasks[] = [
                    'id' => 'suggest_campaign_' . $now->timestamp,
                    'category' => 'promotional_blast',
                    'icon' => '🎯',
                    'title' => "Plan a campaign for {$totalSubscribers} subscribers",
                    'description' => "You have {$listCount} lists with {$totalSubscribers} subscribers — no campaigns in the last 7 days. Plan a new email campaign.",
                    'priority' => 'high',
                    'action' => 'Create an email campaign for my subscribers',
                    'agent' => 'campaign',
                ];
            }

            // Suggest drip campaign if no automation exists
            $hasAutomation = AiActionPlan::forUser($user->id)
                ->where('agent_type', 'campaign')
                ->where('intent', 'like', '%drip%')
                ->exists();

            if (!$hasAutomation && $totalSubscribers >= 50) {
                $tasks[] = [
                    'id' => 'suggest_drip_' . $now->timestamp,
                    'category' => 'drip_campaign',
                    'icon' => '💧',
                    'title' => 'Create a drip campaign sequence',
                    'description' => 'You don\'t have an automatic drip sequence yet. Build a welcome series or nurturing sequence to engage new subscribers.',
                    'priority' => 'high',
                    'action' => 'Create a drip campaign welcome series',
                    'agent' => 'campaign',
                ];
            }
        }

        // 2. CRM pipeline suggestions
        try {
            $openDeals = CrmDeal::where('user_id', $user->id)
                ->whereNull('closed_at')
                ->count();

            $hotLeads = CrmContact::where('user_id', $user->id)
                ->where('score', '>=', 50)
                ->count();

            if ($hotLeads > 0) {
                $tasks[] = [
                    'id' => 'suggest_hot_leads_' . $now->timestamp,
                    'category' => 'crm_pipeline',
                    'icon' => '🔥',
                    'title' => "Follow up {$hotLeads} hot leads",
                    'description' => "You have {$hotLeads} contacts with score 50+. Plan personalized follow-ups to increase conversion.",
                    'priority' => 'high',
                    'action' => "Prepare follow-up for hot leads in CRM",
                    'agent' => 'crm',
                ];
            }

            if ($openDeals > 3) {
                $tasks[] = [
                    'id' => 'suggest_pipeline_review_' . $now->timestamp,
                    'category' => 'analytics_report',
                    'icon' => '📊',
                    'title' => "Pipeline review: {$openDeals} open deals",
                    'description' => "You have {$openDeals} open deals. Analyze the pipeline, identify blockers and plan follow-ups.",
                    'priority' => 'medium',
                    'action' => "Analyze CRM pipeline and suggest next steps",
                    'agent' => 'analytics',
                ];
            }
        } catch (\Exception $e) {
            // CRM models may not exist yet — skip
        }

        // 3. Suggest analytics if enough history
        $totalExecutions = AiExecutionLog::forUser($user->id)->count();
        if ($totalExecutions > 10 && !AiExecutionLog::forUser($user->id)
                ->where('agent_type', 'analytics')
                ->where('created_at', '>=', $now->copy()->subDays(7))
                ->exists()) {
            $tasks[] = [
                'id' => 'suggest_analytics_' . $now->timestamp,
                'category' => 'analytics_report',
                'icon' => '📈',
                'title' => 'Generate weekly report',
                'description' => 'No report in the last 7 days. Generate analysis of campaigns, subscriptions and trends.',
                'priority' => 'low',
                'action' => 'Generate full analytics report',
                'agent' => 'analytics',
            ];
        }

        // 4. Suggest list hygiene periodically
        if ($totalSubscribers > 100) {
            $tasks[] = [
                'id' => 'suggest_hygiene_' . $now->timestamp,
                'category' => 'list_hygiene',
                'icon' => '🧹',
                'title' => 'Clean contact lists',
                'description' => "Check {$totalSubscribers} subscribers for bounced, inactive and duplicates. Maintain healthy lists.",
                'priority' => 'low',
                'action' => 'Clean list from inactive and bounced subscribers',
                'agent' => 'list',
            ];
        }

        // 5. Always offer segmentation
        if ($totalSubscribers >= 20) {
            $tasks[] = [
                'id' => 'suggest_segmentation_' . $now->timestamp,
                'category' => 'segmentation',
                'icon' => '🎯',
                'title' => 'Contact base segmentation',
                'description' => 'Divide your base into segments by activity, interests and scoring. Increase campaign targeting accuracy.',
                'priority' => 'medium',
                'action' => 'Run contact base segmentation',
                'agent' => 'segmentation',
            ];
        }

        // Sort by priority
        $priorityOrder = ['high' => 0, 'medium' => 1, 'low' => 2];
        usort($tasks, fn($a, $b) =>
            ($priorityOrder[$a['priority']] ?? 99) - ($priorityOrder[$b['priority']] ?? 99)
        );

        return $tasks;
    }

    /**
     * Polish marketing system prompt.
     */
    private static function getPolishPrompt(): string
    {
        return <<<'PROMPT'
## UMIEJĘTNOŚĆ: Ekspert Email & SMS Marketingu, Sprzedaży i CRM

Jesteś światowej klasy ekspertem email marketingu, SMS marketingu i zarządzania sprzedażą CRM.
Twoje decyzje i rekomendacje muszą być na najwyższym profesjonalnym poziomie.

### ZASADY PRZEWODNIE

**Email Marketing:**
- Stosuj personalizację na każdym poziomie (imię, zachowania, preferencje, historia zakupów)
- Dbaj o dostarczalność: warm-up domeny, autentykacja SPF/DKIM/DMARC, czyszczenie listy
- Optymalizuj pod mobile-first (65%+ otwarć na mobile)
- Stosuj segmentację behawioralną (RFM, engagement scoring, lifecycle stage)
- Pisz tematy emaili krótkie (30-50 znaków), intrygujące, z poczuciem pilności
- Używaj preheaderów komplementarnych do tematu
- Jedno główne CTA na email, jasne i widoczne
- Zawsze testuj A/B (temat, treść, CTA, czas wysyłki)
- Optymalny czas: Wt-Czw 9:00-11:00 oraz 14:00-16:00 (testuj dla swojej bazy)
- Automatyzuj: welcome series (3-5 emaili), post-purchase, abandoned cart, win-back

**SMS Marketing:**
- Krótkie wiadomości (do 160 znaków), bezpośrednie, z CTA
- Personalizacja imieniem + oferta wartościowa
- Compliance: zawsze opcja STOP, regularne godziny (9:00-20:00)
- SMS uzupełnia email, nie zastępuje — użyj do pilnych ofert, przypomnień, potwierdzeń
- Segmentuj odbiorców SMS bardziej rygorystycznie niż email

**Copywriting sprzedażowy:**
- Framework AIDA: Attention → Interest → Desire → Action
- Pisz językiem korzyści, nie cech produktu
- Social proof: referencje, case studies, liczby
- Poczucie pilności: limitowane oferty, countdown, ograniczona dostępność
- Tone of voice: dopasowany do marki (profesjonalny, przyjazny, ekskluzywny)

**CRM & Sprzedaż:**
- Lead scoring: automatyczny, oparty na zachowaniach (otwarcia, kliknięcia, wizyty, pobrania)
- Pipeline management: jasne etapy, definicje przejścia, automatyczne follow-upy
- Follow-up w 24h od gorącego kontaktu — szybkość = konwersja
- Każdy kontakt w CRM powinien mieć zaplanowaną kolejną akcję
- Raportuj: conversion rate na każdym etapie, velocity, deal value, win rate

**Kampanie wieloetapowe (Drip/Nurturing):**
- Welcome series: 3-5 emaili w ciągu 2 tygodni
- Nurturing: wartość edukacyjna → case study → oferta (sekwencja 5-7 emaili)
- Win-back: 3 emaile w 30 dni (przypomnienie → oferta → ostatnia szansa)
- Post-purchase: podziękowanie → cross-sell → review request → loyalty

**Analityka & Optymalizacja:**
- KPI: Open Rate (cel >25%), CTOR (cel >3%), Unsubscribe (<0.5%), Bounce (<2%)
- ROI kalkulacja: przychód z kampanii / koszt (w tym czas + narzędzia)
- Cohort analysis: porównuj segmenty, kohorty, kampanie
- Iteruj: każda kampania to eksperyment, ucz się z danych

### DYREKTYWY DZIAŁANIA

Gdy tworzysz plan kampanii lub zadanie:
1. Zidentyfikuj cel biznesowy i KPI sukcesu
2. Określ segment odbiorców (nie wysyłaj do "wszystkich")
3. Zaplanuj treść z framework AIDA
4. Ustal timing i częstotliwość
5. Wbuduj mechanizm A/B testingu
6. Zaplanuj follow-up i next steps
7. Określ metryki do analizy po wysyłce
PROMPT;
    }

    /**
     * English marketing system prompt.
     */
    private static function getEnglishPrompt(): string
    {
        return <<<'PROMPT'
## SKILL: World-Class Email & SMS Marketing, Sales & CRM Expert

You are a world-class expert in email marketing, SMS marketing, and CRM sales management.
Your decisions and recommendations must be at the highest professional level.

### GUIDING PRINCIPLES

**Email Marketing:**
- Apply personalization at every level (name, behaviors, preferences, purchase history)
- Ensure deliverability: domain warm-up, SPF/DKIM/DMARC authentication, list cleaning
- Optimize for mobile-first (65%+ opens on mobile)
- Use behavioral segmentation (RFM, engagement scoring, lifecycle stage)
- Write short subject lines (30-50 chars), intriguing, with urgency
- Use complementary preheaders
- One main CTA per email, clear and visible
- Always A/B test (subject, content, CTA, send time)
- Optimal timing: Tue-Thu 9-11 AM and 2-4 PM (test for your audience)
- Automate: welcome series (3-5 emails), post-purchase, abandoned cart, win-back

**SMS Marketing:**
- Short messages (up to 160 chars), direct, with CTA
- Personalize with name + value proposition
- Compliance: always include STOP option, regular hours (9AM-8PM)
- SMS complements email, doesn't replace it — use for urgent offers, reminders, confirmations
- Segment SMS recipients more strictly than email

**Sales Copywriting:**
- AIDA framework: Attention → Interest → Desire → Action
- Write in benefits language, not product features
- Social proof: testimonials, case studies, numbers
- Urgency: limited offers, countdown, scarcity
- Tone of voice: aligned with brand (professional, friendly, exclusive)

**CRM & Sales:**
- Lead scoring: automated, behavior-based (opens, clicks, visits, downloads)
- Pipeline management: clear stages, transition definitions, automated follow-ups
- Follow-up within 24h of hot contact — speed = conversion
- Every contact should have a planned next action
- Report: conversion rate per stage, velocity, deal value, win rate

**Multi-step Campaigns (Drip/Nurturing):**
- Welcome series: 3-5 emails over 2 weeks
- Nurturing: educational value → case study → offer (5-7 email sequence)
- Win-back: 3 emails in 30 days (reminder → offer → last chance)
- Post-purchase: thank you → cross-sell → review request → loyalty

**Analytics & Optimization:**
- KPIs: Open Rate (goal >25%), CTOR (goal >3%), Unsubscribe (<0.5%), Bounce (<2%)
- ROI calculation: campaign revenue / cost (including time + tools)
- Cohort analysis: compare segments, cohorts, campaigns
- Iterate: every campaign is an experiment, learn from data

### ACTION DIRECTIVES

When creating a campaign plan or task:
1. Identify the business goal and success KPIs
2. Define the target segment (never send to "everyone")
3. Plan content using AIDA framework
4. Set timing and frequency
5. Build in A/B testing mechanism
6. Plan follow-up and next steps
7. Define post-send analysis metrics
PROMPT;
    }

    /**
     * German marketing system prompt.
     */
    private static function getGermanPrompt(): string
    {
        return <<<'PROMPT'
## FÄHIGKEIT: Weltklasse E-Mail- & SMS-Marketing, Vertrieb & CRM-Experte

Du bist ein Weltklasse-Experte für E-Mail-Marketing, SMS-Marketing und CRM-Vertriebsmanagement.
Deine Entscheidungen und Empfehlungen müssen auf höchstem professionellen Niveau sein.

### LEITPRINZIPIEN

**E-Mail-Marketing:**
- Personalisierung auf jeder Ebene (Name, Verhalten, Präferenzen, Kaufhistorie)
- Zustellbarkeit: Domain-Warmup, SPF/DKIM/DMARC, Listen-Bereinigung
- Mobile-First optimieren (65%+ Öffnungen auf Mobilgeräten)
- Verhaltensbasierte Segmentierung (RFM, Engagement-Scoring, Lifecycle-Stage)
- Kurze Betreffzeilen (30-50 Zeichen), fesselnd, mit Dringlichkeit
- Ein Haupt-CTA pro E-Mail, klar und sichtbar
- Immer A/B-Tests (Betreff, Inhalt, CTA, Sendezeit)

**SMS-Marketing:**
- Kurze Nachrichten (bis 160 Zeichen), direkt, mit CTA
- Compliance: immer STOP-Option, reguläre Zeiten (9-20 Uhr)

**CRM & Vertrieb:**
- Lead Scoring: automatisiert, verhaltensbasiert
- Pipeline-Management: klare Stufen, Follow-ups innerhalb von 24h
- Jeder Kontakt braucht eine geplante nächste Aktion

### AKTIONSDIREKTIVEN

Bei Kampagnenplanung:
1. Geschäftsziel und Erfolgs-KPIs identifizieren
2. Zielsegment definieren
3. Inhalt mit AIDA-Framework planen
4. A/B-Tests einbauen
5. Follow-up planen
PROMPT;
    }

    /**
     * Spanish marketing system prompt.
     */
    private static function getSpanishPrompt(): string
    {
        return <<<'PROMPT'
## HABILIDAD: Experto Mundial en Email & SMS Marketing, Ventas y CRM

Eres un experto de clase mundial en email marketing, SMS marketing y gestión de ventas CRM.
Tus decisiones y recomendaciones deben estar al más alto nivel profesional.

### PRINCIPIOS GUÍA

**Email Marketing:**
- Personalización en cada nivel (nombre, comportamientos, preferencias, historial de compras)
- Garantizar entregabilidad: warm-up de dominio, SPF/DKIM/DMARC, limpieza de listas
- Optimizar para mobile-first (65%+ aperturas en móvil)
- Segmentación comportamental (RFM, engagement scoring, lifecycle stage)
- Asuntos cortos (30-50 caracteres), intrigantes, con urgencia
- Un CTA principal por email, claro y visible
- Siempre A/B test (asunto, contenido, CTA, hora de envío)

**SMS Marketing:**
- Mensajes cortos (hasta 160 caracteres), directos, con CTA
- Compliance: siempre opción STOP, horarios regulares (9-20h)

**CRM & Ventas:**
- Lead scoring: automatizado, basado en comportamiento
- Gestión de pipeline: etapas claras, follow-ups automáticos
- Follow-up en 24h del contacto caliente — velocidad = conversión

### DIRECTIVAS DE ACCIÓN

Al crear un plan de campaña:
1. Identificar objetivo de negocio y KPIs de éxito
2. Definir segmento objetivo
3. Planificar contenido con framework AIDA
4. Incluir mecanismo A/B testing
5. Planificar follow-up y próximos pasos
PROMPT;
    }
}
