<?php

namespace App\Services\Brain;

use App\Models\AiBrainSettings;
use App\Models\AiConversation;
use App\Models\AiConversationMessage;
use App\Models\User;
use Illuminate\Support\Collection;

class ConversationManager
{
    /**
     * Get or create an active conversation for a user and channel.
     */
    public function getConversation(User $user, string $channel = 'web'): AiConversation
    {
        return AiConversation::getOrCreateActive($user->id, $channel);
    }

    /**
     * Get a specific conversation by ID (for resuming existing conversations).
     */
    public function getConversationById(int $conversationId, int $userId): ?AiConversation
    {
        return AiConversation::forUser($userId)->find($conversationId);
    }

    /**
     * Create a brand-new conversation (for "New Conversation" button).
     */
    public function createNewConversation(User $user, string $channel = 'web'): AiConversation
    {
        return AiConversation::create([
            'user_id' => $user->id,
            'channel' => $channel,
            'status' => 'active',
            'last_activity_at' => now(),
        ]);
    }

    /**
     * Add a user message to the conversation.
     */
    public function addUserMessage(AiConversation $conversation, string $content): AiConversationMessage
    {
        return $conversation->addMessage('user', $content);
    }

    /**
     * Add an assistant (AI) message to the conversation.
     */
    public function addAssistantMessage(
        AiConversation $conversation,
        string $content,
        array $metadata = [],
        int $tokensIn = 0,
        int $tokensOut = 0,
        ?string $model = null,
    ): AiConversationMessage {
        $message = $conversation->addMessage('assistant', $content, $metadata);

        $message->update([
            'tokens_input' => $tokensIn,
            'tokens_output' => $tokensOut,
            'model_used' => $model,
        ]);

        // Update conversation totals
        $conversation->increment('total_tokens', $tokensIn + $tokensOut);

        return $message;
    }

    /**
     * Add a system message to the conversation.
     */
    public function addSystemMessage(AiConversation $conversation, string $content): AiConversationMessage
    {
        return $conversation->addMessage('system', $content);
    }

    /**
     * Build the system prompt with user context.
     */
    public function buildSystemPrompt(User $user, string $knowledgeContext = ''): string
    {
        // Resolve user's timezone and locale
        $userTimezone = $user->timezone ?? config('app.timezone', 'UTC');
        $now = now()->timezone($userTimezone);
        $dateTimeContext = sprintf(
            "AKTUALNA DATA I CZAS: %s (%s) | Strefa czasowa: %s",
            $now->translatedFormat('l, j F Y, H:i'),
            $now->format('Y-m-d H:i:s'),
            $userTimezone
        );

        $locale = $user->locale ?? 'pl';
        $languageMap = [
            'pl' => 'polski',
            'en' => 'angielski (English)',
            'de' => 'niemiecki (Deutsch)',
            'es' => 'hiszpański (Español)',
        ];
        $language = $languageMap[$locale] ?? $locale;

        $prompt = <<<PROMPT
{$dateTimeContext}

Jesteś NetSendo Brain — profesjonalnym asystentem AI specjalizującym się w email marketingu, SMS marketingu, CRM i automatyzacji marketingu.

TWOJA ROLA:
Jesteś strategicznym partnerem marketingowym użytkownika. Twoim celem jest pomaganie w budowaniu skutecznych kampanii marketingowych,
zarządzaniu relacjami z klientami i optymalizacji działań marketingowych. Działasz jak doświadczony marketer i doradca biznesowy.

TWOJE KOMPETENCJE:
• Tworzenie i zarządzanie kampaniami email i SMS (segmentacja, harmonogramowanie, treść)
• Zarządzanie listami kontaktów, subskrybentami i ich segmentacją
• Generowanie skutecznych treści marketingowych (tematy emaili, treść HTML, SMS, CTA)
• Analiza wyników kampanii (open rate, click rate, konwersje, trendy)
• Scoring leadów i zarządzanie lejkiem sprzedażowym (pipeline CRM)
• Automatyzacja marketingu (reguły, triggery, workflow)
• Planowanie strategii marketingowej i doradztwo
• Zarządzanie kontaktami CRM, firmami, dealami i zadaniami

ZASADY PRACY:
1. Domyślnie odpowiadaj po {$language}. Jeśli użytkownik pisze w innym języku, przełącz się na ten język.
2. Bądź konkretny, proaktywny i zorientowany na rezultaty — proponuj rozwiązania, nie tylko opisuj problemy
3. Gdy użytkownik prosi o akcję, stwórz plan z konkretnymi, wykonalnymi krokami
4. Zawsze wyjaśniaj, co zamierzasz zrobić, zanim to zrobisz — buduj zaufanie
5. Podawaj dane liczbowe i KPI gdy są dostępne — opieraj się na danych, nie domysłach
6. Używaj emoji dla lepszej czytelności (szczególnie w Telegramie)
7. Proponuj best practices email marketingu (optymalna pora wysyłki, A/B testy, personalizacja)
8. Gdy masz dostęp do bazy wiedzy użytkownika — ZAWSZE ją wykorzystuj do personalizacji odpowiedzi
9. Ostrzegaj przed potencjalnymi problemami (spam score, niska deliverability, za duża częstotliwość)
10. Dbaj o zgodność z RODO/GDPR w rekomendacjach dotyczących danych osobowych
PROMPT;

        // Inject operational context (cron, telegram, work mode, agents)
        $prompt .= $this->buildOperationalContext($user);

        if ($knowledgeContext) {
            $prompt .= "\n\nBAZA WIEDZY UŻYTKOWNIKA:\n{$knowledgeContext}";
        }

        return $prompt;
    }

    /**
     * Build operational context block — cron, telegram, work mode, agents.
     */
    private function buildOperationalContext(User $user): string
    {
        $settings = AiBrainSettings::getForUser($user->id);
        $userTimezone = $user->timezone ?? config('app.timezone', 'UTC');
        $sections = [];

        // --- Work mode ---
        $modeLabels = [
            'autonomous' => 'Autonomiczny — wykonujesz zadania samodzielnie bez pytania',
            'semi_auto' => 'Półautomatyczny — proponujesz plany, czekasz na akceptację użytkownika',
            'manual' => 'Manualny — tylko doradzasz, nie wykonujesz żadnych akcji',
        ];
        $modeLabel = $modeLabels[$settings->work_mode] ?? $modeLabels['semi_auto'];
        $sections[] = "TWÓJ TRYB PRACY: {$settings->work_mode} — {$modeLabel}";

        // --- CRON awareness ---
        if ($settings->cron_enabled && $settings->cron_interval_minutes) {
            $interval = (int) $settings->cron_interval_minutes;

            $lastRun = $settings->last_cron_run_at
                ? $settings->last_cron_run_at->timezone($userTimezone)->format('Y-m-d H:i')
                : 'jeszcze nigdy';

            $nextRun = $settings->last_cron_run_at
                ? $settings->last_cron_run_at->addMinutes($interval)->timezone($userTimezone)->format('H:i')
                : 'wkrótce';

            $cronBlock = <<<CRON

AUTONOMICZNA PRACA (CRON AKTYWNY):
Masz włączone automatyczne planowanie! Pracujesz autonomicznie co {$interval} minut.
• Ostatnie uruchomienie: {$lastRun}
• Następne uruchomienie: ~{$nextRun}
• W każdym cyklu automatycznie sprawdzasz sugerowane zadania i wykonujesz te o wysokim priorytecie.

CO TO OZNACZA DLA CIEBIE:
- MOŻESZ planować zadania na przyszłe cykle — zostaną wykonane automatycznie
- MOŻESZ rozkładać prace w czasie (np. "teraz przygotuj treść, w następnym cyklu wyślij kampanię")
- MOŻESZ przydzielać zadania sub-agentom, którzy raportują Ci wykonanie
- NIE MÓWI prawdy "nie mogę pracować w tle" — pracujesz autonomicznie co {$interval} minut!
- Gdy użytkownik pyta o planowanie: informuj że masz aktywny harmonogram i zaproponuj konkretny plan z przypisaniem do cykli
CRON;
            $sections[] = $cronBlock;
        }

        // --- Sub-agents ---
        $sections[] = <<<AGENTS

TWOI SUB-AGENCI (możesz do nich delegować zadania):
• campaign — planowanie i tworzenie kampanii email/SMS, harmonogramowanie, A/B testy
• list — zarządzanie listami kontaktów, import/eksport, segmenty
• message — tworzenie treści wiadomości, szablony HTML, copywriting
• crm — zarządzanie kontaktami, firmami, dealami, zadaniami CRM
• analytics — analiza wyników kampanii, raporty, KPI, trendy
• segmentation — segmentacja odbiorców, scoring, grupy docelowe

Każdy sub-agent raportuje Ci wyniki swojej pracy. Możesz tworzyć plany wieloetapowe z podziałem na agentów.
AGENTS;

        // --- Telegram ---
        if ($settings->isTelegramConnected()) {
            $sections[] = <<<TELEGRAM_BLOCK

TELEGRAM (PODŁĄCZONY ✅):
Użytkownik ma podłączonego Telegrama. Po wykonaniu zadań automatycznych (CRON) wyniki są raportowane na Telegram.
• Gdy planujesz zadania — informuj że wyniki trafią na Telegram
• Gdy użytkownik pyta o powiadomienia — potwierdź że raporty idą na Telegram automatycznie
TELEGRAM_BLOCK;
        }

        return "\n\n" . implode("\n", $sections);
    }

    /**
     * Build messages array for AI API call (with system prompt).
     */
    public function buildAiPayload(
        AiConversation $conversation,
        User $user,
        string $knowledgeContext = '',
        int $historyLimit = 20,
    ): array {
        $messages = [];

        // System prompt
        $messages[] = [
            'role' => 'system',
            'content' => $this->buildSystemPrompt($user, $knowledgeContext),
        ];

        // Conversation history
        $history = $conversation->buildAiMessages($historyLimit);
        $messages = array_merge($messages, $history);

        return $messages;
    }

    /**
     * Get recent conversations for a user.
     */
    public function getRecentConversations(User $user, int $limit = 10): Collection
    {
        return AiConversation::forUser($user->id)
            ->orderByDesc('last_activity_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Archive old conversations.
     */
    public function archiveOldConversations(User $user, int $daysOld = 30): int
    {
        return AiConversation::forUser($user->id)
            ->active()
            ->where('last_activity_at', '<', now()->subDays($daysOld))
            ->update(['status' => 'archived']);
    }

    /**
     * Set title for a conversation (can be AI-generated).
     */
    public function setTitle(AiConversation $conversation, string $title): void
    {
        $conversation->update(['title' => $title]);
    }
}
