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
        Schema::create('automation_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            
            // Trigger configuration
            $table->enum('trigger_event', [
                'subscriber_signup',
                'subscriber_activated',
                'email_opened',
                'email_clicked',
                'subscriber_unsubscribed',
                'email_bounced',
                'form_submitted',
                'tag_added',
                'tag_removed',
                'field_updated'
            ]);
            $table->json('trigger_config')->nullable(); // e.g., list_id, form_id, message_id
            
            // Conditions (optional AND/OR logic)
            $table->json('conditions')->nullable();
            $table->enum('condition_logic', ['all', 'any'])->default('all'); // AND vs OR
            
            // Actions to execute
            $table->json('actions'); // Array of action configurations
            
            // Status & tracking
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('execution_count')->default(0);
            $table->timestamp('last_executed_at')->nullable();
            
            // Rate limiting
            $table->boolean('limit_per_subscriber')->default(false);
            $table->unsignedInteger('limit_count')->nullable();
            $table->enum('limit_period', ['hour', 'day', 'week', 'month', 'ever'])->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('trigger_event');
            $table->index('is_active');
            $table->index(['user_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('automation_rules');
    }
};
