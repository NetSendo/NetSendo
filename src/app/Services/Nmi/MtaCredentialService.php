<?php

namespace App\Services\Nmi;

use App\Models\DomainConfiguration;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * SMTP logins of the NetSendo MTA (Haraka).
 *
 * Every sending domain gets its own login. The password stays encrypted in
 * domain_configurations; the MTA authenticates against a JSON file on a volume shared
 * with its container (docker/haraka/plugins/netsendo_auth.js):
 *
 *   { "version": 1, "users": { "<username>": "<sha256 of the password, hex>" } }
 *
 * The file is rebuilt from the database, so it can be thrown away and written again at
 * any time (nmi:sync-mta-auth).
 */
class MtaCredentialService
{
    private const SYNC_LOCK = 'nmi:mta-auth-sync';

    /**
     * Make sure the domain can log in to the MTA: issue a login when it has none and make
     * sure the MTA's login file knows it. Returns true when a login was issued now.
     *
     * @throws RuntimeException when the login file cannot be written
     */
    public function ensureFor(DomainConfiguration $domain): bool
    {
        if ($domain->nmi_enabled && $domain->hasNmiCredentials()) {
            if (! $this->fileKnows($domain->nmi_smtp_username)) {
                $this->sync();
            }

            return false;
        }

        $domain->forceFill([
            'nmi_enabled' => true,
            'nmi_enabled_at' => $domain->nmi_enabled_at ?? now(),
            'nmi_smtp_username' => $domain->nmi_smtp_username ?: $this->newUsername($domain),
            'nmi_smtp_password' => Str::random(48),
            'nmi_credentials_rotated_at' => now(),
        ])->save();

        $this->sync();

        Log::info('NMI login issued for domain', ['domain_id' => $domain->id, 'domain' => $domain->domain]);

        return true;
    }

    /**
     * Replace the domain's password. The old one stops working as soon as the MTA reads
     * the new file (its next login).
     *
     * @throws RuntimeException when the login file cannot be written
     */
    public function rotate(DomainConfiguration $domain): void
    {
        $domain->forceFill([
            'nmi_enabled' => true,
            'nmi_enabled_at' => $domain->nmi_enabled_at ?? now(),
            'nmi_smtp_username' => $domain->nmi_smtp_username ?: $this->newUsername($domain),
            'nmi_smtp_password' => Str::random(48),
            'nmi_credentials_rotated_at' => now(),
        ])->save();

        $this->sync();
    }

    /**
     * Take the domain's login away from the MTA.
     *
     * @throws RuntimeException when the login file cannot be written
     */
    public function revoke(DomainConfiguration $domain): void
    {
        $domain->forceFill([
            'nmi_enabled' => false,
            'nmi_smtp_password' => null,
        ])->save();

        $this->sync();
    }

    /**
     * Write the MTA's login file from the database. Returns the number of logins in it.
     * The file is only replaced when its content changes, and always in one step
     * (write a temporary file, rename), so the MTA never reads half a file.
     *
     * @throws RuntimeException when the file cannot be written
     */
    public function sync(): int
    {
        try {
            return Cache::lock(self::SYNC_LOCK, 15)->block(5, fn () => $this->write());
        } catch (LockTimeoutException) {
            // Another process holds the lock for too long: writing twice is harmless
            return $this->write();
        }
    }

    /**
     * Where the login file is written (NMI_MTA_AUTH_FILE)
     */
    public function authFile(): string
    {
        return (string) config('nmi.mta_auth_file', storage_path('app/nmi-mta/auth.json'));
    }

    /**
     * Usernames with a password hash, as they go into the file
     */
    public function logins(): array
    {
        $logins = [];

        DomainConfiguration::query()
            ->where('nmi_enabled', true)
            ->whereNotNull('nmi_smtp_username')
            ->whereNotNull('nmi_smtp_password')
            ->orderBy('id')
            ->get()
            ->each(function (DomainConfiguration $domain) use (&$logins) {
                $password = $domain->getDecryptedNmiPassword();

                if ($password !== null) {
                    $logins[$domain->nmi_smtp_username] = hash('sha256', $password);
                }
            });

        ksort($logins);

        return $logins;
    }

    private function write(): int
    {
        $logins = $this->logins();
        $file = $this->authFile();
        $content = json_encode(['version' => 1, 'users' => (object) $logins], JSON_PRETTY_PRINT) . "\n";

        if (is_file($file) && @file_get_contents($file) === $content) {
            return count($logins);
        }

        $directory = dirname($file);
        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException("Cannot create the NetSendo MTA login directory {$directory}.");
        }

        $temporary = $file . '.' . bin2hex(random_bytes(6)) . '.tmp';

        // 0644: the MTA runs as another user in another container, and the file holds
        // hashes of long random passwords, not the passwords
        if (@file_put_contents($temporary, $content) === false
            || ! @chmod($temporary, 0644)
            || ! @rename($temporary, $file)) {
            @unlink($temporary);

            throw new RuntimeException("Cannot write the NetSendo MTA login file {$file}.");
        }

        return count($logins);
    }

    private function fileKnows(string $username): bool
    {
        $file = $this->authFile();

        if (! is_file($file)) {
            return false;
        }

        $data = json_decode((string) @file_get_contents($file), true);

        return is_array($data) && isset($data['users'][$username]);
    }

    /**
     * Letters, digits, dots and dashes only: the name travels in an SMTP AUTH command
     */
    private function newUsername(DomainConfiguration $domain): string
    {
        return 'nmi-' . $domain->id . '-' . Str::lower(Str::random(10));
    }
}
