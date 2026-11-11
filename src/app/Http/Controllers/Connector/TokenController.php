<?php

namespace App\Http\Controllers\Connector;

use App\Http\Controllers\Controller;
use App\Services\Connector\ClientRegistry;
use App\Services\Connector\ConnectorUrls;
use App\Services\Connector\OAuthException;
use App\Services\Connector\TokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The OAuth token endpoint (authorization_code and refresh_token grants) and RFC 7009 revocation. */
class TokenController extends Controller
{
    public function __construct(private ClientRegistry $clients, private TokenService $tokens)
    {
    }

    public function token(Request $request): JsonResponse
    {
        try {
            $resource = $this->string($request, 'resource');

            if (! ConnectorUrls::isOurResource($resource)) {
                throw new OAuthException('invalid_target', 'This authorization server only issues tokens for '.ConnectorUrls::resource().'.');
            }

            // Public clients name themselves in the body; a host may also put
            // the client_id in HTTP Basic with an empty secret. Only a client
            // that is already stored is accepted: this endpoint needs no
            // sign-in and must never download a metadata document.
            $client = $this->clients->findStored($this->string($request, 'client_id') ?? (string) $request->getUser());

            $response = match ($this->string($request, 'grant_type')) {
                'authorization_code' => $this->tokens->exchangeAuthorizationCode(
                    $client,
                    $this->required($request, 'code'),
                    $this->required($request, 'redirect_uri'),
                    $this->required($request, 'code_verifier'),
                    $resource,
                ),
                'refresh_token' => $this->tokens->refresh($client, $this->required($request, 'refresh_token')),
                default => throw new OAuthException('unsupported_grant_type', 'Supported grant types: authorization_code, refresh_token.'),
            };
        } catch (OAuthException $e) {
            return response()->json($e->toArray(), $e->status, $this->headers());
        }

        return response()->json($response, 200, $this->headers());
    }

    public function revoke(Request $request): JsonResponse
    {
        $token = $this->string($request, 'token');

        if ($token !== null) {
            $this->tokens->revokeByToken($token);
        }

        // RFC 7009: the answer is 200 whether or not the token existed.
        return response()->json((object) [], 200, $this->headers());
    }

    private function required(Request $request, string $key): string
    {
        return $this->string($request, $key)
            ?? throw new OAuthException('invalid_request', "Missing parameter: {$key}.");
    }

    private function string(Request $request, string $key): ?string
    {
        $value = $request->input($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return ['Cache-Control' => 'no-store', 'Pragma' => 'no-cache'];
    }
}
