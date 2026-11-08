<?php

namespace App\Services\Nmi;

use App\Models\DomainConfiguration;
use Illuminate\Support\Facades\Log;

/**
 * The logins NetSendo MTA (Haraka) accepts: one per domain with NMI switched on.
 *
 * Written to a file on a volume shared with the MTA container (config nmi.mta_auth_file,
 * in Docker the nmi-auth volume); the netsendo_auth Haraka plugin reads it at every AUTH,
 * so a change needs no restart. The file holds a SHA-256 hash of each password, not the
 * password: the passwords are 40 random letters and digits, so a hash without salt cannot
 * be reversed by trying.
 *
 * It also tells the MTA how to send (decided in NetSendo, nothing is set in the MTA): the
 * name it gives itself from the server's main address ("helo", MailServerName) and, per
 * login, the dedicated IPs of the domain with their names. A message names its IP in the
 * X-NMI-IP header; the netsendo_outbound plugin sends it from that IP when the IP is one of
 * the login's and is configured on the server, otherwise from the main address.
 *
 *   {"version": 1, "helo": "netsendo.example.com", "users": {"nmi-3-k2j4h5g6": {"sha256": "…",
 *    "domain": "example.com", "ips": {"203.0.113.10": "mail1.example.com"}}}}
 */
class MtaAuthFile
{
    public function __construct(private MailServerName $names)
    {
    }

    public function path(): string
    {
        return (string) config('nmi.mta_auth_file');
    }

    /**
     * @return array<string, array{sha256: string, domain: string, ips: array<string, ?string>}>
     */
    public function entries(): array
    {
        return DomainConfiguration::query()
            ->with(['dedicatedIps' => fn ($query) => $query->orderBy('id')])
            ->where('nmi_enabled', true)
            ->whereNotNull('nmi_smtp_username')
            ->whereNotNull('nmi_smtp_password')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(function (DomainConfiguration $domain) {
                $password = $domain->getDecryptedNmiPassword();

                return $password === null ? [] : [$domain->nmi_smtp_username => [
                    'sha256' => hash('sha256', $password),
                    'domain' => $domain->domain,
                    'ips' => $this->ips($domain),
                ]];
            })
            ->all();
    }

    /**
     * Dedicated IPs of a domain with the name the MTA sends under from each
     *
     * @return array<string, ?string>
     */
    private function ips(DomainConfiguration $domain): array
    {
        $ips = [];
        foreach ($domain->dedicatedIps as $ip) {
            $address = MailServerName::normalizeIp((string) $ip->ip_address);
            if ($address !== null) {
                $ips[$address] = $this->names->forIp($ip);
            }
        }

        return $ips;
    }

    /**
     * Write the current logins, none while NetSendo MTA is switched off for the installation.
     * Replaces the file in one step (a reader never sees half of it) and leaves it alone when
     * nothing changed. False when it cannot be written.
     */
    public function write(): bool
    {
        $path = $this->path();
        $entries = NmiSwitch::enabled() ? $this->entries() : [];
        $json = json_encode(
            [
                'version' => 1,
                'helo' => $this->names->default(),
                'users' => (object) array_map(
                    fn (array $entry) => array_merge($entry, ['ips' => (object) $entry['ips']]),
                    $entries
                ),
            ],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        ) . "\n";

        if (is_file($path) && @file_get_contents($path) === $json) {
            return true;
        }

        $directory = dirname($path);
        $temporary = $directory . '/.' . basename($path) . '.' . bin2hex(random_bytes(4));

        $written = (is_dir($directory) || @mkdir($directory, 0755, true))
            && @file_put_contents($temporary, $json) !== false
            && @chmod($temporary, 0644)
            && @rename($temporary, $path);

        if (!$written) {
            $error = error_get_last()['message'] ?? null;
            @unlink($temporary);
            Log::error('NetSendo MTA: the login file could not be written; the MTA keeps the previous logins', [
                'path' => $path,
                'error' => $error,
            ]);
        }

        return $written;
    }

    /**
     * Bring the file up to date. While NetSendo MTA is switched off it holds no logins, so the
     * MTA relays for nobody; an installation that never used it gets no file.
     */
    public function sync(): bool
    {
        if (!NmiSwitch::enabled() && !is_file($this->path())) {
            return true;
        }

        return $this->write();
    }
}
