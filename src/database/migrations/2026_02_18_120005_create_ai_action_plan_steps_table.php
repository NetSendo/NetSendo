<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_action_plan_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_action_plan_id')->constrained('ai_action_plans')->onDelete('cascade');
            $table->unsignedInteger('step_order');
            $table->string('action_type'); // send_email, create_list, add_tag, generate_content, etc.
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('config'); // action-specific configuration
            $table->string('status')->default('pending'); // pending, executing, completed, failed, skipped
            $table->json('result')->nullable(); // execution result data
            $table->text('error_message')->nullable();
            $table->unsignedInteger('retry_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['ai_action_plan_id', 'step_order']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_action_plan_steps');
    }
};
