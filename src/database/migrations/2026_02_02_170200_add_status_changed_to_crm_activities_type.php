<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add 'status_changed' to the enum type
        DB::statement("ALTER TABLE crm_activities MODIFY COLUMN type ENUM(
            'note',
            'call',
            'email',
            'meeting',
            'task_completed',
            'stage_changed',
            'deal_created',
            'deal_won',
            'deal_lost',
            'contact_created',
            'system',
            'status_changed'
        )");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE crm_activities MODIFY COLUMN type ENUM(
            'note',
            'call',
            'email',
            'meeting',
            'task_completed',
            'stage_changed',
            'deal_created',
            'deal_won',
            'deal_lost',
            'contact_created',
            'system'
        )");
    }
};
