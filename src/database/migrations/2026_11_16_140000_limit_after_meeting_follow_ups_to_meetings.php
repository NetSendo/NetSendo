<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The default "After the meeting" follow-up sequence enrolled a contact after
 * ANY completed task, its own tasks included, so it fed itself. It now runs
 * after completed meetings only (trigger_conditions.task_type = meeting),
 * unless the account already gave it conditions of its own.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('crm_follow_up_sequences')
            ->where('default_key', 'after_meeting')
            ->where('trigger_type', 'on_task_completed')
            ->whereNull('trigger_conditions')
            ->update(['trigger_conditions' => json_encode(['task_type' => 'meeting'])]);
    }

    public function down(): void
    {
    }
};
