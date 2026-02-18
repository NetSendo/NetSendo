<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_execution_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('ai_action_plan_id')->nullable()->constrained('ai_action_plans')->onDelete('set null');
            $table->foreignId('ai_action_plan_step_id')->nullable()->constrained('ai_action_plan_steps')->onDelete('set null');
            $table->string('agent_type');
            $table->string('action'); // classify_intent, generate_plan, execute_step, enrich_knowledge, etc.
            $table->string('status'); // success, error, timeout
            $table->json('input_data')->nullable();
            $table->json('output_data')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('tokens_input')->default(0);
            $table->unsignedInteger('tokens_output')->default(0);
            $table->string('model_used')->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['ai_action_plan_id']);
            $table->index(['agent_type', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_execution_logs');
    }
};
