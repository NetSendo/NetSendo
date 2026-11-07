<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Secrets that authenticate the bounce webhooks of a mailbox
     * (/webhooks/bounce/{provider}/{mailbox}): an encrypted JSON object with
     * the NetSendo token (Postmark, generic), the SendGrid verification key and
     * the Mailgun webhook signing key.
     */
    public function up(): void
    {
        Schema::table('mailboxes', function (Blueprint $table) {
            if (!Schema::hasColumn('mailboxes', 'bounce_webhook_credentials')) {
                $table->text('bounce_webhook_credentials')->nullable()->after('bounce_last_scan_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mailboxes', function (Blueprint $table) {
            if (Schema::hasColumn('mailboxes', 'bounce_webhook_credentials')) {
                $table->dropColumn('bounce_webhook_credentials');
            }
        });
    }
};
