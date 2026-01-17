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
        $scheme = $parsedUrl['scheme'] ?? 'https';

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

        // Build referer from the original URL's host
        $referer = $scheme . '://' . $host . '/';

        // Retry logic for transient failures
        $maxRetries = 2;
        $lastException = null;
        $lastStatus = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                // Fetch the image with browser-like headers
                $response = Http::timeout(15)
                    ->connectTimeout(10)
                    ->withOptions([
                        'verify' => true,
                        'allow_redirects' => [
                            'max' => 5,
                            'strict' => false,
                            'referer' => true,
                        ],
                    ])
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                        'Accept' => 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
                        'Accept-Language' => 'en-US,en;q=0.9',
                        'Accept-Encoding' => 'gzip, deflate, br',
                        'Referer' => $referer,
                        'Sec-Fetch-Dest' => 'image',
                        'Sec-Fetch-Mode' => 'no-cors',
                        'Sec-Fetch-Site' => 'cross-site',
                    ])
                    ->get($url);

                if ($response->successful()) {
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
                }

                $lastStatus = $response->status();

                // Log each failed attempt
                Log::warning('Image proxy fetch attempt failed', [
                    'url' => $url,
                    'status' => $lastStatus,
                    'attempt' => $attempt,
                    'max_retries' => $maxRetries,
                ]);

                // Don't retry on 4xx errors (client errors)
                if ($lastStatus >= 400 && $lastStatus < 500) {
                    break;
                }

                // Wait before retry (exponential backoff)
                if ($attempt < $maxRetries) {
                    usleep($attempt * 500000); // 0.5s, 1s
                }

            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                $lastException = $e;
                Log::warning('Image proxy connection error', [
                    'url' => $url,
                    'error' => $e->getMessage(),
                    'attempt' => $attempt,
                    'max_retries' => $maxRetries,
                ]);

                if ($attempt < $maxRetries) {
                    usleep($attempt * 500000);
                }
            } catch (\Exception $e) {
                $lastException = $e;
                Log::error('Image proxy unexpected error', [
                    'url' => $url,
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'attempt' => $attempt,
                ]);
                break; // Don't retry on unexpected errors
            }
        }

        // All retries exhausted
        Log::error('Image proxy failed after all retries', [
            'url' => $url,
            'last_status' => $lastStatus,
            'last_error' => $lastException ? $lastException->getMessage() : null,
        ]);

        return response()->json([
            'error' => 'Failed to fetch image',
            'details' => $lastException ? $lastException->getMessage() : "HTTP {$lastStatus}",
        ], 502);
    }
}
