<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('mailboxes', function (Blueprint $table) {
            // Reply monitoring via IMAP: the inbox that receives replies to this mailbox
            $table->boolean('reply_enabled')->default(false)->after('bounce_last_scan_count');
            $table->string('reply_imap_host')->nullable()->after('reply_enabled');
            $table->unsignedSmallInteger('reply_imap_port')->default(993)->after('reply_imap_host');
            $table->enum('reply_imap_encryption', ['ssl', 'tls', 'none'])->default('ssl')->after('reply_imap_port');
            $table->text('reply_imap_credentials')->nullable()->after('reply_imap_encryption');
            $table->string('reply_imap_folder', 100)->default('INBOX')->after('reply_imap_credentials');
            // Highest IMAP UID already read, valid only for the folder's UIDVALIDITY
            $table->unsignedBigInteger('reply_last_uid')->nullable()->after('reply_imap_folder');
            $table->unsignedBigInteger('reply_uid_validity')->nullable()->after('reply_last_uid');
            $table->timestamp('reply_last_scanned_at')->nullable()->after('reply_uid_validity');
            $table->unsignedInteger('reply_last_scan_count')->default(0)->after('reply_last_scanned_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mailboxes', function (Blueprint $table) {
            $table->dropColumn([
                'reply_enabled',
                'reply_imap_host',
                'reply_imap_port',
                'reply_imap_encryption',
                'reply_imap_credentials',
                'reply_imap_folder',
                'reply_last_uid',
                'reply_uid_validity',
                'reply_last_scanned_at',
                'reply_last_scan_count',
            ]);
        });
    }
};
