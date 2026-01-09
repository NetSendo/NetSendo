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
            // Behavior when an already-active subscriber tries to re-subscribe:
            // - reset_date: Reset subscribed_at to now (queue timers restart)
            // - keep_original_date: Keep original subscribed_at (preserve queue position)
            // Note: Former subscribers (unsubscribed/removed) always get their date reset
            $table->string('resubscription_behavior', 20)->default('reset_date')->after('signups_blocked');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contact_lists', function (Blueprint $table) {
            $table->dropColumn('resubscription_behavior');
        });
    }
};
