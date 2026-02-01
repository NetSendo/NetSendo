<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;

class CardIntelExtraction extends Model
{
    protected $table = 'cardintel_extractions';

    protected $fillable = [
        'scan_id',
        'fields_json',
        'confidence_json',
        'normalized_json',
    ];

    protected $casts = [
        'fields_json' => 'array',
        'confidence_json' => 'array',
        'normalized_json' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Default fields structure.
     */
    public const DEFAULT_FIELDS = [
        'first_name' => null,
        'last_name' => null,
        'company' => null,
        'email' => null,
        'phone' => null,
        'website' => null,
        'nip' => null,
        'regon' => null,
        'position' => null,
    ];

    /**
     * Get the scan this extraction belongs to.
     */
    public function scan(): BelongsTo
    {
        return $this->belongsTo(CardIntelScan::class, 'scan_id');
    }

    /**
     * Get fields as a clean array (accessor).
     */
    protected function fields(): Attribute
    {
        return Attribute::make(
            get: fn () => array_merge(self::DEFAULT_FIELDS, $this->fields_json ?? []),
        );
    }

    /**
     * Get confidence scores as array (accessor).
     */
    protected function confidence(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->confidence_json ?? [],
        );
    }

    /**
     * Get normalized fields (accessor).
     */
    protected function normalized(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->normalized_json ?? $this->fields,
        );
    }

    /**
     * Get the full name combined.
     */
    public function getFullNameAttribute(): ?string
    {
        $firstName = $this->fields['first_name'] ?? '';
        $lastName = $this->fields['last_name'] ?? '';

        return trim("$firstName $lastName") ?: null;
    }

    /**
     * Get average confidence score.
     */
    public function getAverageConfidenceAttribute(): float
    {
        $confidence = $this->confidence;

        if (empty($confidence)) {
            return 0.0;
        }

        $values = array_filter($confidence, fn($v) => $v > 0);

        if (empty($values)) {
            return 0.0;
        }

        return round(array_sum($values) / count($values), 2);
    }

    /**
     * Get confidence for a specific field.
     */
    public function getFieldConfidence(string $field): float
    {
        return $this->confidence[$field] ?? 0.0;
    }

    /**
     * Check if a field has high confidence (>= 0.8).
     */
    public function hasHighConfidence(string $field): bool
    {
        return $this->getFieldConfidence($field) >= 0.8;
    }

    /**
     * Get fields with their confidence as formatted array.
     */
    public function getFieldsWithConfidenceAttribute(): array
    {
        $result = [];

        foreach ($this->fields as $key => $value) {
            $result[$key] = [
                'value' => $value,
                'confidence' => $this->getFieldConfidence($key),
                'confidence_percent' => round($this->getFieldConfidence($key) * 100),
            ];
        }

        return $result;
    }

    /**
     * Check if we have minimum required fields (email or phone).
     */
    public function hasMinimumFields(): bool
    {
        $email = $this->fields['email'] ?? null;
        $phone = $this->fields['phone'] ?? null;

        return !empty($email) || !empty($phone);
    }

    /**
     * Check if has business identifiers (NIP/REGON).
     */
    public function hasBusinessIdentifiers(): bool
    {
        return !empty($this->fields['nip']) || !empty($this->fields['regon']);
    }

    /**
     * Update a single field value.
     */
    public function updateField(string $field, ?string $value): self
    {
        $fields = $this->fields_json ?? [];
        $fields[$field] = $value;

        $this->update(['fields_json' => $fields]);

        return $this;
    }

    /**
     * Update multiple field values.
     */
    public function updateFields(array $updates): self
    {
        $fields = $this->fields_json ?? [];

        foreach ($updates as $field => $value) {
            if (array_key_exists($field, self::DEFAULT_FIELDS)) {
                $fields[$field] = $value;
            }
        }

        $this->update(['fields_json' => $fields]);

        return $this;
    }
}
