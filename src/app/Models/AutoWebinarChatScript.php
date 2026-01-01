<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutoWebinarChatScript extends Model
{
    use HasFactory;

    // Message type constants
    public const TYPE_QUESTION = 'question';
    public const TYPE_COMMENT = 'comment';
    public const TYPE_REACTION = 'reaction';
    public const TYPE_TESTIMONIAL = 'testimonial';
    public const TYPE_EXCITEMENT = 'excitement';

    protected $fillable = [
        'webinar_id',
        'show_at_seconds',
        'sender_name',
        'sender_avatar_seed',
        'message_type',
        'message_text',
        'reaction_count',
        'delay_variance_seconds',
        'show_randomly',
        'is_original',
        'source_message_id',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'show_at_seconds' => 'integer',
        'reaction_count' => 'integer',
        'delay_variance_seconds' => 'integer',
        'show_randomly' => 'boolean',
        'is_original' => 'boolean',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Sample names for generating fake chat users.
     */
    public const SAMPLE_NAMES = [
        'Anna K.', 'Piotr M.', 'Katarzyna W.', 'Marcin B.', 'Ewa S.',
        'Tomasz N.', 'Magdalena P.', 'Krzysztof L.', 'Agnieszka R.', 'Michał D.',
        'Joanna T.', 'Paweł Z.', 'Monika H.', 'Adam G.', 'Aleksandra C.',
        'Robert J.', 'Karolina F.', 'Jakub O.', 'Natalia I.', 'Łukasz A.',
    ];

    /**
     * Sample comments for different types.
     */
    public const SAMPLE_MESSAGES = [
        self::TYPE_QUESTION => [
            'Czy to działa też dla początkujących?',
            'Kiedy będzie dostępna oferta?',
            'Czy jest gwarancja zwrotu pieniędzy?',
            'Jak długo mam dostęp do materiałów?',
            'Czy mogę zapłacić na raty?',
            'Czy jest support po zakupie?',
            'Kiedy startują kolejne zajęcia?',
        ],
        self::TYPE_COMMENT => [
            'Super materiał! 👍',
            'Bardzo przydatne informacje',
            'To dokładnie to, czego szukałem',
            'Świetnie wytłumaczone',
            'Dziękuję za tę prezentację',
            'Rewelacja!',
            'Zapisuję sobie te wskazówki',
        ],
        self::TYPE_REACTION => [
            '🔥🔥🔥',
            '👏👏👏',
            '💯',
            '❤️',
            '👍',
            '🚀',
            '⭐⭐⭐',
        ],
        self::TYPE_TESTIMONIAL => [
            'Kupiłem poprzedni kurs i był świetny!',
            'Polecam, zdecydowanie warto!',
            'Już stosuję te metody i działają!',
            'Najlepsze szkolenie jakie widziałem',
            'ROI zwrócił się w pierwszym tygodniu',
        ],
        self::TYPE_EXCITEMENT => [
            'Czekam na ofertę!',
            'Nie mogę się doczekać!',
            'Wow, to genialny pomysł!',
            'Właśnie kupuję!',
            'Biorę to!',
            'Super cena!',
        ],
    ];

    // =====================================
    // Relationships
    // =====================================

    public function webinar(): BelongsTo
    {
        return $this->belongsTo(Webinar::class);
    }

    public function sourceMessage(): BelongsTo
    {
        return $this->belongsTo(WebinarChatMessage::class, 'source_message_id');
    }

    // =====================================
    // Scopes
    // =====================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForTime($query, int $currentSeconds)
    {
        return $query->where('show_at_seconds', '<=', $currentSeconds)
            ->orderBy('show_at_seconds')
            ->orderBy('sort_order');
    }

    public function scopeAtSecond($query, int $second, int $tolerance = 2)
    {
        return $query->whereBetween('show_at_seconds', [$second - $tolerance, $second + $tolerance]);
    }

    // =====================================
    // Accessors
    // =====================================

    /**
     * Get avatar URL for this script message.
     */
    public function getAvatarUrlAttribute(): string
    {
        $seed = $this->sender_avatar_seed ?? $this->sender_name;
        return "https://api.dicebear.com/7.x/avataaars/svg?seed=" . urlencode($seed);
    }

    /**
     * Calculate actual display time with variance.
     */
    public function getActualShowTimeAttribute(): int
    {
        if ($this->delay_variance_seconds === 0) {
            return $this->show_at_seconds;
        }

        // Add random variance based on message id for consistency
        $variance = ($this->id % ($this->delay_variance_seconds * 2 + 1)) - $this->delay_variance_seconds;
        return max(0, $this->show_at_seconds + $variance);
    }

    // =====================================
    // Methods
    // =====================================

    /**
     * Check if should show (considering random flag).
     */
    public function shouldShow(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->show_randomly) {
            // 70% chance to show
            return (mt_rand(1, 100) <= 70);
        }

        return true;
    }

    /**
     * Convert to chat message for display.
     */
    public function toChatMessage(WebinarSession $session): WebinarChatMessage
    {
        return new WebinarChatMessage([
            'webinar_id' => $this->webinar_id,
            'webinar_session_id' => $session->id,
            'sender_type' => WebinarChatMessage::SENDER_BOT,
            'sender_name' => $this->sender_name,
            'sender_avatar_url' => $this->avatar_url,
            'message' => $this->message_text,
            'message_type' => WebinarChatMessage::TYPE_TEXT,
            'show_at_seconds' => $this->actual_show_time,
            'likes_count' => $this->reaction_count,
        ]);
    }

    /**
     * Generate random script messages.
     */
    public static function generateRandomMessages(
        Webinar $webinar,
        int $durationSeconds,
        int $density = 1 // messages per minute on average
    ): array {
        $messages = [];
        $totalMessages = (int) ($durationSeconds / 60 * $density);

        // Distribution: more messages during key moments
        $keyMoments = [
            60 => 3,      // Start of webinar
            300 => 2,     // 5 minutes in
            900 => 2,     // 15 minutes
            1800 => 3,    // 30 minutes (often offer time)
        ];

        // Generate regular messages
        for ($i = 0; $i < $totalMessages; $i++) {
            $showAt = mt_rand(30, $durationSeconds - 60);
            $type = self::getRandomType();

            $messages[] = [
                'webinar_id' => $webinar->id,
                'show_at_seconds' => $showAt,
                'sender_name' => self::getRandomName(),
                'sender_avatar_seed' => uniqid(),
                'message_type' => $type,
                'message_text' => self::getRandomMessage($type),
                'reaction_count' => mt_rand(0, 5),
                'delay_variance_seconds' => 3,
                'show_randomly' => true,
                'is_original' => false,
                'sort_order' => $i,
                'is_active' => true,
            ];
        }

        // Add key moment messages
        foreach ($keyMoments as $second => $count) {
            if ($second < $durationSeconds) {
                for ($i = 0; $i < $count; $i++) {
                    $type = self::getRandomType();
                    $messages[] = [
                        'webinar_id' => $webinar->id,
                        'show_at_seconds' => $second + mt_rand(-10, 10),
                        'sender_name' => self::getRandomName(),
                        'sender_avatar_seed' => uniqid(),
                        'message_type' => $type,
                        'message_text' => self::getRandomMessage($type),
                        'reaction_count' => mt_rand(0, 8),
                        'delay_variance_seconds' => 2,
                        'show_randomly' => false, // Always show key messages
                        'is_original' => false,
                        'sort_order' => 1000 + $i,
                        'is_active' => true,
                    ];
                }
            }
        }

        // Sort by show_at_seconds
        usort($messages, fn($a, $b) => $a['show_at_seconds'] <=> $b['show_at_seconds']);

        return $messages;
    }

    /**
     * Get random name.
     */
    public static function getRandomName(): string
    {
        return self::SAMPLE_NAMES[array_rand(self::SAMPLE_NAMES)];
    }

    /**
     * Get random type weighted towards comments.
     */
    public static function getRandomType(): string
    {
        $weights = [
            self::TYPE_COMMENT => 50,
            self::TYPE_QUESTION => 20,
            self::TYPE_REACTION => 15,
            self::TYPE_EXCITEMENT => 10,
            self::TYPE_TESTIMONIAL => 5,
        ];

        $total = array_sum($weights);
        $rand = mt_rand(1, $total);
        $cumulative = 0;

        foreach ($weights as $type => $weight) {
            $cumulative += $weight;
            if ($rand <= $cumulative) {
                return $type;
            }
        }

        return self::TYPE_COMMENT;
    }

    /**
     * Get random message for type.
     */
    public static function getRandomMessage(string $type): string
    {
        $messages = self::SAMPLE_MESSAGES[$type] ?? self::SAMPLE_MESSAGES[self::TYPE_COMMENT];
        return $messages[array_rand($messages)];
    }

    /**
     * Get type options.
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_QUESTION => 'Pytanie',
            self::TYPE_COMMENT => 'Komentarz',
            self::TYPE_REACTION => 'Reakcja',
            self::TYPE_TESTIMONIAL => 'Opinia',
            self::TYPE_EXCITEMENT => 'Entuzjazm',
        ];
    }
}
