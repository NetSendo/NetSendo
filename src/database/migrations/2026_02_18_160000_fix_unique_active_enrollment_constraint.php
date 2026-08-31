<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The original unique constraint on (sequence_id, crm_contact_id, status)
     * was intended to prevent duplicate active enrollments, but it also prevents
     * contacts from completing the same sequence more than once.
     *
     * We drop it and replace with a constraint only on active enrollments.
     * Since MySQL doesn't support partial unique indexes, we use a virtual
     * generated column that is non-null only for active enrollments.
     */
    public function up(): void
    {
        Schema::table('crm_follow_up_enrollments', function (Blueprint $table) {
            $table->dropUnique('unique_active_enrollment');
        });

        // The generated column below is MySQL-specific DDL, and the index that
        // follows depends on it — on any other driver (the sqlite test database)
        // both are skipped so the schema still builds.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // Add a virtual column that is non-null only when status = 'active',
        // then create a unique index on (sequence_id, crm_contact_id, active_unique_key).
        // MySQL ignores NULLs in unique indexes, so only active rows are checked.
        DB::statement("
            ALTER TABLE `crm_follow_up_enrollments`
            ADD COLUMN `active_unique_key` TINYINT
                GENERATED ALWAYS AS (IF(`status` = 'active', 1, NULL)) VIRTUAL
                AFTER `status`
        ");

        Schema::table('crm_follow_up_enrollments', function (Blueprint $table) {
            $table->unique(
                ['sequence_id', 'crm_contact_id', 'active_unique_key'],
                'unique_active_enrollment'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // up() is a no-op outside MySQL, so there is nothing to reverse there.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('crm_follow_up_enrollments', function (Blueprint $table) {
            $table->dropUnique('unique_active_enrollment');
        });

        DB::statement("
            ALTER TABLE `crm_follow_up_enrollments`
            DROP COLUMN `active_unique_key`
        ");

        Schema::table('crm_follow_up_enrollments', function (Blueprint $table) {
            $table->unique(
                ['sequence_id', 'crm_contact_id', 'status'],
                'unique_active_enrollment'
            );
        });
    }
};
