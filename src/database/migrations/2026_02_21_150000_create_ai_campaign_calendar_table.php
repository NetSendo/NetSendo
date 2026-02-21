<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_campaign_calendar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('week_start');
            $table->date('planned_date');
            $table->string('campaign_type', 50)->default('newsletter'); // newsletter, promotion, nurturing, win_back, announcement
            $table->string('target_audience', 255)->nullable();
            $table->string('topic', 255);
            $table->text('description')->nullable();
            $table->string('status', 30)->default('draft'); // draft, approved, executed, skipped
            $table->unsignedBigInteger('ai_goal_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'week_start']);
            $table->index(['user_id', 'planned_date']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_campaign_calendar');
    }
};
