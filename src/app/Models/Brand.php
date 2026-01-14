<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'logo_media_id',
        'primary_color',
        'secondary_color',
    ];

    /**
     * Get the user that owns the brand.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the logo media for the brand.
     */
    public function logo(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'logo_media_id');
    }

    /**
     * Get all media associated with this brand.
     */
    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
    }

    /**
     * Get all color palettes for this brand.
     */
    public function palettes(): HasMany
    {
        return $this->hasMany(BrandPalette::class);
    }

    /**
     * Get the default palette for this brand.
     */
    public function defaultPalette()
    {
        return $this->palettes()->where('is_default', true)->first();
    }

    /**
     * Get all colors associated with this brand.
     * Includes: primary/secondary colors, palette colors, and colors from logo.
     */
    public function getAllColorsAttribute(): array
    {
        $colors = [];

        // Add primary and secondary colors
        if ($this->primary_color) {
            $colors[] = $this->primary_color;
        }
        if ($this->secondary_color) {
            $colors[] = $this->secondary_color;
        }

        // Add colors from palettes
        foreach ($this->palettes as $palette) {
            if ($palette->colors) {
                $colors = array_merge($colors, $palette->colors);
            }
        }

        // Add colors from logo
        if ($this->logo) {
            $logoColors = $this->logo->colors()->orderBy('position')->pluck('hex_color')->toArray();
            $colors = array_merge($colors, $logoColors);
        }

        return array_unique($colors);
    }

    /**
     * Get media count attribute.
     */
    public function getMediaCountAttribute(): int
    {
        return $this->media()->count();
    }
}
