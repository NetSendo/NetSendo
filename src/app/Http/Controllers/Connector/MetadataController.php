<?php

namespace App\Http\Controllers\Connector;

use App\Http\Controllers\Controller;
use App\Services\Connector\ConnectorUrls;
use App\Services\Connector\Scopes;
use Illuminate\Http\JsonResponse;

/**
 * Discovery documents an MCP host reads before it signs in:
 * RFC 9728 (which authorization server protects /mcp) and RFC 8414 (where that
 * server's endpoints are and what it supports).
 */
class MetadataController extends Controller
{
    public function protectedResource(): JsonResponse
    {
        return $this->document([
            'resource' => ConnectorUrls::resource(),
            'authorization_servers' => [ConnectorUrls::issuer()],
            'scopes_supported' => Scopes::all(),
            'bearer_methods_supported' => ['header'],
            'resource_name' => 'NetSendo',
        ]);
    }

    public function authorizationServer(): JsonResponse
    {
        return $this->document(array_filter([
            'issuer' => ConnectorUrls::issuer(),
            'authorization_endpoint' => route('connector.oauth.authorize'),
            'token_endpoint' => route('connector.oauth.token'),
            'registration_endpoint' => config('connector.registration.dynamic') ? route('connector.oauth.register') : null,
            'revocation_endpoint' => route('connector.oauth.revoke'),
            'response_types_supported' => ['code'],
            'response_modes_supported' => ['query'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
            // Public clients only: no client secret, the code is protected by PKCE.
            'token_endpoint_auth_methods_supported' => ['none'],
            'revocation_endpoint_auth_methods_supported' => ['none'],
            'scopes_supported' => Scopes::all(),
            'client_id_metadata_document_supported' => (bool) config('connector.registration.metadata_documents'),
            'authorization_response_iss_parameter_supported' => true,
        ], fn ($value) => $value !== null));
    }

    /** @param  array<string, mixed>  $document */
    private function document(array $document): JsonResponse
    {
        return response()->json($document, 200, ['Cache-Control' => 'public, max-age=300'], JSON_UNESCAPED_SLASHES);
    }
}
