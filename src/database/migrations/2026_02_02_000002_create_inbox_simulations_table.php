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
        Schema::create('inbox_simulations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('domain_configuration_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('message_id')->nullable()->constrained()->nullOnDelete();

            // Email content analyzed
            $table->string('subject');
            $table->text('content_preview')->nullable(); // First 500 chars
            $table->string('from_email')->nullable();

            // Results
            $table->unsignedTinyInteger('inbox_score'); // 0-100
            $table->enum('predicted_folder', ['inbox', 'promotions', 'spam', 'unknown'])->default('unknown');

            // Provider-specific predictions
            $table->json('provider_predictions')->nullable(); // {gmail: {folder, score}, outlook: {...}, yahoo: {...}}

            // Analysis details
            $table->json('domain_analysis')->nullable(); // SPF/DKIM/DMARC impact
            $table->json('content_analysis')->nullable(); // Spam triggers, HTML issues
            $table->json('issues')->nullable(); // List of detected problems
            $table->json('recommendations')->nullable(); // Actionable suggestions

            // Scoring breakdown
            $table->json('score_breakdown')->nullable(); // {domain: 30, content: 40, reputation: 30}

            // Meta
            $table->boolean('is_test')->default(true); // false if from actual message draft
            $table->timestamp('analyzed_at');

            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'created_at']);
            $table->index('inbox_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inbox_simulations');
    }
};
