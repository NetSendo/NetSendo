<?php

namespace App\Services\Connector;

/**
 * The public URLs of the connector, built from the host of the current request
 * so that they match whatever address the AI host was given.
 */
class ConnectorUrls
{
    /** The MCP endpoint, which is also the OAuth "resource" tokens are issued for. */
    public static function resource(): string
    {
        return url('/mcp');
    }

    /** The OAuth issuer identifier: the instance root, without a trailing slash. */
    public static function issuer(): string
    {
        return rtrim(url('/'), '/');
    }

    public static function protectedResourceMetadata(): string
    {
        return url('/.well-known/oauth-protected-resource/mcp');
    }

    /**
     * Is `resource` (RFC 8707) of a request this connector? Hosts send the MCP
     * URL; some send the instance root.
     */
    public static function isOurResource(?string $resource): bool
    {
        if ($resource === null || $resource === '') {
            return true;
        }

        $normalize = fn (string $url) => rtrim(strtolower($url), '/');

        return in_array($normalize($resource), [$normalize(self::resource()), $normalize(self::issuer())], true);
    }
}
