<?php

namespace App\Services;

use App\Models\Media;
use App\Models\MediaColor;
use Illuminate\Support\Facades\Storage;

class ColorExtractionService
{
    /**
     * Number of colors to extract by default.
     */
    protected int $colorCount = 8;

    /**
     * Quality setting for color extraction (1 = highest quality, 10 = fastest).
     */
    protected int $quality = 5;

    /**
     * Extract dominant colors from an image.
     *
     * @param string $imagePath Path to the image file
     * @param int $count Number of colors to extract
     * @return array Array of color data
     */
    public function extractColors(string $imagePath, ?int $count = null): array
    {
        // Check if GD extension is available
        if (!extension_loaded('gd')) {
            \Log::warning('ColorExtractionService: GD extension not available, skipping color extraction');
            return [];
        }

        $count = $count ?? $this->colorCount;

        // Check if file exists
        if (!file_exists($imagePath)) {
            return [];
        }

        // Get image info
        $imageInfo = @getimagesize($imagePath);
        if (!$imageInfo) {
            return [];
        }

        // Load image based on type
        $image = $this->loadImage($imagePath, $imageInfo[2]);
        if (!$image) {
            return [];
        }

        // Extract colors using quantization
        $colors = $this->quantizeColors($image, $count);

        if (function_exists('imagedestroy')) {
            \imagedestroy($image);
        }

        return $colors;
    }

    /**
     * Load an image resource from file.
     */
    protected function loadImage(string $path, int $type)
    {
        switch ($type) {
            case IMAGETYPE_JPEG:
                if (!function_exists('imagecreatefromjpeg')) {
                    return false;
                }
                return @\imagecreatefromjpeg($path);
            case IMAGETYPE_PNG:
                if (!function_exists('imagecreatefrompng')) {
                    return false;
                }
                return @\imagecreatefrompng($path);
            case IMAGETYPE_GIF:
                if (!function_exists('imagecreatefromgif')) {
                    return false;
                }
                return @\imagecreatefromgif($path);
            case IMAGETYPE_WEBP:
                if (!function_exists('imagecreatefromwebp')) {
                    return false;
                }
                return @\imagecreatefromwebp($path);
            default:
                return false;
        }
    }

    /**
     * Quantize colors from an image using median cut algorithm.
     */
    protected function quantizeColors($image, int $count): array
    {
        // Check if required GD functions are available
        if (!function_exists('imagesx') || !function_exists('imagesy') || !function_exists('imagecolorat')) {
            return [];
        }

        $width = \imagesx($image);
        $height = \imagesy($image);

        // Sample pixels (skip some for performance)
        $step = max(1, (int) sqrt($width * $height / 10000));
        $pixels = [];

        for ($y = 0; $y < $height; $y += $step) {
            for ($x = 0; $x < $width; $x += $step) {
                $rgb = \imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                // Skip very light or very dark colors (often backgrounds)
                $brightness = ($r + $g + $b) / 3;
                if ($brightness > 10 && $brightness < 245) {
                    $pixels[] = [$r, $g, $b];
                }
            }
        }

        if (empty($pixels)) {
            return [];
        }

        // Simple k-means clustering
        $colors = $this->kMeansClustering($pixels, $count);

        // Sort by population (most common first)
        usort($colors, fn($a, $b) => $b['population'] - $a['population']);

        // Add position and dominant flag
        foreach ($colors as $index => &$color) {
            $color['position'] = $index + 1;
            $color['is_dominant'] = $index === 0;
        }

        return $colors;
    }

    /**
     * Simple k-means clustering for color quantization.
     */
    protected function kMeansClustering(array $pixels, int $k): array
    {
        // Initialize centroids randomly
        $centroids = array_slice($pixels, 0, min($k, count($pixels)));

        // Ensure we have enough centroids
        while (count($centroids) < $k && count($pixels) > 0) {
            $centroids[] = $pixels[array_rand($pixels)];
        }

        $maxIterations = 10;

        for ($iteration = 0; $iteration < $maxIterations; $iteration++) {
            // Assign pixels to nearest centroid
            $clusters = array_fill(0, count($centroids), []);

            foreach ($pixels as $pixel) {
                $minDist = PHP_INT_MAX;
                $closest = 0;

                foreach ($centroids as $i => $centroid) {
                    $dist = $this->colorDistance($pixel, $centroid);
                    if ($dist < $minDist) {
                        $minDist = $dist;
                        $closest = $i;
                    }
                }

                $clusters[$closest][] = $pixel;
            }

            // Update centroids
            $newCentroids = [];
            foreach ($clusters as $cluster) {
                if (empty($cluster)) {
                    continue;
                }

                $r = $g = $b = 0;
                foreach ($cluster as $pixel) {
                    $r += $pixel[0];
                    $g += $pixel[1];
                    $b += $pixel[2];
                }
                $count = count($cluster);
                $newCentroids[] = [
                    (int) ($r / $count),
                    (int) ($g / $count),
                    (int) ($b / $count),
                ];
            }

            $centroids = $newCentroids;
        }

        // Build result with population counts
        $result = [];
        foreach ($clusters as $i => $cluster) {
            if (empty($cluster) || !isset($centroids[$i])) {
                continue;
            }

            $r = $centroids[$i][0];
            $g = $centroids[$i][1];
            $b = $centroids[$i][2];

            $result[] = [
                'hex_color' => $this->rgbToHex($r, $g, $b),
                'rgb_r' => $r,
                'rgb_g' => $g,
                'rgb_b' => $b,
                'population' => count($cluster),
            ];
        }

        return $result;
    }

    /**
     * Calculate color distance using Euclidean distance in RGB space.
     */
    protected function colorDistance(array $c1, array $c2): float
    {
        return sqrt(
            pow($c1[0] - $c2[0], 2) +
            pow($c1[1] - $c2[1], 2) +
            pow($c1[2] - $c2[2], 2)
        );
    }

    /**
     * Convert RGB to HEX color.
     */
    public function rgbToHex(int $r, int $g, int $b): string
    {
        return sprintf('#%02X%02X%02X', $r, $g, $b);
    }

    /**
     * Convert HEX to RGB.
     */
    public function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * Extract and save colors for a media item.
     */
    public function extractAndSaveColors(Media $media): void
    {
        // Only process images
        if (!$media->isImage()) {
            return;
        }

        try {
            // Get full path to the file
            $path = Storage::disk('public')->path($media->stored_path);

            // Skip if file doesn't exist
            if (!file_exists($path)) {
                \Log::warning('ColorExtractionService: File not found', ['path' => $path]);
                return;
            }

            // Extract colors
            $colors = $this->extractColors($path);

            if (empty($colors)) {
                return;
            }

            // Delete existing colors
            $media->colors()->delete();

            // Save new colors
            foreach ($colors as $colorData) {
                $media->colors()->create([
                    'hex_color' => $colorData['hex_color'],
                    'rgb_r' => $colorData['rgb_r'],
                    'rgb_g' => $colorData['rgb_g'],
                    'rgb_b' => $colorData['rgb_b'],
                    'population' => $colorData['population'],
                    'is_dominant' => $colorData['is_dominant'] ?? false,
                    'position' => $colorData['position'] ?? 0,
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('ColorExtractionService: Failed to extract colors', [
                'media_id' => $media->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Generate a complementary color palette.
     */
    public function generateComplementaryPalette(string $hexColor): array
    {
        $rgb = $this->hexToRgb($hexColor);

        // Simple complementary color (opposite on color wheel)
        $complementary = [
            'r' => 255 - $rgb['r'],
            'g' => 255 - $rgb['g'],
            'b' => 255 - $rgb['b'],
        ];

        // Lighter and darker variants
        $lighter = [
            'r' => min(255, $rgb['r'] + 40),
            'g' => min(255, $rgb['g'] + 40),
            'b' => min(255, $rgb['b'] + 40),
        ];

        $darker = [
            'r' => max(0, $rgb['r'] - 40),
            'g' => max(0, $rgb['g'] - 40),
            'b' => max(0, $rgb['b'] - 40),
        ];

        return [
            'original' => $hexColor,
            'complementary' => $this->rgbToHex($complementary['r'], $complementary['g'], $complementary['b']),
            'lighter' => $this->rgbToHex($lighter['r'], $lighter['g'], $lighter['b']),
            'darker' => $this->rgbToHex($darker['r'], $darker['g'], $darker['b']),
        ];
    }
}
