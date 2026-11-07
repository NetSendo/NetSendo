<?php

namespace App\Services\Mail;

use App\Models\Mailbox;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Authenticates the bounce webhooks of a mailbox
 * (/webhooks/bounce/{provider}/{mailbox}) with each provider's own mechanism:
 *
 * - SendGrid: Signed Event Webhook — ECDSA (P-256, SHA-256) signature of
 *   timestamp + raw body, verified with the account's public verification key.
 * - Mailgun: HMAC-SHA256 of timestamp + token with the webhook signing key.
 * - Postmark and generic: the mailbox's NetSendo token, sent as HTTP Basic auth
 *   password, X-NetSendo-Webhook-Token header, Bearer token or ?token= query.
 *
 * Secrets live encrypted in mailboxes.bounce_webhook_credentials.
 */
class BounceWebhookAuthenticator
{
    public const TOKEN_HEADER = 'X-NetSendo-Webhook-Token';
    public const SENDGRID_SIGNATURE_HEADER = 'X-Twilio-Email-Event-Webhook-Signature';
    public const SENDGRID_TIMESTAMP_HEADER = 'X-Twilio-Email-Event-Webhook-Timestamp';

    /**
     * @return string|null null when the request is authentic, otherwise the
     *                     reason it is not (for the log)
     */
    public function authenticate(string $provider, Mailbox $mailbox, Request $request): ?string
    {
        $credentials = $mailbox->getDecryptedBounceWebhookCredentials();

        return match ($provider) {
            'sendgrid' => $this->verifySendGrid($mailbox, $credentials['sendgrid_verification_key'] ?? null, $request),
            'mailgun' => $this->verifyMailgun($mailbox, $credentials['mailgun_signing_key'] ?? null, $request),
            'postmark', 'generic' => $this->verifyToken($credentials['token'] ?? null, $request),
            default => 'unknown_provider',
        };
    }

    public static function generateToken(): string
    {
        return Str::random(48);
    }

    /**
     * SendGrid shows the verification key as base64 DER without PEM armour;
     * accept it either way, with or without line breaks.
     */
    public static function sendGridKeyToPem(string $key): string
    {
        $body = preg_replace('/-----(BEGIN|END) PUBLIC KEY-----|\s+/', '', $key);

        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split($body, 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    /**
     * Normalised form stored for a SendGrid key (base64 body only), or null
     * when the text is not an EC public key.
     */
    public static function normaliseSendGridKey(string $key): ?string
    {
        $pem = self::sendGridKeyToPem($key);
        $publicKey = openssl_pkey_get_public($pem);

        if ($publicKey === false) {
            return null;
        }

        $details = openssl_pkey_get_details($publicKey);
        if (($details['type'] ?? null) !== OPENSSL_KEYTYPE_EC) {
            return null;
        }

        return preg_replace('/-----(BEGIN|END) PUBLIC KEY-----|\s+/', '', $key);
    }

    private function verifySendGrid(Mailbox $mailbox, ?string $key, Request $request): ?string
    {
        if (blank($key)) {
            return 'not_configured';
        }

        $signature = $request->header(self::SENDGRID_SIGNATURE_HEADER);
        $timestamp = $request->header(self::SENDGRID_TIMESTAMP_HEADER);

        if (!is_string($signature) || $signature === '' || !is_string($timestamp) || $timestamp === '') {
            return 'missing_signature';
        }

        if (!$this->timestampIsFresh($timestamp)) {
            return 'stale_timestamp';
        }

        $publicKey = openssl_pkey_get_public(self::sendGridKeyToPem($key));
        $decoded = base64_decode($signature, true);

        if ($publicKey === false || $decoded === false
            || openssl_verify($timestamp . $request->getContent(), $decoded, $publicKey, OPENSSL_ALGO_SHA256) !== 1) {
            while (openssl_error_string() !== false) {
                // drain OpenSSL's error queue so it does not leak into later calls
            }

            return 'invalid_signature';
        }

        return $this->guardReplay('sendgrid', $mailbox, $signature);
    }

    private function verifyMailgun(Mailbox $mailbox, ?string $key, Request $request): ?string
    {
        if (blank($key)) {
            return 'not_configured';
        }

        $signature = $request->input('signature');
        if (!is_array($signature)) {
            return 'missing_signature';
        }

        $timestamp = $signature['timestamp'] ?? null;
        $token = $signature['token'] ?? null;
        $hmac = $signature['signature'] ?? null;

        foreach ([$timestamp, $token, $hmac] as $part) {
            if (!is_scalar($part) || (string) $part === '') {
                return 'missing_signature';
            }
        }

        $timestamp = (string) $timestamp;
        $token = (string) $token;

        if (!$this->timestampIsFresh($timestamp)) {
            return 'stale_timestamp';
        }

        $expected = hash_hmac('sha256', $timestamp . $token, $key);
        if (!hash_equals($expected, strtolower((string) $hmac))) {
            return 'invalid_signature';
        }

        // Mailgun signs timestamp + token only, not the event: a signature must
        // not be usable twice with another body.
        return $this->guardReplay('mailgun', $mailbox, $token);
    }

    private function verifyToken(?string $expected, Request $request): ?string
    {
        if (blank($expected)) {
            return 'not_configured';
        }

        $candidates = [
            $request->header(self::TOKEN_HEADER),
            $request->bearerToken(),
            $request->getPassword(),
            $request->query('token'),
        ];

        $given = null;
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                $given = $candidate;
                break;
            }
        }

        if ($given === null) {
            return 'missing_token';
        }

        return hash_equals($expected, $given) ? null : 'invalid_token';
    }

    private function timestampIsFresh(string $timestamp): bool
    {
        if (!ctype_digit($timestamp)) {
            return false;
        }

        return abs(now()->getTimestamp() - (int) $timestamp) <= $this->tolerance();
    }

    private function guardReplay(string $provider, Mailbox $mailbox, string $nonce): ?string
    {
        $key = "bounce-webhook:seen:{$provider}:{$mailbox->id}:" . hash('sha256', $nonce);

        // Kept a little longer than the timestamp window it protects.
        return Cache::add($key, true, $this->tolerance() * 2 + 60) ? null : 'replayed';
    }

    private function tolerance(): int
    {
        return max(1, (int) config('netsendo.bounce_webhooks.timestamp_tolerance', 300));
    }
}
