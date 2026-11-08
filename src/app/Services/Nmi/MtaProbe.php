<?php

namespace App\Services\Nmi;

/**
 * A short SMTP conversation with NetSendo MTA (greeting, EHLO, QUIT; nothing is sent):
 * whether it answers, and which addresses of the server it can send from. The
 * netsendo_outbound Haraka plugin lists them in its EHLO answer to clients on the private
 * network:
 *
 *   250-X-NETSENDO-MTA 1
 *   250-X-NETSENDO-ADDRESS 203.0.113.10
 *
 * On Docker's bridge network the MTA has only its container address, so mail leaves from
 * the server's main address; on the server's own network (network_mode: host) it has
 * every address of the server and sends each mailbox from its dedicated IP.
 */
class MtaProbe
{
    private const TIMEOUT = 3;

    /**
     * @return array{online: bool, message: string, addresses: ?list<string>}
     *               addresses: null when not known (MTA offline, or an image without the report)
     */
    public function probe(): array
    {
        $host = (string) config('nmi.mta_host', 'netsendo-mta');
        $port = (int) config('nmi.mta_port', 25);

        $socket = @fsockopen($host, $port, $errno, $errstr, self::TIMEOUT);
        if ($socket === false) {
            return ['online' => false, 'message' => "Cannot connect to MTA: {$errstr} (error {$errno})", 'addresses' => null];
        }

        stream_set_timeout($socket, self::TIMEOUT);

        try {
            $greeting = $this->readReply($socket);
            if (!str_starts_with($greeting[0] ?? '', '220')) {
                return ['online' => false, 'message' => 'MTA responded but with unexpected greeting', 'addresses' => null];
            }

            fwrite($socket, "EHLO netsendo-panel\r\n");
            $ehlo = $this->readReply($socket);
            fwrite($socket, "QUIT\r\n");

            return [
                'online' => true,
                'message' => 'MTA is online and accepting connections',
                'addresses' => self::parseAddresses($ehlo),
            ];
        } finally {
            fclose($socket);
        }
    }

    /**
     * The addresses listed in an EHLO answer, or null when the MTA does not report them
     *
     * @param  list<string>  $lines
     * @return ?list<string>
     */
    public static function parseAddresses(array $lines): ?array
    {
        $reported = false;
        $addresses = [];

        foreach ($lines as $line) {
            if (!preg_match('/^250[ -](\S+)(?:\s+(\S+))?/', trim($line), $match)) {
                continue;
            }

            $keyword = strtoupper($match[1]);
            if ($keyword === 'X-NETSENDO-MTA') {
                $reported = true;
            } elseif ($keyword === 'X-NETSENDO-ADDRESS' && isset($match[2])) {
                $reported = true;
                if (($address = MailServerName::normalizeIp($match[2])) !== null) {
                    $addresses[] = $address;
                }
            }
        }

        return $reported ? array_values(array_unique($addresses)) : null;
    }

    /**
     * One SMTP reply: lines up to the one with a space after the code
     *
     * @param  resource  $socket
     * @return list<string>
     */
    private function readReply($socket): array
    {
        $lines = [];
        while (count($lines) < 200 && ($line = fgets($socket, 1024)) !== false) {
            $lines[] = rtrim($line, "\r\n");
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }

        return $lines;
    }
}
