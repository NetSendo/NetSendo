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
        Schema::create('webinar_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webinar_id')->constrained()->onDelete('cascade');
            $table->foreignId('webinar_session_id')->nullable()->constrained()->onDelete('cascade');

            // Sender
            $table->foreignId('registration_id')->nullable()->constrained('webinar_registrations')->onDelete('cascade');
            $table->enum('sender_type', ['host', 'moderator', 'attendee', 'system', 'bot'])->default('attendee');
            $table->string('sender_name');
            $table->string('sender_avatar_url')->nullable();

            // Message content
            $table->text('message');
            $table->enum('message_type', ['text', 'product', 'cta', 'poll', 'reaction', 'question', 'answer'])->default('text');

            // Pinning and highlighting
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_highlighted')->default(false);
            $table->boolean('is_answered')->default(false); // For Q&A

            // Visibility
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_deleted')->default(false);

            // Metadata (JSON) - for products, CTAs, polls
            $table->json('metadata')->nullable();
            // For product: { product_id, price, cta_text }
            // For poll: { question, options: [], votes: {} }
            // For reaction: { emoji, count }

            // For auto-webinars: when to show this message
            $table->unsignedInteger('show_at_seconds')->nullable();

            // Reactions count
            $table->unsignedInteger('likes_count')->default(0);

            // For Q&A: parent message (for answers)
            $table->foreignId('parent_id')->nullable()->constrained('webinar_chat_messages')->onDelete('cascade');

            $table->timestamps();

            // Indexes
            $table->index(['webinar_id', 'webinar_session_id', 'is_visible'], 'wcm_session_visible_idx');
            $table->index(['webinar_id', 'is_pinned'], 'wcm_pinned_idx');
            $table->index(['show_at_seconds'], 'wcm_show_at_idx'); // For auto-webinar playback
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webinar_chat_messages');
    }
};
