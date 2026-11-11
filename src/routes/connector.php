<?php

use App\Http\Controllers\Connector\AuthorizationController;
use App\Http\Controllers\Connector\MetadataController;
use App\Http\Controllers\Connector\RegistrationController;
use App\Http\Controllers\Connector\TokenController;
use App\Http\Middleware\AuthenticateConnector;
use App\Http\Middleware\ConnectorEnabled;
use App\Mcp\NetSendoServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Server\Middleware\ReorderJsonAccept;
use Laravel\Mcp\Server\Middleware\ValidateMcpHeaders;
use Laravel\Mcp\Server\Middleware\ValidateOrigin;
use Laravel\Mcp\Server\Transport\HttpTransport;

/*
|--------------------------------------------------------------------------
| NetSendo connector: remote MCP endpoint with OAuth
|--------------------------------------------------------------------------
|
| AI hosts (Claude, Hermes, ChatGPT, Cursor, …) are given the address
| https://<instance>/mcp. They discover the OAuth server from the
| /.well-known documents, send the user to /oauth/authorize to sign in and
| approve, and then call the MCP endpoint with the token they received.
|
| These routes are outside the "web" group (no session, no CSRF), except for
| the consent screen. Turned off with MCP_CONNECTOR_ENABLED=false.
| Self-hosting notes: docs/MCP_CONNECTOR.md.
|
*/

Route::middleware(ConnectorEnabled::class)->group(function () {

    // --- Discovery (RFC 9728, RFC 8414) --------------------------------------
    // Hosts look for the path-suffixed form first, then the root form.
    Route::get('/.well-known/oauth-protected-resource/mcp', [MetadataController::class, 'protectedResource'])
        ->name('connector.metadata.resource');
    Route::get('/.well-known/oauth-protected-resource', [MetadataController::class, 'protectedResource']);

    Route::get('/.well-known/oauth-authorization-server', [MetadataController::class, 'authorizationServer'])
        ->name('connector.metadata.server');
    Route::get('/.well-known/oauth-authorization-server/mcp', [MetadataController::class, 'authorizationServer']);
    // Hosts that only know OpenID Connect discovery read the same document here.
    Route::get('/.well-known/openid-configuration', [MetadataController::class, 'authorizationServer']);

    // --- OAuth endpoints called by the host ----------------------------------
    Route::post('/oauth/register', RegistrationController::class)
        ->middleware('throttle:30,1')
        ->name('connector.oauth.register');
    Route::post('/oauth/token', [TokenController::class, 'token'])
        ->middleware('throttle:120,1')
        ->name('connector.oauth.token');
    Route::post('/oauth/revoke', [TokenController::class, 'revoke'])
        ->middleware('throttle:120,1')
        ->name('connector.oauth.revoke');

    // --- Consent screen (the signed-in panel user) ---------------------------
    Route::middleware(['web', 'auth', '2fa'])->group(function () {
        Route::get('/oauth/authorize', [AuthorizationController::class, 'show'])->name('connector.oauth.authorize');
        Route::post('/oauth/authorize', [AuthorizationController::class, 'decide'])->name('connector.oauth.decide');
    });

    // --- The MCP endpoint (Streamable HTTP) -----------------------------------
    Route::post('/mcp', function () {
        $transport = new HttpTransport(request());
        app()->make(NetSendoServer::class, ['transport' => $transport])->start();

        return $transport->run();
    })->middleware([
        ValidateOrigin::class,
        AuthenticateConnector::class,
        'throttle:connector',
        ReorderJsonAccept::class,
        ValidateMcpHeaders::class,
    ])->name('connector.mcp');

    // The endpoint has no server-to-client stream and no sessions. A person who
    // opens the address in a browser is taken to the instructions.
    Route::get('/mcp', function () {
        if (str_contains((string) request()->header('Accept'), 'text/html')) {
            return redirect('/settings/connector');
        }

        return response('', 405)->header('Allow', 'POST');
    })->middleware(ValidateOrigin::class);

    Route::delete('/mcp', fn () => response('', 405)->header('Allow', 'POST'))
        ->middleware(ValidateOrigin::class);
});
