<?php

namespace App\Console\Commands;

use App\Models\Mailbox;
use App\Services\Mail\ReplyMailboxService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessReplyMailboxCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'replies:process-mailboxes
                            {--mailbox= : Process specific mailbox ID only}
                            {--dry-run : Show what would be processed without making changes}';

    /**
     * The console command description.
     */
    protected $description = 'Scan IMAP reply inboxes for replies from subscribers and record them in the CRM';

    /**
     * Execute the console command.
     */
    public function handle(ReplyMailboxService $replyService): int
    {
        $this->info('📬 Starting reply inbox scan...');

        $isDryRun = $this->option('dry-run');
        $specificMailbox = $this->option('mailbox');

        if ($isDryRun) {
            $this->warn('⚠️  Dry-run mode — no replies will be recorded');
        }

        $query = Mailbox::replyEnabled()->active();

        if ($specificMailbox) {
            $query->where('id', $specificMailbox);
        }

        $mailboxes = $query->get();

        if ($mailboxes->isEmpty()) {
            $this->info('📭 No reply-monitoring mailboxes found.');
            return self::SUCCESS;
        }

        $this->info("📋 Found {$mailboxes->count()} reply-monitoring mailbox(es)");

        $totalStats = [
            'mailboxes_processed' => 0,
            'total_scanned' => 0,
            'total_replies' => 0,
            'total_errors' => 0,
        ];

        foreach ($mailboxes as $mailbox) {
            $this->line('');
            $this->info("🔍 Scanning: {$mailbox->name} ({$mailbox->reply_imap_host})");

            if ($isDryRun) {
                $this->line("   Would scan folder: {$mailbox->reply_imap_folder}");
                $this->line("   Last scanned: " . ($mailbox->reply_last_scanned_at?->diffForHumans() ?? 'never'));
                continue;
            }

            try {
                $stats = $replyService->scanMailbox($mailbox);

                $this->line("   📧 Scanned: {$stats['scanned']} messages");
                $this->line("   ↩️  Replies recorded: {$stats['replies_found']}");

                if ($stats['errors'] > 0) {
                    $this->warn("   ⚠️  Errors: {$stats['errors']}");
                }

                $totalStats['mailboxes_processed']++;
                $totalStats['total_scanned'] += $stats['scanned'];
                $totalStats['total_replies'] += $stats['replies_found'];
                $totalStats['total_errors'] += $stats['errors'];

            } catch (\Exception $e) {
                $this->error("   ❌ Failed: {$e->getMessage()}");
                Log::error("Reply inbox scan failed for mailbox {$mailbox->id}", [
                    'error' => $e->getMessage(),
                ]);
                $totalStats['total_errors']++;
            }
        }

        $this->line('');
        $this->info('✅ Reply scan complete:');
        $this->line("   📬 Mailboxes processed: {$totalStats['mailboxes_processed']}");
        $this->line("   📧 Total scanned: {$totalStats['total_scanned']}");
        $this->line("   ↩️  Total replies: {$totalStats['total_replies']}");

        if ($totalStats['total_errors'] > 0) {
            $this->error("   ❌ Errors: {$totalStats['total_errors']}");
        }

        return self::SUCCESS;
    }
}
