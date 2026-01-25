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
        Schema::table('crm_follow_up_enrollments', function (Blueprint $table) {
            if (!Schema::hasColumn('crm_follow_up_enrollments', 'emails_sent')) {
                $table->integer('emails_sent')->default(0)->after('steps_completed');
            }
            if (!Schema::hasColumn('crm_follow_up_enrollments', 'responses_received')) {
                $table->integer('responses_received')->default(0)->after('emails_sent');
            }
            if (!Schema::hasColumn('crm_follow_up_enrollments', 'tasks_completed')) {
                $table->integer('tasks_completed')->default(0)->after('responses_received');
            }
            if (!Schema::hasColumn('crm_follow_up_enrollments', 'converted')) {
                $table->boolean('converted')->default(false)->after('tasks_completed');
            }
            if (!Schema::hasColumn('crm_follow_up_enrollments', 'last_activity_at')) {
                $table->timestamp('last_activity_at')->nullable()->after('converted');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crm_follow_up_enrollments', function (Blueprint $table) {
            $table->dropColumn([
                'emails_sent',
                'responses_received',
                'tasks_completed',
                'converted',
                'last_activity_at',
            ]);
        });
    }
};
