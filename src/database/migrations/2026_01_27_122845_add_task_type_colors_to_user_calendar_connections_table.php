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
        Schema::table('user_calendar_connections', function (Blueprint $table) {
            $table->json('task_type_colors')->nullable()->after('sync_settings');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_calendar_connections', function (Blueprint $table) {
            $table->dropColumn('task_type_colors');
        });
    }
};
