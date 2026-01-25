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
            // Reminder system
            $table->dateTime('reminder_at')->nullable()->after('completed_at');
            $table->boolean('reminder_sent')->default(false)->after('reminder_at');

            // Follow-up chain
            $table->foreignId('parent_task_id')->nullable()->after('reminder_sent')
                ->constrained('crm_tasks')->nullOnDelete();
            $table->boolean('is_follow_up')->default(false)->after('parent_task_id');

            // No response handling
            $table->enum('no_response_action', ['none', 'reminder', 'escalate', 'close'])
                ->default('none')->after('is_follow_up');
            $table->unsignedInteger('no_response_days')->nullable()->after('no_response_action');

            // Notes
            $table->text('notes')->nullable()->after('description');

            // Sequence enrollment reference
            $table->foreignId('follow_up_enrollment_id')->nullable()->after('no_response_days');

            // Indexes
            $table->index('reminder_at');
            $table->index('is_follow_up');
            $table->index('parent_task_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crm_tasks', function (Blueprint $table) {
            $table->dropIndex(['reminder_at']);
            $table->dropIndex(['is_follow_up']);
            $table->dropIndex(['parent_task_id']);

            $table->dropForeign(['parent_task_id']);
            $table->dropForeign(['follow_up_enrollment_id']);

            $table->dropColumn([
                'reminder_at',
                'reminder_sent',
                'parent_task_id',
                'is_follow_up',
                'no_response_action',
                'no_response_days',
                'notes',
                'follow_up_enrollment_id',
            ]);
        });
    }
};
