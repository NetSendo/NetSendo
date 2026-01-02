<?php

namespace App\Services\Webinar;

use App\Models\AutoWebinarChatScript;
use App\Models\Webinar;
use App\Models\WebinarChatMessage;
use Illuminate\Support\Collection;

class AutoWebinarScriptBuilderService
{
    /**
     * Sample names for random script generation.
     */
    protected array $sampleNames = [
        'Anna Kowalska', 'Piotr Nowak', 'Marta Wiśniewska', 'Tomasz Wójcik',
        'Karolina Kamińska', 'Michał Lewandowski', 'Agnieszka Zielińska', 'Paweł Szymański',
        'Joanna Woźniak', 'Krzysztof Dąbrowski', 'Magdalena Kozłowska', 'Adam Jankowski',
        'Katarzyna Mazur', 'Marcin Krawczyk', 'Aleksandra Piotrowska', 'Łukasz Grabowski',
        'Natalia Pawlak', 'Jakub Michalski', 'Monika Zając', 'Robert Król',
        'Sarah Miller', 'John Smith', 'Emily Johnson', 'Michael Brown',
        'Jessica Davis', 'David Wilson', 'Ashley Martinez', 'Chris Anderson',
    ];

    /**
     * Sample messages by type.
     */
    protected array $sampleMessages = [
        'comment' => [
            'Świetna prezentacja! 👏',
            'Bardzo ciekawe informacje',
            'Dokładnie to, czego szukałem',
            'Super, że o tym mówisz!',
            'Mega wartościowe!',
            'Robię notatki 📝',
            'Wow, nie wiedziałem o tym!',
            'To ma sens!',
            'Dziękuję za te informacje',
            'Niesamowite!',
            'Ciekawi 🔥',
            'To jest złoto!',
        ],
        'question' => [
            'Czy to działa też w mojej branży?',
            'Jak długo to trwa?',
            'Czy mogę to zastosować od razu?',
            'A co z przypadkiem X?',
            'Czy są jakieś wymogi?',
            'Gdzie mogę znaleźć więcej info?',
            'Czy to jest dla początkujących?',
            'Jakie są pierwsze kroki?',
        ],
        'testimonial' => [
            'Używam tego od miesiąca i działa fantastycznie!',
            'Dzięki tej metodzie zwiększyłem sprzedaż o 40%',
            'Polecam każdemu! Zmieniło moje podejście',
            'Na początku byłem sceptyczny, ale wyniki mówią same za siebie',
            'To naprawdę działa! Jestem pod wrażeniem',
        ],
        'excitement' => [
            '🔥🔥🔥',
            'TAK! To jest to!',
            'Nie mogę się doczekać!',
            'Mega! 💪',
            'Czekam na więcej!',
            'To będzie hit!',
            'Jestem gotowy/a!',
            '❤️❤️❤️',
            'Super! 🎉',
        ],
        'reaction' => [
            '👍',
            '❤️',
            '🔥',
            '👏',
            '💯',
            '🙌',
            '✨',
            '💪',
        ],
    ];

    /**
     * Import messages from a previous live webinar.
     */
    public function importFromWebinar(
        Webinar $targetWebinar,
        Webinar $sourceWebinar,
        array $options = []
    ): int {
        $query = $sourceWebinar->chatMessages()
            ->visible()
            ->fromAttendees()
            ->orderBy('created_at');

        // Filter by type if specified
        if (!($options['include_questions'] ?? true)) {
            $query->where('message_type', '!=', WebinarChatMessage::TYPE_QUESTION);
        }

        // Filter by minimum likes
        if ($minLikes = ($options['min_likes'] ?? 0)) {
            $query->where('likes_count', '>=', $minLikes);
        }

        $messages = $query->get();

        if ($messages->isEmpty()) {
            return 0;
        }

        // Calculate relative times from webinar start
        $startTime = $sourceWebinar->started_at ?? $messages->first()->created_at;

        $imported = 0;
        foreach ($messages as $message) {
            $relativeSeconds = $message->created_at->diffInSeconds($startTime);

            // Determine message type
            $type = $this->mapMessageType($message);

            AutoWebinarChatScript::create([
                'webinar_id' => $targetWebinar->id,
                'sender_name' => $message->sender_name,
                'sender_avatar_seed' => $message->sender_name,
                'message_text' => $message->message,
                'message_type' => $type,
                'show_at_seconds' => $relativeSeconds,
                'reaction_count' => $message->likes_count,
                'delay_variance_seconds' => rand(0, 10),
                'is_active' => true,
                'is_original' => true,
                'source_message_id' => $message->id,
                'sort_order' => $imported,
            ]);

            $imported++;
        }

        return $imported;
    }

    /**
     * Generate random script entries.
     */
    public function generateRandomScript(
        Webinar $webinar,
        int $durationSeconds,
        float $density = 2,
        array $options = []
    ): int {
        // Calculate total messages based on density (messages per minute)
        $totalMessages = max(5, (int) (($durationSeconds / 60) * $density));

        // Determine which types to include
        $types = ['comment'];
        if ($options['include_questions'] ?? true) {
            $types[] = 'question';
        }
        if ($options['include_testimonials'] ?? true) {
            $types[] = 'testimonial';
        }
        if ($options['include_excitement'] ?? true) {
            $types[] = 'excitement';
            $types[] = 'reaction';
        }

        $generated = 0;
        $existingCount = $webinar->chatScripts()->count();

        for ($i = 0; $i < $totalMessages; $i++) {
            // Random time with some clustering (more at beginning and end)
            $showAt = $this->generateNaturalTime($durationSeconds, $i, $totalMessages);

            // Random type weighted towards comments
            $type = $this->getWeightedRandomType($types);

            // Random message
            $message = $this->getRandomMessage($type);

            // Random name
            $name = $this->sampleNames[array_rand($this->sampleNames)];

            AutoWebinarChatScript::create([
                'webinar_id' => $webinar->id,
                'sender_name' => $name,
                'sender_avatar_seed' => uniqid() . $name,
                'message_text' => $message,
                'message_type' => $type,
                'show_at_seconds' => $showAt,
                'reaction_count' => $type === 'reaction' ? 0 : rand(0, 5),
                'delay_variance_seconds' => rand(0, 15),
                'show_randomly' => rand(0, 100) < 30, // 30% chance
                'is_active' => true,
                'is_original' => false,
                'sort_order' => $existingCount + $generated,
            ]);

            $generated++;
        }

        return $generated;
    }

    /**
     * Generate a natural distribution of times.
     */
    protected function generateNaturalTime(int $duration, int $index, int $total): int
    {
        // More activity at some key points:
        // - Beginning (first 10%)
        // - Key moments (around 30%, 60%, 80%)
        // - End (last 10%)

        $percentage = $index / max(1, $total - 1);

        // Add some clustering
        $clusters = [0.05, 0.15, 0.30, 0.45, 0.60, 0.75, 0.90];
        $nearestCluster = $clusters[array_rand($clusters)];

        // Blend between linear and cluster-based
        $blendedPercentage = ($percentage * 0.6) + ($nearestCluster * 0.4);

        // Add random variance
        $variance = (rand(-150, 150) / 1000); // ±15%
        $finalPercentage = max(0.02, min(0.98, $blendedPercentage + $variance));

        return (int) ($finalPercentage * $duration);
    }

    /**
     * Get weighted random type.
     */
    protected function getWeightedRandomType(array $allowedTypes): string
    {
        $weights = [
            'comment' => 50,
            'question' => 15,
            'testimonial' => 10,
            'excitement' => 15,
            'reaction' => 10,
        ];

        $totalWeight = 0;
        foreach ($allowedTypes as $type) {
            $totalWeight += $weights[$type] ?? 0;
        }

        $rand = rand(1, $totalWeight);
        $cumulative = 0;

        foreach ($allowedTypes as $type) {
            $cumulative += $weights[$type] ?? 0;
            if ($rand <= $cumulative) {
                return $type;
            }
        }

        return 'comment';
    }

    /**
     * Get random message for type.
     */
    protected function getRandomMessage(string $type): string
    {
        $messages = $this->sampleMessages[$type] ?? $this->sampleMessages['comment'];
        return $messages[array_rand($messages)];
    }

    /**
     * Map WebinarChatMessage type to script type.
     */
    protected function mapMessageType(WebinarChatMessage $message): string
    {
        if ($message->message_type === WebinarChatMessage::TYPE_QUESTION) {
            return 'question';
        }

        // Try to detect type from content
        $text = mb_strtolower($message->message);

        // Check for testimonial indicators
        if (
            str_contains($text, 'polecam') ||
            str_contains($text, 'działa') ||
            str_contains($text, 'zwiększ') ||
            str_contains($text, 'wyniki')
        ) {
            return 'testimonial';
        }

        // Check for excitement
        if (
            str_contains($text, '!') && strlen($text) < 30 ||
            preg_match('/[\x{1F600}-\x{1F64F}]{2,}/u', $text) // Multiple emojis
        ) {
            return 'excitement';
        }

        // Check for reaction (emoji only)
        if (preg_match('/^[\x{1F300}-\x{1F9FF}\s]+$/u', $text) && strlen($text) < 15) {
            return 'reaction';
        }

        return 'comment';
    }

    /**
     * Create a template-based script.
     */
    public function createFromTemplate(
        Webinar $webinar,
        string $template,
        int $durationMinutes = 60
    ): int {
        $templates = [
            'sales' => $this->getSalesTemplate($durationMinutes * 60),
            'educational' => $this->getEducationalTemplate($durationMinutes * 60),
            'launch' => $this->getLaunchTemplate($durationMinutes * 60),
        ];

        $entries = $templates[$template] ?? $templates['sales'];
        $generated = 0;

        foreach ($entries as $entry) {
            AutoWebinarChatScript::create(array_merge($entry, [
                'webinar_id' => $webinar->id,
                'sender_avatar_seed' => uniqid(),
                'is_active' => true,
                'sort_order' => $generated,
            ]));
            $generated++;
        }

        return $generated;
    }

    /**
     * Sales webinar template.
     */
    protected function getSalesTemplate(int $duration): array
    {
        $segments = [
            ['time' => 0.02, 'type' => 'excitement', 'message' => 'Czekałam na to! 🎉'],
            ['time' => 0.05, 'type' => 'comment', 'message' => 'Pierwsza prezentacja od Was!'],
            ['time' => 0.10, 'type' => 'comment', 'message' => 'Ciekawa intro!'],
            ['time' => 0.15, 'type' => 'question', 'message' => 'Czy to działa dla małych firm?'],
            ['time' => 0.20, 'type' => 'comment', 'message' => 'Dokładnie tego szukałem'],
            ['time' => 0.30, 'type' => 'testimonial', 'message' => 'Używam podobnego rozwiązania i działa świetnie!'],
            ['time' => 0.40, 'type' => 'excitement', 'message' => '🔥🔥🔥 Super content!'],
            ['time' => 0.50, 'type' => 'question', 'message' => 'Ile to kosztuje?'],
            ['time' => 0.60, 'type' => 'comment', 'message' => 'Wow, nie wiedziałem że to tak proste'],
            ['time' => 0.70, 'type' => 'excitement', 'message' => 'Chcę to! 💪'],
            ['time' => 0.75, 'type' => 'testimonial', 'message' => 'Zainwestowałem w to i zwróciło się w miesiąc'],
            ['time' => 0.80, 'type' => 'excitement', 'message' => 'KUPUJĘ! 🚀'],
            ['time' => 0.85, 'type' => 'question', 'message' => 'Czy jest gwarancja zwrotu?'],
            ['time' => 0.90, 'type' => 'excitement', 'message' => 'Zamówione! Dziękuję! ❤️'],
            ['time' => 0.95, 'type' => 'testimonial', 'message' => 'Najlepsza decyzja którą podjąłem!'],
        ];

        return array_map(fn($s) => [
            'sender_name' => $this->sampleNames[array_rand($this->sampleNames)],
            'message_text' => $s['message'],
            'message_type' => $s['type'],
            'show_at_seconds' => (int) ($s['time'] * $duration),
            'reaction_count' => rand(0, 5),
            'delay_variance_seconds' => rand(0, 20),
        ], $segments);
    }

    /**
     * Educational webinar template.
     */
    protected function getEducationalTemplate(int $duration): array
    {
        $segments = [
            ['time' => 0.03, 'type' => 'comment', 'message' => 'Cześć wszystkim! 👋'],
            ['time' => 0.08, 'type' => 'question', 'message' => 'Od czego zaczynamy dzisiaj?'],
            ['time' => 0.15, 'type' => 'comment', 'message' => 'Super wstęp!'],
            ['time' => 0.20, 'type' => 'comment', 'message' => 'Robię notatki 📝'],
            ['time' => 0.25, 'type' => 'question', 'message' => 'Możesz to powtórzyć?'],
            ['time' => 0.35, 'type' => 'comment', 'message' => 'Wow, nie wiedziałem o tym!'],
            ['time' => 0.45, 'type' => 'question', 'message' => 'Gdzie mogę znaleźć więcej materiałów?'],
            ['time' => 0.55, 'type' => 'excitement', 'message' => 'To ma sens! 💡'],
            ['time' => 0.65, 'type' => 'comment', 'message' => 'Bardzo przydatne informacje'],
            ['time' => 0.75, 'type' => 'question', 'message' => 'Czy będzie nagranie?'],
            ['time' => 0.85, 'type' => 'excitement', 'message' => 'Świetna prezentacja! 👏'],
            ['time' => 0.95, 'type' => 'comment', 'message' => 'Dziękuję za wiedzę! ❤️'],
        ];

        return array_map(fn($s) => [
            'sender_name' => $this->sampleNames[array_rand($this->sampleNames)],
            'message_text' => $s['message'],
            'message_type' => $s['type'],
            'show_at_seconds' => (int) ($s['time'] * $duration),
            'reaction_count' => rand(0, 3),
            'delay_variance_seconds' => rand(0, 15),
        ], $segments);
    }

    /**
     * Product launch template.
     */
    protected function getLaunchTemplate(int $duration): array
    {
        $segments = [
            ['time' => 0.02, 'type' => 'excitement', 'message' => 'HYPED! 🚀'],
            ['time' => 0.05, 'type' => 'excitement', 'message' => 'Czekałem na to od tygodnia!'],
            ['time' => 0.10, 'type' => 'comment', 'message' => 'Pozdrawiam z Warszawy!'],
            ['time' => 0.12, 'type' => 'comment', 'message' => 'Kraków here! 🙌'],
            ['time' => 0.18, 'type' => 'question', 'message' => 'Co nowego dziś pokażecie?'],
            ['time' => 0.25, 'type' => 'excitement', 'message' => 'OMG to jest niesamowite! 😮'],
            ['time' => 0.30, 'type' => 'comment', 'message' => 'To zmieni grę!'],
            ['time' => 0.40, 'type' => 'excitement', 'message' => '🔥🔥🔥🔥🔥'],
            ['time' => 0.50, 'type' => 'question', 'message' => 'Kiedy będzie dostępne?'],
            ['time' => 0.55, 'type' => 'testimony', 'message' => 'Testowałem beta i jest MEGA!'],
            ['time' => 0.65, 'type' => 'excitement', 'message' => 'TAKE MY MONEY! 💸'],
            ['time' => 0.70, 'type' => 'question', 'message' => 'Jest early birds?'],
            ['time' => 0.78, 'type' => 'excitement', 'message' => 'KUPIONE! 🎉'],
            ['time' => 0.82, 'type' => 'excitement', 'message' => 'Best launch ever! 🏆'],
            ['time' => 0.88, 'type' => 'comment', 'message' => 'Zamówiłem! Nie mogę się doczekać!'],
            ['time' => 0.93, 'type' => 'excitement', 'message' => '❤️❤️❤️ Dziękuję za świetny produkt!'],
            ['time' => 0.97, 'type' => 'comment', 'message' => 'Do zobaczenia następnym razem! 👋'],
        ];

        return array_map(fn($s) => [
            'sender_name' => $this->sampleNames[array_rand($this->sampleNames)],
            'message_text' => $s['message'],
            'message_type' => $s['type'] === 'testimony' ? 'testimonial' : $s['type'],
            'show_at_seconds' => (int) ($s['time'] * $duration),
            'reaction_count' => rand(0, 10),
            'delay_variance_seconds' => rand(0, 10),
        ], $segments);
    }
}
