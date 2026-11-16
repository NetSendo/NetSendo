<?php

namespace App\Services\Connector;

use App\Models\ConnectorClient;
use Illuminate\Support\Facades\Cache;
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
 *
 * Both start without a sign-in, so both are bounded: a document is downloaded
 * only for a signed-in user (the consent screen), never by the token endpoint,
 * and registration stores little and not without limit.
 */
class ClientRegistry
{
    /** How long a downloaded metadata document is trusted before it is fetched again. */
    private const METADATA_TTL_HOURS = 24;

    /** A document that could not be used is not asked for again for this long. */
    private const FAILURE_TTL_SECONDS = 300;

    /** Redirect URIs per self-registered client (VS Code registers 4, the most of the known hosts). */
    private const MAX_REGISTERED_REDIRECT_URIS = 5;

    /** Redirect URIs in a metadata document (Hermes publishes 10). */
    private const MAX_DOCUMENT_REDIRECT_URIS = 20;

    /** A registration that is this young may still be on its way through the sign-in. */
    private const REGISTRATION_GRACE_MINUTES = 10;

    public function __construct(private ClientMetadataFetcher $fetcher)
    {
    }

    /**
     * The client of an authorization request. A metadata document that is not
     * known yet, or is due for a refresh, is downloaded here: call this only
     * for a signed-in user.
     *
     * @throws OAuthException when the client is unknown or its metadata document is unusable
     */
    public function find(string $clientId): ConnectorClient
    {
        if ($this->isMetadataUrl($clientId)) {
            return $this->findByMetadataDocument($clientId);
        }

        return $this->findStored($clientId);
    }

    /**
     * The client as it is stored, without any download. This is what the
     * token endpoint uses: it is called without a sign-in, and a client that
     * may exchange a code has been stored by the consent screen before.
     *
     * @throws OAuthException when the client is not known
     */
    public function findStored(string $clientId): ConnectorClient
    {
        if ($clientId === '' || strlen($clientId) > 2000) {
            throw new OAuthException('invalid_client', 'Missing client_id.', 400);
        }

        $client = $this->isMetadataUrl($clientId)
            ? ConnectorClient::where('identifier', hash('sha256', $clientId))
                ->where('registration_type', ConnectorClient::TYPE_METADATA)
                ->first()
            : ConnectorClient::where('identifier', $clientId)
                ->where('registration_type', ConnectorClient::TYPE_DYNAMIC)
                ->first();

        if (! $client) {
            throw new OAuthException('invalid_client', 'Unknown client. Start the connection again from the application.', 400);
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

        $redirectUris = $this->redirectUris($metadata, 'invalid_redirect_uri', self::MAX_REGISTERED_REDIRECT_URIS);

        $this->makeRoomForRegistration();

        return ConnectorClient::create([
            'identifier' => 'nsc_'.Str::lower(Str::random(40)),
            'registration_type' => ConnectorClient::TYPE_DYNAMIC,
            'name' => $this->clientName($metadata, $redirectUris[0]),
            'redirect_uris' => $redirectUris,
            'client_uri' => $this->optionalUrl($metadata['client_uri'] ?? null),
            'logo_uri' => $this->optionalUrl($metadata['logo_uri'] ?? null),
        ]);
    }

    /**
     * Self-registered clients without a connected app are limited in number.
     * At the limit the oldest ones go first: those that were never connected
     * (and are not in the middle of a sign-in), then those that were connected
     * longest ago. Only when everything is fresh is the registration refused.
     */
    private function makeRoomForRegistration(): void
    {
        $limit = max(1, (int) config('connector.registration.max_unconnected_clients', 500));

        $unconnected = fn () => ConnectorClient::where('registration_type', ConnectorClient::TYPE_DYNAMIC)
            ->whereDoesntHave('connections');

        $excess = $unconnected()->count() - $limit + 1;

        if ($excess <= 0) {
            return;
        }

        $removable = $unconnected()
            ->where('created_at', '<', now()->subMinutes(self::REGISTRATION_GRACE_MINUTES))
            ->whereDoesntHave('authCodes', fn ($query) => $query->where('expires_at', '>', now()))
            // Never connected first (last_used_at is empty), then the least recently used
            ->orderByRaw('last_used_at is not null')
            ->orderBy('last_used_at')
            ->orderBy('id')
            ->limit($excess)
            ->pluck('id');

        ConnectorClient::whereIn('id', $removable)->delete();

        if ($removable->count() < $excess) {
            throw new OAuthException(
                'temporarily_unavailable',
                'Too many applications are waiting to be connected to this installation. Try again in a few minutes.',
                503,
            );
        }
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

        // A document that failed a moment ago is not downloaded again right away.
        $failureKey = 'connector.client_metadata_failed.'.$identifier;
        $failure = Cache::get($failureKey);

        if (is_string($failure)) {
            return $client ?? throw new OAuthException('invalid_client', $failure, 400);
        }

        try {
            $document = $this->fetcher->fetch($url);

            // The document must name the URL it was served from, or anyone could
            // point at someone else's document.
            if (($document['client_id'] ?? null) !== $url) {
                throw new OAuthException('invalid_client', 'The client metadata document does not match its URL.', 400);
            }

            $redirectUris = $this->redirectUris($document, 'invalid_client', self::MAX_DOCUMENT_REDIRECT_URIS);
        } catch (OAuthException $e) {
            Cache::put($failureKey, $e->getMessage(), self::FAILURE_TTL_SECONDS);

            // The publisher is briefly unreachable: keep working with the copy we have.
            return $client ?? throw $e;
        }

        return ConnectorClient::updateOrCreate(['identifier' => $identifier], [
            'registration_type' => ConnectorClient::TYPE_METADATA,
            'metadata_url' => $url,
            'name' => $this->clientName($document, $url),
            'redirect_uris' => $redirectUris,
            'client_uri' => $this->optionalUrl($document['client_uri'] ?? null),
            'logo_uri' => $this->optionalUrl($document['logo_uri'] ?? null),
            'metadata_fetched_at' => now(),
        ]);
    }

    private function isMetadataUrl(string $clientId): bool
    {
        return preg_match('#^https?://#i', $clientId) === 1;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return list<string>
     */
    private function redirectUris(array $metadata, string $error, int $max): array
    {
        $uris = $metadata['redirect_uris'] ?? null;

        if (! is_array($uris) || $uris === [] || count($uris) > $max) {
            throw new OAuthException($error, "redirect_uris must be a list of 1 to {$max} URIs.", 400);
        }

        foreach ($uris as $uri) {
            if (! RedirectUri::isAllowed($uri)) {
                throw new OAuthException(
                    $error,
                    'Redirect URIs must use https, http on a loopback address, or an allowed app scheme, and have at most '
                        .RedirectUri::MAX_LENGTH.' characters: '.(is_string($uri) ? Str::limit($uri, 100) : 'not a string'),
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
        $name = trim(preg_replace('/[\p{C}\s]+/u', ' ', Str::limit($name, 400, '')) ?? '');

        return Str::limit($name !== '' ? $name : 'MCP client', 80, '…');
    }

    private function optionalUrl(mixed $value): ?string
    {
        return is_string($value) && strlen($value) <= 255 && preg_match('#^https://#i', $value) && filter_var($value, FILTER_VALIDATE_URL)
            ? $value
            : null;
    }
}
