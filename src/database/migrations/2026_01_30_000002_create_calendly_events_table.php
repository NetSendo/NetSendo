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
        Schema::create('calendly_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendly_integration_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Calendly identifiers
            $table->string('calendly_event_uri')->unique();
            $table->string('calendly_invitee_uri')->nullable();

            // Event type info
            $table->string('event_type_uri')->nullable();
            $table->string('event_type_name')->nullable();
            $table->string('event_type_slug')->nullable();

            // Invitee info
            $table->string('invitee_email');
            $table->string('invitee_name')->nullable();
            $table->string('invitee_timezone')->nullable();

            // Scheduling
            $table->timestamp('start_time');
            $table->timestamp('end_time');
            $table->string('status')->default('scheduled'); // scheduled, canceled, no_show

            // Location data (Zoom/Google Meet link, etc.)
            $table->json('location')->nullable();

            // Custom questions and answers from booking form
            $table->json('questions_and_answers')->nullable();

            // Cancellation info
            $table->string('cancellation_reason')->nullable();
            $table->string('canceled_by')->nullable(); // invitee, host
            $table->timestamp('canceled_at')->nullable();

            // No-show info
            $table->timestamp('marked_no_show_at')->nullable();

            // NetSendo relationships
            $table->foreignId('subscriber_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('crm_contact_id')->nullable()->constrained('crm_contacts')->onDelete('set null');
            $table->foreignId('crm_task_id')->nullable()->constrained('crm_tasks')->onDelete('set null');

            // Raw webhook payload for debugging
            $table->json('raw_payload')->nullable();

            $table->timestamps();

            $table->index('user_id');
            $table->index('invitee_email');
            $table->index('status');
            $table->index('start_time');
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calendly_events');
    }
};
