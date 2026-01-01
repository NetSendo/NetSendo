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
        Schema::create('funnel_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('funnel_id')->constrained()->onDelete('cascade');
            $table->foreignId('subscriber_id')->constrained()->onDelete('cascade');
            $table->string('task_id'); // External task identifier (e.g., 'quiz-1', 'registration-form')
            $table->json('metadata')->nullable(); // Additional data from external system
            $table->timestamp('completed_at');
            $table->timestamps();

            // Unique constraint to prevent duplicate completions
            $table->unique(['funnel_id', 'subscriber_id', 'task_id']);

            // Index for querying by task
            $table->index('task_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('funnel_tasks');
    }
};
