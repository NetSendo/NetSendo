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
        Schema::create('funnel_subscribers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('funnel_id')->constrained()->onDelete('cascade');
            $table->foreignId('subscriber_id')->constrained()->onDelete('cascade');
            $table->foreignId('current_step_id')->nullable()->constrained('funnel_steps')->nullOnDelete();
            
            // Status of subscriber in this funnel
            $table->enum('status', ['active', 'waiting', 'completed', 'paused', 'exited'])->default('active');
            
            // When to process next step (for delays)
            $table->timestamp('next_action_at')->nullable();
            
            // Tracking data
            $table->timestamp('entered_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('steps_completed')->default(0);
            
            // Step-specific data and history
            $table->json('data')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->unique(['funnel_id', 'subscriber_id']);
            $table->index(['status', 'next_action_at']);
            $table->index('current_step_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('funnel_subscribers');
    }
};
