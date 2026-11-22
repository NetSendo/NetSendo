<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The mailbox a list's campaigns go out from when its default mailbox
     * cannot send campaigns. A Gmail mailbox never can, yet it is a fine
     * default for a list's autoresponders and system emails; the list then
     * names a second mailbox (an SMTP mailbox on the owner's domain) for its
     * broadcasts instead of the campaign being refused or rerouted to a
     * mailbox nobody chose.
     */
    public function up(): void
    {
        Schema::table('contact_lists', function (Blueprint $table) {
            if (! Schema::hasColumn('contact_lists', 'broadcast_mailbox_id')) {
                $table->foreignId('broadcast_mailbox_id')
                    ->nullable()
                    ->after('default_mailbox_id')
                    ->constrained('mailboxes')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('contact_lists', function (Blueprint $table) {
            if (Schema::hasColumn('contact_lists', 'broadcast_mailbox_id')) {
                $table->dropConstrainedForeignId('broadcast_mailbox_id');
            }
        });
    }
};
