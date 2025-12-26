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
        Schema::create('campaign_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_audit_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // quick_win, strategic, growth
            $table->unsignedTinyInteger('priority')->default(5); // 1-10
            $table->string('title');
            $table->text('description');
            $table->decimal('expected_impact', 5, 2)->default(0); // Expected improvement %
            $table->string('effort_level', 10)->default('medium'); // low, medium, high
            $table->string('category', 30)->nullable(); // Related issue category
            $table->json('action_steps')->nullable(); // Array of steps to implement
            $table->json('context')->nullable(); // Additional data for the recommendation
            $table->boolean('is_applied')->default(false);
            $table->timestamp('applied_at')->nullable();
            $table->decimal('result_impact', 5, 2)->nullable(); // Measured impact after applying
            $table->timestamps();

            $table->index(['campaign_audit_id', 'type']);
            $table->index(['campaign_audit_id', 'priority']);
            $table->index(['is_applied']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_recommendations');
    }
};
