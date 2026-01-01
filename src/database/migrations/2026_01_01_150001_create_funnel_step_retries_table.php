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
        Schema::create('funnel_step_retries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('funnel_subscriber_id')->constrained()->onDelete('cascade');
            $table->foreignId('funnel_step_id')->constrained()->onDelete('cascade');
            $table->unsignedTinyInteger('attempt_number');
            $table->timestamp('sent_at');
            $table->timestamp('condition_met_at')->nullable();
            $table->timestamps();

            // Indexes for efficient querying
            $table->index(['funnel_subscriber_id', 'funnel_step_id']);
            $table->index('sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('funnel_step_retries');
    }
};
