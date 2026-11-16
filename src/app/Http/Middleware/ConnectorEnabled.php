<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** MCP_CONNECTOR_ENABLED=false: the connector's endpoints do not exist. */
class ConnectorEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless((bool) config('connector.enabled'), 404);

        return $next($request);
    }
}
