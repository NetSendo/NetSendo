<?php

namespace App\Services\Nmi;

/**
 * Where NetSendo hands mail to NetSendo MTA. The MTA runs on the server's own network
 * (docker-compose.yml, network_mode: host) and listens on netsendo-network's gateway,
 * 10.58.0.1:2525; the app, queue and scheduler containers know it as netsendo-mta.
 * NMI_MTA_HOST / NMI_MTA_PORT point elsewhere for an MTA set up another way.
 */
class MtaEndpoint
{
    public const DEFAULT_HOST = 'netsendo-mta';
    public const DEFAULT_PORT = 2525;

    public static function host(): string
    {
        $host = trim((string) config('nmi.mta_host'));

        return $host === '' ? self::DEFAULT_HOST : $host;
    }

    public static function port(): int
    {
        $port = (int) config('nmi.mta_port');

        // NMI_MTA_PORT=25 of an older .env.example: the MTA on the Docker network took port 25;
        // on the server's network it cannot (a port below 1024), so it listens on 2525
        if ($port <= 0 || ($port === 25 && self::host() === self::DEFAULT_HOST)) {
            return self::DEFAULT_PORT;
        }

        return $port;
    }
}
