<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FunnelExecutionService writes `waiting_condition` when a condition step
     * waits for its condition, but the enum never allowed it: on MySQL (strict)
     * the update threw "Data truncated for column 'status'".
     */
    public function up(): void
    {
        // Appended at the end, so MySQL changes the enum in place instead of
        // copying the table
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE funnel_subscribers MODIFY COLUMN status ENUM('active', 'waiting', 'completed', 'paused', 'exited', 'waiting_condition') NOT NULL DEFAULT 'active'");
        }

        // SQLite stores an enum as a CHECK constraint, which only a table
        // rebuild changes. `task_completed` was added to funnel_steps on MySQL
        // alone (2026_01_01_150002_add_task_completed_condition)
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('funnel_subscribers', function (Blueprint $table) {
                $table->enum('status', ['active', 'waiting', 'completed', 'paused', 'exited', 'waiting_condition'])
                    ->default('active')
                    ->change();
            });

            Schema::table('funnel_steps', function (Blueprint $table) {
                $table->enum('condition_type', ['email_opened', 'email_clicked', 'link_clicked', 'tag_exists', 'field_value', 'task_completed'])
                    ->nullable()
                    ->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // The narrower enum cannot hold these rows
        DB::table('funnel_subscribers')
            ->where('status', 'waiting_condition')
            ->update(['status' => 'paused']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE funnel_subscribers MODIFY COLUMN status ENUM('active', 'waiting', 'completed', 'paused', 'exited') NOT NULL DEFAULT 'active'");
        }

        if (DB::getDriverName() === 'sqlite') {
            Schema::table('funnel_subscribers', function (Blueprint $table) {
                $table->enum('status', ['active', 'waiting', 'completed', 'paused', 'exited'])
                    ->default('active')
                    ->change();
            });

            Schema::table('funnel_steps', function (Blueprint $table) {
                $table->enum('condition_type', ['email_opened', 'email_clicked', 'link_clicked', 'tag_exists', 'field_value'])
                    ->nullable()
                    ->change();
            });
        }
    }
};
