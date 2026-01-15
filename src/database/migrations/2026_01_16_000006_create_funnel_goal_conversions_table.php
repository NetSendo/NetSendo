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
        Schema::create('funnel_goal_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('funnel_id')->constrained()->onDelete('cascade');
            $table->foreignId('funnel_step_id')->constrained()->onDelete('cascade');
            $table->foreignId('funnel_subscriber_id')->constrained()->onDelete('cascade');
            $table->foreignId('subscriber_id')->constrained()->onDelete('cascade');

            // Goal details
            $table->string('goal_name');
            $table->string('goal_type'); // purchase, signup, page_visit, tag_added, custom, webhook
            $table->decimal('value', 10, 2)->default(0); // Revenue value

            // Additional data
            $table->json('metadata')->nullable(); // order_id, product_id, etc.
            $table->string('source')->default('funnel'); // funnel, webhook, manual

            $table->timestamp('converted_at');
            $table->timestamps();

            // Indexes
            $table->index(['funnel_id', 'goal_type']);
            $table->index(['funnel_id', 'converted_at']);
            $table->unique(['funnel_step_id', 'funnel_subscriber_id'], 'unique_step_subscriber');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('funnel_goal_conversions');
    }
};
