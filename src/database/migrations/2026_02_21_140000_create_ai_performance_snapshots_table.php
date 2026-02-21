<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_performance_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('ai_action_plan_id')->nullable();
            $table->unsignedBigInteger('message_id')->nullable();

            // Campaign identifiers
            $table->string('campaign_title')->nullable();
            $table->string('agent_type')->default('campaign');

            // Core metrics
            $table->unsignedInteger('sent_count')->default(0);
            $table->decimal('open_rate', 5, 2)->default(0);
            $table->decimal('click_rate', 5, 2)->default(0);
            $table->decimal('unsubscribe_rate', 5, 2)->default(0);
            $table->decimal('bounce_rate', 5, 2)->default(0);

            // Benchmark comparison (JSON: {open_rate: "above|below|avg", ...})
            $table->json('benchmark_comparison')->nullable();

            // AI-generated insights
            $table->text('lessons_learned')->nullable();
            $table->json('what_worked')->nullable();
            $table->json('what_to_improve')->nullable();

            // Status tracking
            $table->string('review_status')->default('pending'); // pending, reviewed, applied
            $table->timestamp('campaign_sent_at')->nullable();
            $table->timestamp('captured_at')->nullable();

            $table->timestamps();

            $table->index('user_id');
            $table->index('ai_action_plan_id');
            $table->index('message_id');
            $table->index('review_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_performance_snapshots');
    }
};
