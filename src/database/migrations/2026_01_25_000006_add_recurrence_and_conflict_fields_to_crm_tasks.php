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
        Schema::table('crm_tasks', function (Blueprint $table) {
            // Recurrence fields
            $table->boolean('is_recurring')->default(false)->after('selected_calendar_id');
            $table->string('recurrence_rule')->nullable()->after('is_recurring'); // RRULE format
            $table->string('recurrence_type')->nullable()->after('recurrence_rule'); // daily, weekly, monthly, yearly, custom
            $table->integer('recurrence_interval')->default(1)->after('recurrence_type'); // Every X days/weeks/months
            $table->json('recurrence_days')->nullable()->after('recurrence_interval'); // For weekly: [1,3,5] = Mon,Wed,Fri
            $table->date('recurrence_end_date')->nullable()->after('recurrence_days');
            $table->integer('recurrence_count')->nullable()->after('recurrence_end_date'); // Number of occurrences
            $table->unsignedBigInteger('recurring_parent_id')->nullable()->after('recurrence_count'); // Link to parent recurring task

            // Conflict resolution fields
            $table->string('google_calendar_etag')->nullable()->after('google_calendar_synced_at');
            $table->timestamp('local_updated_at')->nullable()->after('google_calendar_etag');
            $table->boolean('has_conflict')->default(false)->after('local_updated_at');
            $table->json('conflict_data')->nullable()->after('has_conflict'); // Store conflicting versions

            // Index for recurring tasks
            $table->index('recurring_parent_id');
            $table->index('is_recurring');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crm_tasks', function (Blueprint $table) {
            $table->dropIndex(['recurring_parent_id']);
            $table->dropIndex(['is_recurring']);

            $table->dropColumn([
                'is_recurring',
                'recurrence_rule',
                'recurrence_type',
                'recurrence_interval',
                'recurrence_days',
                'recurrence_end_date',
                'recurrence_count',
                'recurring_parent_id',
                'google_calendar_etag',
                'local_updated_at',
                'has_conflict',
                'conflict_data',
            ]);
        });
    }
};
