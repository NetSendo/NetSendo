<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service for processing images in email content.
 * Converts external images marked with class="img_to_b64" to inline base64 data URIs.
 */
class EmailImageService
{
    /**
     * Maximum image size in bytes to convert to base64 (default: 500KB)
     * Larger images will be left as external URLs
     */
    protected int $maxImageSize;

    /**
     * Timeout for fetching remote images in seconds
     */
    protected int $timeout;

    /**
     * Allowed image MIME types
     */
    protected array $allowedMimeTypes = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml',
    ];

    public function __construct()
    {
        $this->maxImageSize = config('netsendo.email.max_inline_image_size', 512000); // 500KB default
        $this->timeout = config('netsendo.email.image_fetch_timeout', 10); // 10 seconds default
    }

    /**
     * Process HTML content and convert images with class="img_to_b64" to inline base64.
     *
     * @param string $htmlContent The HTML email content
     * @return string Processed HTML with inline images
     */
    public function processInlineImages(string $htmlContent): string
    {
        // Find all img tags with class containing "img_to_b64"
        $pattern = '/<img\s+[^>]*class\s*=\s*["\'][^"\']*img_to_b64[^"\']*["\'][^>]*>/i';

        return preg_replace_callback($pattern, function ($matches) {
            $imgTag = $matches[0];

            // Extract src attribute
            if (!preg_match('/src\s*=\s*["\']([^"\']+)["\']/i', $imgTag, $srcMatches)) {
                return $imgTag; // No src, return unchanged
            }

            $imageUrl = $srcMatches[1];

            // Skip if already a data URI
            if (str_starts_with($imageUrl, 'data:')) {
                return $imgTag;
            }

            // Try to fetch and convert to base64
            $base64Data = $this->fetchAndConvertToBase64($imageUrl);

            if ($base64Data) {
                // Replace src with base64 data URI
                $newImgTag = preg_replace(
                    '/src\s*=\s*["\'][^"\']+["\']/i',
                    'src="' . $base64Data . '"',
                    $imgTag
                );

                // Remove the img_to_b64 class as it's been processed
                $newImgTag = preg_replace(
                    '/class\s*=\s*["\']([^"\']*)\bimg_to_b64\b([^"\']*)["\']/',
                    'class="$1$2"',
                    $newImgTag
                );

                // Clean up empty class attributes
                $newImgTag = preg_replace('/class\s*=\s*["\']\s*["\']/', '', $newImgTag);

                return $newImgTag;
            }

            // Failed to convert, return original tag
            return $imgTag;
        }, $htmlContent);
    }

    /**
     * Fetch an image from URL and convert it to base64 data URI.
     *
     * @param string $url The image URL
     * @return string|null Base64 data URI or null on failure
     */
    protected function fetchAndConvertToBase64(string $url): ?string
    {
        try {
            // First, check the image size with a HEAD request
            $headResponse = Http::timeout($this->timeout)->head($url);

            if (!$headResponse->successful()) {
                Log::warning("EmailImageService: Failed to reach image URL", ['url' => $url]);
                return null;
            }

            $contentLength = $headResponse->header('Content-Length');
            $contentType = $headResponse->header('Content-Type');

            // Check if size is within limit
            if ($contentLength && (int)$contentLength > $this->maxImageSize) {
                Log::info("EmailImageService: Image too large for inline embedding", [
                    'url' => $url,
                    'size' => $contentLength,
                    'max_size' => $this->maxImageSize,
                ]);
                return null;
            }

            // Validate MIME type
            $mimeType = explode(';', $contentType)[0] ?? '';
            if (!in_array($mimeType, $this->allowedMimeTypes)) {
                Log::warning("EmailImageService: Invalid MIME type for image", [
                    'url' => $url,
                    'mime_type' => $mimeType,
                ]);
                return null;
            }

            // Fetch the actual image content
            $response = Http::timeout($this->timeout)->get($url);

            if (!$response->successful()) {
                Log::warning("EmailImageService: Failed to fetch image", ['url' => $url]);
                return null;
            }

            $imageData = $response->body();

            // Double-check size after fetch (in case Content-Length was missing)
            if (strlen($imageData) > $this->maxImageSize) {
                Log::info("EmailImageService: Image too large after fetch", [
                    'url' => $url,
                    'size' => strlen($imageData),
                ]);
                return null;
            }

            // Convert to base64 data URI
            $base64 = base64_encode($imageData);

            Log::debug("EmailImageService: Successfully converted image to base64", [
                'url' => $url,
                'size' => strlen($imageData),
            ]);

            return "data:{$mimeType};base64,{$base64}";

        } catch (\Exception $e) {
            Log::error("EmailImageService: Error processing image", [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Check if content contains any images that need to be processed.
     *
     * @param string $htmlContent
     * @return bool
     */
    public function hasImagesToProcess(string $htmlContent): bool
    {
        return (bool) preg_match('/<img\s+[^>]*class\s*=\s*["\'][^"\']*img_to_b64[^"\']*["\'][^>]*>/i', $htmlContent);
    }
}
