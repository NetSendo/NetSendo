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
        Schema::table('contact_lists', function (Blueprint $table) {
            // When an already-active subscriber re-subscribes:
            // - true: Reset autoresponder queue (resend all messages from day 0)
            // - false: Keep existing queue entries (don't resend autoresponders)
            // Default is true to match user expectation that re-subscription means fresh start
            $table->boolean('reset_autoresponders_on_resubscription')->default(true)->after('resubscription_behavior');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contact_lists', function (Blueprint $table) {
            $table->dropColumn('reset_autoresponders_on_resubscription');
        });
    }
};
