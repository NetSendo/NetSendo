<?php

namespace App\Services\Mail;

use App\Events\EmailBounced;
use App\Models\Mailbox;
use Illuminate\Support\Facades\Log;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Client;

/**
 * IMAP Bounce Mailbox Scanner
 *
 * Connects to IMAP mailboxes to scan for bounce-back emails (DSN - RFC 3464),
 * parses them, and processes the bounces via BounceProcessingService.
 *
 * Used by the `bounce:process-mailboxes` artisan command (CRON every 5 min).
 */
class BounceMailboxService
{
    /**
     * SMTP status code patterns for bounce classification
     */
    private const HARD_BOUNCE_PATTERNS = [
        '/\b5\.1\.[0-9]\b/',   // Mailbox/address errors
        '/\b5\.2\.1\b/',       // Mailbox disabled/not accepting
        '/\b5\.7\.[0-9]\b/',   // Security/policy rejection
        '/^550\b/m',
        '/^551\b/m',
        '/^553\b/m',
        '/^554\b/m',
        '/User unknown/i',
        '/Mailbox not found/i',
        '/Recipient.*rejected/i',
        '/does not exist/i',
        '/no such user/i',
        '/address rejected/i',
        '/undeliverable/i',
    ];

    private const SOFT_BOUNCE_PATTERNS = [
        '/\b4\.[0-9]\.[0-9]\b/',  // All 4.x.x status codes
        '/\b5\.2\.2\b/',          // Mailbox full (can be temp)
        '/^450\b/m',
        '/^451\b/m',
        '/^452\b/m',
        '/mailbox full/i',
        '/over quota/i',
        '/try again/i',
        '/temporarily/i',
        '/too many connections/i',
        '/rate limit/i',
    ];

    /**
     * Subjects that indicate a bounce-back email
     */
    private const BOUNCE_SUBJECT_PATTERNS = [
        '/Mail delivery failed/i',
        '/Undelivered Mail Returned/i',
        '/Delivery Status Notification/i',
        '/failure notice/i',
        '/Returned mail/i',
        '/Undeliverable/i',
        '/Mail System Error/i',
        '/Delivery Report/i',
    ];

    public function __construct(
        private BounceProcessingService $bounceProcessor
    ) {}

    /**
     * Scan a mailbox for bounce emails and process them.
     *
     * @return array{scanned: int, bounces_found: int, hard: int, soft: int, errors: int}
     */
    public function scanMailbox(Mailbox $mailbox): array
    {
        $stats = [
            'scanned' => 0,
            'bounces_found' => 0,
            'hard' => 0,
            'soft' => 0,
            'errors' => 0,
        ];

        $credentials = $mailbox->getDecryptedBounceCredentials();
        if (empty($credentials['username']) || empty($credentials['password'])) {
            Log::warning("Bounce mailbox {$mailbox->id}: missing IMAP credentials");
            return $stats;
        }

        try {
            $client = $this->createImapClient($mailbox, $credentials);
            $client->connect();

            $folder = $client->getFolder($mailbox->bounce_imap_folder ?? 'INBOX');
            if (!$folder) {
                Log::warning("Bounce mailbox {$mailbox->id}: folder '{$mailbox->bounce_imap_folder}' not found");
                $client->disconnect();
                return $stats;
            }

            // Get unseen messages (or messages since last scan)
            $query = $folder->messages()->unseen();

            // If we have a last scan timestamp, also limit by date
            if ($mailbox->bounce_last_scanned_at) {
                $query->since($mailbox->bounce_last_scanned_at->subMinutes(5));
            }

            $messages = $query->get();
            $stats['scanned'] = $messages->count();

            Log::info("Bounce mailbox {$mailbox->id}: scanning {$stats['scanned']} unseen messages");

            foreach ($messages as $message) {
                try {
                    $result = $this->processMessage($message, $mailbox);

                    if ($result) {
                        $stats['bounces_found']++;
                        if ($result['type'] === EmailBounced::TYPE_HARD) {
                            $stats['hard']++;
                        } else {
                            $stats['soft']++;
                        }

                        // Mark message as seen after processing
                        $message->setFlag('Seen');
                    }
                } catch (\Exception $e) {
                    $stats['errors']++;
                    Log::warning("Bounce mailbox {$mailbox->id}: error processing message", [
                        'error' => $e->getMessage(),
                        'subject' => $message->getSubject()?->toString() ?? 'unknown',
                    ]);
                }
            }

            $client->disconnect();

            // Update mailbox scan stats
            $mailbox->update([
                'bounce_last_scanned_at' => now(),
                'bounce_last_scan_count' => $stats['bounces_found'],
            ]);

            Log::info("Bounce mailbox {$mailbox->id}: scan complete", $stats);

        } catch (\Exception $e) {
            Log::error("Bounce mailbox {$mailbox->id}: IMAP connection failed", [
                'error' => $e->getMessage(),
                'host' => $mailbox->bounce_imap_host,
            ]);
            $stats['errors']++;
        }

        return $stats;
    }

    /**
     * Test IMAP connection with given credentials.
     *
     * @return array{success: bool, message: string, folder_count?: int}
     */
    public function testConnection(Mailbox $mailbox): array
    {
        $credentials = $mailbox->getDecryptedBounceCredentials();
        if (empty($credentials['username']) || empty($credentials['password'])) {
            return ['success' => false, 'message' => 'Missing IMAP credentials'];
        }

        try {
            $client = $this->createImapClient($mailbox, $credentials);
            $client->connect();

            $folders = $client->getFolders();
            $folderCount = count($folders);

            $client->disconnect();

            return [
                'success' => true,
                'message' => "Connected successfully. Found {$folderCount} folder(s).",
                'folder_count' => $folderCount,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Process a single email message — check if it's a bounce and extract details.
     *
     * @return array{email: string, type: string, reason: string}|null
     */
    private function processMessage($message, Mailbox $mailbox): ?array
    {
        $subject = $message->getSubject()?->toString() ?? '';
        $body = $message->getTextBody() ?? $message->getHTMLBody() ?? '';

        // Check if subject looks like a bounce
        $isBounce = false;
        foreach (self::BOUNCE_SUBJECT_PATTERNS as $pattern) {
            if (preg_match($pattern, $subject)) {
                $isBounce = true;
                break;
            }
        }

        // Also check Content-Type for multipart/report (DSN standard)
        $contentType = '';
        try {
            $contentType = $message->getHeader()?->get('content_type')?->toString() ?? '';
        } catch (\Exception $e) {
            // Some messages may not have parseable headers
        }
        if (str_contains($contentType, 'multipart/report') || str_contains($contentType, 'delivery-status')) {
            $isBounce = true;
        }

        if (!$isBounce) {
            return null;
        }

        // Parse DSN from message body
        $parsed = $this->parseDsn($body);
        if (!$parsed) {
            // Try to parse from raw message if structured DSN not found
            try {
                $rawBody = $message->getRawBody() ?? '';
                $parsed = $this->parseDsn($rawBody);
            } catch (\Exception $e) {
                // Ignore raw body parse errors
            }
        }

        if (!$parsed || empty($parsed['email'])) {
            Log::debug("Bounce mailbox {$mailbox->id}: bounce email detected but couldn't parse recipient", [
                'subject' => $subject,
            ]);
            return null;
        }

        // Classify bounce type
        $bounceType = $this->classifyBounce(
            $parsed['status'] ?? '',
            $parsed['diagnostic'] ?? $body
        );

        // Process the bounce via shared service
        $this->bounceProcessor->processBounce(
            email: $parsed['email'],
            bounceType: $bounceType,
            bounceReason: mb_substr($parsed['diagnostic'] ?? $subject, 0, 255),
            messageId: null,
            provider: 'imap_bounce',
            // Bounces in this mailbox come from mail its owner sent
            userId: $mailbox->user_id
        );

        return [
            'email' => $parsed['email'],
            'type' => $bounceType,
            'reason' => $parsed['diagnostic'] ?? $subject,
        ];
    }

    /**
     * Parse a DSN (Delivery Status Notification) from email body text.
     * Follows RFC 3464 format.
     *
     * @return array{email: string, status: string, diagnostic: string}|null
     */
    public function parseDsn(string $body): ?array
    {
        $email = null;
        $status = null;
        $diagnostic = null;

        // 1. Try to extract recipient email
        // Pattern: Final-Recipient: rfc822;user@example.com
        if (preg_match('/Final-Recipient:\s*(?:rfc822|RFC822);\s*<?([^\s>]+@[^\s>]+)>?/i', $body, $m)) {
            $email = strtolower(trim($m[1]));
        }
        // Pattern: Original-Recipient: rfc822;user@example.com
        elseif (preg_match('/Original-Recipient:\s*(?:rfc822|RFC822);\s*<?([^\s>]+@[^\s>]+)>?/i', $body, $m)) {
            $email = strtolower(trim($m[1]));
        }
        // Pattern: <user@example.com>: Recipient address rejected
        elseif (preg_match('/<([^\s>]+@[^\s>]+)>:\s*(.*?)(?:\n|$)/i', $body, $m)) {
            $email = strtolower(trim($m[1]));
            if (!$diagnostic) {
                $diagnostic = trim($m[2]);
            }
        }
        // Pattern: 550 5.1.1 user@example.com
        elseif (preg_match('/\b5\d{2}\s+\d\.\d\.\d\s+<?([^\s>]+@[^\s>]+)>?/i', $body, $m)) {
            $email = strtolower(trim($m[1]));
        }
        // Pattern: RCPT TO:<user@example.com>
        elseif (preg_match('/RCPT TO:\s*<([^\s>]+@[^\s>]+)>/i', $body, $m)) {
            $email = strtolower(trim($m[1]));
        }

        if (!$email) {
            return null;
        }

        // 2. Extract status code
        // Pattern: Status: 5.1.1
        if (preg_match('/Status:\s*(\d\.\d\.\d)/i', $body, $m)) {
            $status = $m[1];
        }
        // Pattern: smtp; 550 5.1.1
        elseif (preg_match('/(?:smtp|SMTP);\s*(\d{3})\s+(\d\.\d\.\d)/i', $body, $m)) {
            $status = $m[2];
        }

        // 3. Extract diagnostic code
        // Pattern: Diagnostic-Code: smtp; 550 5.1.1 User unknown
        if (preg_match('/Diagnostic-Code:\s*(?:smtp|SMTP);\s*(.*?)(?:\n\S|\n\n|$)/is', $body, $m)) {
            $diagnostic = trim(preg_replace('/\s+/', ' ', $m[1]));
        }
        // Pattern: "550 5.1.1 <user@example.com>: Recipient address rejected: User unknown"
        elseif (!$diagnostic && preg_match('/(5\d{2}\s+\d\.\d\.\d\s+.*?)(?:\n|$)/i', $body, $m)) {
            $diagnostic = trim($m[1]);
        }

        return [
            'email' => $email,
            'status' => $status,
            'diagnostic' => $diagnostic ? mb_substr($diagnostic, 0, 500) : null,
        ];
    }

    /**
     * Classify a bounce as hard or soft based on status code and diagnostic.
     */
    public function classifyBounce(string $statusCode, string $diagnosticText): string
    {
        // First check status code (most reliable)
        if (!empty($statusCode)) {
            $major = (int) substr($statusCode, 0, 1);
            if ($major === 5) {
                // 5.2.2 = mailbox full — could be transient, treat as soft
                if ($statusCode === '5.2.2') {
                    return EmailBounced::TYPE_SOFT;
                }
                return EmailBounced::TYPE_HARD;
            }
            if ($major === 4) {
                return EmailBounced::TYPE_SOFT;
            }
        }

        // Fall back to diagnostic text pattern matching
        foreach (self::HARD_BOUNCE_PATTERNS as $pattern) {
            if (preg_match($pattern, $diagnosticText)) {
                return EmailBounced::TYPE_HARD;
            }
        }

        foreach (self::SOFT_BOUNCE_PATTERNS as $pattern) {
            if (preg_match($pattern, $diagnosticText)) {
                return EmailBounced::TYPE_SOFT;
            }
        }

        // Default to hard bounce for safety (prevents re-sending to bad addresses)
        return EmailBounced::TYPE_HARD;
    }

    /**
     * Create an IMAP client for a bounce mailbox.
     */
    private function createImapClient(Mailbox $mailbox, array $credentials): Client
    {
        $cm = new ClientManager();

        return $cm->make([
            'host' => $mailbox->bounce_imap_host,
            'port' => $mailbox->bounce_imap_port ?? 993,
            'encryption' => $mailbox->bounce_imap_encryption ?? 'ssl',
            'validate_cert' => true,
            'username' => $credentials['username'],
            'password' => $credentials['password'],
            'protocol' => 'imap',
            'authentication' => null,
        ]);
    }
}
