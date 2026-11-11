<?php

namespace App\Services\Connector;

use App\Models\ConnectorClient;
use Illuminate\Support\Str;

/**
 * Finds and creates connector clients.
 *
 * A host identifies itself in one of two ways:
 *  - a Client ID Metadata Document: the client_id is an https URL; NetSendo
 *    downloads the JSON behind it and takes the name and redirect URIs from
 *    there (Claude's "published identity", ChatGPT, Hermes, VS Code);
 *  - dynamic registration (RFC 7591): the host first POSTs its name and
 *    redirect URIs to /oauth/register and gets a random client_id.
 */
class ClientRegistry
{
    /** How long a downloaded metadata document is trusted before it is fetched again. */
    private const METADATA_TTL_HOURS = 24;

    public function __construct(private ClientMetadataFetcher $fetcher)
    {
    }

    /** @throws OAuthException when the client is unknown or its metadata document is unusable */
    public function find(string $clientId): ConnectorClient
    {
        if ($clientId === '') {
            throw new OAuthException('invalid_client', 'Missing client_id.', 400);
        }

        if (preg_match('#^https?://#i', $clientId)) {
            return $this->findByMetadataDocument($clientId);
        }

        $client = ConnectorClient::where('identifier', $clientId)
            ->where('registration_type', ConnectorClient::TYPE_DYNAMIC)
            ->first();

        if (! $client) {
            throw new OAuthException('invalid_client', 'Unknown client. Register the client again.', 400);
        }

        return $client;
    }

    /**
     * Register a client from RFC 7591 metadata.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function register(array $metadata): ConnectorClient
    {
        if (! config('connector.registration.dynamic')) {
            throw new OAuthException('access_denied', 'Dynamic client registration is turned off on this installation.', 403);
        }

        return ConnectorClient::create([
            'identifier' => 'nsc_'.Str::lower(Str::random(40)),
            'registration_type' => ConnectorClient::TYPE_DYNAMIC,
            'name' => $this->clientName($metadata, $metadata['redirect_uris'][0] ?? ''),
            'redirect_uris' => $this->redirectUris($metadata, 'invalid_redirect_uri'),
            'client_uri' => $this->optionalUrl($metadata['client_uri'] ?? null),
            'logo_uri' => $this->optionalUrl($metadata['logo_uri'] ?? null),
        ]);
    }

    private function findByMetadataDocument(string $url): ConnectorClient
    {
        if (! config('connector.registration.metadata_documents')) {
            throw new OAuthException('invalid_client', 'Client metadata documents are turned off on this installation.', 400);
        }

        $this->fetcher->validatedHost($url);

        $identifier = hash('sha256', $url);
        $client = ConnectorClient::where('identifier', $identifier)->first();

        if ($client && $client->metadata_fetched_at?->gt(now()->subHours(self::METADATA_TTL_HOURS))) {
            return $client;
        }

        try {
            $document = $this->fetcher->fetch($url);
        } catch (OAuthException $e) {
            // The publisher is briefly unreachable: keep working with the copy we have.
            if ($client) {
                return $client;
            }

            throw $e;
        }

        // The document must name the URL it was served from, or anyone could
        // point at someone else's document.
        if (($document['client_id'] ?? null) !== $url) {
            throw new OAuthException('invalid_client', 'The client metadata document does not match its URL.', 400);
        }

        $attributes = [
            'registration_type' => ConnectorClient::TYPE_METADATA,
            'metadata_url' => $url,
            'name' => $this->clientName($document, $url),
            'redirect_uris' => $this->redirectUris($document, 'invalid_client'),
            'client_uri' => $this->optionalUrl($document['client_uri'] ?? null),
            'logo_uri' => $this->optionalUrl($document['logo_uri'] ?? null),
            'metadata_fetched_at' => now(),
        ];

        return ConnectorClient::updateOrCreate(['identifier' => $identifier], $attributes);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return list<string>
     */
    private function redirectUris(array $metadata, string $error): array
    {
        $uris = $metadata['redirect_uris'] ?? null;

        if (! is_array($uris) || $uris === [] || count($uris) > 20) {
            throw new OAuthException($error, 'redirect_uris must be a list of 1 to 20 URIs.', 400);
        }

        foreach ($uris as $uri) {
            if (! RedirectUri::isAllowed($uri)) {
                throw new OAuthException(
                    $error,
                    'Redirect URIs must use https, http on a loopback address, or an allowed app scheme: '.(is_string($uri) ? Str::limit($uri, 200) : 'not a string'),
                    400,
                );
            }
        }

        return array_values(array_unique($uris));
    }

    /** @param  array<string, mixed>  $metadata */
    private function clientName(array $metadata, string $fallbackUrl): string
    {
        $name = $metadata['client_name'] ?? null;

        if (! is_string($name) || trim($name) === '') {
            $name = RedirectUri::displayHost($fallbackUrl) ?: 'MCP client';
        }

        // Shown on the consent screen: no control characters, one line, bounded.
        $name = trim(preg_replace('/[\p{C}\s]+/u', ' ', $name) ?? '');

        return Str::limit($name !== '' ? $name : 'MCP client', 120, '…');
    }

    private function optionalUrl(mixed $value): ?string
    {
        return is_string($value) && strlen($value) <= 2000 && preg_match('#^https://#i', $value) && filter_var($value, FILTER_VALIDATE_URL)
            ? $value
            : null;
    }
}
