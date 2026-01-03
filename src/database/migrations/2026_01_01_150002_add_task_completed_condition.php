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
        // For MySQL/MariaDB: Alter the enum to include 'task_completed'
        // First, we need to modify the enum column
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE funnel_steps MODIFY COLUMN condition_type ENUM('email_opened', 'email_clicked', 'link_clicked', 'tag_exists', 'field_value', 'task_completed') NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove 'task_completed' from enum
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE funnel_steps MODIFY COLUMN condition_type ENUM('email_opened', 'email_clicked', 'link_clicked', 'tag_exists', 'field_value') NULL");
        }
    }
};
