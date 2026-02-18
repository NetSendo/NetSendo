<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_brain_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('ai_brain_settings', 'cron_enabled')) {
                $table->boolean('cron_enabled')->default(false)->after('is_active');
            }
            if (!Schema::hasColumn('ai_brain_settings', 'cron_interval_minutes')) {
                $table->integer('cron_interval_minutes')->default(60)->after('cron_enabled');
            }
            if (!Schema::hasColumn('ai_brain_settings', 'last_cron_run_at')) {
                $table->timestamp('last_cron_run_at')->nullable()->after('cron_interval_minutes');
            }
            if (!Schema::hasColumn('ai_brain_settings', 'last_activity_at')) {
                $table->timestamp('last_activity_at')->nullable()->after('last_cron_run_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ai_brain_settings', function (Blueprint $table) {
            $table->dropColumn(['cron_enabled', 'cron_interval_minutes', 'last_cron_run_at', 'last_activity_at']);
        });
    }
};
