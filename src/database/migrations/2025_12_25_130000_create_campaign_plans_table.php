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
        Schema::create('campaign_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->enum('status', ['draft', 'active', 'completed', 'exported'])->default('draft');

            // Business Context (Step 1)
            $table->string('industry')->nullable();
            $table->string('business_model')->nullable();
            $table->string('campaign_goal')->nullable();
            $table->decimal('average_order_value', 10, 2)->nullable();
            $table->decimal('margin_percent', 5, 2)->nullable();
            $table->integer('decision_cycle_days')->nullable();

            // Audience Data (Step 2)
            $table->json('audience_snapshot')->nullable(); // Stores list stats at plan creation
            $table->json('selected_lists')->nullable();

            // AI Generated Strategy
            $table->json('strategy')->nullable(); // Full AI-generated plan
            $table->json('forecast')->nullable(); // Projected metrics

            // Export tracking
            $table->timestamp('exported_at')->nullable();
            $table->json('exported_items')->nullable(); // What was created

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_plans');
    }
};
