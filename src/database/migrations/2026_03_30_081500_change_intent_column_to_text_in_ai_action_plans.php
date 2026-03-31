<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_action_plans', function (Blueprint $table) {
            $table->text('intent')->change();
        });

        // Reset mailbox reputation data — previous DNSBL checks had false positives
        // due to public DNS resolver error responses being treated as "listed".
        // The scheduled task will re-check with corrected logic.
        \Illuminate\Support\Facades\DB::table('mailboxes')->update([
            'reputation_overall' => 'unchecked',
            'reputation_status' => null,
            'reputation_checked_at' => null,
        ]);
    }

    public function down(): void
    {
        Schema::table('ai_action_plans', function (Blueprint $table) {
            $table->string('intent')->change();
        });
    }
};
