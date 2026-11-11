<?php

namespace App\Http\Controllers\Connector;

use App\Http\Controllers\Controller;
use App\Services\Connector\ClientRegistry;
use App\Services\Connector\OAuthException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** RFC 7591 dynamic client registration for hosts that do not publish a metadata document. */
class RegistrationController extends Controller
{
    public function __invoke(Request $request, ClientRegistry $clients): JsonResponse
    {
        $metadata = $request->json()->all();

        try {
            $client = $clients->register(is_array($metadata) ? $metadata : []);
        } catch (OAuthException $e) {
            // RFC 7591 §3.2.2 names its own error for everything that is not a redirect URI.
            $error = $e->error === 'invalid_redirect_uri' || $e->error === 'access_denied' ? $e->error : 'invalid_client_metadata';

            return response()->json(['error' => $error, 'error_description' => $e->getMessage()], $e->status, ['Cache-Control' => 'no-store']);
        }

        return response()->json(array_filter([
            'client_id' => $client->identifier,
            'client_id_issued_at' => $client->created_at->getTimestamp(),
            'client_name' => $client->name,
            'redirect_uris' => $client->redirect_uris,
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
            'token_endpoint_auth_method' => 'none',
            'client_uri' => $client->client_uri,
            'logo_uri' => $client->logo_uri,
        ], fn ($value) => $value !== null), 201, ['Cache-Control' => 'no-store'], JSON_UNESCAPED_SLASHES);
    }
}
