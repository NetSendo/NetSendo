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
        Schema::create('crm_follow_up_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sequence_id')
                ->constrained('crm_follow_up_sequences')
                ->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);

            // Delay configuration
            $table->unsignedInteger('delay_days')->default(0);
            $table->unsignedInteger('delay_hours')->default(0);

            // Action type
            $table->enum('action_type', [
                'task',
                'email',
                'sms',
                'wait_for_response',
            ])->default('task');

            // Task configuration (when action_type = 'task')
            $table->enum('task_type', ['call', 'email', 'meeting', 'task', 'follow_up'])
                ->nullable();
            $table->string('task_title')->nullable();
            $table->text('task_description')->nullable();
            $table->enum('task_priority', ['low', 'medium', 'high'])->default('medium');

            // Email configuration (when action_type = 'email')
            $table->foreignId('email_template_id')->nullable();

            // Condition handling
            $table->enum('condition_if_no_response', ['continue', 'stop', 'escalate'])
                ->default('continue');
            $table->unsignedInteger('wait_days_for_response')->nullable();

            $table->timestamps();

            $table->index(['sequence_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_follow_up_steps');
    }
};
