<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyAuth
{
    /**
     * Request attribute holding the identity of an in-process connector call:
     * an ApiKey (a stored one, or the unsaved one that stands for a connected
     * app and its granted permissions).
     */
    public const CONNECTOR_ATTRIBUTE = 'netsendo.connector_api_key';

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ?string $permission = null): Response
    {
        // A connector tool call: the connected app was authenticated on /mcp and its
        // API requests are dispatched inside the application (ApiDispatcher). The
        // attribute cannot be set from outside; it carries the permissions to check.
        $apiKey = $request->attributes->get(self::CONNECTOR_ATTRIBUTE);

        if (! $apiKey instanceof ApiKey) {
            $authHeader = $request->header('Authorization');

            if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
                return $this->unauthorized('Missing or invalid Authorization header. Use: Bearer <api_key>');
            }

            $plainKey = substr($authHeader, 7); // Remove 'Bearer ' prefix

            if (!str_starts_with($plainKey, 'ns_live_')) {
                return $this->unauthorized('Invalid API key format. Key should start with ns_live_');
            }

            $apiKey = ApiKey::findByKey($plainKey);

            if (!$apiKey) {
                return $this->unauthorized('Invalid API key');
            }
        }

        if ($apiKey->isExpired()) {
            return $this->unauthorized('API key has expired');
        }

        // Check specific permission if required
        if ($permission && !$apiKey->hasPermission($permission)) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => "This API key does not have the '{$permission}' permission",
            ], 403);
        }

        // Mark key as used (async to not slow down request)
        $apiKey->markAsUsed();

        // Bind API key and user to request for use in controllers
        $request->merge(['api_key' => $apiKey]);
        $request->setUserResolver(fn () => $apiKey->user);

        return $next($request);
    }

    /**
     * Return unauthorized response
     */
    protected function unauthorized(string $message): Response
    {
        return response()->json([
            'error' => 'Unauthorized',
            'message' => $message,
        ], 401);
    }
}
