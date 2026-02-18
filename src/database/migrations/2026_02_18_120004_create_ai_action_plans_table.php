<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_action_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('ai_conversation_id')->nullable()->constrained('ai_conversations')->onDelete('set null');
            $table->string('agent_type'); // campaign, list, message, crm
            $table->string('intent'); // send_campaign, create_list, generate_content, etc.
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('plan_data'); // structured plan with steps
            $table->string('work_mode'); // autonomous, semi_auto, manual
            $table->string('status')->default('draft'); // draft, pending_approval, approved, executing, completed, failed, cancelled
            $table->json('execution_summary')->nullable();
            $table->unsignedInteger('total_steps')->default(0);
            $table->unsignedInteger('completed_steps')->default(0);
            $table->unsignedInteger('failed_steps')->default(0);
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'agent_type']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_action_plans');
    }
};
