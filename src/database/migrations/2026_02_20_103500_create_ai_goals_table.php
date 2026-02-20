<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_goals')) {
            return;
        }

        Schema::create('ai_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_conversation_id')->nullable()->constrained('ai_conversations')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('active'); // active, paused, completed, failed, cancelled
            $table->string('priority')->default('medium'); // low, medium, high, urgent
            $table->json('success_criteria')->nullable();
            $table->json('context')->nullable();
            $table->integer('total_plans')->default(0);
            $table->integer('completed_plans')->default(0);
            $table->integer('failed_plans')->default(0);
            $table->integer('progress_percent')->default(0);
            $table->timestamp('target_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        if (!Schema::hasColumn('ai_action_plans', 'ai_goal_id')) {
            Schema::table('ai_action_plans', function (Blueprint $table) {
                $table->foreignId('ai_goal_id')->nullable()->after('ai_conversation_id')
                      ->constrained('ai_goals')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ai_action_plans', 'ai_goal_id')) {
            Schema::table('ai_action_plans', function (Blueprint $table) {
                $table->dropForeign(['ai_goal_id']);
                $table->dropColumn('ai_goal_id');
            });
        }

        Schema::dropIfExists('ai_goals');
    }
};
