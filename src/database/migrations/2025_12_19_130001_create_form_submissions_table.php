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
        Schema::create('form_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_form_id')->constrained()->onDelete('cascade');
            $table->foreignId('subscriber_id')->nullable()->constrained()->onDelete('set null');
            
            // Status
            $table->enum('status', ['pending', 'confirmed', 'rejected', 'error'])->default('pending');
            
            // Submission data
            $table->json('submission_data'); // Raw form data
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('referrer', 500)->nullable();
            $table->string('source', 100)->default('form'); // form, api, wordpress, elementor
            
            // Error handling
            $table->text('error_message')->nullable();
            
            // Processing
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['subscription_form_id', 'status']);
            $table->index(['subscription_form_id', 'created_at']);
            $table->index('subscriber_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_submissions');
    }
};
