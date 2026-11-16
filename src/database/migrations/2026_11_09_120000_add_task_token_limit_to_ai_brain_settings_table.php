<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brain's token limit belongs to one task (a chat message, a plan run, a goal step, a cron task)
 * instead of the whole day: a daily limit stopped every task once it was reached. 0 = no limit.
 * daily_token_limit stays in the table but nothing reads it any more.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('ai_brain_settings', 'task_token_limit')) {
            return;
        }

        Schema::table('ai_brain_settings', function (Blueprint $table) {
            $table->unsignedInteger('task_token_limit')->nullable()->default(100000)->after('daily_token_limit');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('ai_brain_settings', 'task_token_limit')) {
            Schema::table('ai_brain_settings', function (Blueprint $table) {
                $table->dropColumn('task_token_limit');
            });
        }
    }
};
