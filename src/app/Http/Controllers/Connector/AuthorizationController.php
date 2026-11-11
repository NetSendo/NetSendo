<?php

namespace App\Http\Controllers\Connector;

use App\Http\Controllers\Controller;
use App\Models\ConnectorClient;
use App\Services\Connector\ClientRegistry;
use App\Services\Connector\ConnectorUrls;
use App\Services\Connector\OAuthException;
use App\Services\Connector\RedirectUri;
use App\Services\Connector\Scopes;
use App\Services\Connector\TokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * The OAuth authorization endpoint: the consent screen an AI host sends the
 * user to. It shows which app asks, which NetSendo account is signed in and
 * which permissions are requested; the user can grant fewer.
 */
class AuthorizationController extends Controller
{
    private const SESSION_KEY = 'connector.authorize';

    public function __construct(private ClientRegistry $clients, private TokenService $tokens)
    {
    }

    public function show(Request $request): Response
    {
        // Until the client and its redirect URI are known to be genuine, an
        // error is shown here and never sent to the redirect URI.
        try {
            $client = $this->clients->find((string) $request->query('client_id', ''));
            $redirectUri = (string) $request->query('redirect_uri', '');

            if ($redirectUri === '' || ! RedirectUri::matches($redirectUri, $client->redirect_uris ?? [])) {
                throw new OAuthException('invalid_request', 'The redirect_uri is not registered for this application.');
            }
        } catch (OAuthException $e) {
            return $this->framed(
                Inertia::render('Connector/Authorize', ['error' => $e->error, 'errorDescription' => $e->getMessage()])
                    ->toResponse($request)
                    ->setStatusCode(400)
            );
        }

        $state = $request->query('state');
        $state = is_string($state) ? $state : null;

        // From here on, errors go back to the application.
        try {
            if ($request->query('response_type') !== 'code') {
                throw new OAuthException('unsupported_response_type', 'Only response_type=code is supported.');
            }

            $challenge = $request->query('code_challenge');

            if (! is_string($challenge) || ! preg_match('/^[A-Za-z0-9\-_]{43,128}$/', $challenge)
                || $request->query('code_challenge_method') !== 'S256') {
                throw new OAuthException('invalid_request', 'PKCE with code_challenge_method=S256 is required.');
            }

            $resource = $request->query('resource');
            $resource = is_string($resource) && $resource !== '' ? $resource : null;

            if (! ConnectorUrls::isOurResource($resource)) {
                throw new OAuthException('invalid_target', 'This authorization server only issues tokens for '.ConnectorUrls::resource().'.');
            }

            $scope = $request->query('scope');
            $scopes = Scopes::parse(is_string($scope) ? $scope : null);

            if ($scopes === []) {
                throw new OAuthException('invalid_scope', 'None of the requested scopes exist.');
            }
        } catch (OAuthException $e) {
            return redirect()->away($this->redirectUrl($redirectUri, [
                'error' => $e->error,
                'error_description' => $e->getMessage(),
                'state' => $state,
            ]));
        }

        // The checked request waits in the session; the form only refers to it,
        // so nothing of it can be changed between showing and approving.
        $requestId = (string) Str::uuid();
        // Requests that were shown and never answered do not pile up in the session.
        $waiting = array_filter(
            (array) $request->session()->get(self::SESSION_KEY, []),
            fn ($entry) => is_array($entry) && ($entry['expires_at'] ?? 0) >= now()->getTimestamp(),
        );
        $request->session()->put(self::SESSION_KEY, array_slice($waiting, -9, null, true));
        $request->session()->put(self::SESSION_KEY.'.'.$requestId, [
            'client_id' => $client->id,
            'redirect_uri' => $redirectUri,
            'scopes' => $scopes,
            'state' => $state,
            'code_challenge' => $challenge,
            'resource' => $resource,
            'expires_at' => now()->addMinutes(15)->getTimestamp(),
        ]);

        $user = $request->user();

        return $this->framed(
            Inertia::render('Connector/Authorize', [
                'requestId' => $requestId,
                'client' => [
                    'name' => $client->name,
                    // Proven by the metadata document's address; a self-registered
                    // client can call itself anything.
                    'verified_host' => $client->verifiedHost(),
                    'redirect_host' => RedirectUri::displayHost($redirectUri),
                    'is_local' => RedirectUri::isLoopback($redirectUri),
                ],
                'account' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_team_member' => ! $user->isAdmin(),
                    'owner_name' => $user->isAdmin() ? null : $user->getAdminUser()?->name,
                ],
                'scopeGroups' => Scopes::grouped(),
                'requestedScopes' => $scopes,
                'instanceHost' => $request->getHost(),
            ])->toResponse($request)
        );
    }

    public function decide(Request $request): Response
    {
        $validated = $request->validate([
            'request_id' => 'required|uuid',
            'decision' => 'required|in:approve,deny',
            'scopes' => 'array',
            'scopes.*' => 'string',
        ]);

        $pending = $request->session()->pull(self::SESSION_KEY.'.'.$validated['request_id']);
        $client = is_array($pending) ? ConnectorClient::find($pending['client_id']) : null;

        if (! $client || ($pending['expires_at'] ?? 0) < now()->getTimestamp()) {
            return back()->withErrors(['request_id' => __('connector.authorize.expired')]);
        }

        if ($validated['decision'] === 'deny') {
            return Inertia::location($this->redirectUrl($pending['redirect_uri'], [
                'error' => 'access_denied',
                'error_description' => 'The user denied the request.',
                'state' => $pending['state'],
            ]));
        }

        // The user may grant fewer permissions than requested, never more.
        $granted = array_values(array_intersect($pending['scopes'], Scopes::filter($validated['scopes'] ?? [])));

        if ($granted === []) {
            // Keep the request so that the form can be submitted again.
            $request->session()->put(self::SESSION_KEY.'.'.$validated['request_id'], $pending);

            return back()->withErrors(['scopes' => __('connector.authorize.no_scopes')]);
        }

        $code = $this->tokens->createAuthorizationCode(
            $client,
            $request->user(),
            $pending['redirect_uri'],
            $granted,
            $pending['code_challenge'],
            $pending['resource'],
        );

        return Inertia::location($this->redirectUrl($pending['redirect_uri'], [
            'code' => $code,
            'state' => $pending['state'],
        ]));
    }

    /**
     * The redirect back to the application, with `iss` (RFC 9207) so that the
     * host can tell which authorization server answered.
     *
     * @param  array<string, ?string>  $parameters
     */
    private function redirectUrl(string $redirectUri, array $parameters): string
    {
        $parameters = array_filter($parameters, fn ($value) => $value !== null) + ['iss' => ConnectorUrls::issuer()];

        return $redirectUri.(str_contains($redirectUri, '?') ? '&' : '?').http_build_query($parameters);
    }

    /** The consent screen must not be shown inside another site's frame. */
    private function framed(Response $response): Response
    {
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Content-Security-Policy', "frame-ancestors 'none'");
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
