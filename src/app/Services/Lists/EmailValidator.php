<?php

namespace App\Services\Lists;

use Illuminate\Support\Facades\Cache;

/**
 * Address-quality checks used by list import and list hygiene.
 *
 * Every check is deliberately local (syntax, known-domain lists) except
 * hasMx(), which does a DNS lookup and is therefore cached and always
 * called on a bounded number of distinct domains — never once per row.
 */
class EmailValidator
{
    /**
     * Local parts that almost always belong to a shared inbox rather than a
     * person. They inflate list size, rarely engage and raise complaint rates.
     */
    public const ROLE_PREFIXES = [
        'admin', 'administrator', 'abuse', 'billing', 'biuro', 'contact', 'kontakt',
        'help', 'hello', 'hr', 'info', 'kariera', 'mail', 'marketing', 'no-reply',
        'noreply', 'office', 'postmaster', 'recepcja', 'reklamacje', 'root', 'sales',
        'sekretariat', 'service', 'serwis', 'support', 'sysadmin', 'team', 'webmaster',
        'zamowienia',
    ];

    /**
     * Throwaway-mailbox providers. Addresses here are valid but worthless —
     * they expire, then start hard-bouncing.
     */
    public const DISPOSABLE_DOMAINS = [
        '0-mail.com', '10minutemail.com', '20minutemail.com', 'anonbox.net',
        'burnermail.io', 'dispostable.com', 'drop.pl', 'e4ward.com', 'emailondeck.com',
        'fakeinbox.com', 'getairmail.com', 'getnada.com', 'guerrillamail.com',
        'inboxbear.com', 'jetable.org', 'mailcatch.com', 'maildrop.cc', 'mailinator.com',
        'mailnesia.com', 'mintemail.com', 'moakt.com', 'mohmal.com', 'mytemp.email',
        'nada.email', 'onetimemail.org', 'sharklasers.com', 'spam4.me', 'spamgourmet.com',
        'tempmail.net', 'tempmailo.com', 'temp-mail.org', 'throwawaymail.com',
        'trashmail.com', 'wegwerfemail.de', 'yopmail.com',
    ];

    /**
     * Frequent misspellings of large providers → the intended domain. A typo
     * domain is a guaranteed hard bounce, so it is worth catching on import.
     */
    public const TYPO_DOMAINS = [
        'gmial.com' => 'gmail.com',
        'gmai.com' => 'gmail.com',
        'gmail.co' => 'gmail.com',
        'gmail.con' => 'gmail.com',
        'gmail.cm' => 'gmail.com',
        'gmail.pl' => 'gmail.com',
        'gnail.com' => 'gmail.com',
        'gmaill.com' => 'gmail.com',
        'hotmial.com' => 'hotmail.com',
        'hotmai.com' => 'hotmail.com',
        'hotmail.con' => 'hotmail.com',
        'outlok.com' => 'outlook.com',
        'outlook.con' => 'outlook.com',
        'yaho.com' => 'yahoo.com',
        'yahoo.con' => 'yahoo.com',
        'wp.p' => 'wp.pl',
        'wp.com' => 'wp.pl',
        'onet.p' => 'onet.pl',
        'onet.com' => 'onet.pl',
        'interia.p' => 'interia.pl',
        'o2.p' => 'o2.pl',
        'gazeta.p' => 'gazeta.pl',
    ];

    /**
     * Providers that ignore dots and treat "+tag" as an alias, so
     * j.kowalski+news@gmail.com and jkowalski@gmail.com are one mailbox.
     */
    private const ALIAS_FOLDING_DOMAINS = ['gmail.com', 'googlemail.com'];

    /**
     * Lowercase + trim. This is the form stored and compared against, and it
     * is NOT the same as canonical() — two different addresses may normalise
     * differently yet share a canonical form.
     */
    public function normalize(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $email = trim($email);

        return $email === '' ? null : mb_strtolower($email);
    }

    /**
     * The mailbox an address actually delivers to, used for duplicate
     * detection: Gmail dot/plus aliases collapse onto one canonical value.
     */
    public function canonical(?string $email): ?string
    {
        $email = $this->normalize($email);

        if ($email === null || !str_contains($email, '@')) {
            return $email;
        }

        [$local, $domain] = explode('@', $email, 2);

        if (in_array($domain, self::ALIAS_FOLDING_DOMAINS, true)) {
            $local = explode('+', $local, 2)[0];
            $local = str_replace('.', '', $local);
            $domain = 'gmail.com';
        } else {
            $local = explode('+', $local, 2)[0];
        }

        return $local === '' ? $email : $local . '@' . $domain;
    }

    public function syntaxValid(?string $email): bool
    {
        return $email !== null && filter_var(trim($email), FILTER_VALIDATE_EMAIL) !== false;
    }

    public function domain(?string $email): ?string
    {
        $email = $this->normalize($email);

        if ($email === null || !str_contains($email, '@')) {
            return null;
        }

        return explode('@', $email, 2)[1];
    }

    public function localPart(?string $email): ?string
    {
        $email = $this->normalize($email);

        if ($email === null || !str_contains($email, '@')) {
            return null;
        }

        return explode('@', $email, 2)[0];
    }

    public function isRole(?string $email): bool
    {
        $local = $this->localPart($email);

        if ($local === null) {
            return false;
        }

        // Strip "+tag" and any trailing separator/number ("info-2", "biuro.1")
        $local = explode('+', $local, 2)[0];
        $base = preg_replace('/[._-]?\d+$/', '', $local);

        return in_array($base, self::ROLE_PREFIXES, true);
    }

    public function isDisposable(?string $email): bool
    {
        $domain = $this->domain($email);

        return $domain !== null && in_array($domain, self::DISPOSABLE_DOMAINS, true);
    }

    /**
     * Suggested correction for a known misspelled domain, or null.
     */
    public function typoSuggestion(?string $email): ?string
    {
        $domain = $this->domain($email);

        if ($domain === null || !isset(self::TYPO_DOMAINS[$domain])) {
            return null;
        }

        return $this->localPart($email) . '@' . self::TYPO_DOMAINS[$domain];
    }

    /**
     * Whether the domain can receive mail at all (MX, or an A record as the
     * RFC 5321 fallback). Cached for a day per domain — the caller is
     * responsible for keeping the number of distinct domains bounded.
     */
    public function hasMx(?string $domain): bool
    {
        if ($domain === null || $domain === '') {
            return false;
        }

        return Cache::remember(
            'list_hygiene:mx:' . $domain,
            now()->addDay(),
            fn () => checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A')
        );
    }

    /**
     * Full local (DNS-free) verdict for one address.
     *
     * @return array{email: ?string, valid: bool, issues: string[], suggestion: ?string}
     */
    public function inspect(?string $email): array
    {
        $normalized = $this->normalize($email);
        $issues = [];

        if ($normalized === null) {
            return ['email' => null, 'valid' => false, 'issues' => ['missing'], 'suggestion' => null];
        }

        if (!$this->syntaxValid($normalized)) {
            $issues[] = 'invalid_syntax';
        }

        if ($this->isRole($normalized)) {
            $issues[] = 'role_address';
        }

        if ($this->isDisposable($normalized)) {
            $issues[] = 'disposable_domain';
        }

        $suggestion = $this->typoSuggestion($normalized);
        if ($suggestion !== null) {
            $issues[] = 'typo_domain';
        }

        return [
            'email' => $normalized,
            'valid' => !in_array('invalid_syntax', $issues, true),
            'issues' => $issues,
            'suggestion' => $suggestion,
        ];
    }
}
