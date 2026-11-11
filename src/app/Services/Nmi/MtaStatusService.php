<?php

namespace App\Services\Nmi;

/**
 * Whether the NetSendo MTA (the Haraka container, compose profile `nmi`) answers, and
 * which addresses it can send from.
 */
class MtaStatusService
{
    private const TIMEOUT_SECONDS = 3;

    /**
     * Capability in which the MTA lists the addresses it can send from
     * (docker/haraka/plugins/netsendo_outbound.js)
     */
    private const BIND_CAPABILITY = 'XNMI-BIND';

    /**
     * Open an SMTP session, read the greeting and ask what the MTA offers.
     *
     * `bind_addresses` is null when the MTA does not say (it is offline, or runs without
     * the netsendo_outbound plugin); an empty list means it said "none".
     *
     * @return array{online: bool, host: string, port: int, message: string, auth: bool, bind_addresses: ?array, checked_at: string}
     */
    public function probe(): array
    {
        $host = (string) config('nmi.mta_host', 'netsendo-mta');
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

        return [
            'online' => $isOnline,
            'host' => $host,
            'port' => $port,
            'message' => $message,
            ...$this->capabilities($capabilities),
            'checked_at' => now()->toIso8601String(),
        ];
    }

    /**
     * What the lines of an EHLO reply say: whether the MTA takes logins, and the addresses
     * it can send from.
     *
     * @return array{auth: bool, bind_addresses: ?array}
     */
    public function capabilities(array $ehloLines): array
    {
        return [
            'auth' => $this->offers($ehloLines, 'AUTH'),
            'bind_addresses' => $this->bindAddresses($ehloLines),
        ];
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
