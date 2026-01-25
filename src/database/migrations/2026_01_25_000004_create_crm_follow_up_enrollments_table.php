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
        Schema::create('crm_follow_up_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sequence_id')
                ->constrained('crm_follow_up_sequences')
                ->cascadeOnDelete();
            $table->foreignId('crm_contact_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('current_step_id')
                ->nullable()
                ->constrained('crm_follow_up_steps')
                ->nullOnDelete();

            $table->enum('status', ['active', 'paused', 'completed', 'cancelled'])
                ->default('active');

            $table->dateTime('started_at');
            $table->dateTime('next_action_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('paused_at')->nullable();

            // Track progress
            $table->unsignedInteger('steps_completed')->default(0);
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['sequence_id', 'status']);
            $table->index(['crm_contact_id', 'status']);
            $table->index('next_action_at');

            // Prevent duplicate active enrollments
            $table->unique(['sequence_id', 'crm_contact_id', 'status'], 'unique_active_enrollment');
        });

        // Add foreign key to crm_tasks for enrollment reference
        Schema::table('crm_tasks', function (Blueprint $table) {
            $table->foreign('follow_up_enrollment_id')
                ->references('id')
                ->on('crm_follow_up_enrollments')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crm_tasks', function (Blueprint $table) {
            $table->dropForeign(['follow_up_enrollment_id']);
        });

        Schema::dropIfExists('crm_follow_up_enrollments');
    }
};
