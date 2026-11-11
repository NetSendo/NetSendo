<?php

namespace App\Services\Nmi;

/**
 * Whether the NetSendo MTA (the Haraka container, compose profile `nmi`) answers.
 */
class MtaStatusService
{
    private const TIMEOUT_SECONDS = 3;

    /**
     * Open an SMTP session and read the greeting.
     *
     * @return array{online: bool, host: string, port: int, message: string, checked_at: string}
     */
    public function probe(): array
    {
        $host = (string) config('nmi.mta_host', 'netsendo-mta');
        $port = (int) config('nmi.mta_port', 25);

        $isOnline = false;
        $message = '';

        try {
            $socket = @fsockopen($host, $port, $errno, $errstr, self::TIMEOUT_SECONDS);

            if ($socket) {
                stream_set_timeout($socket, self::TIMEOUT_SECONDS);
                $response = (string) fgets($socket, 1024);
                fclose($socket);

                // A valid SMTP greeting starts with 220
                if (str_starts_with(trim($response), '220')) {
                    $isOnline = true;
                    $message = 'MTA is online and accepting connections';
                } else {
                    $message = 'MTA responded but with unexpected greeting';
                }
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
            'checked_at' => now()->toIso8601String(),
        ];
    }
}
