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
        Schema::create('lead_scoring_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event_type'); // email_opened, email_clicked, form_submitted, etc.
            $table->string('name'); // Human-readable name
            $table->text('description')->nullable();
            $table->integer('points'); // Can be negative for decay
            $table->string('condition_field')->nullable(); // e.g. 'page_url', 'tag_name'
            $table->string('condition_operator')->nullable(); // contains, equals, starts_with, regex
            $table->string('condition_value')->nullable(); // e.g. '/pricing', 'hot'
            $table->integer('cooldown_minutes')->default(0); // Prevent spam scoring
            $table->integer('max_daily_occurrences')->nullable(); // Limit daily triggers
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0); // Higher = processed first
            $table->timestamps();

            $table->index(['user_id', 'event_type', 'is_active']);
            $table->index(['user_id', 'priority']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_scoring_rules');
    }
};
