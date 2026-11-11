<?php

namespace App\Services\Connector;

/**
 * Rules for OAuth redirect URIs of connector clients.
 *
 * Allowed: https anywhere; http only on a loopback address (desktop and CLI
 * hosts listen on localhost); a private URL scheme only when it is listed in
 * connector.redirect_schemes (a scheme such as javascript: must never be a
 * redirect target).
 */
class RedirectUri
{
    private const LOOPBACK_HOSTS = ['localhost', '127.0.0.1', '[::1]'];

    /** The longest redirect URI of the known MCP hosts has 53 characters. */
    public const MAX_LENGTH = 255;

    public static function isAllowed(mixed $uri): bool
    {
        if (! is_string($uri) || $uri === '' || strlen($uri) > self::MAX_LENGTH || preg_match('/[\x00-\x20\x7f]/', $uri)) {
            return false;
        }

        $parts = parse_url($uri);

        if (! is_array($parts) || empty($parts['scheme']) || isset($parts['fragment']) || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host'] ?? '');

        if ($scheme === 'https') {
            return $host !== '';
        }

        if ($scheme === 'http') {
            return in_array($host, self::LOOPBACK_HOSTS, true);
        }

        return in_array($scheme, array_map('strtolower', config('connector.redirect_schemes', [])), true);
    }

    /**
     * Does the redirect URI of an authorization request match a registered one?
     * Exact comparison, except that the port of a loopback URI is ignored:
     * native apps listen on a port chosen at run time (RFC 8252 §7.3).
     *
     * @param  array<int, string>  $registered
     */
    public static function matches(string $requested, array $registered): bool
    {
        foreach ($registered as $candidate) {
            if (! is_string($candidate)) {
                continue;
            }

            if (hash_equals($candidate, $requested)) {
                return true;
            }

            if (self::isLoopback($candidate) && self::isLoopback($requested)
                && self::withoutPort($candidate) === self::withoutPort($requested)) {
                return true;
            }
        }

        return false;
    }

    public static function isLoopback(string $uri): bool
    {
        $parts = parse_url($uri);

        return is_array($parts)
            && strtolower($parts['scheme'] ?? '') === 'http'
            && in_array(strtolower($parts['host'] ?? ''), self::LOOPBACK_HOSTS, true);
    }

    private static function withoutPort(string $uri): string
    {
        $parts = parse_url($uri);

        return strtolower($parts['host'] ?? '').($parts['path'] ?? '').(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    /**
     * Does the redirect URI lead back to the publisher of a client metadata
     * document? Yes for the same host, for a host below or above it
     * (app.example.com and example.com), and for a loopback address (an app on
     * the user's own machine). A document on a shared host (a file hosting or
     * pages service) with a redirect somewhere else does not pass: anyone can
     * publish there.
     */
    public static function belongsToHost(string $redirectUri, string $publisherHost): bool
    {
        if (self::isLoopback($redirectUri)) {
            return true;
        }

        $parts = parse_url($redirectUri);
        $host = strtolower($parts['host'] ?? '');
        $publisherHost = strtolower($publisherHost);

        if (strtolower($parts['scheme'] ?? '') !== 'https' || $host === '' || $publisherHost === '') {
            return false;
        }

        return $host === $publisherHost
            || str_ends_with($host, '.'.$publisherHost)
            || str_ends_with($publisherHost, '.'.$host);
    }

    /** The host shown to the user on the consent screen ("…will return you to X"). */
    public static function displayHost(string $uri): string
    {
        $parts = parse_url($uri);
        $scheme = strtolower($parts['scheme'] ?? '');

        if (in_array($scheme, ['http', 'https'], true)) {
            return (string) ($parts['host'] ?? $uri);
        }

        return $scheme.'://';
    }
}
