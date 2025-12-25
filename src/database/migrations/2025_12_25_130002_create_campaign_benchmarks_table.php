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
        Schema::create('campaign_benchmarks', function (Blueprint $table) {
            $table->id();
            $table->string('industry');
            $table->string('campaign_type')->nullable(); // sales, onboarding, reactivation, nurturing, etc.
            $table->decimal('avg_open_rate', 5, 2)->nullable(); // in percent
            $table->decimal('avg_click_rate', 5, 2)->nullable(); // in percent
            $table->decimal('avg_conversion_rate', 5, 2)->nullable(); // in percent
            $table->decimal('avg_unsubscribe_rate', 5, 2)->nullable(); // in percent
            $table->integer('recommended_messages')->nullable(); // Suggested number of messages
            $table->integer('recommended_timeline_days')->nullable(); // Suggested campaign duration
            $table->json('best_practices')->nullable(); // Tips and suggestions
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_benchmarks');
    }
};
