<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;

class CardIntelEnrichment extends Model
{
    protected $table = 'cardintel_enrichment';

    protected $fillable = [
        'scan_id',
        'website_summary',
        'firmographics_json',
        'language',
        'b2b_b2c_guess',
        'use_case_hypothesis',
    ];

    protected $casts = [
        'firmographics_json' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Business type constants.
     */
    public const TYPE_B2B = 'B2B';
    public const TYPE_B2C = 'B2C';
    public const TYPE_MIXED = 'MIXED';
    public const TYPE_UNKNOWN = 'UNKNOWN';

    /**
     * Get the scan this enrichment belongs to.
     */
    public function scan(): BelongsTo
    {
        return $this->belongsTo(CardIntelScan::class, 'scan_id');
    }

    /**
     * Get firmographics as array (accessor).
     */
    protected function firmographics(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->firmographics_json ?? [],
        );
    }

    /**
     * Check if enrichment has website summary.
     */
    public function hasWebsiteSummary(): bool
    {
        return !empty($this->website_summary);
    }

    /**
     * Check if enrichment has firmographics data.
     */
    public function hasFirmographics(): bool
    {
        return !empty($this->firmographics_json);
    }

    /**
     * Check if enrichment has use case hypothesis.
     */
    public function hasUseCaseHypothesis(): bool
    {
        return !empty($this->use_case_hypothesis);
    }

    /**
     * Check if enrichment has any data.
     */
    public function hasAnyData(): bool
    {
        return $this->hasWebsiteSummary()
            || $this->hasFirmographics()
            || $this->hasUseCaseHypothesis();
    }

    /**
     * Get company name from firmographics.
     */
    public function getCompanyNameAttribute(): ?string
    {
        return $this->firmographics['name'] ?? null;
    }

    /**
     * Get company industry from firmographics.
     */
    public function getIndustryAttribute(): ?string
    {
        return $this->firmographics['industry'] ?? null;
    }

    /**
     * Get company address from firmographics.
     */
    public function getAddressAttribute(): ?string
    {
        return $this->firmographics['address'] ?? null;
    }

    /**
     * Get company size from firmographics.
     */
    public function getCompanySizeAttribute(): ?string
    {
        return $this->firmographics['size'] ?? null;
    }

    /**
     * Check if NIP was verified.
     */
    public function isNipVerified(): bool
    {
        return ($this->firmographics['nip_verified'] ?? false) === true;
    }

    /**
     * Get VAT status from firmographics.
     */
    public function getVatStatusAttribute(): ?string
    {
        return $this->firmographics['vat_status'] ?? null;
    }

    /**
     * Get business context summary for message generation.
     */
    public function getBusinessContextAttribute(): string
    {
        $parts = [];

        if ($this->website_summary) {
            $parts[] = "O firmie: " . $this->website_summary;
        }

        if ($industry = $this->industry) {
            $parts[] = "Branża: " . $industry;
        }

        if ($this->b2b_b2c_guess && $this->b2b_b2c_guess !== self::TYPE_UNKNOWN) {
            $parts[] = "Typ: " . $this->b2b_b2c_guess;
        }

        if ($this->use_case_hypothesis) {
            $parts[] = "Potencjalny use-case: " . $this->use_case_hypothesis;
        }

        return implode("\n", $parts);
    }

    /**
     * Get label for B2B/B2C type (Polish).
     */
    public function getBusinessTypeLabelAttribute(): string
    {
        return match($this->b2b_b2c_guess) {
            self::TYPE_B2B => 'Business-to-Business',
            self::TYPE_B2C => 'Business-to-Consumer',
            self::TYPE_MIXED => 'Mieszany (B2B + B2C)',
            default => 'Nieznany',
        };
    }

    /**
     * Check if this is a B2B company.
     */
    public function isB2B(): bool
    {
        return $this->b2b_b2c_guess === self::TYPE_B2B;
    }

    /**
     * Check if this is a B2C company.
     */
    public function isB2C(): bool
    {
        return $this->b2b_b2c_guess === self::TYPE_B2C;
    }
}
