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
        Schema::create('lead_score_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crm_contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_scoring_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type');
            $table->integer('points_change');
            $table->integer('score_before');
            $table->integer('score_after');
            $table->json('metadata')->nullable(); // Additional context (message_id, url, etc.)
            $table->timestamp('created_at');

            $table->index(['crm_contact_id', 'created_at']);
            $table->index(['crm_contact_id', 'event_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_score_history');
    }
};
