<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class WebinarChatReaction extends Model
{
    use HasFactory;

    // Reaction type constants
    public const TYPE_HEART = 'heart';
    public const TYPE_THUMBS_UP = 'thumbs_up';
    public const TYPE_FIRE = 'fire';
    public const TYPE_CLAP = 'clap';
    public const TYPE_WOW = 'wow';
    public const TYPE_LAUGH = 'laugh';
    public const TYPE_THINK = 'think';

    protected $fillable = [
        'webinar_id',
        'webinar_session_id',
        'registration_id',
        'type',
        'is_simulated',
        'position_x',
    ];

    protected $casts = [
        'is_simulated' => 'boolean',
        'position_x' => 'integer',
    ];

    // =====================================
    // Relationships
    // =====================================

    public function webinar(): BelongsTo
    {
        return $this->belongsTo(Webinar::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(WebinarSession::class, 'webinar_session_id');
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(WebinarRegistration::class, 'registration_id');
    }

    // =====================================
    // Scopes
    // =====================================

    /**
     * Scope to get reactions from the last X seconds.
     */
    public function scopeRecent($query, int $seconds = 30)
    {
        return $query->where('created_at', '>=', now()->subSeconds($seconds));
    }

    /**
     * Scope for real (non-simulated) reactions.
     */
    public function scopeReal($query)
    {
        return $query->where('is_simulated', false);
    }

    /**
     * Scope for simulated reactions.
     */
    public function scopeSimulated($query)
    {
        return $query->where('is_simulated', true);
    }

    // =====================================
    // Static Methods
    // =====================================

    /**
     * Get all available reaction types with their emoji.
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_HEART => '❤️',
            self::TYPE_THUMBS_UP => '👍',
            self::TYPE_FIRE => '🔥',
            self::TYPE_CLAP => '👏',
            self::TYPE_WOW => '😮',
            self::TYPE_LAUGH => '😂',
            self::TYPE_THINK => '🤔',
        ];
    }

    /**
     * Get emoji for type.
     */
    public static function getEmoji(string $type): string
    {
        return self::getTypes()[$type] ?? '❤️';
    }

    /**
     * Get count by type for a webinar.
     */
    public static function getCountByType(
        int $webinarId,
        ?int $sessionId = null,
        int $recentSeconds = 60
    ): array {
        $query = static::where('webinar_id', $webinarId)
            ->recent($recentSeconds)
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type');

        if ($sessionId) {
            $query->where('webinar_session_id', $sessionId);
        }

        $results = $query->pluck('count', 'type')->toArray();

        // Ensure all types are present
        $counts = [];
        foreach (array_keys(self::getTypes()) as $type) {
            $counts[$type] = $results[$type] ?? 0;
        }

        return $counts;
    }

    /**
     * Get total reaction count for display.
     */
    public static function getTotalCount(
        int $webinarId,
        ?int $sessionId = null,
        int $recentSeconds = 60
    ): int {
        $query = static::where('webinar_id', $webinarId)
            ->recent($recentSeconds);

        if ($sessionId) {
            $query->where('webinar_session_id', $sessionId);
        }

        return $query->count();
    }

    /**
     * Generate simulated reaction for auto-webinar.
     */
    public static function generateSimulated(
        Webinar $webinar,
        ?WebinarSession $session = null,
        ?string $type = null
    ): self {
        // Random type if not specified, weighted towards heart and thumbs_up
        if (!$type) {
            $types = [
                self::TYPE_HEART => 35,
                self::TYPE_THUMBS_UP => 25,
                self::TYPE_FIRE => 15,
                self::TYPE_CLAP => 10,
                self::TYPE_WOW => 8,
                self::TYPE_LAUGH => 5,
                self::TYPE_THINK => 2,
            ];

            $rand = rand(1, 100);
            $cumulative = 0;
            foreach ($types as $t => $weight) {
                $cumulative += $weight;
                if ($rand <= $cumulative) {
                    $type = $t;
                    break;
                }
            }
        }

        return static::create([
            'webinar_id' => $webinar->id,
            'webinar_session_id' => $session?->id,
            'type' => $type,
            'is_simulated' => true,
            'position_x' => rand(10, 90), // Random horizontal position
        ]);
    }

    /**
     * Cleanup old reactions (for performance).
     */
    public static function cleanupOld(int $keepHours = 24): int
    {
        return static::where('created_at', '<', now()->subHours($keepHours))
            ->delete();
    }

    // =====================================
    // Methods
    // =====================================

    /**
     * Get data for broadcast.
     */
    public function toBroadcast(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'emoji' => self::getEmoji($this->type),
            'position_x' => $this->position_x ?? rand(10, 90),
            'is_simulated' => $this->is_simulated,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
