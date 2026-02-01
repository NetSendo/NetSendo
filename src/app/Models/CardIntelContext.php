<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;

class CardIntelContext extends Model
{
    protected $table = 'cardintel_context';

    protected $fillable = [
        'scan_id',
        'context_level',
        'quality_score',
        'signals_json',
        'reasoning_short',
    ];

    protected $casts = [
        'quality_score' => 'integer',
        'signals_json' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Context level constants.
     */
    public const LEVEL_LOW = 'LOW';
    public const LEVEL_MEDIUM = 'MEDIUM';
    public const LEVEL_HIGH = 'HIGH';

    /**
     * Signal keys used in scoring.
     */
    public const SIGNALS = [
        'has_email' => 'Ma adres email',
        'has_phone' => 'Ma numer telefonu',
        'has_website' => 'Ma stronę www',
        'has_nip_or_regon' => 'Ma NIP lub REGON',
        'corporate_email' => 'Email firmowy (nie gmail/wp)',
        'complete_name' => 'Pełne imię i nazwisko',
        'has_company' => 'Podana firma',
        'company_not_personal' => 'Firma ≠ osoba fizyczna',
        'has_position' => 'Podane stanowisko',
        'nip_verified' => 'NIP zweryfikowany w rejestrze',
        'website_accessible' => 'Strona www dostępna',
    ];

    /**
     * Get the scan this context belongs to.
     */
    public function scan(): BelongsTo
    {
        return $this->belongsTo(CardIntelScan::class, 'scan_id');
    }

    /**
     * Get signals as array (accessor).
     */
    protected function signals(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->signals_json ?? [],
        );
    }

    /**
     * Check if context level is LOW.
     */
    public function isLow(): bool
    {
        return $this->context_level === self::LEVEL_LOW;
    }

    /**
     * Check if context level is MEDIUM.
     */
    public function isMedium(): bool
    {
        return $this->context_level === self::LEVEL_MEDIUM;
    }

    /**
     * Check if context level is HIGH.
     */
    public function isHigh(): bool
    {
        return $this->context_level === self::LEVEL_HIGH;
    }

    /**
     * Get positive signals (true values).
     */
    public function getPositiveSignalsAttribute(): array
    {
        return array_filter($this->signals, fn($v) => $v === true);
    }

    /**
     * Get negative signals (false values).
     */
    public function getNegativeSignalsAttribute(): array
    {
        return array_filter($this->signals, fn($v) => $v === false);
    }

    /**
     * Get signals with their human-readable labels.
     */
    public function getSignalsWithLabelsAttribute(): array
    {
        $result = [];

        foreach ($this->signals as $key => $value) {
            $result[] = [
                'key' => $key,
                'value' => $value,
                'label' => self::SIGNALS[$key] ?? $key,
            ];
        }

        return $result;
    }

    /**
     * Get reasoning as bullet points array.
     */
    public function getReasoningBulletsAttribute(): array
    {
        if (empty($this->reasoning_short)) {
            return [];
        }

        // Split by newlines or bullet markers
        $lines = preg_split('/[\n\r]+|•|▪|●/', $this->reasoning_short);

        return array_values(array_filter(array_map('trim', $lines)));
    }

    /**
     * Get color for context level (for UI).
     */
    public function getLevelColorAttribute(): string
    {
        return match($this->context_level) {
            self::LEVEL_HIGH => 'green',
            self::LEVEL_MEDIUM => 'yellow',
            self::LEVEL_LOW => 'gray',
            default => 'gray',
        };
    }

    /**
     * Get label for context level (Polish).
     */
    public function getLevelLabelAttribute(): string
    {
        return match($this->context_level) {
            self::LEVEL_HIGH => 'Wysoki',
            self::LEVEL_MEDIUM => 'Średni',
            self::LEVEL_LOW => 'Niski',
            default => 'Nieznany',
        };
    }

    /**
     * Check if this context qualifies for auto-send.
     */
    public function qualifiesForAutoSend(int $minScore = 80, bool $requireCorporateEmail = true): bool
    {
        if (!$this->isHigh()) {
            return false;
        }

        if ($this->quality_score < $minScore) {
            return false;
        }

        if ($requireCorporateEmail && !($this->signals['corporate_email'] ?? false)) {
            return false;
        }

        return true;
    }
}
