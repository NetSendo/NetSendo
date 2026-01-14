<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaColor extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'media_id',
        'hex_color',
        'rgb_r',
        'rgb_g',
        'rgb_b',
        'population',
        'is_dominant',
        'position',
    ];

    protected $casts = [
        'rgb_r' => 'integer',
        'rgb_g' => 'integer',
        'rgb_b' => 'integer',
        'population' => 'integer',
        'is_dominant' => 'boolean',
        'position' => 'integer',
    ];

    /**
     * Get the media that owns this color.
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    /**
     * Get the color as HEX string.
     */
    public function toHex(): string
    {
        return $this->hex_color;
    }

    /**
     * Get the color as RGB array.
     */
    public function toRgb(): array
    {
        return [
            'r' => $this->rgb_r,
            'g' => $this->rgb_g,
            'b' => $this->rgb_b,
        ];
    }

    /**
     * Get the color as RGB string.
     */
    public function toRgbString(): string
    {
        return "rgb({$this->rgb_r}, {$this->rgb_g}, {$this->rgb_b})";
    }

    /**
     * Get the color as HSL array.
     */
    public function toHsl(): array
    {
        $r = $this->rgb_r / 255;
        $g = $this->rgb_g / 255;
        $b = $this->rgb_b / 255;

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;

        if ($max === $min) {
            $h = $s = 0;
        } else {
            $d = $max - $min;
            $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);

            switch ($max) {
                case $r:
                    $h = (($g - $b) / $d + ($g < $b ? 6 : 0)) / 6;
                    break;
                case $g:
                    $h = (($b - $r) / $d + 2) / 6;
                    break;
                case $b:
                    $h = (($r - $g) / $d + 4) / 6;
                    break;
            }
        }

        return [
            'h' => round($h * 360),
            's' => round($s * 100),
            'l' => round($l * 100),
        ];
    }

    /**
     * Calculate luminance for contrast ratio.
     */
    public function getLuminanceAttribute(): float
    {
        $rgb = [$this->rgb_r, $this->rgb_g, $this->rgb_b];

        $rgb = array_map(function ($val) {
            $val = $val / 255;
            return $val <= 0.03928 ? $val / 12.92 : pow(($val + 0.055) / 1.055, 2.4);
        }, $rgb);

        return 0.2126 * $rgb[0] + 0.7152 * $rgb[1] + 0.0722 * $rgb[2];
    }

    /**
     * Check if the color is dark (for choosing white/black text).
     */
    public function isDark(): bool
    {
        return $this->luminance < 0.5;
    }

    /**
     * Get contrasting text color (black or white).
     */
    public function getContrastingTextColorAttribute(): string
    {
        return $this->isDark() ? '#FFFFFF' : '#000000';
    }
}
