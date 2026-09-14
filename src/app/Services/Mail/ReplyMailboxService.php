<?php

namespace App\Services\Mail;

use App\Models\Mailbox;
use Illuminate\Support\Facades\Log;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Connection\Protocols\ProtocolInterface;
use Webklex\PHPIMAP\IMAP;

/**
 * Reads new messages from a mailbox's reply inbox over IMAP and records the
 * replies from subscribers.
 *
 * The reply inbox is the user's own mailbox, so the folder is opened read-only
 * (EXAMINE) and messages are fetched with BODY.PEEK: nothing is marked as read,
 * moved or deleted. The highest UID read is stored, so each scan reads only
 * messages that arrived since the previous one.
 */
class ReplyMailboxService
{
    /**
     * Headers fetched per IMAP request.
     */
    private const FETCH_CHUNK = 50;

    /**
     * How far back the first scan of a folder looks.
     */
    private const FIRST_SCAN_DAYS = 1;

    public function __construct(
        private ReplyProcessingService $replyProcessor
    ) {}

    /**
     * Scan a mailbox's reply inbox for new replies and record them.
     *
     * @return array{scanned: int, replies_found: int, errors: int}
     */
    public function scanMailbox(Mailbox $mailbox): array
    {
        $stats = [
            'scanned' => 0,
            'replies_found' => 0,
            'errors' => 0,
        ];

        $credentials = $mailbox->getDecryptedReplyCredentials();
        if (empty($credentials['username']) || empty($credentials['password'])) {
            Log::warning("Reply mailbox {$mailbox->id}: missing IMAP credentials");
            return $stats;
        }

        try {
            $client = $this->createImapClient($mailbox, $credentials);
            $client->connect();

            $folder = $client->getFolder($mailbox->reply_imap_folder ?? 'INBOX');
            if (!$folder) {
                Log::warning("Reply mailbox {$mailbox->id}: folder '{$mailbox->reply_imap_folder}' not found");
                $client->disconnect();
                return $stats;
            }

            $connection = $client->getConnection();
            $status = $connection->examineFolder($folder->path)->validatedData();
            $uidValidity = (int) ($status['uidvalidity'] ?? 0);

            // UIDs from before the folder's UIDVALIDITY changed mean nothing any more
            $lastUid = $mailbox->reply_uid_validity === $uidValidity ? $mailbox->reply_last_uid : null;

            $uids = $this->newUids($connection, $lastUid);
            $stats['scanned'] = count($uids);

            Log::info("Reply mailbox {$mailbox->id}: scanning {$stats['scanned']} new messages");

            foreach (array_chunk($uids, self::FETCH_CHUNK) as $chunk) {
                $headers = $connection->fetch(['UID', 'BODY.PEEK[HEADER]'], $chunk, null, IMAP::ST_UID)->validatedData();

                foreach ($chunk as $uid) {
                    try {
                        if ($this->processMessage($connection, $mailbox, $uid, $headers[$uid]['BODY[HEADER]'] ?? null)) {
                            $stats['replies_found']++;
                        }
                    } catch (\Exception $e) {
                        $stats['errors']++;
                        Log::warning("Reply mailbox {$mailbox->id}: error processing message", [
                            'uid' => $uid,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            $client->disconnect();

            // Everything below UIDNEXT existed when the folder was opened and has been read now
            $readUpTo = max([$lastUid ?? 0, (int) ($status['uidnext'] ?? 1) - 1, ...$uids]);

            $mailbox->update([
                'reply_last_uid' => $readUpTo > 0 ? $readUpTo : null,
                'reply_uid_validity' => $uidValidity,
                'reply_last_scanned_at' => now(),
                'reply_last_scan_count' => $stats['replies_found'],
            ]);

            Log::info("Reply mailbox {$mailbox->id}: scan complete", $stats);

        } catch (\Exception $e) {
            Log::error("Reply mailbox {$mailbox->id}: IMAP scan failed", [
                'error' => $e->getMessage(),
                'host' => $mailbox->reply_imap_host,
            ]);
            $stats['errors']++;
        }

        return $stats;
    }

    /**
     * Test IMAP connection with the mailbox's reply inbox settings.
     *
     * @return array{success: bool, message: string, folder_count?: int}
     */
    public function testConnection(Mailbox $mailbox): array
    {
        $credentials = $mailbox->getDecryptedReplyCredentials();
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
     * UIDs of the messages that arrived after $lastUid, oldest first. Without a
     * last UID, the messages of the last FIRST_SCAN_DAYS days.
     *
     * @return list<int>
     */
    private function newUids(ProtocolInterface $connection, ?int $lastUid): array
    {
        $criteria = $lastUid === null
            ? ['SINCE', now()->subDays(self::FIRST_SCAN_DAYS)->format('d-M-Y')]
            : ['UID', ($lastUid + 1) . ':*'];

        $result = $connection->search($criteria, IMAP::ST_UID)->validatedData();

        return collect(is_array($result) ? $result : [])
            ->filter(fn ($uid) => is_numeric($uid))
            ->map(fn ($uid) => (int) $uid)
            // "n:*" always matches the newest message, even when its UID is below n
            ->filter(fn (int $uid) => $lastUid === null || $uid > $lastUid)
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Fetch the full message only when its headers show a reply from a subscriber.
     */
    private function processMessage(ProtocolInterface $connection, Mailbox $mailbox, int $uid, ?string $rawHeaders): bool
    {
        if ($rawHeaders === null
            || !$this->replyProcessor->findReplyingSubscriber($mailbox, InboundEmail::fromRaw($rawHeaders))) {
            return false;
        }

        $message = $connection->fetch(['UID', 'BODY.PEEK[]'], [$uid], null, IMAP::ST_UID)->validatedData();
        $raw = $message[$uid]['BODY[]'] ?? null;

        return $raw !== null && $this->replyProcessor->process($mailbox, InboundEmail::fromRaw($raw)) !== null;
    }

    /**
     * Create an IMAP client for a reply inbox.
     */
    protected function createImapClient(Mailbox $mailbox, array $credentials): Client
    {
        $cm = new ClientManager();

        return $cm->make([
            'host' => $mailbox->reply_imap_host,
            'port' => $mailbox->reply_imap_port ?? 993,
            'encryption' => $mailbox->reply_imap_encryption ?? 'ssl',
            'validate_cert' => true,
            'username' => $credentials['username'],
            'password' => $credentials['password'],
            'protocol' => 'imap',
            'authentication' => null,
        ]);
    }
}
