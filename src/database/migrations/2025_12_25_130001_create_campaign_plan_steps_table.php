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
        Schema::create('campaign_plan_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_plan_id')->constrained()->onDelete('cascade');
            $table->integer('order')->default(0);
            $table->enum('channel', ['email', 'sms'])->default('email');
            $table->string('message_type'); // educational, sales, reminder, social_proof, follow_up, onboarding
            $table->string('subject')->nullable();
            $table->text('description')->nullable();
            $table->integer('delay_days')->default(0); // Days from previous step or campaign start
            $table->integer('delay_hours')->default(0); // Hours offset within the day
            $table->json('conditions')->nullable(); // IF/THEN logic: {"trigger": "opened", "previous_step": 1, "action": "send"}
            $table->json('content_hints')->nullable(); // AI suggestions for content
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_plan_steps');
    }
};
