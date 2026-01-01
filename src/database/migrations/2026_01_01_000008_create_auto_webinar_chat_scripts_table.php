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
        Schema::create('auto_webinar_chat_scripts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webinar_id')->constrained()->onDelete('cascade');

            // When to show this message (seconds from start)
            $table->unsignedInteger('show_at_seconds');

            // Sender info (simulated)
            $table->string('sender_name');
            $table->string('sender_avatar_seed')->nullable(); // For generating consistent avatars

            // Message type
            $table->enum('message_type', ['question', 'comment', 'reaction', 'testimonial', 'excitement'])->default('comment');

            // Message content
            $table->text('message_text');

            // Reaction simulation
            $table->unsignedInteger('reaction_count')->default(0); // Simulated likes

            // Variation settings (for natural feel)
            $table->unsignedInteger('delay_variance_seconds')->default(0); // Random delay +-X seconds
            $table->boolean('show_randomly')->default(false); // 70% chance to show

            // Source tracking
            $table->boolean('is_original')->default(false); // From original live webinar
            $table->foreignId('source_message_id')->nullable()->constrained('webinar_chat_messages')->onDelete('set null');

            // Order for same timestamp
            $table->unsignedInteger('sort_order')->default(0);

            // Status
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Indexes
            $table->index(['webinar_id', 'show_at_seconds']);
            $table->index(['webinar_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auto_webinar_chat_scripts');
    }
};
