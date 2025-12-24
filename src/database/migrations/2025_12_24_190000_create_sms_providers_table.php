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
        Schema::create('sms_providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Provider identification
            $table->string('name'); // Display name, e.g., "My Twilio Account"
            $table->string('provider'); // Provider type: twilio, smsapi, smsapi_com

            // Encrypted credentials (JSON)
            $table->text('credentials');

            // Sender configuration
            $table->string('from_number')->nullable(); // For Twilio
            $table->string('from_name', 11)->nullable(); // For SMS API (max 11 chars)

            // Status
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);

            // Limits and tracking
            $table->unsignedInteger('daily_limit')->nullable();
            $table->unsignedInteger('sent_today')->default(0);
            $table->date('sent_today_date')->nullable(); // To reset counter daily

            // Metadata
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_status')->nullable(); // success, failed

            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'is_active']);
            $table->index(['user_id', 'is_default']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sms_providers');
    }
};
