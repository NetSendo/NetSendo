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
        Schema::create('webinars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Basic info
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('thumbnail_url')->nullable();

            // Type and status
            $table->enum('type', ['live', 'auto', 'hybrid'])->default('live');
            $table->enum('status', ['draft', 'scheduled', 'live', 'ended', 'published'])->default('draft');

            // Video settings
            $table->string('video_url')->nullable(); // For uploaded videos (auto-webinars)
            $table->string('youtube_live_id')->nullable(); // For YouTube Live integration
            $table->string('video_provider')->nullable(); // 'youtube', 'vimeo', 'upload', etc.

            // Pages
            $table->foreignId('registration_page_id')->nullable()->constrained('external_pages')->onDelete('set null');
            $table->foreignId('thank_you_page_id')->nullable()->constrained('external_pages')->onDelete('set null');
            $table->string('thank_you_url')->nullable(); // Alternative: custom URL

            // List integration
            $table->foreignId('target_list_id')->nullable()->constrained('contact_lists')->onDelete('set null');

            // Tagging
            $table->string('registration_tag')->nullable();
            $table->string('attended_tag')->nullable();
            $table->string('missed_tag')->nullable();
            $table->string('purchased_tag')->nullable();

            // Settings (JSON)
            $table->json('settings')->nullable();
            // Settings include:
            // - theme: 'light' | 'dark'
            // - primary_color, secondary_color
            // - chat_enabled: boolean
            // - chat_moderated: boolean
            // - show_attendee_count: boolean
            // - allow_replay: boolean
            // - replay_available_hours: int
            // - registration_fields: array
            // - branding: { logo, background_image }
            // - countdown_enabled: boolean
            // - max_attendees: int | null

            // Scheduling
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration_minutes')->nullable();

            // Counters
            $table->unsignedInteger('registrations_count')->default(0);
            $table->unsignedInteger('attendees_count')->default(0);
            $table->unsignedInteger('peak_viewers')->default(0);

            // Timezone
            $table->string('timezone')->default('Europe/Warsaw');

            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'status']);
            $table->index(['scheduled_at']);
            $table->index(['type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webinars');
    }
};
