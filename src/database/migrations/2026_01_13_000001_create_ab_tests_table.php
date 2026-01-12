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
        Schema::create('ab_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->enum('status', ['draft', 'running', 'paused', 'completed', 'cancelled'])->default('draft');
            $table->enum('test_type', ['subject', 'content', 'sender', 'send_time', 'full'])->default('subject');
            $table->enum('winning_metric', ['open_rate', 'click_rate', 'conversion_rate'])->default('open_rate');
            $table->unsignedTinyInteger('sample_percentage')->default(20);
            $table->unsignedInteger('test_duration_hours')->default(4);
            $table->boolean('auto_select_winner')->default(true);
            $table->unsignedTinyInteger('confidence_threshold')->default(95);
            $table->foreignId('winner_variant_id')->nullable();
            $table->timestamp('test_started_at')->nullable();
            $table->timestamp('test_ended_at')->nullable();
            $table->timestamp('winner_sent_at')->nullable();
            $table->json('test_settings')->nullable();
            $table->json('final_results')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['message_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ab_tests');
    }
};
