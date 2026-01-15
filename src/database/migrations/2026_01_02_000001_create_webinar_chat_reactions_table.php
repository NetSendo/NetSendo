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
        if (Schema::hasTable('webinar_chat_reactions')) {
            return;
        }

        Schema::create('webinar_chat_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webinar_id')->constrained()->onDelete('cascade');
            $table->foreignId('webinar_session_id')->nullable()->constrained('webinar_sessions')->onDelete('cascade');
            $table->foreignId('registration_id')->nullable()->constrained('webinar_registrations')->onDelete('cascade');

            // Reaction type
            $table->enum('type', ['heart', 'thumbs_up', 'fire', 'clap', 'wow', 'laugh', 'think'])->default('heart');

            // For auto-webinar simulated reactions
            $table->boolean('is_simulated')->default(false);

            // Optional: position on screen for animation
            $table->unsignedTinyInteger('position_x')->nullable(); // 0-100 percentage

            $table->timestamps();

            // Indexes for efficient queries
            $table->index(['webinar_id', 'created_at']);
            $table->index(['webinar_id', 'webinar_session_id', 'created_at'], 'wcr_webinar_session_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webinar_chat_reactions');
    }
};
