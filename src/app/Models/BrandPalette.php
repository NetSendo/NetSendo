<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrandPalette extends Model
{
    use HasFactory;

    protected $fillable = [
        'brand_id',
        'name',
        'colors',
        'is_default',
    ];

    protected $casts = [
        'colors' => 'array',
        'is_default' => 'boolean',
    ];

    /**
     * Get the brand that owns this palette.
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Get the number of colors in the palette.
     */
    public function getColorCountAttribute(): int
    {
        return count($this->colors ?? []);
    }

    /**
     * Add a color to the palette.
     */
    public function addColor(string $hexColor): void
    {
        $colors = $this->colors ?? [];
        if (!in_array($hexColor, $colors)) {
            $colors[] = $hexColor;
            $this->colors = $colors;
            $this->save();
        }
    }

    /**
     * Remove a color from the palette.
     */
    public function removeColor(string $hexColor): void
    {
        $colors = $this->colors ?? [];
        $this->colors = array_values(array_filter($colors, fn($c) => $c !== $hexColor));
        $this->save();
    }

    /**
     * Set this palette as the default for the brand.
     */
    public function setAsDefault(): void
    {
        // Unset other default palettes for this brand
        static::where('brand_id', $this->brand_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->is_default = true;
        $this->save();
    }
}
