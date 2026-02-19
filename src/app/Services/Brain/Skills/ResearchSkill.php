<?php

namespace App\Services\Brain\Skills;

use App\Models\AiBrainSettings;
use App\Models\CrmContact;
use App\Models\User;

/**
 * ResearchSkill — Provides research-awareness to the Brain orchestrator.
 *
 * This skill augments the system prompts with research capabilities
 * and generates research-related task suggestions.
 */
class ResearchSkill
{
    /**
     * Get the research-awareness system prompt.
     */
    public static function getSystemPrompt(string $language = 'en'): string
    {
        return match ($language) {
            'pl' => self::getPolishPrompt(),
            'de' => self::getGermanPrompt(),
            'es' => self::getSpanishPrompt(),
            default => self::getEnglishPrompt(),
        };
    }

    private static function getEnglishPrompt(): string
    {
        return <<<PROMPT
## Internet Research Capabilities

You have access to real-time internet research tools:
- **Web Search** — search Google for current information, news, and data
- **Deep Research** — AI-powered comprehensive research with cited sources
- **Company Research** — investigate companies, competitors, and market position
- **Trend Analysis** — discover market trends, industry insights, and emerging patterns
- **Content Research** — find inspiration and best practices for marketing content

WHEN TO USE RESEARCH:
• User asks about competitors, market trends, or external topics
• User needs up-to-date information that's beyond your training data
• Creating campaigns that require current market context
• Enriching CRM company data with real-world information
• User explicitly requests research or investigation

HOW RESEARCH WORKS:
1. Research tasks are routed to the Research Agent
2. The agent creates a research plan (web search + deep analysis)
3. Findings are presented with cited sources
4. Valuable findings can be auto-saved to the Knowledge Base for future use

IMPORTANT: When you detect a user needs external/real-time information, route to the 'research' agent instead of trying to answer from memory.
PROMPT;
    }

    private static function getPolishPrompt(): string
    {
        return <<<PROMPT
## Możliwości Badań Internetowych

Masz dostęp do narzędzi badawczych w czasie rzeczywistym:
- **Wyszukiwanie www** — przeszukaj Google po aktualne informacje, wiadomości i dane
- **Głęboki Research** — kompleksowe badania wspierane przez AI z cytowanymi źródłami
- **Badanie Firm** — analizuj firmy, konkurencję i pozycję rynkową
- **Analiza Trendów** — odkrywaj trendy rynkowe, wnioski branżowe i nowe wzorce
- **Badanie Treści** — znajdź inspirację i najlepsze praktyki dla treści marketingowych

KIEDY UŻYWAĆ RESEARCHU:
• Użytkownik pyta o konkurencję, trendy rynkowe lub tematy zewnętrzne
• Użytkownik potrzebuje aktualnych informacji wykraczających poza dane treningowe
• Tworzenie kampanii wymagających aktualnego kontekstu rynkowego
• Wzbogacanie danych firm w CRM realną informacją
• Użytkownik wprost prosi o badanie lub dochodzenie

WAŻNE: Gdy wykryjesz, że użytkownik potrzebuje zewnętrznych/bieżących informacji, przekieruj do agenta 'research' zamiast odpowiadać z pamięci.
PROMPT;
    }

    private static function getGermanPrompt(): string
    {
        return <<<PROMPT
## Internet-Recherchekapazitäten

Sie haben Zugang zu Echtzeit-Recherchetools:
- **Websuche** — Google-Suche nach aktuellen Informationen, Nachrichten und Daten
- **Tiefenrecherche** — KI-gestützte umfassende Recherche mit zitierten Quellen
- **Unternehmensrecherche** — Unternehmen, Wettbewerber und Marktposition analysieren
- **Trendanalyse** — Markttrends, Brancheneinblicke und neue Muster entdecken
- **Inhaltsrecherche** — Inspiration und Best Practices für Marketinginhalte finden

WANN RECHERCHE NUTZEN:
• Nutzer fragt nach Wettbewerbern, Markttrends oder externen Themen
• Nutzer benötigt aktuelle Informationen jenseits der Trainingsdaten
• Kampagnenerstellung mit aktuellem Marktkontext
• CRM-Unternehmensdaten mit realen Informationen anreichern

WICHTIG: Leiten Sie zum 'research'-Agenten weiter, wenn externe/aktuelle Informationen benötigt werden.
PROMPT;
    }

    private static function getSpanishPrompt(): string
    {
        return <<<PROMPT
## Capacidades de Investigación en Internet

Tienes acceso a herramientas de investigación en tiempo real:
- **Búsqueda web** — buscar en Google información actual, noticias y datos
- **Investigación profunda** — investigación completa impulsada por IA con fuentes citadas
- **Investigación de empresas** — analizar empresas, competidores y posición en el mercado
- **Análisis de tendencias** — descubrir tendencias del mercado y patrones emergentes
- **Investigación de contenido** — encontrar inspiración y mejores prácticas para contenido de marketing

CUÁNDO USAR INVESTIGACIÓN:
• Usuario pregunta sobre competidores, tendencias o temas externos
• Usuario necesita información actualizada más allá de los datos de entrenamiento
• Creación de campañas que requieren contexto de mercado actual
• Enriquecimiento de datos de empresas en CRM con información real

IMPORTANTE: Cuando detectes que el usuario necesita información externa/actual, dirige al agente 'research' en lugar de responder de memoria.
PROMPT;
    }

    /**
     * Get research-related task suggestions for the cron task list.
     */
    public static function getSuggestedTasks(User $user): array
    {
        $settings = AiBrainSettings::getForUser($user->id);

        if (!$settings->isResearchEnabled()) {
            return [];
        }

        $tasks = [];

        // Check if user has companies without enriched data
        $emptyCompanies = \App\Models\CrmCompany::forUser($user->id)
            ->whereNull('website')
            ->limit(5)
            ->count();

        if ($emptyCompanies > 0) {
            $tasks[] = [
                'id' => 'research_enrich_companies',
                'title' => __('brain.research.task_enrich_companies', ['count' => $emptyCompanies]),
                'category' => 'company_research',
                'priority' => 'medium',
                'agent' => 'research',
                'action' => "Research and enrich {$emptyCompanies} companies in CRM that are missing website and industry data",
            ];
        }

        // Check if there are hot leads worth researching
        $hotLeads = CrmContact::forUser($user->id)->hotLeads()->count();
        if ($hotLeads > 0) {
            $tasks[] = [
                'id' => 'research_hot_leads',
                'title' => __('brain.research.task_research_leads', ['count' => $hotLeads]),
                'category' => 'company_research',
                'priority' => 'medium',
                'agent' => 'research',
                'action' => "Research the companies of {$hotLeads} hot leads to prepare for sales outreach",
            ];
        }

        // Always suggest a trend analysis
        $tasks[] = [
            'id' => 'research_industry_trends',
            'title' => __('brain.research.task_trends'),
            'category' => 'market_trends',
            'priority' => 'low',
            'agent' => 'research',
            'action' => 'Research the latest email marketing and CRM trends and save findings to knowledge base',
        ];

        return $tasks;
    }
}
