<?php

namespace App\Http\Middleware;

use App\Models\ApiRequestLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogApiRequest
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        $response = $next($request);

        // Log the request asynchronously to not slow down the response
        $this->logRequest($request, $response, $startTime);

        return $response;
    }

    /**
     * Log the API request and response.
     */
    protected function logRequest(Request $request, Response $response, float $startTime): void
    {
        try {
            $user = $request->user();
            $apiKey = $request->get('api_key');

            if (!$user) {
                return; // Don't log unauthenticated requests
            }

            // Calculate duration in milliseconds
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            // Get request body, sanitize sensitive data
            $requestBody = $this->sanitizeRequestBody($request->all());

            // Get response body (truncated for large responses)
            $responseBody = $this->getResponseBody($response);

            ApiRequestLog::create([
                'user_id' => $user->id,
                'api_key_id' => $apiKey?->id,
                'method' => $request->method(),
                'endpoint' => $request->path(),
                'request_body' => $requestBody,
                'response_status' => $response->getStatusCode(),
                'response_body' => $responseBody,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'duration_ms' => $durationMs,
            ]);
        } catch (\Exception $e) {
            // Silently fail - logging should never break the API
            \Log::error('Failed to log API request: ' . $e->getMessage());
        }
    }

    /**
     * Sanitize request body to remove sensitive data.
     */
    protected function sanitizeRequestBody(array $body): array
    {
        $sensitiveKeys = ['password', 'api_key', 'token', 'secret', 'authorization'];

        foreach ($body as $key => $value) {
            if (is_string($key)) {
                $lowerKey = strtolower($key);
                foreach ($sensitiveKeys as $sensitive) {
                    if (str_contains($lowerKey, $sensitive)) {
                        $body[$key] = '[REDACTED]';
                        break;
                    }
                }
            }

            if (is_array($value)) {
                $body[$key] = $this->sanitizeRequestBody($value);
            }
        }

        return $body;
    }

    /**
     * Get response body, truncated if too large.
     */
    protected function getResponseBody(Response $response): ?array
    {
        try {
            $content = $response->getContent();

            if (strlen($content) > 10000) {
                return ['_truncated' => true, '_message' => 'Response too large to store'];
            }

            $decoded = json_decode($content, true);

            return is_array($decoded) ? $decoded : null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
