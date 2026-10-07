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
        Schema::table('messages', function (Blueprint $table) {
            // text/plain part; empty = generated from the HTML at send time
            if (!Schema::hasColumn('messages', 'plain_text')) {
                $table->longText('plain_text')->nullable()->after('content');
            }
            // NetSendo click/open tracking; null = the mailbox decides
            if (!Schema::hasColumn('messages', 'tracking_enabled')) {
                $table->boolean('tracking_enabled')->nullable()->after('custom_headers');
            }
        });

        Schema::table('mailboxes', function (Blueprint $table) {
            if (!Schema::hasColumn('mailboxes', 'tracking_enabled')) {
                $table->boolean('tracking_enabled')->default(true)->after('custom_headers');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['plain_text', 'tracking_enabled']);
        });

        Schema::table('mailboxes', function (Blueprint $table) {
            $table->dropColumn('tracking_enabled');
        });
    }
};
