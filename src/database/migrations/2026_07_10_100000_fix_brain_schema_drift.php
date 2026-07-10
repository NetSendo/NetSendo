<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brain 2.0 Phase 0 (D6): fix schema drift between code and database.
 *
 * - ai_brain_settings.cron_max_tasks — read by RunBrainCronCommand but never migrated
 * - ai_campaign_calendar.executed_at — written by RunBrainCronCommand but never migrated
 * - ai_pending_approvals.ai_action_plan_id — goal proposals create approvals without a plan
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_brain_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('cron_max_tasks')->default(5)->after('cron_interval_minutes');
        });

        Schema::table('ai_campaign_calendar', function (Blueprint $table) {
            $table->timestamp('executed_at')->nullable()->after('status');
        });

        Schema::table('ai_pending_approvals', function (Blueprint $table) {
            $table->unsignedBigInteger('ai_action_plan_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('ai_brain_settings', function (Blueprint $table) {
            $table->dropColumn('cron_max_tasks');
        });

        Schema::table('ai_campaign_calendar', function (Blueprint $table) {
            $table->dropColumn('executed_at');
        });

        Schema::table('ai_pending_approvals', function (Blueprint $table) {
            $table->unsignedBigInteger('ai_action_plan_id')->nullable(false)->change();
        });
    }
};
