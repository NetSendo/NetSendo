<?php

namespace App\Services\Nmi;

use Illuminate\Support\Facades\Cache;

/**
 * Whether this server can open outgoing connections on port 25, which NetSendo MTA
 * needs to deliver to the recipients' mail servers. Many cloud providers block it
 * (Google Compute Engine always does). Only a TCP connection to a public mail server
 * (config nmi.port25_check_host), closed at once; nothing is sent. The answer is
 * kept for 15 minutes.
 */
class OutboundPortCheck
{
    public const CACHE_KEY = 'nmi:outbound-port-25';

    /**
     * True: reachable, false: blocked or unreachable, null: the check is switched off
     * (empty NMI_PORT25_CHECK_HOST)
     */
    public function port25Open(): ?bool
    {
        $host = trim((string) config('nmi.port25_check_host'));
        if ($host === '') {
            return null;
        }

        return Cache::remember(self::CACHE_KEY, now()->addMinutes(15), function () use ($host) {
            $socket = @fsockopen($host, 25, $errno, $errstr, 5);
            if ($socket === false) {
                return false;
            }
            fclose($socket);

            return true;
        });
    }
}
