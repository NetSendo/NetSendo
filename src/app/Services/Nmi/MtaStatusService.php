<?php

namespace App\Services\Nmi;

use Illuminate\Support\Facades\Cache;

/**
 * Whether the NetSendo MTA (the Haraka container, service `nmi-mta`) answers, which
 * addresses it can send from, and whether mail can leave this server at all: port 25
 * to the outside, the public address receivers see and its PTR name (XNMI-EGRESS,
 * docker/haraka/plugins/netsendo_outbound.js).
 */
class MtaStatusService
{
    private const TIMEOUT_SECONDS = 3;

    /** Capability with the result of the MTA's port 25 check */
    private const EGRESS_CAPABILITY = 'XNMI-EGRESS';

    /** The server's public address, for the SPF records of NMI domains */
    public const SERVER_ADDRESS_CACHE_KEY = 'nmi:server-address';

    /**
     * Capability in which the MTA lists the addresses it can send from
     * (docker/haraka/plugins/netsendo_outbound.js)
     */
    private const BIND_CAPABILITY = 'XNMI-BIND';

    /**
     * Open an SMTP session, read the greeting and ask what the MTA offers.
     *
     * `bind_addresses` is null when the MTA does not say (it is offline, or runs without
     * the netsendo_outbound plugin); an empty list means it said "none". So is `egress`
     * (see egress()).
     *
     * @return array{online: bool, host: string, port: int, message: string, auth: bool, bind_addresses: ?array, egress: ?array, checked_at: string}
     */
    public function probe(): array
    {
        $host = (string) config('nmi.mta_host', 'nmi-mta');
        $port = (int) config('nmi.mta_port', 25);

        $isOnline = false;
        $message = '';
        $capabilities = [];

        try {
            $socket = @fsockopen($host, $port, $errno, $errstr, self::TIMEOUT_SECONDS);

            if ($socket) {
                stream_set_timeout($socket, self::TIMEOUT_SECONDS);
                $greeting = $this->readReply($socket);

                // A valid SMTP greeting starts with 220
                if (str_starts_with($greeting[0] ?? '', '220')) {
                    $isOnline = true;
                    $message = 'MTA is online and accepting connections';

                    fwrite($socket, "EHLO netsendo.app\r\n");
                    $capabilities = $this->readReply($socket);
                    fwrite($socket, "QUIT\r\n");
                } else {
                    $message = 'MTA responded but with unexpected greeting';
                }

                fclose($socket);
            } else {
                $message = "Cannot connect to MTA: {$errstr} (error {$errno})";
            }
        } catch (\Throwable $e) {
            $message = 'Connection error: ' . $e->getMessage();
        }

        $status = [
            'online' => $isOnline,
            'host' => $host,
            'port' => $port,
            'message' => $message,
            ...$this->capabilities($capabilities),
            'checked_at' => now()->toIso8601String(),
        ];

        if ($ip = $status['egress']['public_ip'] ?? null) {
            Cache::put(self::SERVER_ADDRESS_CACHE_KEY, $ip, now()->addDay());
        }

        return $status;
    }

    /**
     * The public address mail without a dedicated IP leaves from, as the MTA last saw it;
     * null when it cannot tell (offline, port 25 closed, check switched off). Cached for a
     * day, so pages that list SPF records do not open an SMTP session each time.
     */
    public function serverAddress(): ?string
    {
        $cached = Cache::get(self::SERVER_ADDRESS_CACHE_KEY);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        // Asked at most every 10 minutes while it does not know
        if (Cache::has(self::SERVER_ADDRESS_CACHE_KEY . ':miss')) {
            return null;
        }

        $ip = $this->probe()['egress']['public_ip'] ?? null;
        if ($ip === null) {
            Cache::put(self::SERVER_ADDRESS_CACHE_KEY . ':miss', true, now()->addMinutes(10));
        }

        return $ip;
    }

    /**
     * What the lines of an EHLO reply say: whether the MTA takes logins, and the addresses
     * it can send from.
     *
     * @return array{auth: bool, bind_addresses: ?array, egress: ?array}
     */
    public function capabilities(array $ehloLines): array
    {
        return [
            'auth' => $this->offers($ehloLines, 'AUTH'),
            'bind_addresses' => $this->bindAddresses($ehloLines),
            'egress' => $this->egress($ehloLines),
        ];
    }

    /**
     * What the MTA found out about sending from this server, null when it does not say
     * (an MTA image older than 2.11.0):
     *
     * - port25: open, blocked (the provider drops it), unknown (no answer it could judge,
     *   e.g. no DNS), pending (not checked yet), off (NMI_EGRESS_PROBE=false);
     * - public_ip: the address receivers see;
     * - ptr: confirmed (its PTR name resolves back to it), unconfirmed, none;
     * - helo: the HELO name of mail without a dedicated IP, helo_from: env | ptr;
     * - checked_at: when it last checked (ISO 8601).
     *
     * @return array{port25: string, public_ip: ?string, ptr: ?string, helo: ?string, helo_from: ?string, checked_at: ?string}|null
     */
    public function egress(array $ehloLines): ?array
    {
        foreach ($ehloLines as $line) {
            if (! preg_match('/^250[ -]' . self::EGRESS_CAPABILITY . '(?:\s+(.*))?$/i', $line, $match)) {
                continue;
            }

            $values = [];
            foreach (preg_split('/\s+/', trim($match[1] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $pair) {
                [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
                $values[strtolower($key)] = $value;
            }

            $port25 = strtolower($values['port25'] ?? '');
            $ptr = strtolower($values['ptr'] ?? '');
            $helo = strtolower(rtrim($values['helo'] ?? '', '.'));
            $checked = ctype_digit($values['checked'] ?? '') ? (int) $values['checked'] : null;

            return [
                'port25' => in_array($port25, ['open', 'blocked', 'unknown', 'pending', 'off'], true) ? $port25 : 'unknown',
                'public_ip' => filter_var($values['ip'] ?? '', FILTER_VALIDATE_IP) ?: null,
                'ptr' => in_array($ptr, ['confirmed', 'unconfirmed', 'none'], true) ? $ptr : null,
                'helo' => preg_match('/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $helo) ? $helo : null,
                'helo_from' => in_array($values['helo_from'] ?? '', ['env', 'ptr'], true) ? $values['helo_from'] : null,
                'checked_at' => $checked ? date(DATE_ATOM, $checked) : null,
            ];
        }

        return null;
    }

    /**
     * Whether mail of a dedicated IP really leaves the MTA from that address:
     * true/false as the MTA reports it, null when it cannot be told.
     */
    public function canSendFrom(string $ip): ?bool
    {
        $addresses = $this->probe()['bind_addresses'];

        if ($addresses === null) {
            return null;
        }

        $wanted = self::packed($ip);

        foreach ($addresses as $address) {
            if ($wanted !== null && self::packed($address) === $wanted) {
                return true;
            }
        }

        return false;
    }

    /**
     * Lines of one SMTP reply, e.g. ["250-mta Hello", "250-AUTH PLAIN", "250 XNMI-BIND 1.2.3.4"]
     *
     * @param resource $socket
     */
    private function readReply($socket): array
    {
        $lines = [];

        while (($line = fgets($socket, 2048)) !== false) {
            $line = rtrim($line, "\r\n");
            $lines[] = $line;

            // "250 text" ends a reply, "250-text" continues it
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }

        return $lines;
    }

    private function offers(array $capabilities, string $keyword): bool
    {
        foreach ($capabilities as $line) {
            if (preg_match('/^250[ -]' . preg_quote($keyword, '/') . '\b/i', $line)) {
                return true;
            }
        }

        return false;
    }

    private function bindAddresses(array $capabilities): ?array
    {
        $addresses = null;

        foreach ($capabilities as $line) {
            if (! preg_match('/^250[ -]' . self::BIND_CAPABILITY . '(?:\s+(.*))?$/i', $line, $match)) {
                continue;
            }

            $addresses ??= [];
            foreach (preg_split('/[\s,]+/', trim($match[1] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $address) {
                if (filter_var($address, FILTER_VALIDATE_IP)) {
                    $addresses[] = $address;
                }
            }
        }

        return $addresses === null ? null : array_values(array_unique($addresses));
    }

    /**
     * One form per address, so differently written IPv6 addresses compare equal
     */
    private static function packed(string $ip): ?string
    {
        $packed = @inet_pton(trim($ip));

        return $packed === false ? null : $packed;
    }
}
