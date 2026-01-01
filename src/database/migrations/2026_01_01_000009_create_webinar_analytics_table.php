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
        Schema::create('webinar_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webinar_id')->constrained()->onDelete('cascade');
            $table->foreignId('webinar_session_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('registration_id')->nullable()->constrained('webinar_registrations')->onDelete('cascade');

            // Event type
            $table->enum('event_type', [
                'page_view',           // Registration page viewed
                'registration',        // User registered
                'join',                // User joined webinar
                'leave',               // User left webinar
                'video_play',          // Video started playing
                'video_pause',         // Video paused
                'video_progress',      // Video progress checkpoint
                'chat_sent',           // User sent chat message
                'product_view',        // Product overlay viewed
                'product_click',       // Product CTA clicked
                'cta_view',            // CTA viewed
                'cta_click',           // CTA clicked
                'purchase',            // Purchase completed
                'share',               // Webinar shared
                'reaction',            // User reacted (emoji, like)
                'poll_vote',           // User voted in poll
            ]);

            // Video position when event occurred
            $table->unsignedInteger('video_time_seconds')->nullable();

            // Metadata (JSON) - event-specific data
            $table->json('metadata')->nullable();
            // For product_click: { product_id, product_name, price }
            // For purchase: { product_id, amount, currency, transaction_id }
            // For video_progress: { percent: 25/50/75/100 }

            // Technical info
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('device_type')->nullable(); // desktop, mobile, tablet
            $table->string('browser')->nullable();
            $table->string('country', 2)->nullable();

            $table->timestamp('created_at');

            // Indexes
            $table->index(['webinar_id', 'event_type']);
            $table->index(['webinar_session_id', 'event_type']);
            $table->index(['registration_id']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webinar_analytics');
    }
};
