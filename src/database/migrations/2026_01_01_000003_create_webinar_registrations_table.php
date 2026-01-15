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
        if (Schema::hasTable('webinar_registrations')) {
            return;
        }

        Schema::create('webinar_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webinar_id')->constrained()->onDelete('cascade');
            $table->foreignId('webinar_session_id')->nullable()->constrained()->onDelete('set null');

            // Subscriber link (if exists in system)
            $table->foreignId('subscriber_id')->nullable()->constrained()->onDelete('set null');

            // Registration details
            $table->string('email');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone')->nullable();

            // Custom fields (JSON)
            $table->json('custom_fields')->nullable();

            // Unique access token for watch page
            $table->string('access_token', 64)->unique();

            // Status
            $table->enum('status', ['registered', 'confirmed', 'attended', 'missed', 'partial'])->default('registered');

            // Attendance tracking
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->unsignedInteger('watch_time_seconds')->default(0);
            $table->unsignedInteger('max_video_position_seconds')->default(0);

            // UTM tracking
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_content')->nullable();
            $table->string('utm_term')->nullable();

            // Technical info
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('referrer_url')->nullable();

            // Email notifications
            $table->boolean('reminder_24h_sent')->default(false);
            $table->boolean('reminder_1h_sent')->default(false);
            $table->boolean('reminder_15min_sent')->default(false);
            $table->boolean('replay_email_sent')->default(false);

            // Engagement
            $table->unsignedInteger('chat_messages_count')->default(0);
            $table->unsignedInteger('reactions_count')->default(0);

            // Purchase tracking
            $table->boolean('made_purchase')->default(false);
            $table->decimal('purchase_amount', 10, 2)->default(0);

            $table->timestamps();

            // Indexes
            $table->index(['webinar_id', 'status']);
            $table->index(['email']);
            $table->index(['subscriber_id']);
            $table->unique(['webinar_id', 'email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webinar_registrations');
    }
};
