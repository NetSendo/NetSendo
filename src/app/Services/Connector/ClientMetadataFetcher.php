<?php

namespace App\Services\Connector;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Downloads a Client ID Metadata Document: the JSON an AI host publishes at the
 * https URL it uses as its client_id.
 *
 * The URL comes from an unauthenticated request, so the download must not be
 * usable to reach the server's own network: https only, the host must resolve
 * to public addresses, the connection is pinned to the address that was
 * checked, redirects are not followed, and the answer is small and quick.
 */
class ClientMetadataFetcher
{
    private const MAX_BYTES = 65_536;

    private const TIMEOUT = 5;

    /** The DNS lookup may take this long at most. */
    protected const RESOLVE_SECONDS = 4;

    /**
     * @return array<string, mixed> the decoded document
     *
     * @throws OAuthException when the URL is not acceptable or the document cannot be read
     */
    public function fetch(string $url): array
    {
        $host = $this->validatedHost($url);
        $address = $this->publicAddress($host);

        try {
            $response = Http::withOptions([
                'allow_redirects' => false,
                // Connect to the address that was checked above, whatever DNS says a moment later.
                'curl' => [CURLOPT_RESOLVE => [$host.':443:'.$address]],
                'on_headers' => function ($response): void {
                    if ((int) $response->getHeaderLine('Content-Length') > self::MAX_BYTES) {
                        throw new \RuntimeException('Document too large');
                    }
                },
                'progress' => function ($total, $downloaded): void {
                    if ($downloaded > self::MAX_BYTES) {
                        throw new \RuntimeException('Document too large');
                    }
                },
            ])
                ->timeout(self::TIMEOUT)
                ->connectTimeout(self::TIMEOUT)
                ->withHeaders(['Accept' => 'application/json', 'User-Agent' => 'NetSendo-Connector'])
                ->get($url);
        } catch (Throwable) {
            throw new OAuthException('invalid_client', 'The client metadata document could not be downloaded.', 400);
        }

        $document = $response->successful() && strlen($response->body()) <= self::MAX_BYTES
            ? json_decode($response->body(), true)
            : null;

        if (! is_array($document) || array_is_list($document)) {
            throw new OAuthException('invalid_client', 'The client metadata document is not a JSON object.', 400);
        }

        return $document;
    }

    /**
     * A client_id URL must be https with a path, and carry no credentials,
     * fragment or dot segments.
     */
    public function validatedHost(string $url): string
    {
        $parts = parse_url($url);

        $valid = is_array($parts)
            && strlen($url) <= 2000
            && ! preg_match('/[\x00-\x20\x7f]/', $url)
            && strtolower($parts['scheme'] ?? '') === 'https'
            && ! empty($parts['host'])
            && ! isset($parts['user']) && ! isset($parts['pass']) && ! isset($parts['fragment'])
            && (! isset($parts['port']) || (int) $parts['port'] === 443)
            && isset($parts['path']) && $parts['path'] !== '/'
            && ! preg_match('#/\.{1,2}(/|$)#', $parts['path']);

        if (! $valid) {
            throw new OAuthException('invalid_client', 'The client_id is not a valid client metadata document URL.', 400);
        }

        return strtolower($parts['host']);
    }

    /** Resolve the host and return one address, refusing private, loopback and reserved ranges. */
    protected function publicAddress(string $host): string
    {
        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->resolve($host);

        if ($addresses === []) {
            throw new OAuthException('invalid_client', 'The host of the client metadata document could not be resolved.', 400);
        }

        foreach ($addresses as $address) {
            if (! self::isPublicAddress($address)) {
                throw new OAuthException('invalid_client', 'The client metadata document must be on a public host.', 400);
            }
        }

        return $addresses[0];
    }

    /**
     * The IPv4 addresses of a host, or none when the lookup fails or takes
     * longer than a few seconds.
     *
     * PHP's own lookup cannot be given a time limit and waits as long as the
     * system resolver does (half a minute for a name server that never
     * answers), with a PHP worker held all that time. The lookup is therefore
     * done by Node.js, which the connector needs anyway, with a limit of its
     * own and one more around the process.
     *
     * @return list<string>
     */
    protected function resolve(string $host): array
    {
        $script = 'const { Resolver } = require("node:dns").promises;'
            .'new Resolver({ timeout: 1500, tries: 2 }).resolve4(process.argv[1])'
            .'.then((a) => console.log(JSON.stringify(a)), () => console.log("[]"));';

        $process = @proc_open(
            [(string) config('connector.engine.node', 'node'), '-e', $script, $host],
            [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes,
            null,
            ['PATH' => getenv('PATH') ?: '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin'],
        );

        if (! is_resource($process)) {
            return [];
        }

        stream_set_blocking($pipes[1], false);
        $deadline = microtime(true) + static::RESOLVE_SECONDS;
        $output = '';

        try {
            while (! feof($pipes[1]) && ($remaining = $deadline - microtime(true)) > 0) {
                $read = [$pipes[1]];
                $write = $except = null;

                if (@stream_select($read, $write, $except, 0, (int) min($remaining * 1_000_000, 200_000)) === false) {
                    break;
                }

                $output .= (string) stream_get_contents($pipes[1]);
            }
        } finally {
            fclose($pipes[1]);

            if (proc_get_status($process)['running'] ?? false) {
                proc_terminate($process, 9);
            }
            proc_close($process);
        }

        $addresses = json_decode(trim($output), true);

        return is_array($addresses)
            ? array_values(array_filter($addresses, fn ($address) => is_string($address) && filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)))
            : [];
    }

    public static function isPublicAddress(string $address): bool
    {
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        // Carrier-grade NAT (100.64.0.0/10) is not covered by the PHP flags.
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $long = ip2long($address);

            return ($long & 0xFFC00000) !== ip2long('100.64.0.0');
        }

        return true;
    }
}
