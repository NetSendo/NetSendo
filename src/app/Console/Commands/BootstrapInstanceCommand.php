<?php

namespace App\Console\Commands;

use App\Http\Middleware\DormantMode;
use App\Models\User;
use App\Services\LicenseVerificationService;
use App\Services\PasswordRelayService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Sets up a new installation without the first-run screen, for hosted installs (NetSendo
 * Cloud): the administrator account, its password by email, and the licence. Until now the
 * first visitor of a fresh installation registered the administrator; run this right after
 * installing, before the address is public. Safe to run again with the same email.
 */
class BootstrapInstanceCommand extends Command
{
    protected $signature = 'netsendo:bootstrap
        {--admin-email= : Email of the administrator account (required)}
        {--admin-name= : Name shown in the panel (default: the part of the email before @)}
        {--locale= : Panel language of the administrator: en, pl, de or es}
        {--license= : Licence key to activate}
        {--domain= : Domain the licence is bound to (default: the host of APP_URL)}
        {--no-password-mail : Do not email the administrator a password}';

    protected $description = 'Set up a new installation: administrator account, password by email, licence';

    public function handle(PasswordRelayService $relay, LicenseVerificationService $licences): int
    {
        $email = Str::lower(trim((string) $this->option('admin-email')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('--admin-email is required and must be a valid email address.');

            return self::INVALID;
        }

        $locale = $this->option('locale');
        if ($locale !== null && ! in_array($locale, DormantMode::LOCALES, true)) {
            $this->error('--locale must be one of: ' . implode(', ', DormantMode::LOCALES) . '.');

            return self::INVALID;
        }

        $admin = User::where('email', $email)->first();
        $created = false;

        if (! $admin) {
            if (User::exists()) {
                $this->error('This installation already has users. netsendo:bootstrap only sets up a new one.');

                return self::FAILURE;
            }

            // A random password nobody sees; the real one comes by email below
            $admin = User::create([
                'name' => $this->option('admin-name') ?: Str::before($email, '@'),
                'email' => $email,
                'password' => Hash::make(Str::password(40)),
                'locale' => $locale ?? 'en',
            ]);
            $admin->forceFill(['email_verified_at' => now()])->save();

            try {
                $admin->assignRole('Admin');
            } catch (\Throwable $e) {
                // Same as the first-run registration: the role may not exist yet
            }

            $created = true;
            $this->info("Administrator account created: {$email}");
        } else {
            $this->info("Administrator account already exists: {$email}");
        }

        $ok = true;

        $key = trim((string) $this->option('license'));
        if ($key !== '') {
            $domain = $this->option('domain') ?: parse_url((string) config('app.url'), PHP_URL_HOST);
            $result = $licences->verifyLicense($key, (string) $domain);

            if (! ($result['valid'] ?? false)) {
                $this->error('Licence not activated: ' . ($result['message'] ?? 'the licence server did not confirm the key.'));
                $ok = false;
            } else {
                $plan = strtoupper($result['plan'] ?? (string) base64_decode(Str::before($key, '-')));
                $licences->storeLicense($key, $plan, $result['expires_at'] ?? null, (string) $domain);
                $this->info("Licence {$plan} active for {$domain}");
            }
        }

        // Only for a new account: on a later run the administrator uses "Forgot password"
        if ($created && ! $this->option('no-password-mail')) {
            if ($relay->sendNewPassword($admin)) {
                $this->info("Password emailed to {$email}");
            } else {
                $this->error('The password email could not be sent. The administrator can use "Forgot password" on the sign-in page.');
                $ok = false;
            }
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
