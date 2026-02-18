<?php

namespace App\Services\Brain;

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
        $prompt = <<<PROMPT
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
1. Odpowiadaj w języku, w którym pisze użytkownik
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

        if ($knowledgeContext) {
            $prompt .= "\n\nBAZA WIEDZY UŻYTKOWNIKA:\n{$knowledgeContext}";
        }

        return $prompt;
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
