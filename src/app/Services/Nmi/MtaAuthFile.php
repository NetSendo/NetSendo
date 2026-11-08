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
 *   {"version": 1, "users": {"nmi-3-k2j4h5g6": {"sha256": "…", "domain": "example.com"}}}
 */
class MtaAuthFile
{
    public function path(): string
    {
        return (string) config('nmi.mta_auth_file');
    }

    /**
     * @return array<string, array{sha256: string, domain: string}>
     */
    public function entries(): array
    {
        return DomainConfiguration::query()
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
                ]];
            })
            ->all();
    }

    /**
     * Write the current logins. Replaces the file in one step (a reader never sees half of
     * it) and leaves it alone when nothing changed. False when it cannot be written.
     */
    public function write(): bool
    {
        $path = $this->path();
        $json = json_encode(
            ['version' => 1, 'users' => (object) $this->entries()],
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
     * Write the file when NMI is switched on for the installation
     */
    public function sync(): bool
    {
        return !config('nmi.features.enabled') || $this->write();
    }
}
