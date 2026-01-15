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
        Schema::create('funnel_ab_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('funnel_id')->constrained()->onDelete('cascade');
            $table->foreignId('split_step_id')->constrained('funnel_steps')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'running', 'paused', 'completed'])->default('draft');
            $table->unsignedInteger('sample_size')->nullable()->comment('Min sample before declaring winner');
            $table->unsignedInteger('confidence_level')->default(95)->comment('Required confidence % for winner');
            $table->enum('winning_metric', ['conversion_rate', 'click_rate', 'open_rate', 'goal_completion'])->default('conversion_rate');
            $table->unsignedBigInteger('winner_variant_id')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('winner_declared_at')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['funnel_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('funnel_ab_tests');
    }
};
