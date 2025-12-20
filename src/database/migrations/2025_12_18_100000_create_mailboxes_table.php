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
        Schema::create('mailboxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Basic info
            $table->string('name'); // Display name, e.g. "Main Mailbox"
            $table->enum('provider', ['smtp', 'sendgrid', 'gmail'])->default('smtp');
            $table->string('from_email');
            $table->string('from_name');
            
            // Status
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            
            // Type restrictions (which message types can use this mailbox)
            // For Gmail: ['autoresponder', 'system'] - no broadcast
            // For SMTP/SendGrid: ['broadcast', 'autoresponder', 'system']
            $table->json('allowed_types')->nullable();
            
            // Encrypted credentials (JSON structure depends on provider)
            // SMTP: {host, port, encryption, username, password}
            // SendGrid: {api_key}
            // Gmail: {username, password} (App Password)
            $table->text('credentials');
            
            // Rate limiting
            $table->unsignedInteger('daily_limit')->nullable(); // null = unlimited
            $table->unsignedInteger('sent_today')->default(0);
            $table->date('sent_today_date')->nullable(); // To reset counter daily
            
            // Metadata
            $table->timestamp('last_tested_at')->nullable();
            $table->boolean('last_test_success')->nullable();
            $table->string('last_test_message')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['user_id', 'is_default']);
            $table->index(['user_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mailboxes');
    }
};
