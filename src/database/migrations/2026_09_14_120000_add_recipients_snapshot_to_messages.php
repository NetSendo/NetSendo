<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Marks a broadcast whose recipients were resolved once, by a selection its
     * lists alone do not describe (an API batch narrowing a list by tag or
     * subscriber). The CRON sync never adds recipients to such a message.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->boolean('recipients_snapshot')->default(false)->after('recipients_calculated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('recipients_snapshot');
        });
    }
};
