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
        Schema::create('webinar_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webinar_id')->constrained()->onDelete('cascade');

            // Scheduling
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();

            // Status
            $table->enum('status', ['scheduled', 'live', 'ended', 'cancelled'])->default('scheduled');

            // Replay flag (for auto-webinars)
            $table->boolean('is_replay')->default(false);

            // Session number (for recurring auto-webinars)
            $table->unsignedInteger('session_number')->default(1);

            // Stats
            $table->unsignedInteger('attendees_count')->default(0);
            $table->unsignedInteger('peak_viewers')->default(0);
            $table->unsignedInteger('chat_messages_count')->default(0);

            // Video playback position (for auto-webinars)
            $table->unsignedInteger('current_position_seconds')->default(0);

            $table->timestamps();

            // Indexes
            $table->index(['webinar_id', 'status']);
            $table->index(['scheduled_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webinar_sessions');
    }
};
