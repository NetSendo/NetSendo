<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ImageProxyController extends Controller
{
    /**
     * Allowed image MIME types
     */
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml',
    ];

    /**
     * Maximum image size in bytes (5MB)
     */
    private const MAX_SIZE = 5 * 1024 * 1024;

    /**
     * Cache TTL in seconds (1 hour)
     */
    private const CACHE_TTL = 3600;

    /**
     * Proxy an external image to bypass CORS restrictions.
     * Used primarily for thumbnail generation in the template builder.
     */
    public function proxy(Request $request)
    {
        $url = $request->query('url');

        if (empty($url)) {
            return response()->json(['error' => 'URL parameter is required'], 400);
        }

        // Validate URL format
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return response()->json(['error' => 'Invalid URL format'], 400);
        }

        // Parse URL and validate it's not a local/internal URL
        $parsedUrl = parse_url($url);
        $host = $parsedUrl['host'] ?? '';

        // Block local/internal URLs for security
        $blockedHosts = ['localhost', '127.0.0.1', '0.0.0.0', '::1'];
        if (in_array($host, $blockedHosts) || preg_match('/^(192\.168\.|10\.|172\.(1[6-9]|2[0-9]|3[0-1])\.)/', $host)) {
            return response()->json(['error' => 'Local URLs are not allowed'], 403);
        }

        // Check cache first
        $cacheKey = 'image_proxy:' . md5($url);
        $cachedResponse = Cache::get($cacheKey);

        if ($cachedResponse) {
            return response($cachedResponse['content'])
                ->header('Content-Type', $cachedResponse['mime_type'])
                ->header('Cache-Control', 'public, max-age=86400')
                ->header('Access-Control-Allow-Origin', '*');
        }

        try {
            // Fetch the image
            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; NetSendo/1.0)',
                    'Accept' => 'image/*',
                ])
                ->get($url);

            if (!$response->successful()) {
                Log::warning('Image proxy failed to fetch URL', [
                    'url' => $url,
                    'status' => $response->status(),
                ]);
                return response()->json(['error' => 'Failed to fetch image'], 502);
            }

            $content = $response->body();
            $contentType = $response->header('Content-Type');

            // Extract MIME type (remove charset if present)
            $mimeType = explode(';', $contentType)[0] ?? 'application/octet-stream';
            $mimeType = trim($mimeType);

            // Validate MIME type
            if (!in_array($mimeType, self::ALLOWED_MIME_TYPES)) {
                // Try to detect from content if header is unreliable
                $finfo = new \finfo(FILEINFO_MIME_TYPE);
                $detectedMime = $finfo->buffer($content);

                if (!in_array($detectedMime, self::ALLOWED_MIME_TYPES)) {
                    Log::warning('Image proxy rejected non-image content', [
                        'url' => $url,
                        'mime_type' => $mimeType,
                        'detected_mime' => $detectedMime,
                    ]);
                    return response()->json(['error' => 'URL does not contain a valid image'], 415);
                }

                $mimeType = $detectedMime;
            }

            // Check file size
            if (strlen($content) > self::MAX_SIZE) {
                return response()->json(['error' => 'Image too large (max 5MB)'], 413);
            }

            // Cache the response
            Cache::put($cacheKey, [
                'content' => $content,
                'mime_type' => $mimeType,
            ], self::CACHE_TTL);

            return response($content)
                ->header('Content-Type', $mimeType)
                ->header('Cache-Control', 'public, max-age=86400')
                ->header('Access-Control-Allow-Origin', '*');

        } catch (\Exception $e) {
            Log::error('Image proxy error', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Failed to fetch image'], 502);
        }
    }
}
